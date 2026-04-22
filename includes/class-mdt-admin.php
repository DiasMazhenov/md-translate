<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MDT_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_mdt_flush_cache',    array( $this, 'ajax_flush_cache' ) );
		add_action( 'wp_ajax_mdt_test_translate', array( $this, 'ajax_test_translate' ) );
		add_action( 'wp_ajax_mdt_glossary_save',  array( $this, 'ajax_glossary_save' ) );
		add_action( 'wp_ajax_mdt_glossary_delete',array( $this, 'ajax_glossary_delete' ) );
		add_action( 'wp_ajax_mdt_glossary_list',  array( $this, 'ajax_glossary_list' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'MD-Translate Settings', 'md-translate' ),
			__( 'MD-Translate', 'md-translate' ),
			'manage_options',
			'md-translate',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		$fields = array(
			'mdt_api_provider', 'mdt_google_api_key', 'mdt_source_lang',
			'mdt_target_langs', 'mdt_switcher_pos',   'mdt_switcher_style',
			'mdt_switcher_show_names', 'mdt_switcher_show_flags', 'mdt_switcher_show_codes',
			'mdt_switcher_icon_size',
			'mdt_cache_enabled', 'mdt_cache_lifetime',
			// CSS custom properties
			'mdt_css_font_size', 'mdt_css_padding', 'mdt_css_radius', 'mdt_css_gap',
			'mdt_css_color', 'mdt_css_bg', 'mdt_css_border_color',
			'mdt_css_active_color', 'mdt_css_active_bg',
			'mdt_css_hover_color', 'mdt_css_hover_bg',
			'mdt_css_custom',
		);
		foreach ( $fields as $field ) {
			register_setting( 'mdt_settings_group', $field );
		}
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_md-translate' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'mdt-admin', MDT_PLUGIN_URL . 'assets/css/admin.css', array(), MDT_VERSION );
		wp_enqueue_script( 'mdt-admin', MDT_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), MDT_VERSION, true );
		wp_localize_script( 'mdt-admin', 'mdtAdmin', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'mdt_admin' ),
			'langs'    => MDT_Widget::language_map(),
			'i18n'     => array(
				'flushing'      => __( 'Flushing…',          'md-translate' ),
				'flushed'       => __( 'Cache cleared!',     'md-translate' ),
				'testing'       => __( 'Translating…',       'md-translate' ),
				'saving'        => __( 'Saving…',            'md-translate' ),
				'saved'         => __( 'Saved!',             'md-translate' ),
				'deleting'      => __( 'Deleting…',          'md-translate' ),
				'confirmDelete' => __( 'Delete this entry?', 'md-translate' ),
			),
		) );
	}

	// ---- AJAX: cache ----

	public function ajax_flush_cache() {
		check_ajax_referer( 'mdt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Forbidden' ); }
		MDT_Cache::flush();
		wp_send_json_success( __( 'Translation cache cleared.', 'md-translate' ) );
	}

	public function ajax_test_translate() {
		check_ajax_referer( 'mdt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Forbidden' ); }

		$text   = sanitize_text_field( wp_unslash( $_POST['text']   ?? '' ) );
		$target = sanitize_text_field( wp_unslash( $_POST['target'] ?? 'en' ) );

		if ( '' === $text ) { wp_send_json_error( __( 'Please enter text.', 'md-translate' ) ); }

		$translator = new MDT_Translator();
		$result     = $translator->translate( $text, $target );

		if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); }
		wp_send_json_success( $result );
	}

	// ---- AJAX: glossary ----

	public function ajax_glossary_save() {
		check_ajax_referer( 'mdt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Forbidden' ); }

		$data = array(
			'id'             => absint( $_POST['id'] ?? 0 ),
			'source_text'    => sanitize_textarea_field( wp_unslash( $_POST['source_text']    ?? '' ) ),
			'target_lang'    => sanitize_text_field( wp_unslash( $_POST['target_lang']    ?? '' ) ),
			'translated'     => sanitize_textarea_field( wp_unslash( $_POST['translated']     ?? '' ) ),
			'case_sensitive' => ! empty( $_POST['case_sensitive'] ),
			'whole_word'     => ! empty( $_POST['whole_word'] ),
		);

		if ( '' === $data['source_text'] || '' === $data['target_lang'] || '' === $data['translated'] ) {
			wp_send_json_error( __( 'All fields are required.', 'md-translate' ) );
		}

		$id = MDT_Glossary::save( $data );
		wp_send_json_success( array( 'id' => $id ) );
	}

	public function ajax_glossary_delete() {
		check_ajax_referer( 'mdt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Forbidden' ); }
		MDT_Glossary::delete( absint( $_POST['id'] ?? 0 ) );
		wp_send_json_success();
	}

	public function ajax_glossary_list() {
		check_ajax_referer( 'mdt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Forbidden' ); }
		wp_send_json_success( MDT_Glossary::get_flat() );
	}

	// ---- Page ----

	public function render_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		?>
		<div class="wrap mdt-wrap">
			<h1><?php esc_html_e( 'MD-Translate', 'md-translate' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', admin_url( 'options-general.php?page=md-translate' ) ) ); ?>"
					class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'md-translate' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'glossary', admin_url( 'options-general.php?page=md-translate' ) ) ); ?>"
					class="nav-tab <?php echo 'glossary' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Glossary', 'md-translate' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'widget', admin_url( 'options-general.php?page=md-translate' ) ) ); ?>"
					class="nav-tab <?php echo 'widget' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Switcher / Widget', 'md-translate' ); ?>
				</a>
			</nav>

			<div class="mdt-tab-content">
				<?php
				if ( 'glossary' === $tab ) {
					$this->render_tab_glossary();
				} elseif ( 'widget' === $tab ) {
					$this->render_tab_widget();
				} else {
					$this->render_tab_settings();
				}
				?>
			</div>
		</div>
		<?php
	}

	// ---- Tab: Settings ----

	private function render_tab_settings() {
		$provider   = get_option( 'mdt_api_provider', 'unofficial' );
		$api_key    = get_option( 'mdt_google_api_key', '' );
		$source     = get_option( 'mdt_source_lang', 'auto' );
		$targets    = (array) get_option( 'mdt_target_langs', array( 'en', 'ru', 'de' ) );
		$cache_on   = get_option( 'mdt_cache_enabled', '1' );
		$cache_ttl  = get_option( 'mdt_cache_lifetime', 86400 );
		$all_langs  = MDT_Widget::language_map();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'mdt_settings_group' ); ?>

			<div class="mdt-card">
				<h2><?php esc_html_e( 'Translation Provider', 'md-translate' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Provider', 'md-translate' ); ?></th>
						<td>
							<label><input type="radio" name="mdt_api_provider" value="unofficial" <?php checked( $provider, 'unofficial' ); ?>>
								<?php esc_html_e( 'Unofficial Google Translate (free, no key)', 'md-translate' ); ?>
							</label><br>
							<label><input type="radio" name="mdt_api_provider" value="google_api" <?php checked( $provider, 'google_api' ); ?>>
								<?php esc_html_e( 'Google Cloud Translation API (API key required)', 'md-translate' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Unofficial is free but may be rate-limited and violates Google ToS. Cloud API is stable for production.', 'md-translate' ); ?></p>
						</td>
					</tr>
					<tr class="mdt-api-key-row" <?php echo 'google_api' !== $provider ? 'style="display:none"' : ''; ?>>
						<th><?php esc_html_e( 'Google API Key', 'md-translate' ); ?></th>
						<td>
							<input type="text" name="mdt_google_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Google Cloud Console → APIs & Services → Credentials.', 'md-translate' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="mdt-card">
				<h2><?php esc_html_e( 'Languages', 'md-translate' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Source Language', 'md-translate' ); ?></th>
						<td>
							<select name="mdt_source_lang">
								<option value="auto" <?php selected( $source, 'auto' ); ?>><?php esc_html_e( 'Auto-detect', 'md-translate' ); ?></option>
								<?php foreach ( $all_langs as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $source, $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Target Languages', 'md-translate' ); ?></th>
						<td>
							<div class="mdt-lang-grid">
								<?php foreach ( $all_langs as $code => $label ) : ?>
									<label>
										<input type="checkbox" name="mdt_target_langs[]"
											value="<?php echo esc_attr( $code ); ?>"
											<?php checked( in_array( $code, $targets, true ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
				</table>
			</div>

			<div class="mdt-card">
				<h2><?php esc_html_e( 'Cache', 'md-translate' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable Cache', 'md-translate' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="mdt_cache_enabled" value="1" <?php checked( $cache_on, '1' ); ?>>
								<?php esc_html_e( 'Store translations in DB to reduce API calls', 'md-translate' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Lifetime (seconds)', 'md-translate' ); ?></th>
						<td>
							<input type="number" name="mdt_cache_lifetime" value="<?php echo esc_attr( $cache_ttl ); ?>" min="60" class="small-text">
							<p class="description"><?php esc_html_e( 'Default: 86400 (24 h)', 'md-translate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Flush Cache', 'md-translate' ); ?></th>
						<td>
							<button type="button" id="mdt-flush-cache" class="button"><?php esc_html_e( 'Clear Translation Cache', 'md-translate' ); ?></button>
							<span id="mdt-flush-msg"></span>
						</td>
					</tr>
				</table>
			</div>

			<div class="mdt-card">
				<h2><?php esc_html_e( 'Test Translation', 'md-translate' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Text', 'md-translate' ); ?></th>
						<td><textarea id="mdt-test-input" rows="3" class="large-text"><?php esc_html_e( 'Hello, World!', 'md-translate' ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Target', 'md-translate' ); ?></th>
						<td>
							<select id="mdt-test-lang">
								<?php foreach ( $all_langs as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, 'ru' ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" id="mdt-test-btn" class="button button-secondary"><?php esc_html_e( 'Translate', 'md-translate' ); ?></button>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Result', 'md-translate' ); ?></th>
						<td><div id="mdt-test-result" class="mdt-test-result"></div></td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	// ---- Tab: Glossary ----

	private function render_tab_glossary() {
		$all_langs = MDT_Widget::language_map();
		$targets   = (array) get_option( 'mdt_target_langs', array() );
		?>
		<div class="mdt-card">
			<h2><?php esc_html_e( 'Glossary — Custom Translation Overrides', 'md-translate' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Words, phrases, or full sentences listed here will replace the machine-translated version. Applied after translation, per language.', 'md-translate' ); ?>
			</p>

			<div class="mdt-glossary-form">
				<h3 id="mdt-glossary-form-title"><?php esc_html_e( 'Add Entry', 'md-translate' ); ?></h3>
				<input type="hidden" id="mdt-glossary-id" value="0">
				<table class="form-table mdt-glossary-fields">
					<tr>
						<th><?php esc_html_e( 'Source text', 'md-translate' ); ?><br><small><?php esc_html_e( '(as it appears after machine translation)', 'md-translate' ); ?></small></th>
						<td><textarea id="mdt-g-source" class="regular-text" rows="3" placeholder="<?php esc_attr_e( 'Word, phrase, or full sentence to replace', 'md-translate' ); ?>"></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Language', 'md-translate' ); ?></th>
						<td>
							<select id="mdt-g-lang">
								<?php
								$show_langs = ! empty( $targets ) ? $targets : array_keys( $all_langs );
								foreach ( $show_langs as $code ) :
									$label = $all_langs[ $code ] ?? strtoupper( $code );
								?>
									<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Replace with', 'md-translate' ); ?></th>
						<td><textarea id="mdt-g-translated" class="regular-text" rows="3" placeholder="<?php esc_attr_e( 'Your custom translation', 'md-translate' ); ?>"></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Options', 'md-translate' ); ?></th>
						<td>
							<label><input type="checkbox" id="mdt-g-case"> <?php esc_html_e( 'Case-sensitive', 'md-translate' ); ?></label>&nbsp;&nbsp;
							<label><input type="checkbox" id="mdt-g-whole" checked> <?php esc_html_e( 'Whole word only', 'md-translate' ); ?></label>
							<br><small><?php esc_html_e( '"Whole word" is ignored automatically for multi-word phrases and sentences.', 'md-translate' ); ?></small>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" id="mdt-glossary-save" class="button button-primary"><?php esc_html_e( 'Save Entry', 'md-translate' ); ?></button>
					<button type="button" id="mdt-glossary-cancel" class="button" style="display:none"><?php esc_html_e( 'Cancel', 'md-translate' ); ?></button>
					<span id="mdt-glossary-msg"></span>
				</p>
			</div>
		</div>

		<div class="mdt-card">
			<h2><?php esc_html_e( 'Existing Entries', 'md-translate' ); ?></h2>

			<div class="mdt-glossary-filter">
				<label><?php esc_html_e( 'Filter by language:', 'md-translate' ); ?>
					<select id="mdt-glossary-filter-lang">
						<option value=""><?php esc_html_e( '— All —', 'md-translate' ); ?></option>
						<?php foreach ( $all_langs as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<table class="wp-list-table widefat fixed striped mdt-glossary-table" id="mdt-glossary-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source text',  'md-translate' ); ?></th>
						<th><?php esc_html_e( 'Language',     'md-translate' ); ?></th>
						<th><?php esc_html_e( 'Replace with', 'md-translate' ); ?></th>
						<th><?php esc_html_e( 'Options',      'md-translate' ); ?></th>
						<th><?php esc_html_e( 'Actions',      'md-translate' ); ?></th>
					</tr>
				</thead>
				<tbody id="mdt-glossary-tbody">
					<tr><td colspan="5"><?php esc_html_e( 'Loading…', 'md-translate' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	// ---- Tab: Widget / Switcher ----

	private function render_tab_widget() {
		$pos        = get_option( 'mdt_switcher_pos',        'top' );
		$style      = get_option( 'mdt_switcher_style',      'list' );
		$show_names = get_option( 'mdt_switcher_show_names', '1' );
		$show_flags = get_option( 'mdt_switcher_show_flags', '0' );
		$show_codes = get_option( 'mdt_switcher_show_codes', '0' );
		$icon_size  = get_option( 'mdt_switcher_icon_size',   20 );
		$custom_css = get_option( 'mdt_css_custom',           '' );

		$css = array(
			'font_size'    => get_option( 'mdt_css_font_size',    '' ),
			'padding'      => get_option( 'mdt_css_padding',      '' ),
			'radius'       => get_option( 'mdt_css_radius',       '' ),
			'gap'          => get_option( 'mdt_css_gap',          '' ),
			'color'        => get_option( 'mdt_css_color',        '' ),
			'bg'           => get_option( 'mdt_css_bg',           '' ),
			'border_color' => get_option( 'mdt_css_border_color', '' ),
			'active_color' => get_option( 'mdt_css_active_color', '' ),
			'active_bg'    => get_option( 'mdt_css_active_bg',    '' ),
			'hover_color'  => get_option( 'mdt_css_hover_color',  '' ),
			'hover_bg'     => get_option( 'mdt_css_hover_bg',     '' ),
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'mdt_settings_group' ); ?>

			<div class="mdt-card">
				<h2><?php esc_html_e( 'Switcher Defaults', 'md-translate' ); ?></h2>
				<p class="description">
					<?php printf( esc_html__( 'Default settings for automatic injection and %s with no attributes.', 'md-translate' ), '<code>[md_translate_switcher]</code>' ); ?>
				</p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Auto-inject position', 'md-translate' ); ?></th>
						<td>
							<select name="mdt_switcher_pos">
								<option value="top"    <?php selected( $pos, 'top' );    ?>><?php esc_html_e( 'Top of content',          'md-translate' ); ?></option>
								<option value="bottom" <?php selected( $pos, 'bottom' ); ?>><?php esc_html_e( 'Bottom of content',        'md-translate' ); ?></option>
								<option value="widget" <?php selected( $pos, 'widget' ); ?>><?php esc_html_e( 'Widget / Shortcode only', 'md-translate' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Display style', 'md-translate' ); ?></th>
						<td>
							<select name="mdt_switcher_style">
								<option value="list"     <?php selected( $style, 'list' );     ?>><?php esc_html_e( 'List (horizontal)',  'md-translate' ); ?></option>
								<option value="dropdown" <?php selected( $style, 'dropdown' ); ?>><?php esc_html_e( 'Dropdown (select)', 'md-translate' ); ?></option>
								<option value="flags"    <?php selected( $style, 'flags' );    ?>><?php esc_html_e( 'Flags (inline)',     'md-translate' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Show', 'md-translate' ); ?></th>
						<td>
							<label><input type="checkbox" name="mdt_switcher_show_names" value="1" <?php checked( $show_names, '1' ); ?>>
								<?php esc_html_e( 'Language names', 'md-translate' ); ?></label><br>
							<label><input type="checkbox" name="mdt_switcher_show_flags" value="1" <?php checked( $show_flags, '1' ); ?>>
								<?php esc_html_e( 'Flag emoji', 'md-translate' ); ?></label><br>
							<label><input type="checkbox" name="mdt_switcher_show_codes" value="1" <?php checked( $show_codes, '1' ); ?>>
								<?php esc_html_e( 'Language codes (DE, RU…)', 'md-translate' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Source icon size', 'md-translate' ); ?></th>
						<td>
							<input type="number" name="mdt_switcher_icon_size" min="12" max="64"
								value="<?php echo esc_attr( $icon_size ); ?>" style="width:70px"> px
							<p class="description"><?php esc_html_e( 'Size of the translate icon shown for the original/source language item.', 'md-translate' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="mdt-card">
				<h2><?php esc_html_e( 'CSS Customization', 'md-translate' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'These values are applied as CSS custom properties on every .mdt-switcher element. Leave blank to use the theme default.', 'md-translate' ); ?>
				</p>

				<h3 style="margin-bottom:4px"><?php esc_html_e( 'Layout', 'md-translate' ); ?></h3>
				<table class="form-table mdt-css-table">
					<?php $this->css_row( 'mdt_css_font_size',    __( 'Font size',     'md-translate' ), $css['font_size'],    '14px',   'e.g. 14px, 0.875rem' ); ?>
					<?php $this->css_row( 'mdt_css_padding',      __( 'Item padding',  'md-translate' ), $css['padding'],      '3px 10px', 'e.g. 4px 12px' ); ?>
					<?php $this->css_row( 'mdt_css_radius',       __( 'Border radius', 'md-translate' ), $css['radius'],       '3px',    'e.g. 4px, 20px, 0' ); ?>
					<?php $this->css_row( 'mdt_css_gap',          __( 'Item gap',      'md-translate' ), $css['gap'],          '4px',    'e.g. 6px, 0.5rem' ); ?>
				</table>

				<h3 style="margin-bottom:4px"><?php esc_html_e( 'Colors — Normal state', 'md-translate' ); ?></h3>
				<table class="form-table mdt-css-table">
					<?php $this->css_color_row( 'mdt_css_color',        __( 'Text color',        'md-translate' ), $css['color'] ); ?>
					<?php $this->css_color_row( 'mdt_css_bg',           __( 'Background',        'md-translate' ), $css['bg'] ); ?>
					<?php $this->css_color_row( 'mdt_css_border_color', __( 'Border color',      'md-translate' ), $css['border_color'] ); ?>
				</table>

				<h3 style="margin-bottom:4px"><?php esc_html_e( 'Colors — Active (current language)', 'md-translate' ); ?></h3>
				<table class="form-table mdt-css-table">
					<?php $this->css_color_row( 'mdt_css_active_color', __( 'Active text color', 'md-translate' ), $css['active_color'] ); ?>
					<?php $this->css_color_row( 'mdt_css_active_bg',    __( 'Active background', 'md-translate' ), $css['active_bg'] ); ?>
				</table>

				<h3 style="margin-bottom:4px"><?php esc_html_e( 'Colors — Hover', 'md-translate' ); ?></h3>
				<table class="form-table mdt-css-table">
					<?php $this->css_color_row( 'mdt_css_hover_color',  __( 'Hover text color',  'md-translate' ), $css['hover_color'] ); ?>
					<?php $this->css_color_row( 'mdt_css_hover_bg',     __( 'Hover background',  'md-translate' ), $css['hover_bg'] ); ?>
				</table>
			</div>

			<div class="mdt-card">
				<h2><?php esc_html_e( 'Custom CSS', 'md-translate' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Additional CSS output in wp_head. Use .mdt-switcher, .mdt-switcher--list, .mdt-switcher--dropdown, .mdt-switcher--flags, .mdt-source-icon, etc.', 'md-translate' ); ?>
				</p>
				<textarea name="mdt_css_custom" rows="8" class="large-text code"><?php echo esc_textarea( $custom_css ); ?></textarea>
			</div>

			<div class="mdt-card">
				<h2><?php esc_html_e( 'Shortcode Reference', 'md-translate' ); ?></h2>
				<pre class="mdt-code">[md_translate_switcher]
[md_translate_switcher style="dropdown" show_flags="1" show_names="0"]
[md_translate_switcher style="flags"    show_flags="1" show_names="1" show_codes="0"]
[md_translate_switcher style="list"     show_flags="1" show_names="0" show_codes="1" icon_size="24" class="my-nav-switcher"]</pre>
				<table class="widefat mdt-attr-table" style="margin-top:10px">
					<thead><tr>
						<th><?php esc_html_e( 'Attribute',   'md-translate' ); ?></th>
						<th><?php esc_html_e( 'Values',      'md-translate' ); ?></th>
						<th><?php esc_html_e( 'Default',     'md-translate' ); ?></th>
					</tr></thead>
					<tbody>
						<tr><td><code>style</code></td><td><code>list</code> · <code>dropdown</code> · <code>flags</code></td><td><?php echo esc_html( $style ); ?></td></tr>
						<tr><td><code>show_names</code></td><td><code>0</code> / <code>1</code></td><td><?php echo esc_html( $show_names ); ?></td></tr>
						<tr><td><code>show_flags</code></td><td><code>0</code> / <code>1</code></td><td><?php echo esc_html( $show_flags ); ?></td></tr>
						<tr><td><code>show_codes</code></td><td><code>0</code> / <code>1</code></td><td><?php echo esc_html( $show_codes ); ?></td></tr>
						<tr><td><code>icon_size</code></td><td><?php esc_html_e( 'px integer', 'md-translate' ); ?></td><td><?php echo esc_html( $icon_size ); ?></td></tr>
						<tr><td><code>class</code></td><td><?php esc_html_e( 'Extra CSS class on wrapper', 'md-translate' ); ?></td><td>—</td></tr>
					</tbody>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	// ---- CSS field helpers ----

	private function css_row( $name, $label, $value, $placeholder = '', $hint = '' ) {
		?>
		<tr>
			<th><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					class="regular-text mdt-css-input">
				<?php if ( $hint ) : ?>
					<span class="description"><?php echo esc_html( $hint ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function css_color_row( $name, $label, $value ) {
		?>
		<tr>
			<th><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td style="display:flex;align-items:center;gap:8px;padding-top:8px">
				<input type="color" id="<?php echo esc_attr( $name . '_picker' ); ?>"
					value="<?php echo esc_attr( $value ?: '#000000' ); ?>"
					class="mdt-color-picker"
					data-target="<?php echo esc_attr( $name ); ?>">
				<input type="text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. #0073aa or rgba(0,0,0,0.5)', 'md-translate' ); ?>"
					class="regular-text mdt-css-input mdt-color-text">
			</td>
		</tr>
		<?php
	}
}
