<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the custom glossary: per-language word/phrase overrides applied
 * after machine translation.
 */
class MDT_Glossary {

	const TABLE_NAME = 'mdt_glossary';

	// ---- Schema ----

	public static function create_table() {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE_NAME;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_text    TEXT          NOT NULL,
			target_lang    VARCHAR(10)   NOT NULL,
			translated     TEXT          NOT NULL,
			case_sensitive TINYINT(1)    NOT NULL DEFAULT 0,
			whole_word     TINYINT(1)    NOT NULL DEFAULT 1,
			created_at     DATETIME      NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_target_lang (target_lang)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Upgrade existing table columns from VARCHAR(500) to TEXT.
	 * Safe to call on every activation.
	 */
	public static function upgrade_table() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->query( "ALTER TABLE {$table} MODIFY source_text TEXT NOT NULL, MODIFY translated TEXT NOT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// ---- CRUD ----

	public static function get_all( $target_lang = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		if ( $target_lang ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE target_lang = %s ORDER BY source_text ASC", $target_lang )
			);
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY target_lang ASC, source_text ASC" );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}" . self::TABLE_NAME . " WHERE id = %d", (int) $id )
		);
	}

	public static function save( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$row = array(
			'source_text'    => sanitize_textarea_field( $data['source_text'] ),
			'target_lang'    => sanitize_text_field( $data['target_lang'] ),
			'translated'     => sanitize_textarea_field( $data['translated'] ),
			'case_sensitive' => ! empty( $data['case_sensitive'] ) ? 1 : 0,
			'whole_word'     => ! empty( $data['whole_word'] ) ? 1 : 0,
			'created_at'     => current_time( 'mysql' ),
		);
		$fmt = array( '%s', '%s', '%s', '%d', '%d', '%s' );

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $data['id'] ), $fmt, array( '%d' ) );
			return (int) $data['id'];
		}

		$wpdb->insert( $table, $row, $fmt );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . self::TABLE_NAME, array( 'id' => (int) $id ), array( '%d' ) );
	}

	// ---- Application ----

	/**
	 * Apply glossary substitutions to already-translated text.
	 *
	 * @param  string $text
	 * @param  string $target_lang
	 * @return string
	 */
	public static function apply( $text, $target_lang ) {
		$entries = self::get_all( $target_lang );
		if ( empty( $entries ) ) {
			return $text;
		}

		foreach ( $entries as $entry ) {
			$flags = 'u'; // unicode
			if ( ! $entry->case_sensitive ) {
				$flags .= 'i';
			}

			$source    = preg_quote( $entry->source_text, '/' );
			$is_phrase = str_contains( $entry->source_text, ' ' );

			// Word-boundary only makes sense for single words; phrases match as-is
			if ( $entry->whole_word && ! $is_phrase ) {
				$source = '(?<!\w)' . $source . '(?!\w)';
			}

			$text = preg_replace( '/' . $source . '/' . $flags, $entry->translated, $text );
		}

		return $text;
	}

	/**
	 * Return glossary as a flat lookup array for JS preview.
	 * [ ['source' => ..., 'translated' => ..., 'lang' => ...], ... ]
	 */
	public static function get_flat() {
		$rows   = self::get_all();
		$result = array();
		foreach ( $rows as $r ) {
			$result[] = array(
				'id'             => (int) $r->id,
				'source_text'    => $r->source_text,
				'target_lang'    => $r->target_lang,
				'translated'     => $r->translated,
				'case_sensitive' => (bool) $r->case_sensitive,
				'whole_word'     => (bool) $r->whole_word,
			);
		}
		return $result;
	}
}
