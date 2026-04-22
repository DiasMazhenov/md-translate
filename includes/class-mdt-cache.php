<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MDT_Cache {

	const TABLE_NAME = 'mdt_translations';

	public static function create_table() {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE_NAME;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			cache_key   VARCHAR(64)  NOT NULL,
			source_lang VARCHAR(10)  NOT NULL,
			target_lang VARCHAR(10)  NOT NULL,
			source_text LONGTEXT     NOT NULL,
			translated  LONGTEXT     NOT NULL,
			created_at  DATETIME     NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY cache_key (cache_key)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function get( $source_text, $source_lang, $target_lang ) {
		global $wpdb;

		$lifetime = (int) get_option( 'mdt_cache_lifetime', 86400 );
		if ( ! get_option( 'mdt_cache_enabled', '1' ) ) {
			return false;
		}

		$key  = self::make_key( $source_text, $source_lang, $target_lang );
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translated, created_at FROM {$wpdb->prefix}" . self::TABLE_NAME . " WHERE cache_key = %s",
				$key
			)
		);

		if ( ! $row ) {
			return false;
		}

		$age = time() - strtotime( $row->created_at );
		if ( $age > $lifetime ) {
			self::delete( $key );
			return false;
		}

		return $row->translated;
	}

	public static function set( $source_text, $source_lang, $target_lang, $translated ) {
		global $wpdb;

		if ( ! get_option( 'mdt_cache_enabled', '1' ) ) {
			return;
		}

		$key = self::make_key( $source_text, $source_lang, $target_lang );
		$wpdb->replace(
			$wpdb->prefix . self::TABLE_NAME,
			array(
				'cache_key'   => $key,
				'source_lang' => $source_lang,
				'target_lang' => $target_lang,
				'source_text' => $source_text,
				'translated'  => $translated,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public static function flush() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}" . self::TABLE_NAME ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Flush cached translations for a specific target language.
	 * Called automatically when a glossary entry is saved or deleted.
	 */
	public static function flush_by_lang( $target_lang ) {
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . self::TABLE_NAME,
			array( 'target_lang' => sanitize_text_field( $target_lang ) ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private static function delete( $key ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . self::TABLE_NAME, array( 'cache_key' => $key ), array( '%s' ) );
	}

	private static function make_key( $text, $source, $target ) {
		return md5( $source . '|' . $target . '|' . $text );
	}
}
