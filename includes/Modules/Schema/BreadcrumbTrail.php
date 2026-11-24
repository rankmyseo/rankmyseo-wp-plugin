<?php
/**
 * Visible and schema breadcrumb trails for a single post.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

use WP_Post;
use WP_Term;

/**
 * Builds the Home → section → current page trail used by JSON-LD and builders.
 */
final class BreadcrumbTrail {
	/**
	 * Breadcrumb items for a post or page.
	 *
	 * The returned section is the primary category name for posts, so schema
	 * can keep `articleSection` in sync with the visible trail.
	 *
	 * @param WP_Post $post          Post object.
	 * @param string  $home_url      Site home URL.
	 * @param string  $current_title Current page title.
	 * @param string  $permalink     Current page URL.
	 * @return array{crumbs: list<array{name: string, url: string}>, section: string}
	 */
	public static function for_post( WP_Post $post, string $home_url, string $current_title, string $permalink ): array {
		$crumbs = [
			[
				'name' => __( 'Home', 'rankmyseo' ),
				'url'  => trailingslashit( untrailingslashit( $home_url ) ),
			],
		];

		$section = '';

		if ( 'page' === $post->post_type && function_exists( 'get_post_ancestors' ) ) {
			$ancestors = array_reverse( get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_post( (int) $ancestor_id );
				if ( ! $ancestor instanceof WP_Post ) {
					continue;
				}
				$crumbs[] = [
					'name' => self::plain( (string) $ancestor->post_title, 110 ),
					'url'  => (string) get_permalink( $ancestor ),
				];
			}
		} elseif ( 'post' === $post->post_type && function_exists( 'get_the_category' ) ) {
			$categories = get_the_category( (int) $post->ID );
			if ( is_array( $categories ) && isset( $categories[0] ) && $categories[0] instanceof WP_Term ) {
				$term     = $categories[0];
				$section  = self::plain( (string) $term->name, 80 );
				$link     = function_exists( 'get_category_link' ) ? get_category_link( $term ) : '';
				$crumbs[] = [
					'name' => $section,
					'url'  => is_string( $link ) ? $link : '',
				];
			}
		}

		$crumbs[] = [
			'name' => self::plain( $current_title, 110 ),
			'url'  => $permalink,
		];

		return [
			'crumbs'  => $crumbs,
			'section' => $section,
		];
	}

	/**
	 * Strip tags and trim to a maximum length.
	 *
	 * @param string $value Raw text.
	 * @param int    $max   Maximum characters.
	 * @return string
	 */
	private static function plain( string $value, int $max ): string {
		$text = trim( wp_strip_all_tags( $value ) );
		if ( mb_strlen( $text ) > $max ) {
			return mb_substr( $text, 0, $max );
		}

		return $text;
	}
}
