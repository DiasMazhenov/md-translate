<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MDT_Frontend {

	const COOKIE_NAME    = 'mdt_lang';
	const COOKIE_RESET   = 'mdt_reset';
	const COOKIE_LIFETIME = YEAR_IN_SECONDS;

	/** Cached active lang for the current request */
	private $active_lang = null;

	public function __construct() {
		// Priority 1 — run before most plugins
		add_action( 'init',              array( $this, 'handle_lang_cookie' ),         1 );
		add_action( 'template_redirect', array( $this, 'maybe_redirect_saved_lang' ),  1 );
		// Priority 2 — start buffer after redirect check
		add_action( 'template_redirect', array( $this, 'maybe_start_output_buffer' ),  2 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// the_content is used ONLY for switcher injection now —
		// actual translation is done by the output buffer
		add_filter( 'the_content', array( $this, 'inject_switcher' ), 20 );

		add_shortcode( 'md_translate_switcher', array( $this, 'shortcode_switcher' ) );
		add_action( 'wp_head', array( $this, 'add_hreflang_tags' ) );
	}

	// =========================================================================
	// Active language
	// =========================================================================

	private function get_active_lang() {
		if ( null !== $this->active_lang ) {
			return $this->active_lang;
		}
		$this->active_lang = MDT_Widget::get_active_lang();
		return $this->active_lang;
	}

	// =========================================================================
	// Cookie / language persistence
	// =========================================================================

	/**
	 * Runs on 'init'.
	 * - If ?mdt_reset=1 → clear cookie and redirect to clean URL.
	 * - If ?lang=XX     → set cookie for future visits.
	 */
	public function handle_lang_cookie() {
		if ( is_admin() ) {
			return;
		}

		// User clicked "Original" — clear the saved language
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::COOKIE_RESET ] ) ) {
			setcookie( self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
			unset( $_COOKIE[ self::COOKIE_NAME ] );
			$clean = remove_query_arg( array( 'lang', self::COOKIE_RESET ) );
			wp_safe_redirect( $clean );
			exit;
		}

		// Persist the chosen language to cookie
		if ( isset( $_GET['lang'] ) ) {
			$lang    = sanitize_text_field( wp_unslash( $_GET['lang'] ) );
			$targets = (array) get_option( 'mdt_target_langs', array() );
			if ( in_array( $lang, $targets, true ) ) {
				setcookie( self::COOKIE_NAME, $lang, time() + self::COOKIE_LIFETIME, COOKIEPATH, COOKIE_DOMAIN );
				$_COOKIE[ self::COOKIE_NAME ] = $lang; // make available in current request
			}
		}
		// phpcs:enable
	}

	/**
	 * Runs on 'template_redirect' (priority 1).
	 * If user has a saved language cookie but the current URL has no ?lang=,
	 * redirect transparently so the page is served translated.
	 */
	public function maybe_redirect_saved_lang() {
		if ( is_admin() ) {
			return;
		}

		// Already have lang in URL or are resetting — no redirect needed
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['lang'] ) || isset( $_GET[ self::COOKIE_RESET ] ) ) {
			return;
		}
		// phpcs:enable

		$saved   = isset( $_COOKIE[ self::COOKIE_NAME ] )
			? sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] )
			: '';
		$targets = (array) get_option( 'mdt_target_langs', array() );

		if ( '' !== $saved && in_array( $saved, $targets, true ) ) {
			$url = add_query_arg( 'lang', $saved, MDT_Widget::current_url_without_lang() );
			wp_safe_redirect( $url, 302 );
			exit;
		}
	}

	// =========================================================================
	// Output buffering — full-body translation
	// =========================================================================

	/**
	 * Start buffering the full page output so we can translate ALL text,
	 * including Elementor widgets, header buttons, sidebars, etc.
	 */
	public function maybe_start_output_buffer() {
		if ( '' === $this->get_active_lang() ) {
			return;
		}
		ob_start( array( $this, 'process_full_output' ) );
	}

	/**
	 * ob_start callback — translates only <body>…</body>, leaving <head> intact
	 * to avoid corrupting meta tags, canonical URLs, og: tags, scripts, etc.
	 */
	public function process_full_output( $html ) {
		$lang = $this->get_active_lang();
		if ( '' === $lang ) {
			return $html;
		}

		// Translate <title>…</title> in <head>
		$html = preg_replace_callback(
			'~(<title[^>]*>)([\s\S]*?)(</title>)~i',
			function ( $m ) use ( $lang ) {
				$text = trim( $m[2] );
				if ( '' === $text ) {
					return $m[0];
				}
				$t      = new MDT_Translator();
				$source = get_option( 'mdt_source_lang', 'auto' );
				$result = $t->translate( $text, $lang, $source );
				return $m[1] . ( is_wp_error( $result ) ? $m[2] : $result ) . $m[3];
			},
			$html
		);

		// Translate <body>…</body>
		if ( ! preg_match( '/(<body(?:[^>]*)>)([\s\S]*)(<\/body\s*>)/i', $html, $m ) ) {
			return $this->translate_html_safe( $html, $lang );
		}

		$open  = $m[1];
		$body  = $m[2];
		$close = $m[3];

		return substr( $html, 0, strpos( $html, $open ) )
			. $open
			. $this->translate_html_safe( $body, $lang )
			. $close;
	}

	/**
	 * Translate only text nodes in HTML.
	 * Skips: <script>, <style>, <noscript>, HTML comments, all HTML tags,
	 *        and blocks wrapped in <!-- mdt-skip-start -->...<!-- mdt-skip-end -->.
	 */
	private function translate_html_safe( $html, $target_lang ) {
		$source = get_option( 'mdt_source_lang', 'auto' );

		// Pre-pass: extract skip blocks (e.g. language switcher) and replace with placeholders
		$skipped = array();
		$html = preg_replace_callback(
			'~<!-- mdt-skip-start -->[\s\S]*?<!-- mdt-skip-end -->~',
			function ( $m ) use ( &$skipped ) {
				$key            = "\x02MDT_SKIP_" . count( $skipped ) . "\x03";
				$skipped[ $key ] = $m[0];
				return $key;
			},
			$html
		);

		// Single-pass regex: ordered from most specific to most general
		$result = preg_replace_callback(
			'~
			  # 1 — full skip blocks (script / style / noscript)
			  (<(?:script|style|noscript)(?:\s[^>]*)?>[\s\S]*?</(?:script|style|noscript)>)
			  # 2 — HTML comments
			| (<!--[\s\S]*?-->)
			  # 3 — self-closing or any tag
			| (<[^>]+>)
			  # 4 — text node
			| ([^<]+)
			~xi',
			function ( $m ) use ( $target_lang, $source ) {
				// Groups 1, 2, 3 — pass through unchanged
				if ( '' !== $m[1] || '' !== $m[2] || '' !== $m[3] ) {
					return $m[0];
				}

				$text = $m[4];
				if ( '' === trim( $text ) ) {
					return $text;
				}

				$t      = new MDT_Translator();
				$result = $t->translate( $text, $target_lang, $source );
				return is_wp_error( $result ) ? $text : $result;
			},
			$html
		);

		$result = $result ?? $html;

		// Restore skip blocks
		if ( ! empty( $skipped ) ) {
			$result = str_replace( array_keys( $skipped ), array_values( $skipped ), $result );
		}

		return $result;
	}

	// =========================================================================
	// Switcher injection (the_content — no translation here)
	// =========================================================================

	public function inject_switcher( $content ) {
		if ( ! is_singular() ) {
			return $content;
		}

		$pos = get_option( 'mdt_switcher_pos', 'top' );
		if ( ! in_array( $pos, array( 'top', 'bottom' ), true ) ) {
			return $content;
		}

		$switcher = $this->build_switcher_html();
		return 'top' === $pos ? $switcher . $content : $content . $switcher;
	}

	/** Shortcode: [md_translate_switcher style="…" show_flags="1" …] */
	public function shortcode_switcher( $atts ) {
		$atts = shortcode_atts(
			array(
				'style'      => get_option( 'mdt_switcher_style',      'list' ),
				'show_names' => get_option( 'mdt_switcher_show_names', '1' ),
				'show_flags' => get_option( 'mdt_switcher_show_flags', '0' ),
				'show_codes' => get_option( 'mdt_switcher_show_codes', '0' ),
				'icon_size'  => get_option( 'mdt_switcher_icon_size',   20 ),
				'class'      => '',
			),
			$atts,
			'md_translate_switcher'
		);

		return MDT_Widget::render( array(
			'style'      => $atts['style'],
			'show_names' => (bool) $atts['show_names'],
			'show_flags' => (bool) $atts['show_flags'],
			'show_codes' => (bool) $atts['show_codes'],
			'icon_size'  => (int) $atts['icon_size'],
			'class'      => $atts['class'],
		) );
	}

	private function build_switcher_html() {
		return MDT_Widget::render( array(
			'style'      => get_option( 'mdt_switcher_style',      'list' ),
			'show_names' => (bool) get_option( 'mdt_switcher_show_names', '1' ),
			'show_flags' => (bool) get_option( 'mdt_switcher_show_flags', '0' ),
			'show_codes' => (bool) get_option( 'mdt_switcher_show_codes', '0' ),
			'icon_size'  => (int) get_option( 'mdt_switcher_icon_size', 20 ),
		) );
	}

	// =========================================================================
	// Assets & hreflang
	// =========================================================================

	public function enqueue_assets() {
		wp_enqueue_style( 'mdt-frontend', MDT_PLUGIN_URL . 'assets/css/frontend.css', array(), MDT_VERSION );
		wp_enqueue_script( 'mdt-frontend', MDT_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), MDT_VERSION, true );
		wp_localize_script( 'mdt-frontend', 'mdtFrontend', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'mdt_frontend' ),
			'activeLang' => $this->get_active_lang(),
			'resetParam' => self::COOKIE_RESET,
			'targets'    => array_values( (array) get_option( 'mdt_target_langs', array() ) ),
		) );
	}

	public function add_hreflang_tags() {
		if ( ! is_singular() ) {
			return;
		}
		$targets     = (array) get_option( 'mdt_target_langs', array() );
		$current_url = MDT_Widget::current_url_without_lang();

		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $current_url ) . '">' . "\n";
		foreach ( $targets as $lang_code ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $lang_code ) . '" href="' . esc_url( add_query_arg( 'lang', $lang_code, $current_url ) ) . '">' . "\n";
		}
	}
}
