<?php
/**
 * Registers post and term meta with REST exposure, auth, and sanitization.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * WordPress register_post_meta / register_term_meta wiring.
 */
final class MetaRegistry {
	/**
	 * Hook registration onto init so public types are available.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_keys' ], 20 );
	}

	/**
	 * Register every SEO meta key for public posts and terms.
	 *
	 * @return void
	 */
	public function register_keys(): void {
		$post_types = get_post_types( [ 'public' => true ] );
		if ( ! is_array( $post_types ) ) {
			$post_types = [ 'post', 'page' ];
		}

		foreach ( $post_types as $post_type ) {
			foreach ( $this->definitions( 'post' ) as $key => $args ) {
				register_post_meta( (string) $post_type, $key, $args );
			}
		}

		$taxonomies = get_taxonomies( [ 'public' => true ] );
		if ( ! is_array( $taxonomies ) ) {
			$taxonomies = [ 'category', 'post_tag' ];
		}

		foreach ( $taxonomies as $taxonomy ) {
			foreach ( $this->definitions( 'term' ) as $key => $args ) {
				register_term_meta( (string) $taxonomy, $key, $args );
			}
		}
	}

	/**
	 * Auth callback for protected post meta.
	 *
	 * @param bool   $allowed   Whether the user can add the object.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post ID.
	 * @return bool
	 */
	public static function auth_post( bool $allowed, string $meta_key, int $object_id ): bool {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', $object_id );
	}

	/**
	 * Auth callback for protected term meta.
	 *
	 * @param bool   $allowed   Whether the user can add the object.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Term ID.
	 * @return bool
	 */
	public static function auth_term( bool $allowed, string $meta_key, int $object_id ): bool {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_term', $object_id );
	}

	/**
	 * Argument sets passed to register_meta.
	 *
	 * @param string $object_type post or term.
	 * @return array<string, array<string, mixed>>
	 */
	private function definitions( string $object_type ): array {
		$auth = 'term' === $object_type ? [ self::class, 'auth_term' ] : [ self::class, 'auth_post' ];

		$string = static function ( callable $sanitize, int $max = 0 ) use ( $auth ): array {
			unset( $max );
			return [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $auth,
				'sanitize_callback' => $sanitize,
			];
		};

		return [
			MetaKeys::TITLE               => $string( [ MetaSanitizer::class, 'sanitize_title' ] ),
			MetaKeys::DESCRIPTION         => $string( [ MetaSanitizer::class, 'sanitize_description' ] ),
			MetaKeys::CANONICAL           => $string( [ MetaSanitizer::class, 'sanitize_canonical' ] ),
			MetaKeys::FOCUS_KEYWORD       => $string( [ MetaSanitizer::class, 'sanitize_keyword' ] ),
			MetaKeys::OG_TITLE            => $string( [ MetaSanitizer::class, 'sanitize_title' ] ),
			MetaKeys::OG_DESCRIPTION      => $string( [ MetaSanitizer::class, 'sanitize_description' ] ),
			MetaKeys::OG_IMAGE            => $string( [ MetaSanitizer::class, 'sanitize_image' ] ),
			MetaKeys::TWITTER_TITLE       => $string( [ MetaSanitizer::class, 'sanitize_title' ] ),
			MetaKeys::TWITTER_DESCRIPTION => $string( [ MetaSanitizer::class, 'sanitize_description' ] ),
			MetaKeys::TWITTER_IMAGE       => $string( [ MetaSanitizer::class, 'sanitize_image' ] ),
			MetaKeys::TWITTER_CARD        => $string( [ MetaSanitizer::class, 'sanitize_twitter_card' ] ),
			MetaKeys::CORNERSTONE         => [
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $auth,
				'sanitize_callback' => [ MetaSanitizer::class, 'sanitize_bool' ],
			],
			MetaKeys::SECONDARY_KEYWORDS  => [
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type' => 'string',
						],
					],
				],
				'auth_callback'     => $auth,
				'sanitize_callback' => [ MetaSanitizer::class, 'sanitize_keywords' ],
			],
			MetaKeys::ROBOTS              => [
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'       => 'object',
						'properties' => [
							'noindex'           => [ 'type' => 'boolean' ],
							'nofollow'          => [ 'type' => 'boolean' ],
							'noarchive'         => [ 'type' => 'boolean' ],
							'nosnippet'         => [ 'type' => 'boolean' ],
							'noimageindex'      => [ 'type' => 'boolean' ],
							'max_snippet'       => [ 'type' => 'integer' ],
							'max_image_preview' => [ 'type' => 'string' ],
							'max_video_preview' => [ 'type' => 'integer' ],
						],
					],
				],
				'auth_callback'     => $auth,
				'sanitize_callback' => [ Robots::class, 'sanitize' ],
			],
		];
	}
}
