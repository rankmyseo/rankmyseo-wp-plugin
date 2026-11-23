<?php
/**
 * Detects RankMySEO noindex robots meta and sitemap inclusion rules.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Shared rules for excluding posts from XML sitemaps.
 */
final class RobotsMeta {
	/**
	 * Post and term meta key storing the robots directive object.
	 */
	public const META_KEY = '_rankmyseo_robots';

	/**
	 * Substrings that identify a stored noindex directive in JSON or PHP serialized meta.
	 *
	 * @var list<string>
	 */
	public const SQL_NEEDLES = [
		'"noindex":true',
		'"noindex": true',
		's:7:"noindex";b:1',
	];

	/**
	 * Whether a stored robots meta value asks crawlers not to index the object.
	 *
	 * Accepts JSON (`{"noindex":true}`) and PHP-serialized arrays written by WordPress meta APIs.
	 *
	 * @param string $raw Raw meta value.
	 * @return bool
	 */
	public static function is_noindex( string $raw ): bool {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return false;
		}

		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) && array_key_exists( 'noindex', $decoded ) ) {
			return self::is_truthy( $decoded['noindex'] );
		}

		if ( 1 === preg_match( '/^a:\d+:\{/', $raw ) ) {
			$unserialized = unserialize( $raw, [ 'allowed_classes' => false ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Reading WordPress' own meta serialization format with classes disabled.
			if ( is_array( $unserialized ) && array_key_exists( 'noindex', $unserialized ) ) {
				return self::is_truthy( $unserialized['noindex'] );
			}
		}

		return false;
	}

	/**
	 * Whether a public post belongs in the sitemap.
	 *
	 * @param array{id?: int, password?: string, status?: string, type?: string, robots?: string} $post         Post attributes.
	 * @param array<string, bool>                                                                 $type_map     Explicit post-type inclusion map. Missing keys use the default.
	 * @param array<int, int>                                                                     $excluded_ids Admin-excluded post IDs.
	 * @return bool
	 */
	public static function should_include_post( array $post, array $type_map, array $excluded_ids ): bool {
		if ( 'publish' !== (string) ( $post['status'] ?? '' ) ) {
			return false;
		}

		if ( '' !== (string) ( $post['password'] ?? '' ) ) {
			return false;
		}

		$id = (int) ( $post['id'] ?? 0 );
		if ( $id > 0 && in_array( $id, $excluded_ids, true ) ) {
			return false;
		}

		if ( ! self::is_type_included( (string) ( $post['type'] ?? '' ), $type_map ) ) {
			return false;
		}

		if ( self::is_noindex( (string) ( $post['robots'] ?? '' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a post type or taxonomy is included.
	 *
	 * An empty map includes every public type except attachments, which stay out until explicitly enabled.
	 *
	 * @param string              $type    Object name.
	 * @param array<string, bool> $type_map Explicit inclusion map.
	 * @return bool
	 */
	public static function is_type_included( string $type, array $type_map ): bool {
		if ( '' === $type ) {
			return false;
		}

		if ( array_key_exists( $type, $type_map ) ) {
			return (bool) $type_map[ $type ];
		}

		return 'attachment' !== $type;
	}

	/**
	 * Coerce a robots flag to bool.
	 *
	 * @param mixed $value Stored flag.
	 * @return bool
	 */
	private static function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 1 === (int) $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), [ '1', 'true', 'yes' ], true );
		}

		return false;
	}
}
