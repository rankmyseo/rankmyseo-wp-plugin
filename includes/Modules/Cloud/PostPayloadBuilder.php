<?php
/**
 * Builds inventory and event payloads from WordPress posts.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

/**
 * Maps public posts into the RankMySEO sync contract.
 */
final class PostPayloadBuilder {
	/**
	 * Whether this post should be sent to RankMySEO.
	 *
	 * @param object $post Post object.
	 * @return bool
	 */
	public static function is_syncable( object $post ): bool {
		$type = isset( $post->post_type ) ? (string) $post->post_type : '';
		if ( in_array( $type, array( 'revision', 'attachment', 'nav_menu_item' ), true ) ) {
			return false;
		}
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post ) ) {
			return false;
		}
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post ) ) {
			return false;
		}
		if ( function_exists( 'is_post_type_viewable' ) ) {
			return (bool) is_post_type_viewable( $type );
		}
		return in_array( $type, array( 'post', 'page' ), true );
	}

	/**
	 * Build an inventory row for one post.
	 *
	 * @param object $post Post object.
	 * @return array<string, mixed>|null
	 */
	public static function inventory_from_post( object $post ): ?array {
		if ( ! self::is_syncable( $post ) ) {
			return null;
		}
		$url = self::permalink( $post );
		if ( '' === $url ) {
			return null;
		}

		$row = array(
			'wpPostId' => (int) $post->ID,
			'url'      => $url,
			'postType' => sanitize_key( (string) $post->post_type ),
			'status'   => sanitize_key( (string) $post->post_status ),
		);

		$title = self::meta_or_title( $post );
		if ( '' !== $title ) {
			$row['title'] = $title;
		}
		$description = self::meta_text( (int) $post->ID, '_rankmyseo_description', 400 );
		if ( '' !== $description ) {
			$row['metaDescription'] = $description;
		}
		$keyword = self::meta_text( (int) $post->ID, '_rankmyseo_focus_keyword', 200 );
		if ( '' !== $keyword ) {
			$row['focusKeyword'] = $keyword;
		}
		$score = get_post_meta( (int) $post->ID, '_rankmyseo_seo_score', true );
		if ( is_numeric( $score ) ) {
			$row['seoScore'] = max( 0, min( 100, (int) $score ) );
		}
		$row['contentHash'] = hash(
			'sha256',
			(string) ( $post->post_modified_gmt ?? '' ) . '|' . $title . '|' . $description
		);
		return $row;
	}

	/**
	 * Build one sync event for a post.
	 *
	 * @param object      $post         Post object.
	 * @param string      $type         Event type.
	 * @param string|null $previous_url Previous public URL for a slug change.
	 * @return array<string, mixed>|null
	 */
	public static function event_from_post( object $post, string $type, ?string $previous_url = null ): ?array {
		if ( ! self::is_syncable( $post ) ) {
			return null;
		}
		$url = self::permalink( $post );
		$row = array(
			'type'     => $type,
			'wpPostId' => (int) $post->ID,
			'status'   => sanitize_key( (string) $post->post_status ),
			'postType' => sanitize_key( (string) $post->post_type ),
		);
		if ( '' !== $url ) {
			$row['url'] = $url;
		}
		$title = self::meta_or_title( $post );
		if ( '' !== $title ) {
			$row['title'] = $title;
		}
		if ( is_string( $previous_url ) && '' !== $previous_url ) {
			$row['previousUrl'] = $previous_url;
		}
		return $row;
	}

	/**
	 * Previous public URL after a slug change.
	 *
	 * @param object $after  Updated post.
	 * @param object $before Previous post.
	 * @return string
	 */
	public static function previous_url( object $after, object $before ): string {
		$current     = self::permalink( $after );
		$after_slug  = (string) ( $after->post_name ?? '' );
		$before_slug = (string) ( $before->post_name ?? '' );
		if ( '' === $current || '' === $after_slug || '' === $before_slug ) {
			return $current;
		}
		if ( ! str_contains( $current, $after_slug ) ) {
			return $current;
		}
		return str_replace( $after_slug, $before_slug, $current );
	}

	/**
	 * Collect published posts for a manual inventory sync.
	 *
	 * @param int $limit Maximum posts.
	 * @return list<array<string, mixed>>
	 */
	public static function collect( int $limit ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$limit = max( 1, min( 100, $limit ) );
		$posts = get_posts(
			array(
				'post_type'   => array( 'post', 'page' ),
				'post_status' => 'publish',
				'numberposts' => $limit,
			)
		);
		if ( ! is_array( $posts ) ) {
			return array();
		}
		$rows = array();
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}
			$row = self::inventory_from_post( $post );
			if ( null !== $row ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Public permalink, or an empty string when WordPress cannot build one.
	 *
	 * @param object $post Post object.
	 * @return string
	 */
	private static function permalink( object $post ): string {
		if ( ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$url = get_permalink( $post );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * SEO title override, falling back to the post title.
	 *
	 * @param object $post Post object.
	 * @return string
	 */
	private static function meta_or_title( object $post ): string {
		$custom = self::meta_text( (int) $post->ID, '_rankmyseo_title', 300 );
		if ( '' !== $custom ) {
			return $custom;
		}
		$title = sanitize_text_field( (string) ( $post->post_title ?? '' ) );
		if ( strlen( $title ) > 300 ) {
			$title = substr( $title, 0, 300 );
		}
		return $title;
	}

	/**
	 * Read one sanitized post meta string.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param int    $max     Maximum length.
	 * @return string
	 */
	private static function meta_text( int $post_id, string $key, int $max ): string {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$value = get_post_meta( $post_id, $key, true );
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = sanitize_text_field( (string) $value );
		if ( strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max );
		}
		return $text;
	}
}
