<?php
/**
 * Plugin Name: MD-Translate
 * Plugin URI:  https://github.com/your-repo/md-translate
 * Description: Automatically translates WordPress pages and posts using Google Translate. Includes a per-language glossary and a customisable language-switcher widget.
 * Version:     1.1.0
 * Author: Mazhenov.kz
 * Author URI: https://mazhenov.kz/
 * License:     GPL-2.0+
 * Text Domain: md-translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MDT_VERSION',    '1.2.0' );
define( 'MDT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MDT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MDT_PLUGIN_DIR . 'includes/class-mdt-cache.php';
require_once MDT_PLUGIN_DIR . 'includes/class-mdt-glossary.php';
require_once MDT_PLUGIN_DIR . 'includes/class-mdt-translator.php';
require_once MDT_PLUGIN_DIR . 'includes/class-mdt-widget.php';
require_once MDT_PLUGIN_DIR . 'includes/class-mdt-admin.php';
require_once MDT_PLUGIN_DIR . 'includes/class-mdt-frontend.php';

function mdt_init() {
	new MDT_Admin();
	new MDT_Frontend();
}
add_action( 'plugins_loaded', 'mdt_init' );

add_action( 'widgets_init', function () {
	register_widget( 'MDT_Widget' );
} );

// Output CSS custom properties + freeform custom CSS in <head>
add_action( 'wp_head', 'mdt_output_custom_css', 99 );
function mdt_output_custom_css() {
	$vars = array(
		'--mdt-font-size'    => get_option( 'mdt_css_font_size',    '' ),
		'--mdt-padding'      => get_option( 'mdt_css_padding',      '' ),
		'--mdt-radius'       => get_option( 'mdt_css_radius',       '' ),
		'--mdt-gap'          => get_option( 'mdt_css_gap',          '' ),
		'--mdt-color'        => get_option( 'mdt_css_color',        '' ),
		'--mdt-bg'           => get_option( 'mdt_css_bg',           '' ),
		'--mdt-border-color' => get_option( 'mdt_css_border_color', '' ),
		'--mdt-active-color' => get_option( 'mdt_css_active_color', '' ),
		'--mdt-active-bg'    => get_option( 'mdt_css_active_bg',    '' ),
		'--mdt-hover-color'  => get_option( 'mdt_css_hover_color',  '' ),
		'--mdt-hover-bg'     => get_option( 'mdt_css_hover_bg',     '' ),
	);

	$props = '';
	foreach ( $vars as $prop => $val ) {
		$val = trim( $val );
		if ( '' !== $val ) {
			$props .= $prop . ':' . $val . ';';
		}
	}

	$custom = trim( get_option( 'mdt_css_custom', '' ) );

	if ( '' === $props && '' === $custom ) {
		return;
	}

	echo "<style id=\"mdt-custom-css\">\n";
	if ( '' !== $props ) {
		echo '.mdt-switcher{' . esc_html( $props ) . "}\n";
	}
	if ( '' !== $custom ) {
		// Custom CSS is stored as-is (admin-only input); strip tags for safety
		echo wp_strip_all_tags( $custom ) . "\n";
	}
	echo "</style>\n";
}

register_activation_hook( __FILE__, 'mdt_activate' );
function mdt_activate() {
	MDT_Cache::create_table();
	MDT_Glossary::create_table();
	MDT_Glossary::upgrade_table();

	$defaults = array(
		'source_lang'          => 'auto',
		'target_langs'         => array( 'en', 'ru', 'de' ),
		'switcher_pos'         => 'top',
		'switcher_style'       => 'list',
		'switcher_show_names'  => '1',
		'switcher_show_flags'  => '0',
		'switcher_show_codes'  => '0',
		'cache_enabled'        => '1',
		'cache_lifetime'       => 86400,
		'api_provider'         => 'unofficial',
		'google_api_key'       => '',
	);
	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( 'mdt_' . $key ) ) {
			update_option( 'mdt_' . $key, $value );
		}
	}
}
