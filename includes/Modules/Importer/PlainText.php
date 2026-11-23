<?php
/**
 * Small sanitizers for imported strings.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Normalizes imported text, URLs, and attachment IDs.
 */
final class PlainText {
	/**
	 * Collapse a value to one plain line and cap its length.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum characters.
	 * @return string
	 */
	public static function line( $value, int $max ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$text = sanitize_text_field( (string) $value );
		if ( mb_strlen( $text ) > $max ) {
			$text = mb_substr( $text, 0, $max );
		}

		return $text;
	}

	/**
	 * Keep an absolute http(s) URL or a numeric attachment ID.
	 *
	 * @param mixed $value Raw URL or attachment ID.
	 * @return string
	 */
	public static function url_or_attachment( $value ): string {
		if ( is_array( $value ) ) {
			if ( isset( $value['url'] ) ) {
				return self::url_or_attachment( $value['url'] );
			}
			if ( isset( $value['id'] ) ) {
				return self::url_or_attachment( $value['id'] );
			}
			return '';
		}

		if ( is_int( $value ) ) {
			return $value > 0 ? (string) $value : '';
		}

		$text = trim( (string) $value );
		if ( '' === $text ) {
			return '';
		}

		if ( ctype_digit( $text ) ) {
			return '0' === $text ? '' : $text;
		}

		$clean = esc_url_raw( $text );
		if ( ! is_string( $clean ) ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $clean ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * Coerce common truthy flags without treating an empty string as true.
	 *
	 * @param mixed $value Raw flag.
	 * @return bool
	 */
	public static function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 1 === (int) $value;
		}

		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, [ '1', 'true', 'yes', 'on', 'noindex', 'nofollow' ], true );
	}

	/**
	 * Decode a JSON string into an array when possible.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public static function decode_structured( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$trim = trim( $value );
		if ( '' === $trim ) {
			return $value;
		}

		if ( function_exists( 'is_serialized' ) && is_serialized( $trim ) && function_exists( 'maybe_unserialize' ) ) {
			return maybe_unserialize( $trim );
		}

		$first = $trim[0];
		if ( '{' === $first || '[' === $first ) {
			$decoded = json_decode( $trim, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return $value;
	}
}
