<?php
/**
 * Normalizes request paths and computes the indexed source hash.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Turns visitor URLs and admin input into a stable path plus SHA-256 hash.
 */
final class PathNormalizer {
	/**
	 * Settings repository.
	 *
	 * @var RedirectSettings
	 */
	private RedirectSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param RedirectSettings $settings Redirect settings.
	 */
	public function __construct( RedirectSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Normalize a path or absolute URL to a lowercase path without a query string.
	 *
	 * @param string $input Raw path or URL.
	 * @return string
	 */
	public function normalize_path( string $input ): string {
		$path = $this->extract_path( $input );
		$path = str_replace( "\0", '', $path );
		$path = rawurldecode( $path );
		$path = str_replace( '\\', '/', $path );

		$parts = explode( '/', $path );
		$clean = [];
		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $clean );
				continue;
			}
			$clean[] = $part;
		}

		$normalized = '/' . implode( '/', $clean );
		$normalized = (string) preg_replace( '#/+#', '/', $normalized );
		$normalized = strtolower( $normalized );

		if ( $this->settings->strip_trailing_slash() && '/' !== $normalized ) {
			$normalized = rtrim( $normalized, '/' );
		}

		if ( '' === $normalized ) {
			return '/';
		}

		if ( strlen( $normalized ) > 512 ) {
			$normalized = substr( $normalized, 0, 512 );
		}

		return $normalized;
	}

	/**
	 * Normalize a rule source for the given match type.
	 *
	 * Regex patterns are trimmed but not rewritten as paths.
	 *
	 * @param string $source     Raw source.
	 * @param string $match_type exact, prefix, or regex.
	 * @return string
	 */
	public function normalize_source( string $source, string $match_type ): string {
		if ( 'regex' === $match_type ) {
			return trim( str_replace( "\0", '', $source ) );
		}

		return $this->normalize_path( $source );
	}

	/**
	 * SHA-256 hex hash used by the source_hash index.
	 *
	 * @param string $value Normalized path or regex pattern.
	 * @return string
	 */
	public function hash( string $value ): string {
		return hash( 'sha256', $value );
	}

	/**
	 * Extract the query string without the leading question mark.
	 *
	 * @param string $input Raw path or URL.
	 * @return string
	 */
	public function extract_query( string $input ): string {
		$input = trim( str_replace( "\0", '', $input ) );
		$query = '';

		if ( preg_match( '#^https?://#i', $input ) ) {
			$parts = wp_parse_url( $input );
			if ( is_array( $parts ) && isset( $parts['query'] ) && is_string( $parts['query'] ) ) {
				$query = $parts['query'];
			}
		} else {
			$qpos = strpos( $input, '?' );
			if ( false !== $qpos ) {
				$query = substr( $input, $qpos + 1 );
			}
		}

		$hash = strpos( $query, '#' );
		if ( false !== $hash ) {
			$query = substr( $query, 0, $hash );
		}

		if ( strlen( $query ) > 512 ) {
			$query = substr( $query, 0, 512 );
		}

		return $query;
	}

	/**
	 * Normalize the current front-end request path.
	 *
	 * @return string
	 */
	public function current_request_path(): string {
		$uri = '/';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
		}

		return $this->normalize_path( $uri );
	}

	/**
	 * Read the current request query string.
	 *
	 * @return string
	 */
	public function current_request_query(): string {
		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			$query = sanitize_text_field( wp_unslash( (string) $_SERVER['QUERY_STRING'] ) );
			if ( strlen( $query ) > 512 ) {
				$query = substr( $query, 0, 512 );
			}
			return $query;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		return $this->extract_query( $uri );
	}

	/**
	 * Pull the path component out of a path or absolute URL.
	 *
	 * @param string $input Raw input.
	 * @return string
	 */
	private function extract_path( string $input ): string {
		$input = trim( $input );
		if ( '' === $input ) {
			return '/';
		}

		if ( preg_match( '#^https?://#i', $input ) ) {
			$parts = wp_parse_url( $input );
			if ( is_array( $parts ) && isset( $parts['path'] ) && is_string( $parts['path'] ) && '' !== $parts['path'] ) {
				return $parts['path'];
			}
			return '/';
		}

		$cut = strcspn( $input, '?#' );
		return substr( $input, 0, $cut );
	}
}
