<?php
/**
 * Shared helpers for source adapters.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Robots, separators, and redirect rows used by every adapter.
 */
final class AdapterSupport {
	/**
	 * Separators the titles module accepts.
	 *
	 * @var array<int, string>
	 */
	private const SEPARATORS = [ '-', '|', '·', '—', '/', '•' ];

	/**
	 * Map a source separator token or character onto an allowed separator.
	 *
	 * @param mixed $value Raw separator.
	 * @return string Empty when it cannot be mapped.
	 */
	public static function separator( $value ): string {
		if ( is_array( $value ) ) {
			return '';
		}

		$raw = trim( (string) $value );
		if ( '' === $raw ) {
			return '';
		}

		if ( in_array( $raw, self::SEPARATORS, true ) ) {
			return $raw;
		}

		$map = [
			'sc-dash'   => '-',
			'sc-ndash'  => '-',
			'sc-mdash'  => '—',
			'sc-middot' => '·',
			'sc-bull'   => '•',
			'sc-pipe'   => '|',
			'sc-slash'  => '/',
			'dash'      => '-',
			'hyphen'    => '-',
			'pipe'      => '|',
			'middot'    => '·',
			'bullet'    => '•',
			'emdash'    => '—',
		];

		$key = strtolower( $raw );
		return $map[ $key ] ?? '';
	}

	/**
	 * Normalize a robots payload to boolean flags.
	 *
	 * @param mixed $value Raw robots value.
	 * @return array<string, bool>
	 */
	public static function robots( $value ): array {
		$value = PlainText::decode_structured( $value );
		$flags = [
			'noindex'      => false,
			'nofollow'     => false,
			'noarchive'    => false,
			'nosnippet'    => false,
			'noimageindex' => false,
		];

		if ( is_string( $value ) ) {
			$parts = preg_split( '/\s*,\s*/', strtolower( $value ) );
			$value = is_array( $parts ) ? $parts : [];
		}

		if ( ! is_array( $value ) ) {
			return $flags;
		}

		foreach ( $value as $key => $item ) {
			$name = is_string( $key ) ? strtolower( $key ) : '';
			$text = is_string( $item ) ? strtolower( trim( $item ) ) : '';

			if ( 'index' === $name && 'noindex' === $text ) {
				$flags['noindex'] = true;
			}
			if ( 'follow' === $name && 'nofollow' === $text ) {
				$flags['nofollow'] = true;
			}

			foreach ( array_keys( $flags ) as $flag ) {
				if ( $flag === $name && PlainText::is_truthy( $item ) ) {
					$flags[ $flag ] = true;
				}
				if ( $flag === $text ) {
					$flags[ $flag ] = true;
				}
			}
		}

		return $flags;
	}

	/**
	 * Build a redirect row or return null when it is not safe to store.
	 *
	 * @param PathHash $paths      Path hasher.
	 * @param string   $source     Raw source path or pattern.
	 * @param string   $target     Raw target URL.
	 * @param int      $status     HTTP status.
	 * @param string   $match_type exact, prefix, or regex.
	 * @param bool     $active     Whether the rule is enabled.
	 * @return array<string, mixed>|null
	 */
	public static function redirect( PathHash $paths, string $source, string $target, int $status, string $match_type, bool $active ): ?array {
		if ( ! in_array( $match_type, [ 'exact', 'prefix', 'regex' ], true ) ) {
			$match_type = 'exact';
		}

		if ( ! in_array( $status, [ 301, 302, 307, 410, 451 ], true ) ) {
			$status = 301;
		}

		$source_path = $paths->normalize_source( $source, $match_type );
		if ( '' === $source_path || '/' === $source_path ) {
			return null;
		}

		$target_url = PlainText::url_or_attachment( $target );
		if ( ctype_digit( $target_url ) ) {
			$target_url = '';
		}

		if ( '' === $target_url && ! in_array( $status, [ 410, 451 ], true ) ) {
			return null;
		}

		if ( '' !== $target_url && 'regex' !== $match_type ) {
			$target_path = $paths->normalize_path( $target_url );
			if ( $target_path === $source_path ) {
				return null;
			}
		}

		if ( strlen( $target_url ) > 1024 ) {
			$target_url = substr( $target_url, 0, 1024 );
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		return [
			'source_path'   => $source_path,
			'source_hash'   => $paths->hash( $source_path ),
			'match_type'    => $match_type,
			'target_url'    => '' === $target_url ? null : $target_url,
			'status_code'   => $status,
			'is_active'     => $active ? 1 : 0,
			'hits'          => 0,
			'last_hit_at'   => null,
			'source_origin' => 'import',
			'created_at'    => $now,
			'updated_at'    => $now,
		];
	}

	/**
	 * Turn an @handle into a profile URL and leave a real URL unchanged.
	 *
	 * @param string $value Handle or URL.
	 * @return string
	 */
	public static function profile_url( string $value ): string {
		$value = trim( $value );
		if ( str_starts_with( $value, '@' ) && strlen( $value ) > 1 ) {
			return 'https://x.com/' . rawurlencode( substr( $value, 1 ) );
		}

		return $value;
	}

	/**
	 * Collect absolute profile URLs.
	 *
	 * @param mixed $values URL list or map.
	 * @return array<int, string>
	 */
	public static function urls( $values ): array {
		if ( is_string( $values ) ) {
			$parts  = preg_split( '/\s+/', $values );
			$values = is_array( $parts ) ? $parts : [];
		}

		if ( ! is_array( $values ) ) {
			return [];
		}

		$urls = [];
		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['url'] ) ) {
					$value = $value['url'];
				} else {
					foreach ( self::urls( $value ) as $nested ) {
						if ( ! in_array( $nested, $urls, true ) ) {
							$urls[] = $nested;
						}
					}
					continue;
				}
			}
			$url = PlainText::url_or_attachment( $value );
			if ( '' === $url || ctype_digit( $url ) || in_array( $url, $urls, true ) ) {
				continue;
			}
			$urls[] = $url;
			if ( count( $urls ) >= 15 ) {
				break;
			}
		}

		return $urls;
	}

	/**
	 * First image-like value that is present.
	 *
	 * @param mixed ...$candidates Candidate values.
	 * @return string
	 */
	public static function first_image( ...$candidates ): string {
		foreach ( $candidates as $candidate ) {
			$image = PlainText::url_or_attachment( $candidate );
			if ( '' !== $image ) {
				return $image;
			}
		}

		return '';
	}
}
