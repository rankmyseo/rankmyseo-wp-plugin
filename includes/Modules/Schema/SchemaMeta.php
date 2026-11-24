<?php
/**
 * Per-post schema meta registration and sanitization.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

use WP_Error;

/**
 * Registers `_rankmyseo_schema_*` post meta and validates custom JSON-LD.
 */
final class SchemaMeta {
	/**
	 * Override for the generated content type. Empty uses the post-type default.
	 */
	public const META_TYPE = '_rankmyseo_schema_type';

	/**
	 * Custom JSON-LD document that replaces the generated @graph for one post.
	 */
	public const META_CUSTOM = '_rankmyseo_schema_custom';

	/**
	 * JSON list of FAQ question/answer pairs.
	 */
	public const META_FAQ = '_rankmyseo_schema_faq';

	/**
	 * Maximum stored custom JSON-LD length.
	 */
	private const CUSTOM_JSON_MAX = 50000;

	/**
	 * Maximum recursion depth for custom JSON-LD.
	 */
	private const CUSTOM_JSON_DEPTH = 12;

	/**
	 * Allowed per-post schema types, including `none` to suppress content nodes.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_TYPES = [
		'Article',
		'BlogPosting',
		'NewsArticle',
		'WebPage',
		'AboutPage',
		'ContactPage',
		'CollectionPage',
		'ProfilePage',
		'SearchResultsPage',
		'Product',
		'LocalBusiness',
		'FAQPage',
		'HowTo',
		'none',
	];

	/**
	 * WebPage subtypes that replace the WebPage @type instead of adding a content node.
	 *
	 * @var list<string>
	 */
	public const WEBPAGE_TYPES = [
		'WebPage',
		'AboutPage',
		'ContactPage',
		'CollectionPage',
		'ProfilePage',
		'SearchResultsPage',
	];

	/**
	 * Article subtypes.
	 *
	 * @var list<string>
	 */
	public const ARTICLE_TYPES = [
		'Article',
		'BlogPosting',
		'NewsArticle',
	];

	/**
	 * Register post meta for every post type.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$auth = [ self::class, 'auth_callback' ];

		register_meta(
			'post',
			self::META_TYPE,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_type' ],
				'auth_callback'     => $auth,
			]
		);

		register_meta(
			'post',
			self::META_CUSTOM,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_custom' ],
				'auth_callback'     => $auth,
			]
		);

		register_meta(
			'post',
			self::META_FAQ,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ self::class, 'sanitize_faq' ],
				'auth_callback'     => $auth,
			]
		);
	}

	/**
	 * Authorize meta writes for users who can edit the post.
	 *
	 * @param bool         $allowed  Whether the user can add the object.
	 * @param string       $meta_key Meta key.
	 * @param int          $post_id  Post ID.
	 * @param int          $user_id  User ID.
	 * @param string       $cap      Capability name.
	 * @param array<mixed> $caps     Primitive capabilities.
	 * @return bool
	 */
	public static function auth_callback( bool $allowed, string $meta_key, int $post_id, int $user_id = 0, string $cap = '', array $caps = [] ): bool {
		unset( $allowed, $meta_key, $user_id, $cap, $caps );

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize a schema type override.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string Allowed type, or an empty string when invalid.
	 */
	public static function sanitize_type( $value ): string {
		$type = is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
		$type = preg_replace( '/[^A-Za-z0-9]/', '', $type ) ?? '';

		if ( in_array( $type, self::ALLOWED_TYPES, true ) ) {
			return $type;
		}

		return '';
	}

	/**
	 * Stored schema type override for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Allowed type, or an empty string when the post uses the default.
	 */
	public static function read_type( int $post_id ): string {
		$raw = get_post_meta( $post_id, self::META_TYPE, true );
		return is_string( $raw ) ? self::sanitize_type( $raw ) : '';
	}

	/**
	 * Store or clear a per-post schema type override.
	 *
	 * An empty or unknown value removes the override so the post-type default applies.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $value   Raw type.
	 * @return void
	 */
	public static function write_type( int $post_id, $value ): void {
		$type = self::sanitize_type( $value );
		if ( '' === $type ) {
			delete_post_meta( $post_id, self::META_TYPE );
			return;
		}

		update_post_meta( $post_id, self::META_TYPE, $type );
	}

	/**
	 * Sanitize custom JSON-LD for storage. Invalid JSON is discarded.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string Canonical JSON, or an empty string.
	 */
	public static function sanitize_custom( $value ): string {
		$result = self::validate_custom_json( $value );
		if ( $result instanceof WP_Error ) {
			return '';
		}

		return $result;
	}

	/**
	 * Validate custom JSON-LD and return canonical JSON.
	 *
	 * An empty string is valid and clears the override.
	 *
	 * @param mixed $value Raw JSON string or decoded array.
	 * @return string|WP_Error
	 */
	public static function validate_custom_json( $value ) {
		if ( is_array( $value ) ) {
			$decoded = $value;
		} elseif ( is_string( $value ) ) {
			$raw = trim( wp_unslash( $value ) );
			if ( '' === $raw ) {
				return '';
			}
			if ( strlen( $raw ) > self::CUSTOM_JSON_MAX ) {
				return new WP_Error(
					'rankmyseo_schema_json',
					__( 'Custom JSON-LD is too large (maximum 50,000 characters).', 'rankmyseo' )
				);
			}
			$decoded = json_decode( $raw, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				return new WP_Error(
					'rankmyseo_schema_json',
					__( 'Custom JSON-LD must be a JSON object or array.', 'rankmyseo' )
				);
			}
		} else {
			return new WP_Error(
				'rankmyseo_schema_json',
				__( 'Custom JSON-LD must be a JSON object or array.', 'rankmyseo' )
			);
		}

		$clean = self::sanitize_node( $decoded, 0 );
		if ( ! is_array( $clean ) || [] === $clean ) {
			return new WP_Error(
				'rankmyseo_schema_json',
				__( 'Custom JSON-LD did not contain any usable fields.', 'rankmyseo' )
			);
		}

		$encoded = wp_json_encode( $clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return new WP_Error(
				'rankmyseo_schema_json',
				__( 'Custom JSON-LD could not be encoded.', 'rankmyseo' )
			);
		}

		return $encoded;
	}

	/**
	 * Decode stored custom JSON-LD into graph nodes.
	 *
	 * @param string $json Stored JSON.
	 * @return list<array<string, mixed>>|null
	 */
	public static function decode_graph( string $json ): ?array {
		$json = trim( $json );
		if ( '' === $json ) {
			return null;
		}

		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		if ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) {
			$nodes = $decoded['@graph'];
		} elseif ( self::is_list( $decoded ) ) {
			$nodes = $decoded;
		} else {
			$nodes = [ $decoded ];
		}

		$clean = [];
		foreach ( $nodes as $node ) {
			if ( is_array( $node ) && [] !== $node ) {
				$clean[] = $node;
			}
		}

		return [] === $clean ? null : $clean;
	}

