<?php
/**
 * Pure URL helpers for shop filters and permalink bases.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

/**
 * Detects catalog query parameters and rewrites paths without calling the network.
 */
final class CatalogPaths {
	/**
	 * Query keys that only change the order or filters of a shop archive.
	 *
	 * @var list<string>
	 */
	public const EXACT_PARAMS = [ 'orderby', 'min_price', 'max_price', 'rating_filter' ];

	/**
	 * Whether a query key is a shop sort, price, or layered-nav filter.
	 *
	 * @param string $key Query parameter name.
	 * @return bool
	 */
	public static function is_tracked_param( string $key ): bool {
		$key = strtolower( $key );
		if ( in_array( $key, self::EXACT_PARAMS, true ) ) {
			return true;
		}

		return str_starts_with( $key, 'filter_' ) || str_starts_with( $key, 'query_type_' );
	}

	/**
	 * Whether a query bag contains a tracked shop parameter.
	 *
	 * @param array<mixed> $query Query parameters.
	 * @return bool
	 */
	public static function has_tracked_params( array $query ): bool {
		foreach ( array_keys( $query ) as $key ) {
			if ( self::is_tracked_param( (string) $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove tracked shop parameters from a URL and keep every other parameter.
	 *
	 * @param string $url Absolute or relative URL.
	 * @return string
	 */
	public static function strip_tracked_params( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return $url;
		}

		$query = [];
		if ( isset( $parts['query'] ) && is_string( $parts['query'] ) && '' !== $parts['query'] ) {
			parse_str( $parts['query'], $query );
		}

		if ( ! self::has_tracked_params( $query ) ) {
			return $url;
		}

		foreach ( array_keys( $query ) as $key ) {
			if ( self::is_tracked_param( (string) $key ) ) {
				unset( $query[ $key ] );
			}
		}

		$rebuilt = http_build_query( $query );
		unset( $parts['query'] );
		if ( '' !== $rebuilt ) {
			$parts['query'] = $rebuilt;
		}

		return self::rebuild( $parts, isset( $parts['path'] ) ? (string) $parts['path'] : '' );
	}

	/**
	 * Path relative to the site home, without leading or trailing slashes.
	 *
	 * @param string $request_path Request path or absolute URL.
	 * @param string $home_path    Home path, such as `/shop` for a subdirectory install.
	 * @return string
	 */
	public static function relative_path( string $request_path, string $home_path = '' ): string {
		$path = $request_path;
		if ( str_contains( $path, '://' ) ) {
			$parsed = wp_parse_url( $path );
			$path   = is_array( $parsed ) && isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
		}

		$path = trim( $path );
		$home = trim( $home_path, '/' );
		$path = trim( $path, '/' );
		if ( '' !== $home && ( $path === $home || str_starts_with( $path, $home . '/' ) ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	}

	/**
	 * Remove the first `/{base}/` segment from a URL path.
	 *
	 * @param string $url  Absolute URL.
	 * @param string $base Permalink base without slashes, such as `product`.
	 * @return string
	 */
	public static function strip_base( string $url, string $base ): string {
		$base = trim( $base, '/' );
		if ( '' === $base || '' === $url ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['path'] ) || ! is_string( $parts['path'] ) ) {
			return $url;
		}

		$path   = $parts['path'];
		$needle = '/' . $base . '/';
		$pos    = strpos( $path, $needle );
		if ( false === $pos ) {
			if ( trim( $path, '/' ) !== $base ) {
				return $url;
			}
			$path = '/';
		} else {
			$path = substr( $path, 0, $pos ) . '/' . substr( $path, $pos + strlen( $needle ) );
			$path = '/' . ltrim( (string) preg_replace( '#/+#', '/', $path ), '/' );
		}

		return self::rebuild( $parts, $path );
	}

	/**
	 * Whether two URLs point at the same path.
	 *
	 * @param string $left  First URL or path.
	 * @param string $right Second URL or path.
	 * @return bool
	 */
	public static function same_path( string $left, string $right ): bool {
		return self::relative_path( $left ) === self::relative_path( $right );
	}

	/**
	 * Rebuild a URL from parse_url parts and a replacement path.
	 *
	 * @param array<string, mixed> $parts Parsed URL parts.
	 * @param string               $path  Replacement path.
	 * @return string
	 */
	private static function rebuild( array $parts, string $path ): string {
		$scheme = isset( $parts['scheme'] ) ? (string) $parts['scheme'] . '://' : '';
		$host   = isset( $parts['host'] ) ? (string) $parts['host'] : '';
		$port   = isset( $parts['port'] ) ? ':' . (string) $parts['port'] : '';
		$user   = isset( $parts['user'] ) ? (string) $parts['user'] : '';
		$pass   = isset( $parts['pass'] ) ? ':' . (string) $parts['pass'] : '';
		$auth   = '' !== $user ? $user . $pass . '@' : '';
		$query  = isset( $parts['query'] ) && '' !== (string) $parts['query'] ? '?' . (string) $parts['query'] : '';
		$frag   = isset( $parts['fragment'] ) ? '#' . (string) $parts['fragment'] : '';

		if ( '' === $path ) {
			$path = '/';
		} elseif ( ! str_starts_with( $path, '/' ) ) {
			$path = '/' . $path;
		}

		if ( '' === $scheme && '' === $host ) {
			return $path . $query . $frag;
		}

		return $scheme . $auth . $host . $port . $path . $query . $frag;
	}
}
