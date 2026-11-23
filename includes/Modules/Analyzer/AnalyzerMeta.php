<?php
/**
 * Registers analyzer score meta. Focus keyword stays on the meta module when present.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\MetaSanitizer;

/**
 * Post meta for the saved score and its check breakdown.
 */
final class AnalyzerMeta {
	public const SUMMARY = '_rankmyseo_seo_summary';

	/**
	 * Hook registration onto init, after the meta module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_keys' ], 21 );
	}

	/**
	 * Register score, summary, and focus keyword when it is not already registered.
	 *
	 * @return void
	 */
	public function register_keys(): void {
		$post_types = get_post_types( [ 'public' => true ] );
		if ( ! is_array( $post_types ) ) {
			$post_types = [ 'post', 'page' ];
		}

		foreach ( $post_types as $post_type ) {
			$post_type = (string) $post_type;
			if ( ! $this->is_registered( $post_type, MetaKeys::FOCUS_KEYWORD ) ) {
				register_post_meta(
					$post_type,
					MetaKeys::FOCUS_KEYWORD,
					[
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'auth_callback'     => [ $this, 'auth_post' ],
						'sanitize_callback' => [ MetaSanitizer::class, 'sanitize_keyword' ],
					]
				);
			}

			register_post_meta(
				$post_type,
				MetaKeys::SEO_SCORE,
				[
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => [ $this, 'auth_post' ],
					'sanitize_callback' => [ self::class, 'sanitize_score' ],
				]
			);

			register_post_meta(
				$post_type,
				self::SUMMARY,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => [ $this, 'auth_post' ],
					'sanitize_callback' => [ self::class, 'sanitize_summary' ],
				]
			);
		}
	}

	/**
	 * Editors of the post may read and write the score.
	 *
	 * @param bool   $allowed   Incoming permission.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post ID.
	 * @return bool
	 */
	public function auth_post( bool $allowed, string $meta_key, int $object_id ): bool {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', $object_id );
	}

	/**
	 * Clamp a score to 0–100.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_score( $value ): int {
		$score = (int) $value;
		if ( $score < 0 ) {
			return 0;
		}
		if ( $score > 100 ) {
			return 100;
		}
		return $score;
	}

	/**
	 * Keep a compact JSON object and drop anything else.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_summary( $value ): string {
		if ( is_array( $value ) ) {
			$encoded = wp_json_encode( $value );
			$value   = is_string( $encoded ) ? $encoded : '';
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		$encoded = wp_json_encode( $decoded );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > 20000 ) {
			return '';
		}
		return $encoded;
	}

	/**
	 * Whether a post meta key is already registered.
	 *
	 * @param string $post_type Post type.
	 * @param string $key       Meta key.
	 * @return bool
	 */
	private function is_registered( string $post_type, string $key ): bool {
		if ( ! function_exists( 'get_registered_meta_keys' ) ) {
			return false;
		}
		$keys = get_registered_meta_keys( 'post', $post_type );
		return is_array( $keys ) && isset( $keys[ $key ] );
	}
}
