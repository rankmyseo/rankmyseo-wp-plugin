<?php
/**
 * Finds other published posts that target the same focus keyword.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

use RankMySEO\Modules\Meta\MetaKeys;
use WP_Post;

/**
 * Case-insensitive keyword collision lookup.
 */
final class CannibalizationQuery {
	/**
	 * Matching published posts and pages.
	 *
	 * @param string $keyword   Focus keyword.
	 * @param int    $exclude   Post ID to leave out.
	 * @return list<array{id: int, title: string, editLink: string, postType: string}>
	 */
	public function find( string $keyword, int $exclude = 0 ): array {
		$keyword = trim( $keyword );
		if ( '' === $keyword ) {
			return [];
		}

		$types = get_post_types( [ 'public' => true ] );
		if ( ! is_array( $types ) ) {
			$types = [ 'post', 'page' ];
		}

		$posts = get_posts(
			[
				'post_type'      => array_values( $types ),
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'post__not_in'   => $exclude > 0 ? [ $exclude ] : [],
				'meta_key'       => MetaKeys::FOCUS_KEYWORD,
			]
		);

		$needle  = mb_strtolower( $keyword );
		$matches = [];
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$stored = trim( (string) get_post_meta( (int) $post->ID, MetaKeys::FOCUS_KEYWORD, true ) );
			if ( '' === $stored || mb_strtolower( $stored ) !== $needle ) {
				continue;
			}
			$matches[] = [
				'id'       => (int) $post->ID,
				'title'    => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'rankmyseo' ),
				'editLink' => $this->edit_link( (int) $post->ID ),
				'postType' => (string) $post->post_type,
			];
		}

		return $matches;
	}

	/**
	 * Admin edit URL for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function edit_link( int $post_id ): string {
		if ( function_exists( 'get_edit_post_link' ) ) {
			$link = get_edit_post_link( $post_id, 'raw' );
			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
		}
		return admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	}
}