	/**
	 * Read FAQ pairs stored on a post.
	 *
	 * @param int $post_id Post ID.
	 * @return list<array{question: string, answer: string}>
	 */
	public static function read_faqs( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_FAQ, true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return [];
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return [];
		}

		return self::normalize_faqs( $decoded );
	}

	/**
	 * Sanitize FAQ meta to a JSON string.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_faq( $value ): string {
		if ( is_string( $value ) ) {
			$value = trim( wp_unslash( $value ) );
			if ( '' === $value ) {
				return '';
			}
			$decoded = json_decode( $value, true );
		} else {
			$decoded = $value;
		}

		if ( ! is_array( $decoded ) ) {
			return '';
		}

		$faqs = self::normalize_faqs( $decoded );
		if ( [] === $faqs ) {
			return '';
		}

		$encoded = wp_json_encode( $faqs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Normalize a mixed FAQ list.
	 *
	 * @param array<mixed> $items Raw items.
	 * @return list<array{question: string, answer: string}>
	 */
	public static function normalize_faqs( array $items ): array {
		$faqs = [];
		foreach ( $items as $item ) {
			if ( count( $faqs ) >= 50 || ! is_array( $item ) ) {
				continue;
			}
			$question = isset( $item['question'] ) ? self::plain_text( $item['question'], 300 ) : '';
			$answer   = isset( $item['answer'] ) ? self::plain_text( $item['answer'], 5000 ) : '';
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$faqs[] = [
				'question' => $question,
				'answer'   => $answer,
			];
		}

		return $faqs;
	}

	/**
	 * Recursively sanitize a JSON node.
	 *
	 * @param mixed $node  Decoded JSON value.
	 * @param int   $depth Current depth.
	 * @return mixed
	 */
	private static function sanitize_node( $node, int $depth ) {
		if ( $depth > self::CUSTOM_JSON_DEPTH ) {
			return null;
		}

		if ( is_string( $node ) ) {
			return self::plain_text( $node, 10000 );
		}

		if ( is_int( $node ) || is_float( $node ) || is_bool( $node ) || null === $node ) {
			return $node;
		}

		if ( ! is_array( $node ) ) {
			return null;
		}

		$clean = [];
		foreach ( $node as $key => $value ) {
			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				continue;
			}
			if ( is_string( $key ) && strlen( $key ) > 80 ) {
				continue;
			}
			$child = self::sanitize_node( $value, $depth + 1 );
			if ( null === $child && null !== $value ) {
				continue;
			}
			if ( is_int( $key ) ) {
				$clean[] = $child;
			} else {
				$clean[ $key ] = $child;
			}
		}

		return $clean;
	}

	/**
	 * Reduce a value to plain text.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum characters.
	 * @return string
	 */
	private static function plain_text( $value, int $max ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		$text = wp_strip_all_tags( $text );
		$text = str_replace( "\0", '', $text );
		$text = trim( preg_replace( '/[ \t]+/', ' ', $text ) ?? $text );
		if ( mb_strlen( $text ) > $max ) {
			$text = mb_substr( $text, 0, $max );
		}

		return $text;
	}

	/**
	 * Whether an array is a list (PHP 8.0 compatible).
	 *
	 * @param array<mixed> $value Array to inspect.
	 * @return bool
	 */
	private static function is_list( array $value ): bool {
		$index = 0;
		foreach ( $value as $key => $unused ) {
			unset( $unused );
			if ( $key !== $index ) {
				return false;
			}
			++$index;
		}

		return true;
	}
}
