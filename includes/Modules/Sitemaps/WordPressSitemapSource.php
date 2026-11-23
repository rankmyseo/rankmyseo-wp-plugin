<?php
/**
 * WordPress database source for paginated sitemap providers.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

use wpdb;

/**
 * Reads public posts, terms, and authors with bounded SQL and noindex exclusion.
 */
final class WordPressSitemapSource implements SitemapSource {
	/**
	 * Sitemap settings.
	 *
	 * @var SitemapSettings
	 */
	private SitemapSettings $settings;

	/**
	 * WordPress database handle.
	 *
	 * @var wpdb|null
	 */
	private ?wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param SitemapSettings $settings Settings repository.
	 * @param wpdb|null       $wpdb     Optional database handle. Defaults to the global $wpdb.
	 */
	public function __construct( SitemapSettings $settings, ?wpdb $wpdb = null ) {
		$this->settings = $settings;
		$this->wpdb     = $wpdb;
	}

	/**
	 * {@inheritDoc}
	 */
	public function providers(): array {
		$providers = [];

		foreach ( $this->public_post_type_labels() as $name => $label ) {
			if ( ! $this->settings->is_post_type_included( $name ) ) {
				continue;
			}
			$providers[] = [
				'kind'  => 'post',
				'name'  => $name,
				'label' => $label,
				'slug'  => $this->slug_for( 'post', $name ),
			];
		}

		foreach ( $this->public_taxonomy_labels() as $name => $label ) {
			if ( ! $this->settings->is_taxonomy_included( $name ) ) {
				continue;
			}
			$providers[] = [
				'kind'  => 'taxonomy',
				'name'  => $name,
				'label' => $label,
				'slug'  => $this->slug_for( 'taxonomy', $name ),
			];
		}

		if ( $this->settings->include_authors() ) {
			$providers[] = [
				'kind'  => 'author',
				'name'  => 'author',
				'label' => __( 'Authors', 'rankmyseo' ),
				'slug'  => 'author',
			];
		}

		/**
		 * Filter sitemap providers before the index is rendered.
		 *
		 * @param list<array{kind: string, name: string, label: string, slug: string}> $providers Provider rows.
		 */
		$filtered = apply_filters( 'rankmyseo_sitemap_providers', $providers );
		if ( ! is_array( $filtered ) ) {
			return $providers;
		}

		$rows = [];
		foreach ( $filtered as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['kind'], $row['name'], $row['slug'] ) ) {
				continue;
			}
			$rows[] = [
				'kind'  => (string) $row['kind'],
				'name'  => (string) $row['name'],
				'label' => isset( $row['label'] ) ? (string) $row['label'] : (string) $row['name'],
				'slug'  => (string) $row['slug'],
			];
		}

		return $rows;
	}

	/**
	 * Count indexable URLs for one provider.
	 *
	 * @param string $kind Provider kind: post, taxonomy, or author.
	 * @param string $name Object name.
	 * @return int
	 */
	public function count( string $kind, string $name ): int {
		if ( 'post' === $kind ) {
			return $this->count_posts( $name );
		}
		if ( 'taxonomy' === $kind ) {
			return $this->count_terms( $name );
		}
		if ( 'author' === $kind ) {
			return $this->count_authors();
		}

		return 0;
	}

	/**
	 * Load one page of indexable URL entries.
	 *
	 * @param string $kind     Provider kind.
	 * @param string $name     Object name.
	 * @param int    $page     Page number starting at 1.
	 * @param int    $per_page Page size.
	 * @return array<int, array{loc: string, lastmod: string, images: array<int, string>}>
	 */
	public function entries( string $kind, string $name, int $page, int $per_page ): array {
		if ( 'post' === $kind ) {
			return $this->post_entries( $name, $page, $per_page );
		}
		if ( 'taxonomy' === $kind ) {
			return $this->term_entries( $name, $page, $per_page );
		}
		if ( 'author' === $kind ) {
			return $this->author_entries( $page, $per_page );
		}

		return [];
	}

	/**
	 * Public post type labels keyed by name.
	 *
	 * Falls back to posts and pages when WordPress post-type APIs are unavailable.
	 *
	 * @return array<string, string>
	 */
	public function public_post_type_labels(): array {
		if ( ! function_exists( 'get_post_types' ) ) {
			return [
				'post' => __( 'Posts', 'rankmyseo' ),
				'page' => __( 'Pages', 'rankmyseo' ),
			];
		}

		$objects = get_post_types( [ 'public' => true ], 'objects' );
		return $this->labels_from_objects( $objects );
	}

	/**
	 * Public taxonomy labels keyed by name.
	 *
	 * @return array<string, string>
	 */
	public function public_taxonomy_labels(): array {
		if ( ! function_exists( 'get_taxonomies' ) ) {
			return [
				'category' => __( 'Categories', 'rankmyseo' ),
				'post_tag' => __( 'Tags', 'rankmyseo' ),
			];
		}

		$objects = get_taxonomies( [ 'public' => true ], 'objects' );
		return $this->labels_from_objects( $objects );
	}

	/**
	 * Whether a post type is registered.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public function has_post_type( string $post_type ): bool {
		return array_key_exists( $post_type, $this->public_post_type_labels() );
	}

	/**
	 * Resolve a public sitemap slug to a provider, or null when unknown.
	 *
	 * @param string $slug Sitemap slug from the rewrite.
	 * @return array{kind: string, name: string, label: string, slug: string}|null
	 */
	public function resolve( string $slug ): ?array {
		foreach ( $this->providers() as $provider ) {
			if ( $provider['slug'] === $slug ) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * Public URL slug for a provider, avoiding post-type and taxonomy collisions.
	 *
	 * @param string $kind Provider kind.
	 * @param string $name Object name.
	 * @return string
	 */
	public function slug_for( string $kind, string $name ): string {
		if ( 'author' === $kind ) {
			return 'author';
		}

		if ( 'taxonomy' === $kind && array_key_exists( $name, $this->public_post_type_labels() ) ) {
			return 'tax-' . $name;
		}

		if ( 'post' === $kind && 'author' === $name ) {
			return 'type-author';
		}

		return $name;
	}

	/**
	 * Count indexable posts of one type.
	 *
	 * @param string $post_type Post type name.
	 * @return int
	 */
	private function count_posts( string $post_type ): int {
		$sql = $this->post_sql( $post_type, 0, 0, true );
		return $this->int_var( $sql );
	}

	/**
	 * One page of post URLs.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $page      Page number.
	 * @param int    $per_page  Page size.
	 * @return list<array{loc: string, lastmod: string, images: list<string>}>
	 */
	private function post_entries( string $post_type, int $page, int $per_page ): array {
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$sql    = $this->post_sql( $post_type, $per_page, $offset, false );
		$rows   = $this->rows( $sql );
		$images = $this->settings->include_images() ? $this->featured_image_map( $rows ) : [];
		$urls   = [];

		foreach ( $rows as $row ) {
			$post_id = (int) ( $row['ID'] ?? 0 );
			if ( $post_id < 1 || ! function_exists( 'get_permalink' ) ) {
				continue;
			}

			$permalink = get_permalink( $post_id );
			if ( ! is_string( $permalink ) || '' === $permalink ) {
				continue;
			}

			$entry_images = [];
			if ( isset( $images[ $post_id ] ) ) {
				$entry_images[] = $images[ $post_id ];
			}
			if ( $this->settings->include_images() && isset( $row['post_content'] ) ) {
				$entry_images = array_merge( $entry_images, $this->content_images( (string) $row['post_content'] ) );
			}

			$urls[] = [
				'loc'     => $permalink,
				'lastmod' => $this->format_gmt( (string) ( $row['post_modified_gmt'] ?? '' ) ),
				'images'  => array_values( array_unique( array_filter( $entry_images ) ) ),
			];
		}

		return $urls;
	}

	/**
	 * Build the prepared post query used for counts and pages.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $per_page  Page size. Ignored for counts.
	 * @param int    $offset    Offset. Ignored for counts.
	 * @param bool   $count     Whether to select COUNT(*).
	 * @return string
	 */
	private function post_sql( string $post_type, int $per_page, int $offset, bool $count ): string {
		$db      = $this->db();
		$columns = $count ? 'COUNT(p.ID)' : 'p.ID, p.post_modified_gmt, p.post_name, p.post_type, p.post_parent';
		if ( ! $count && $this->settings->include_images() ) {
			$columns .= ', p.post_content';
		}

		$args         = [ $post_type ];
		$exclude_sql  = '';
		$excluded_ids = $this->settings->excluded_post_ids();
		if ( [] !== $excluded_ids ) {
			$exclude_sql = ' AND p.ID NOT IN (' . implode( ',', array_fill( 0, count( $excluded_ids ), '%d' ) ) . ')';
			foreach ( $excluded_ids as $excluded_id ) {
				$args[] = $excluded_id;
			}
		}

		$args[] = RobotsMeta::META_KEY;
		foreach ( RobotsMeta::SQL_NEEDLES as $needle ) {
			$args[] = '%' . $db->esc_like( $needle ) . '%';
		}

		$limit_sql = '';
		if ( ! $count ) {
			$limit_sql = ' ORDER BY p.ID ASC LIMIT %d OFFSET %d';
			$args[]    = max( 1, $per_page );
			$args[]    = max( 0, $offset );
		}

		$like_sql = implode( ' OR ', array_fill( 0, count( RobotsMeta::SQL_NEEDLES ), 'pm.meta_value LIKE %s' ) );
		$sql      = "SELECT {$columns} FROM {$db->posts} p WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_password = ''{$exclude_sql} AND NOT EXISTS ( SELECT 1 FROM {$db->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s AND ( {$like_sql} ) ){$limit_sql}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders and $wpdb table properties only. Values are passed to prepare().
		return $db->prepare( $sql, ...$args );
	}

	/**
	 * Count non-empty, indexable terms.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return int
	 */
	private function count_terms( string $taxonomy ): int {
		return $this->int_var( $this->term_sql( $taxonomy, 0, 0, true ) );
	}

	/**
	 * One page of term archive URLs.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $page     Page number.
	 * @param int    $per_page Page size.
	 * @return list<array{loc: string, lastmod: string, images: list<string>}>
	 */
	private function term_entries( string $taxonomy, int $page, int $per_page ): array {
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$rows   = $this->rows( $this->term_sql( $taxonomy, $per_page, $offset, false ) );
		$urls   = [];

		foreach ( $rows as $row ) {
			$term_id = (int) ( $row['term_id'] ?? 0 );
			if ( $term_id < 1 || ! function_exists( 'get_term_link' ) ) {
				continue;
			}
			$link = get_term_link( $term_id, $taxonomy );
			if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
				continue;
			}
			$urls[] = [
				'loc'     => $link,
				'lastmod' => '',
				'images'  => [],
			];
		}

		return $urls;
	}

	/**
	 * Prepared term query.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $per_page Page size.
	 * @param int    $offset   Offset.
	 * @param bool   $count    Whether this is a count query.
	 * @return string
	 */
	private function term_sql( string $taxonomy, int $per_page, int $offset, bool $count ): string {
		$db           = $this->db();
		$columns      = $count ? 'COUNT(t.term_id)' : 't.term_id, t.slug, tt.taxonomy';
		$args         = [ $taxonomy ];
		$exclude_sql  = '';
		$excluded_ids = $this->settings->excluded_term_ids();
		if ( [] !== $excluded_ids ) {
			$exclude_sql = ' AND t.term_id NOT IN (' . implode( ',', array_fill( 0, count( $excluded_ids ), '%d' ) ) . ')';
			foreach ( $excluded_ids as $excluded_id ) {
				$args[] = $excluded_id;
			}
		}

		$args[] = RobotsMeta::META_KEY;
		foreach ( RobotsMeta::SQL_NEEDLES as $needle ) {
			$args[] = '%' . $db->esc_like( $needle ) . '%';
		}

		$limit_sql = '';
		if ( ! $count ) {
			$limit_sql = ' ORDER BY t.term_id ASC LIMIT %d OFFSET %d';
			$args[]    = max( 1, $per_page );
			$args[]    = max( 0, $offset );
		}

		$like_sql = implode( ' OR ', array_fill( 0, count( RobotsMeta::SQL_NEEDLES ), 'tm.meta_value LIKE %s' ) );
		$sql      = "SELECT {$columns} FROM {$db->terms} t INNER JOIN {$db->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND tt.count > 0{$exclude_sql} AND NOT EXISTS ( SELECT 1 FROM {$db->termmeta} tm WHERE tm.term_id = t.term_id AND tm.meta_key = %s AND ( {$like_sql} ) ){$limit_sql}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders and $wpdb table properties only. Values are passed to prepare().
		return $db->prepare( $sql, ...$args );
	}

	/**
	 * Count authors who have at least one indexable public post.
	 *
	 * @return int
	 */
	private function count_authors(): int {
		return $this->int_var( $this->author_sql( 0, 0, true ) );
	}

	/**
	 * One page of author archive URLs.
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Page size.
	 * @return list<array{loc: string, lastmod: string, images: list<string>}>
	 */
	private function author_entries( int $page, int $per_page ): array {
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$rows   = $this->rows( $this->author_sql( $per_page, $offset, false ) );
		$urls   = [];

		foreach ( $rows as $row ) {
			$user_id = (int) ( $row['ID'] ?? 0 );
			if ( $user_id < 1 || ! function_exists( 'get_author_posts_url' ) ) {
				continue;
			}
			$urls[] = [
				'loc'     => get_author_posts_url( $user_id ),
				'lastmod' => $this->format_gmt( (string) ( $row['lastmod'] ?? '' ) ),
				'images'  => [],
			];
		}

		return $urls;
	}

	/**
	 * Prepared author query limited to included public post types.
	 *
	 * @param int  $per_page Page size.
	 * @param int  $offset   Offset.
	 * @param bool $count    Whether this is a count query.
	 * @return string
	 */
	private function author_sql( int $per_page, int $offset, bool $count ): string {
		$db    = $this->db();
		$types = [];
		foreach ( array_keys( $this->public_post_type_labels() ) as $post_type ) {
			if ( $this->settings->is_post_type_included( $post_type ) ) {
				$types[] = $post_type;
			}
		}
		if ( [] === $types ) {
			$types = [ 'post' ];
		}

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args         = $types;
		$args[]       = RobotsMeta::META_KEY;
		foreach ( RobotsMeta::SQL_NEEDLES as $needle ) {
			$args[] = '%' . $db->esc_like( $needle ) . '%';
		}

		$like_sql = implode( ' OR ', array_fill( 0, count( RobotsMeta::SQL_NEEDLES ), 'pm.meta_value LIKE %s' ) );
		$columns  = $count ? 'COUNT(DISTINCT u.ID)' : 'u.ID, u.user_nicename, MAX(p.post_modified_gmt) AS lastmod';
		$group    = $count ? '' : ' GROUP BY u.ID';
		$limit    = '';
		if ( ! $count ) {
			$limit  = ' ORDER BY u.ID ASC LIMIT %d OFFSET %d';
			$args[] = max( 1, $per_page );
			$args[] = max( 0, $offset );
		}

		$sql = "SELECT {$columns} FROM {$db->users} u INNER JOIN {$db->posts} p ON p.post_author = u.ID WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ({$placeholders}) AND NOT EXISTS ( SELECT 1 FROM {$db->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s AND ( {$like_sql} ) ){$group}{$limit}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders and $wpdb table properties only. Values are passed to prepare().
		return $db->prepare( $sql, ...$args );
	}

	/**
	 * Map post IDs to featured image URLs.
	 *
	 * @param list<array<string, mixed>> $rows Post rows.
	 * @return array<int, string>
	 */
	private function featured_image_map( array $rows ): array {
		$ids = [];
		foreach ( $rows as $row ) {
			$id = (int) ( $row['ID'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		if ( [] === $ids || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return [];
		}

		$db           = $this->db();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "SELECT post_id, meta_value FROM {$db->postmeta} WHERE meta_key = '_thumbnail_id' AND post_id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list is generated from integer IDs.
		$prepared = $db->prepare( $sql, ...$ids );
		$map      = [];
		foreach ( $this->rows( $prepared ) as $row ) {
			$post_id       = (int) ( $row['post_id'] ?? 0 );
			$attachment_id = (int) ( $row['meta_value'] ?? 0 );
			if ( $post_id < 1 || $attachment_id < 1 ) {
				continue;
			}
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				$map[ $post_id ] = $url;
			}
		}

		return $map;
	}

	/**
	 * Absolute image URLs found in post content.
	 *
	 * @param string $content Post content HTML.
	 * @return list<string>
	 */
	private function content_images( string $content ): array {
		if ( '' === $content || ! preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			return [];
		}

		$images = [];
		foreach ( $matches[1] as $src ) {
			if ( ! is_string( $src ) ) {
				continue;
			}
			$url = $this->absolute_image_url( $src );
			if ( '' !== $url ) {
				$images[] = $url;
			}
			if ( count( $images ) >= 20 ) {
				break;
			}
		}

		return $images;
	}

	/**
	 * Normalize an image src to an absolute HTTP URL.
	 *
	 * @param string $src Image src attribute.
	 * @return string
	 */
	private function absolute_image_url( string $src ): string {
		$src = trim( html_entity_decode( $src, ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $src || str_starts_with( strtolower( $src ), 'data:' ) ) {
			return '';
		}
		if ( str_starts_with( $src, '//' ) ) {
			$src = 'https:' . $src;
		} elseif ( str_starts_with( $src, '/' ) ) {
			$src = home_url( $src );
		}
		if ( ! preg_match( '#^https?://#i', $src ) ) {
			return '';
		}

		return $src;
	}

	/**
	 * Format a GMT MySQL datetime as a W3C timestamp.
	 *
	 * @param string $gmt MySQL datetime.
	 * @return string
	 */
	private function format_gmt( string $gmt ): string {
		$gmt = trim( $gmt );
		if ( '' === $gmt || '0000-00-00 00:00:00' === $gmt ) {
			return '';
		}

		try {
			$date = new \DateTimeImmutable( $gmt, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $exception ) {
			unset( $exception );
			return '';
		}

		return $date->format( 'c' );
	}

	/**
	 * Run a prepared query and return associative rows.
	 *
	 * @param string $sql Prepared SQL.
	 * @return list<array<string, mixed>>
	 */
	private function rows( string $sql ): array {
		$db = $this->db();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Sitemap reads are prepared, bounded, and cached by SitemapCache.
		$results = $db->get_results( $sql, ARRAY_A );
		if ( ! is_array( $results ) ) {
			return [];
		}

		$rows = [];
		foreach ( $results as $result ) {
			if ( is_array( $result ) ) {
				$rows[] = $result;
			}
		}

		return $rows;
	}

	/**
	 * Run a prepared count query.
	 *
	 * @param string $sql Prepared SQL.
	 * @return int
	 */
	private function int_var( string $sql ): int {
		$db = $this->db();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Count queries are prepared and bounded.
		$value = $db->get_var( $sql );
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Database handle.
	 *
	 * @return wpdb
	 */
	private function db(): wpdb {
		if ( $this->wpdb instanceof wpdb ) {
			return $this->wpdb;
		}

		global $wpdb;
		$this->wpdb = $wpdb;
		return $this->wpdb;
	}

	/**
	 * Extract name => label pairs from WordPress object lists.
	 *
	 * @param mixed $objects Result of get_post_types or get_taxonomies.
	 * @return array<string, string>
	 */
	private function labels_from_objects( $objects ): array {
		if ( ! is_array( $objects ) ) {
			return [];
		}

		$labels = [];
		foreach ( $objects as $key => $object ) {
			$name = '';
			if ( is_object( $object ) && isset( $object->name ) ) {
				$name = (string) $object->name;
			} elseif ( is_string( $key ) ) {
				$name = $key;
			}
			if ( '' === $name ) {
				continue;
			}
			$label           = ( is_object( $object ) && isset( $object->label ) ) ? (string) $object->label : $name;
			$labels[ $name ] = '' !== $label ? $label : $name;
		}

		return $labels;
	}
}
