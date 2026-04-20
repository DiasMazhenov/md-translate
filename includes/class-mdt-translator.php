<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles translation requests.
 *
 * Supports two providers:
 *   - 'unofficial'  — scrapes translate.google.com (free, no key, ToS-restricted)
 *   - 'google_api'  — Google Cloud Translation API v2 (requires API key)
 */
class MDT_Translator {

	// Max characters per single unofficial-API request
	const CHUNK_SIZE = 4500;

	/**
	 * Translate text from $source_lang to $target_lang.
	 *
	 * @param  string $text
	 * @param  string $target_lang  e.g. 'de', 'fr', 'ru'
	 * @param  string $source_lang  e.g. 'en', or 'auto'
	 * @return string|WP_Error
	 */
	public function translate( $text, $target_lang, $source_lang = 'auto' ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return $text;
		}

		// Return from cache if available
		$cached = MDT_Cache::get( $text, $source_lang, $target_lang );
		if ( false !== $cached ) {
			return $cached;
		}

		$provider = get_option( 'mdt_api_provider', 'unofficial' );
		$result   = ( 'google_api' === $provider )
			? $this->translate_via_cloud_api( $text, $target_lang, $source_lang )
			: $this->translate_via_unofficial( $text, $target_lang, $source_lang );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Apply glossary overrides after machine translation
		$result = MDT_Glossary::apply( $result, $target_lang );

		MDT_Cache::set( $text, $source_lang, $target_lang, $result );
		return $result;
	}

	// -------------------------------------------------------------------------
	// Unofficial Google Translate (no API key)
	// -------------------------------------------------------------------------

	private function translate_via_unofficial( $text, $target, $source ) {
		// Split long texts into chunks to stay within URL limits
		$chunks = $this->split_text( $text );
		$parts  = array();

		foreach ( $chunks as $chunk ) {
			$translated = $this->fetch_unofficial( $chunk, $target, $source );
			if ( is_wp_error( $translated ) ) {
				return $translated;
			}
			$parts[] = $translated;
		}

		return implode( ' ', $parts );
	}

	private function fetch_unofficial( $text, $target, $source ) {
		$url = add_query_arg(
			array(
				'client' => 'gtx',
				'sl'     => $source,
				'tl'     => $target,
				'dt'     => 't',
				'q'      => rawurlencode( $text ),
			),
			'https://translate.googleapis.com/translate_a/single'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'Mozilla/5.0 (compatible; MD-Translate WordPress Plugin)',
				'headers'    => array(
					'Accept'          => 'application/json',
					'Accept-Language' => 'en-US,en;q=0.9',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new WP_Error(
				'mdt_http_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Google Translate returned HTTP %d.', 'md-translate' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data[0] ) ) {
			return new WP_Error( 'mdt_parse_error', __( 'Could not parse translation response.', 'md-translate' ) );
		}

		$translated = '';
		foreach ( $data[0] as $segment ) {
			if ( ! empty( $segment[0] ) ) {
				$translated .= $segment[0];
			}
		}

		return $translated;
	}

	// -------------------------------------------------------------------------
	// Google Cloud Translation API v2
	// -------------------------------------------------------------------------

	private function translate_via_cloud_api( $text, $target, $source ) {
		$api_key = get_option( 'mdt_google_api_key', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'mdt_no_key', __( 'Google Cloud API key is not configured.', 'md-translate' ) );
		}

		$url  = 'https://translation.googleapis.com/language/translate/v2';
		$body = array(
			'q'      => $text,
			'target' => $target,
			'format' => 'html',
			'key'    => $api_key,
		);
		if ( 'auto' !== $source ) {
			$body['source'] = $source;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			$raw = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg = isset( $raw['error']['message'] ) ? $raw['error']['message'] : 'Unknown error';
			return new WP_Error( 'mdt_api_error', $msg );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['data']['translations'][0]['translatedText'] ) ) {
			return new WP_Error( 'mdt_parse_error', __( 'Could not parse Cloud API response.', 'md-translate' ) );
		}

		return html_entity_decode( $data['data']['translations'][0]['translatedText'], ENT_QUOTES, 'UTF-8' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function split_text( $text ) {
		if ( mb_strlen( $text ) <= self::CHUNK_SIZE ) {
			return array( $text );
		}

		$chunks    = array();
		$sentences = preg_split( '/(?<=[.!?])\s+/u', $text );
		$current   = '';

		foreach ( $sentences as $sentence ) {
			if ( mb_strlen( $current ) + mb_strlen( $sentence ) > self::CHUNK_SIZE ) {
				if ( '' !== $current ) {
					$chunks[] = $current;
				}
				$current = $sentence;
			} else {
				$current .= ( '' === $current ? '' : ' ' ) . $sentence;
			}
		}

		if ( '' !== $current ) {
			$chunks[] = $current;
		}

		return $chunks ?: array( $text );
	}
}
