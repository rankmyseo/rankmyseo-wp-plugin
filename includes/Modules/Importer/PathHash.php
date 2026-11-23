<?php
/**
 * Path normalization and source_hash matching the redirects module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Builds the indexed source_hash for an imported redirect.
 *
 * The redirects module hashes `hash('sha256', normalized_path)`. Normalization
 * lowercases the path, drops the query string, resolves dot segments, and strips
 * a trailing slash when that redirect setting is on (the default).
 */
final class PathHash {
	/**
	 * Whether trailing slashes are removed before hashing.
	 *
	 * @var bool
	 */
	private bool $strip_trailing_slash;

	/**
	 * Constructor.
	 *
	 * @param bool $strip_trailing_slash Match `rankmyseo_redirects_settings`.
	 */
	public function __construct( bool $strip_trailing_slash = true ) {
		$this->strip_trailing_slash = $strip_trailing_slash;
	}

	/**
	 * Read the trailing-slash setting from the redirects option when it exists.
	 *
	 * @param SiteStore $store Destination store.
	 * @return self
	 */
	public static function from_store( SiteStore $store ): self {
		$settings = $store->get_option( MetaCatalog::OPTION_REDIRECTS );
		$strip    = true;

		if ( is_array( $settings ) && array_key_exists( 'strip_trailing_slash', $settings ) ) {
			$strip = ! in_array( $settings['strip_trailing_slash'], [ false, 0, '0', 'false', 'no', 'off' ], true );
		}

		return new self( $strip );
	}

	/**
	 * Normalize a public path the same way the redirects module does.
	 *
	 * @param string $input Raw path or absolute URL.
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

		if ( $this->strip_trailing_slash && '/' !== $normalized ) {
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
	 * Normalize a rule source for exact, prefix, or regex matching.
	 *
	 * @param string $source     Raw source.
	 * @param string $match_type exact, prefix, or regex.
	 * @return string
	 */
	public function normalize_source( string $source, string $match_type ): string {
		if ( 'regex' === $match_type ) {
			$pattern = trim( str_replace( "\0", '', $source ) );
			if ( strlen( $pattern ) > 512 ) {
				$pattern = substr( $pattern, 0, 512 );
			}
			return $pattern;
		}

		return $this->normalize_path( $source );
	}

	/**
	 * SHA-256 hex digest stored in source_hash.
	 *
	 * @param string $value Normalized path or regex pattern.
	 * @return string
	 */
	public function hash( string $value ): string {
		return hash( 'sha256', $value );
	}

	/**
	 * Pull the path out of a path or absolute URL.
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
