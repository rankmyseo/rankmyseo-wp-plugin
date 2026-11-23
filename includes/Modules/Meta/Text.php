<?php
/**
 * Plain-text and URL sanitizers for SEO fields.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Shared text cleanup for titles, descriptions, and image URLs.
 */
final class Text {
	/**
	 * Strip tags, shortcodes, and extra whitespace, then optionally truncate.
	 *
	 * @param string $value Raw text.
	 * @param int    $max   Maximum length. Zero disables truncation.
	 * @return string
	 */
	public static function plain( string $value, int $max = 0 ): string {
		$value = strip_shortcodes( $value );
		$value = wp_strip_all_tags( $value );
		$value = sanitize_text_field( $value );

		if ( $max > 0 && mb_strlen( $value ) > $max ) {
			$value = mb_substr( $value, 0, $max );
		}

		return trim( $value );
	}

	/**
	 * Accept an http(s) URL or a numeric attachment ID. Reject other schemes.
	 *
	 * @param string $value Raw URL or attachment ID.
	 * @return string
	 */
	public static function url_or_attachment( string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ctype_digit( $value ) ) {
			return $value;
		}

		$clean = esc_url_raw( $value );
		if ( '' === $clean ) {
			return '';
		}

		$parts  = wp_parse_url( $clean );
		$scheme = is_array( $parts ) && isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';

		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * Resolve a stored image value to a public URL.
	 *
	 * @param string $value    URL or attachment ID.
	 * @param string $fallback URL used when the value is empty.
	 * @return string
	 */
	public static function image_url( string $value, string $fallback = '' ): string {
		$candidate = '' !== $value ? $value : $fallback;

		if ( '' === $candidate ) {
			return '';
		}

		if ( ctype_digit( $candidate ) && function_exists( 'wp_get_attachment_image_url' ) ) {
			$resolved = wp_get_attachment_image_url( (int) $candidate, 'full' );
			return is_string( $resolved ) ? $resolved : '';
		}

		$url = self::url_or_attachment( $candidate );
		return ctype_digit( $url ) ? '' : $url;
	}
}
