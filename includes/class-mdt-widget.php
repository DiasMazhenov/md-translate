<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Language-switcher widget.
 *
 * Shortcode: [md_translate_switcher style="list|dropdown|flags"
 *             show_names="1" show_flags="1" show_codes="0"
 *             icon_size="20" class="my-class"]
 */
class MDT_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'mdt_language_switcher',
			__( 'MD-Translate: Language Switcher', 'md-translate' ),
			array( 'description' => __( 'Displays a language switcher for MD-Translate.', 'md-translate' ) )
		);
	}

	// ---- Render ----

	public function widget( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore
		}
		echo self::render( $instance ); // phpcs:ignore
		echo $args['after_widget']; // phpcs:ignore
	}

	/**
	 * @param array $opts
	 *   style         list|dropdown|flags
	 *   show_names    bool
	 *   show_flags    bool
	 *   show_codes    bool
	 *   icon_size     int px — size of the translate-icon SVG (source item)
	 *   class         extra CSS class on the wrapper
	 *   active_lang   string — override active language (internal use)
	 */
	public static function render( $opts = array() ) {
		$targets = (array) get_option( 'mdt_target_langs', array() );
		if ( empty( $targets ) ) {
			return '';
		}

		$defaults = array(
			'style'       => get_option( 'mdt_switcher_style',      'list' ),
			'show_names'  => (bool) get_option( 'mdt_switcher_show_names', 1 ),
			'show_flags'  => (bool) get_option( 'mdt_switcher_show_flags', 0 ),
			'show_codes'  => (bool) get_option( 'mdt_switcher_show_codes', 0 ),
			'icon_size'   => (int) get_option( 'mdt_switcher_icon_size', 20 ),
			'class'       => '',
			'active_lang' => self::get_active_lang(),
		);
		$opts = wp_parse_args( $opts, $defaults );

		// Cast booleans from shortcode string values ("0"/"1")
		foreach ( array( 'show_names', 'show_flags', 'show_codes' ) as $k ) {
			$opts[ $k ] = filter_var( $opts[ $k ], FILTER_VALIDATE_BOOLEAN );
		}
		$opts['icon_size'] = max( 12, (int) $opts['icon_size'] );

		$current_url = self::current_url_without_lang();
		$all_langs   = self::language_map();

		$items   = array();
		// Source / original item — add mdt_reset so clicking it clears the cookie
		$items[] = array(
			'code'     => '',
			'label'    => self::source_label(),
			'flag'     => '',
			'url'      => add_query_arg( 'mdt_reset', '1', $current_url ),
			'active'   => '' === $opts['active_lang'],
			'is_source'=> true,
		);
		foreach ( $targets as $code ) {
			$items[] = array(
				'code'     => $code,
				'label'    => $all_langs[ $code ] ?? strtoupper( $code ),
				'flag'     => self::flag_emoji( $code ),
				'url'      => add_query_arg( 'lang', $code, $current_url ),
				'active'   => $opts['active_lang'] === $code,
				'is_source'=> false,
			);
		}

		// Build CSS custom properties from saved vars
		$css_vars   = self::build_css_vars();
		$extra_class = ! empty( $opts['class'] ) ? ' ' . sanitize_html_class( $opts['class'] ) : '';

		switch ( $opts['style'] ) {
			case 'dropdown':
				return self::render_dropdown( $items, $opts, $css_vars, $extra_class );
			case 'flags':
				return self::render_flags( $items, $opts, $css_vars, $extra_class );
			default:
				return self::render_list( $items, $opts, $css_vars, $extra_class );
		}
	}

	// ---- Style renderers ----

	private static function render_list( $items, $opts, $css_vars, $extra_class ) {
		ob_start();
		?>
		<ul class="mdt-switcher mdt-switcher--list<?php echo esc_attr( $extra_class ); ?>"
			style="<?php echo esc_attr( $css_vars ); ?>"
			data-mdt-style="list">
			<?php foreach ( $items as $item ) : ?>
				<li class="mdt-switcher__item<?php echo $item['active'] ? ' mdt-switcher__item--active' : ''; ?><?php echo $item['is_source'] ? ' mdt-switcher__item--source' : ''; ?>">
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="mdt-switcher__link">
						<?php echo self::item_html( $item, $opts ); // phpcs:ignore ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return ob_get_clean();
	}

	private static function render_dropdown( $items, $opts, $css_vars, $extra_class ) {
		ob_start();
		?>
		<div class="mdt-switcher mdt-switcher--dropdown<?php echo esc_attr( $extra_class ); ?>"
			style="<?php echo esc_attr( $css_vars ); ?>"
			data-mdt-style="dropdown">
			<select class="mdt-switcher__select" onchange="window.location.href=this.value">
				<?php foreach ( $items as $item ) : ?>
					<option value="<?php echo esc_url( $item['url'] ); ?>" <?php selected( $item['active'] ); ?>>
						<?php echo esc_html( self::dropdown_label( $item, $opts ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_flags( $items, $opts, $css_vars, $extra_class ) {
		ob_start();
		?>
		<div class="mdt-switcher mdt-switcher--flags<?php echo esc_attr( $extra_class ); ?>"
			style="<?php echo esc_attr( $css_vars ); ?>"
			data-mdt-style="flags">
			<?php foreach ( $items as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>"
					class="mdt-switcher__flag-item<?php echo $item['active'] ? ' mdt-switcher__flag-item--active' : ''; ?><?php echo $item['is_source'] ? ' mdt-switcher__flag-item--source' : ''; ?>"
					title="<?php echo esc_attr( $item['label'] ); ?>">
					<?php echo self::item_html( $item, $opts ); // phpcs:ignore ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build inner HTML for list / flags items.
	 * Source item shows SVG icon only if show_flags is enabled.
	 */
	private static function item_html( $item, $opts ) {
		$parts = array();

		if ( $item['is_source'] ) {
			// SVG translate-icon — shown for source item only if show_flags enabled
			if ( $opts['show_flags'] ) {
				$icon_size = (int) $opts['icon_size'];
				$svg_url   = MDT_PLUGIN_URL . 'assets/images/translate-icon.svg';
				$parts[]   = '<img src="' . esc_url( $svg_url ) . '" '
					. 'width="' . $icon_size . '" height="' . $icon_size . '" '
					. 'class="mdt-source-icon" alt="" aria-hidden="true">';
			}

			if ( $opts['show_names'] ) {
				$parts[] = '<span class="mdt-lang-name">' . esc_html( $item['label'] ) . '</span>';
			}
			if ( $opts['show_codes'] ) {
				$src_lang = get_option( 'mdt_source_lang', 'auto' );
				$parts[] = '<span class="mdt-lang-code">' . esc_html( self::language_code_display( $src_lang ) ) . '</span>';
			}

			// Fallback — always show something
			if ( empty( $parts ) ) {
				$parts[] = '<span class="mdt-lang-name">' . esc_html( $item['label'] ) . '</span>';
			}
		} else {
			if ( $opts['show_flags'] && $item['flag'] ) {
				$parts[] = '<span class="mdt-flag" aria-hidden="true">' . esc_html( $item['flag'] ) . '</span>';
			}
			if ( $opts['show_names'] ) {
				$parts[] = '<span class="mdt-lang-name">' . esc_html( $item['label'] ) . '</span>';
			}
			if ( $opts['show_codes'] && $item['code'] ) {
				$parts[] = '<span class="mdt-lang-code">' . esc_html( self::language_code_display( $item['code'] ) ) . '</span>';
			}
			// Fallback — always show something
			if ( empty( $parts ) ) {
				$parts[] = '<span class="mdt-lang-name">' . esc_html( $item['label'] ) . '</span>';
			}
		}

		return implode( '', $parts );
	}

	/**
	 * Build text label for a <option> in dropdown mode.
	 * <option> cannot contain HTML, so source uses 🌐 (if show_flags) and flags use emoji text.
	 */
	private static function dropdown_label( $item, $opts ) {
		if ( $item['is_source'] ) {
			$label = '';

			// Show globe emoji only if show_flags is enabled
			if ( $opts['show_flags'] ) {
				$label = '🌐';
			}

			if ( $opts['show_names'] ) {
				if ( '' !== $label ) {
					$label .= ' ';
				}
				$label .= $item['label'];
			} elseif ( $opts['show_codes'] ) {
				// Show source language code (e.g., "RU", "EN", "AUTO")
				$src_lang = get_option( 'mdt_source_lang', 'auto' );
				if ( '' !== $label ) {
					$label .= ' ';
				}
				$label .= self::language_code_display( $src_lang );
			}

			// Fallback: if nothing was built, show language name
			if ( '' === $label ) {
				$label = $item['label'];
			}

			return $label;
		}

		$label = '';

		// Add flag emoji only if enabled and available
		if ( $opts['show_flags'] && $item['flag'] ) {
			$label .= $item['flag'];
			// Add space separator only if text follows
			if ( $opts['show_names'] || $opts['show_codes'] ) {
				$label .= ' ';
			}
		}

		// Add language name or code
		if ( $opts['show_names'] ) {
			$label .= $item['label'];
		} elseif ( $opts['show_codes'] ) {
			$label .= self::language_code_display( $item['code'] );
		}

		// Fallback: if all visibility options are off, show language name
		if ( '' === $label ) {
			$label = $item['label'];
		}

		return $label;
	}

	// ---- CSS custom properties ----

	/**
	 * Build an inline style string from saved CSS var options.
	 */
	private static function build_css_vars() {
		$vars = array(
			'--mdt-font-size'       => get_option( 'mdt_css_font_size',       '' ),
			'--mdt-padding'         => get_option( 'mdt_css_padding',         '' ),
			'--mdt-radius'          => get_option( 'mdt_css_radius',          '' ),
			'--mdt-gap'             => get_option( 'mdt_css_gap',             '' ),
			'--mdt-color'           => get_option( 'mdt_css_color',           '' ),
			'--mdt-bg'              => get_option( 'mdt_css_bg',              '' ),
			'--mdt-border-color'    => get_option( 'mdt_css_border_color',    '' ),
			'--mdt-active-color'    => get_option( 'mdt_css_active_color',    '' ),
			'--mdt-active-bg'       => get_option( 'mdt_css_active_bg',       '' ),
			'--mdt-hover-color'     => get_option( 'mdt_css_hover_color',     '' ),
			'--mdt-hover-bg'        => get_option( 'mdt_css_hover_bg',        '' ),
		);

		$style = '';
		foreach ( $vars as $prop => $val ) {
			$val = trim( $val );
			if ( '' !== $val ) {
				$style .= $prop . ':' . $val . ';';
			}
		}
		return $style;
	}

	// ---- Widget admin form ----

	public function form( $instance ) {
		$d = wp_parse_args( $instance, array(
			'title'      => '',
			'style'      => 'list',
			'show_names' => 1,
			'show_flags' => 0,
			'show_codes' => 0,
			'icon_size'  => 20,
		) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'md-translate' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text" value="<?php echo esc_attr( $d['title'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"><?php esc_html_e( 'Style', 'md-translate' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'style' ) ); ?>">
				<option value="list"     <?php selected( $d['style'], 'list' ); ?>><?php esc_html_e( 'List',         'md-translate' ); ?></option>
				<option value="dropdown" <?php selected( $d['style'], 'dropdown' ); ?>><?php esc_html_e( 'Dropdown',     'md-translate' ); ?></option>
				<option value="flags"    <?php selected( $d['style'], 'flags' ); ?>><?php esc_html_e( 'Flags inline', 'md-translate' ); ?></option>
			</select>
		</p>
		<p>
			<label><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_names' ) ); ?>" value="1" <?php checked( $d['show_names'] ); ?>>
				<?php esc_html_e( 'Show language names', 'md-translate' ); ?></label>
		</p>
		<p>
			<label><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_flags' ) ); ?>" value="1" <?php checked( $d['show_flags'] ); ?>>
				<?php esc_html_e( 'Show flag emoji', 'md-translate' ); ?></label>
		</p>
		<p>
			<label><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_codes' ) ); ?>" value="1" <?php checked( $d['show_codes'] ); ?>>
				<?php esc_html_e( 'Show language code (DE, RU…)', 'md-translate' ); ?></label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'icon_size' ) ); ?>"><?php esc_html_e( 'Source icon size (px)', 'md-translate' ); ?></label>
			<input type="number" min="12" max="64"
				id="<?php echo esc_attr( $this->get_field_id( 'icon_size' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'icon_size' ) ); ?>"
				value="<?php echo esc_attr( $d['icon_size'] ); ?>" style="width:60px">
		</p>
		<?php
	}

	public function update( $new, $old ) {
		return array(
			'title'      => sanitize_text_field( $new['title'] ?? '' ),
			'style'      => in_array( $new['style'] ?? '', array( 'list', 'dropdown', 'flags' ), true ) ? $new['style'] : 'list',
			'show_names' => ! empty( $new['show_names'] ) ? 1 : 0,
			'show_flags' => ! empty( $new['show_flags'] ) ? 1 : 0,
			'show_codes' => ! empty( $new['show_codes'] ) ? 1 : 0,
			'icon_size'  => max( 12, (int) ( $new['icon_size'] ?? 20 ) ),
		);
	}

	// ---- Static helpers ----

	public static function get_active_lang() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lang    = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : '';
		$targets = (array) get_option( 'mdt_target_langs', array() );
		return in_array( $lang, $targets, true ) ? $lang : '';
	}

	public static function current_url_without_lang() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		return remove_query_arg( 'lang', $url );
	}

	private static function source_label() {
		$src = get_option( 'mdt_source_lang', 'auto' );
		if ( 'auto' === $src ) {
			return __( 'Original', 'md-translate' );
		}
		$map = self::language_map();
		return $map[ $src ] ?? strtoupper( $src );
	}

	private static function flag_emoji( $code ) {
		$map = array(
			'af'=>'🇿🇦','sq'=>'🇦🇱','ar'=>'🇸🇦','hy'=>'🇦🇲','az'=>'🇦🇿','eu'=>'🇪🇸',
			'be'=>'🇧🇾','bn'=>'🇧🇩','bs'=>'🇧🇦','bg'=>'🇧🇬','ca'=>'🇪🇸','zh'=>'🇨🇳',
			'hr'=>'🇭🇷','cs'=>'🇨🇿','da'=>'🇩🇰','nl'=>'🇳🇱','en'=>'🇺🇸','eo'=>'🏳',
			'et'=>'🇪🇪','fi'=>'🇫🇮','fr'=>'🇫🇷','gl'=>'🇪🇸','ka'=>'🇬🇪','de'=>'🇩🇪',
			'el'=>'🇬🇷','gu'=>'🇮🇳','ht'=>'🇭🇹','he'=>'🇮🇱','hi'=>'🇮🇳','hu'=>'🇭🇺',
			'is'=>'🇮🇸','id'=>'🇮🇩','ga'=>'🇮🇪','it'=>'🇮🇹','ja'=>'🇯🇵','kn'=>'🇮🇳',
			'kk'=>'🇰🇿','ko'=>'🇰🇷','lv'=>'🇱🇻','lt'=>'🇱🇹','mk'=>'🇲🇰','ms'=>'🇲🇾',
			'mt'=>'🇲🇹','mr'=>'🇮🇳','mn'=>'🇲🇳','ne'=>'🇳🇵','no'=>'🇳🇴','fa'=>'🇮🇷',
			'pl'=>'🇵🇱','pt'=>'🇵🇹','ro'=>'🇷🇴','ru'=>'🇷🇺','sr'=>'🇷🇸','sk'=>'🇸🇰',
			'sl'=>'🇸🇮','es'=>'🇪🇸','sw'=>'🇰🇪','sv'=>'🇸🇪','tl'=>'🇵🇭','ta'=>'🇮🇳',
			'te'=>'🇮🇳','th'=>'🇹🇭','tr'=>'🇹🇷','uk'=>'🇺🇦','ur'=>'🇵🇰','uz'=>'🇺🇿',
			'vi'=>'🇻🇳','cy'=>'🏴󠁧󠁢󠁷󠁬󠁳󠁿',
		);
		return $map[ $code ] ?? '';
	}

	/**
	 * Map language code to display code (e.g., 'kk' → 'KZ', others → uppercase).
	 */
	private static function language_code_display( $code ) {
		$map = array(
			'kk' => 'KZ',  // Kazakh
		);
		return $map[ $code ] ?? strtoupper( $code );
	}

	public static function language_map() {
		return array(
			'af'=>'Afrikaans','sq'=>'Albanian','ar'=>'Arabic','hy'=>'Armenian',
			'az'=>'Azerbaijani','eu'=>'Basque','be'=>'Belarusian','bn'=>'Bengali',
			'bs'=>'Bosnian','bg'=>'Bulgarian','ca'=>'Catalan','zh'=>'Chinese',
			'hr'=>'Croatian','cs'=>'Czech','da'=>'Danish','nl'=>'Dutch',
			'en'=>'English','eo'=>'Esperanto','et'=>'Estonian','fi'=>'Finnish',
			'fr'=>'French','gl'=>'Galician','ka'=>'Georgian','de'=>'German',
			'el'=>'Greek','gu'=>'Gujarati','ht'=>'Haitian Creole','he'=>'Hebrew',
			'hi'=>'Hindi','hu'=>'Hungarian','is'=>'Icelandic','id'=>'Indonesian',
			'ga'=>'Irish','it'=>'Italian','ja'=>'Japanese','kn'=>'Kannada',
			'kk'=>'Kazakh','ko'=>'Korean','lv'=>'Latvian','lt'=>'Lithuanian',
			'mk'=>'Macedonian','ms'=>'Malay','mt'=>'Maltese','mr'=>'Marathi',
			'mn'=>'Mongolian','ne'=>'Nepali','no'=>'Norwegian','fa'=>'Persian',
			'pl'=>'Polish','pt'=>'Portuguese','ro'=>'Romanian','ru'=>'Russian',
			'sr'=>'Serbian','sk'=>'Slovak','sl'=>'Slovenian','es'=>'Spanish',
			'sw'=>'Swahili','sv'=>'Swedish','tl'=>'Tagalog','ta'=>'Tamil',
			'te'=>'Telugu','th'=>'Thai','tr'=>'Turkish','uk'=>'Ukrainian',
			'ur'=>'Urdu','uz'=>'Uzbek','vi'=>'Vietnamese','cy'=>'Welsh',
		);
	}
}
