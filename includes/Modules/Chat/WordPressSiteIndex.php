<?php
/**
 * WordPress database implementation of the local site index.
 *
 * Reads posts, SEO post meta, redirects, and 404 logs. Never makes HTTP calls.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Chat;

// Table names are allowlisted before interpolation. Values go through $wpdb->prepare().
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

use RankMySEO\Database\Schema;
use wpdb;

/**
 * SQL-backed site index for the offline assistant and dashboard summary.
 */
final class WordPressSiteIndex implements SiteIndex {
	/**
	 * Meta keys this index is allowed to read.
	 *
	 * @var list<string>
	 */
	private const META_KEYS = [
		'_rankmyseo_title',
		'_rankmyseo_description',
		'_rankmyseo_seo_score',
		'_rankmyseo_focus_keyword',
	];

	/**
	 * WordPress database adapter.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database adapter.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_missing_titles(): int {
		return $this->count_missing_meta( '_rankmyseo_title' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $limit Maximum rows.
	 */
	public function list_missing_titles( int $limit ): array {
		return $this->list_missing_meta( '_rankmyseo_title', $limit );
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_missing_descriptions(): int {
		return $this->count_missing_meta( '_rankmyseo_description' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $limit Maximum rows.
	 */
	public function list_missing_descriptions( int $limit ): array {
		return $this->list_missing_meta( '_rankmyseo_description', $limit );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $threshold Scores below this value.
	 */
	public function count_low_scores( int $threshold ): int {
		$posts = $this->posts_table();
		$meta  = $this->postmeta_table();
		if ( '' === $posts || '' === $meta ) {
			return 0;
		}

		$threshold = max( 1, min( 100, $threshold ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$value = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$posts} p INNER JOIN {$meta} ms ON ms.post_id = p.ID AND ms.meta_key = %s WHERE p.post_status = 'publish' AND p.post_type IN ('post','page') AND ms.meta_value <> '' AND CAST(ms.meta_value AS UNSIGNED) < %d",
				'_rankmyseo_seo_score',
				$threshold
			)
		);

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $threshold Scores below this value.
	 * @param int $limit     Maximum rows.
	 */
	public function list_low_scores( int $threshold, int $limit ): array {
		$posts = $this->posts_table();
		$meta  = $this->postmeta_table();
		if ( '' === $posts || '' === $meta ) {
			return [];
		}

		$threshold = max( 1, min( 100, $threshold ) );
		$limit     = $this->clamp_limit( $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_name, p.post_type, ms.meta_value AS seo_score FROM {$posts} p INNER JOIN {$meta} ms ON ms.post_id = p.ID AND ms.meta_key = %s WHERE p.post_status = 'publish' AND p.post_type IN ('post','page') AND ms.meta_value <> '' AND CAST(ms.meta_value AS UNSIGNED) < %d ORDER BY CAST(ms.meta_value AS UNSIGNED) ASC, p.post_modified DESC LIMIT %d",
				'_rankmyseo_seo_score',
				$threshold,
				$limit
			),
			ARRAY_A
		);

		$items = [];
		foreach ( $this->normalize_rows( $rows ) as $row ) {
			$item              = $this->map_post_row( $row );
			$item['seo_score'] = isset( $row['seo_score'] ) && is_numeric( $row['seo_score'] ) ? (int) $row['seo_score'] : null;
			$items[]           = $item;
		}

		return $items;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $min_words Minimum word count that counts as enough content.
	 */
	public function count_thin_posts( int $min_words ): int {
		$posts = $this->posts_table();
		if ( '' === $posts ) {
			return 0;
		}

		$max_spaces = max( 0, $min_words - 1 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$value = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(p.ID) FROM {$posts} p WHERE p.post_status = 'publish' AND p.post_type IN ('post','page') AND (CHAR_LENGTH(p.post_content) - CHAR_LENGTH(REPLACE(p.post_content, ' ', ''))) < %d",
				$max_spaces
			)
		);

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $min_words Minimum word count that counts as enough content.
	 * @param int $limit     Maximum rows.
	 */
	public function list_thin_posts( int $min_words, int $limit ): array {
		$posts = $this->posts_table();
		if ( '' === $posts ) {
			return [];
		}

		$max_spaces = max( 0, $min_words - 1 );
		$limit      = $this->clamp_limit( $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_name, p.post_type, p.post_content FROM {$posts} p WHERE p.post_status = 'publish' AND p.post_type IN ('post','page') AND (CHAR_LENGTH(p.post_content) - CHAR_LENGTH(REPLACE(p.post_content, ' ', ''))) < %d ORDER BY p.post_modified DESC LIMIT %d",
				$max_spaces,
				$limit
			),
			ARRAY_A
		);

		$items = [];
		foreach ( $this->normalize_rows( $rows ) as $row ) {
			$content            = isset( $row['post_content'] ) ? (string) $row['post_content'] : '';
			$item               = $this->map_post_row( $row );
			$item['word_count'] = $this->word_count( $content );
			$items[]            = $item;
		}

		return $items;
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_open_404s(): int {
		$table = $this->custom_table( '404_logs' );
		if ( '' === $table || ! $this->table_exists( $table ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$value = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(id) FROM {$table} WHERE status = %s",
				'open'
			)
		);

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $limit Maximum rows.
	 */
	public function list_open_404s( int $limit ): array {
		$table = $this->custom_table( '404_logs' );
		if ( '' === $table || ! $this->table_exists( $table ) ) {
			return [];
		}

		$limit = $this->clamp_limit( $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, request_path, hits, last_seen_at FROM {$table} WHERE status = %s ORDER BY last_seen_at DESC, hits DESC LIMIT %d",
				'open',
				$limit
			),
			ARRAY_A
		);

		$items = [];
		foreach ( $this->normalize_rows( $rows ) as $row ) {
			$path    = isset( $row['request_path'] ) ? sanitize_text_field( (string) $row['request_path'] ) : '';
			$items[] = [
				'id'        => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'title'     => '' !== $path ? $path : __( '(empty path)', 'rankmyseo' ),
				'slug'      => '',
				'type'      => '404',
				'edit_link' => '',
				'page'      => 'redirects',
				'hits'      => isset( $row['hits'] ) ? (int) $row['hits'] : 0,
			];
		}

		return $items;
	}

	/**
	 * {@inheritDoc}
	 */
	public function redirect_counts(): array {
		$table = $this->custom_table( 'redirects' );
		if ( '' === $table || ! $this->table_exists( $table ) ) {
			return [
				'total'  => 0,
				'active' => 0,
			];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $this->wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$active = $this->wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE is_active = 1" );

		return [
			'total'  => is_numeric( $total ) ? (int) $total : 0,
			'active' => is_numeric( $active ) ? (int) $active : 0,
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $post_id Post ID.
	 */
	public function get_post( int $post_id ): ?array {
		if ( $post_id <= 0 ) {
			return null;
		}

		$posts = $this->posts_table();
		if ( '' === $posts ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT ID, post_title, post_name, post_type, post_status, post_content FROM {$posts} WHERE ID = %d LIMIT 1",
				$post_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->hydrate_post( $row );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $lookup Title, slug, or numeric ID.
	 * @param int    $limit  Maximum rows.
	 */
	public function find_posts( string $lookup, int $limit ): array {
		$lookup = trim( $lookup );
		if ( '' === $lookup ) {
			return [];
		}

		if ( ctype_digit( $lookup ) ) {
			$post = $this->get_post( (int) $lookup );
			return null === $post ? [] : [ $post ];
		}

		$posts = $this->posts_table();
		if ( '' === $posts ) {
			return [];
		}

		$limit = $this->clamp_limit( $limit );
		$slug  = sanitize_title( $lookup );
		$like  = '%' . $this->wpdb->esc_like( $lookup ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT ID, post_title, post_name, post_type, post_status, post_content FROM {$posts} WHERE post_status IN ('publish','draft','pending','private') AND (post_name = %s OR post_title LIKE %s) ORDER BY post_modified DESC LIMIT %d",
				$slug,
				$like,
				$limit
			),
			ARRAY_A
		);

		$items = [];
		foreach ( $this->normalize_rows( $rows ) as $row ) {
			$items[] = $this->hydrate_post( $row );
		}

		return $items;
	}

	/**
	 * Count published posts and pages missing one whitelisted SEO meta value.
	 *
	 * @param string $meta_key Whitelisted meta key.
	 * @return int
	 */
	private function count_missing_meta( string $meta_key ): int {
		if ( ! in_array( $meta_key, self::META_KEYS, true ) ) {
			return 0;
		}

		$posts = $this->posts_table();
		$meta  = $this->postmeta_table();
		if ( '' === $posts || '' === $meta ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$value = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(p.ID) FROM {$posts} p LEFT JOIN {$meta} mt ON mt.post_id = p.ID AND mt.meta_key = %s WHERE p.post_status = 'publish' AND p.post_type IN ('post','page') AND (mt.post_id IS NULL OR mt.meta_value = '')",
				$meta_key
			)
		);

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * List published posts and pages missing one whitelisted SEO meta value.
	 *
	 * @param string $meta_key Whitelisted meta key.
	 * @param int    $limit    Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	private function list_missing_meta( string $meta_key, int $limit ): array {
		if ( ! in_array( $meta_key, self::META_KEYS, true ) ) {
			return [];
		}

		$posts = $this->posts_table();
		$meta  = $this->postmeta_table();
		if ( '' === $posts || '' === $meta ) {
			return [];
		}

		$limit = $this->clamp_limit( $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_name, p.post_type FROM {$posts} p LEFT JOIN {$meta} mt ON mt.post_id = p.ID AND mt.meta_key = %s WHERE p.post_status = 'publish' AND p.post_type IN ('post','page') AND (mt.post_id IS NULL OR mt.meta_value = '') ORDER BY p.post_modified DESC LIMIT %d",
				$meta_key,
				$limit
			),
			ARRAY_A
		);

		$items = [];
		foreach ( $this->normalize_rows( $rows ) as $row ) {
			$items[] = $this->map_post_row( $row );
		}

		return $items;
	}

	/**
	 * Attach SEO meta and a word count to a post row, then drop raw content.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate_post( array $row ): array {
		$item    = $this->map_post_row( $row );
		$content = isset( $row['post_content'] ) ? (string) $row['post_content'] : '';
		$post_id = (int) $item['id'];
		$meta    = $this->load_meta( $post_id );

		$item['word_count']      = $this->word_count( $content );
		$item['seo_title']       = $meta['_rankmyseo_title'] ?? '';
		$item['seo_description'] = $meta['_rankmyseo_description'] ?? '';
		$item['focus_keyword']   = $meta['_rankmyseo_focus_keyword'] ?? '';
		$item['seo_score']       = isset( $meta['_rankmyseo_seo_score'] ) && is_numeric( $meta['_rankmyseo_seo_score'] )
			? (int) $meta['_rankmyseo_seo_score']
			: null;
		$item['status']          = isset( $row['post_status'] ) ? sanitize_key( (string) $row['post_status'] ) : '';

		return $item;
	}

	/**
	 * Load the whitelisted SEO meta for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string>
	 */
	private function load_meta( int $post_id ): array {
		$meta_table = $this->postmeta_table();
		if ( '' === $meta_table || $post_id <= 0 ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$meta_table} WHERE post_id = %d AND meta_key IN (%s, %s, %s, %s)",
				$post_id,
				self::META_KEYS[0],
				self::META_KEYS[1],
				self::META_KEYS[2],
				self::META_KEYS[3]
			),
			ARRAY_A
		);

		$meta = [];
		foreach ( $this->normalize_rows( $rows ) as $row ) {
			$key = isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '';
			if ( ! in_array( $key, self::META_KEYS, true ) ) {
				continue;
			}
			$meta[ $key ] = isset( $row['meta_value'] ) ? (string) $row['meta_value'] : '';
		}

		return $meta;
	}

	/**
	 * Map a post row into the public item shape. Content is never included.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function map_post_row( array $row ): array {
		$post_id = isset( $row['ID'] ) ? (int) $row['ID'] : 0;
		$title   = isset( $row['post_title'] ) ? sanitize_text_field( (string) $row['post_title'] ) : '';
		if ( '' === $title ) {
			$title = sprintf(
				/* translators: %d: WordPress post ID. */
				__( 'Untitled item #%d', 'rankmyseo' ),
				$post_id
			);
		}

		$type = isset( $row['post_type'] ) ? sanitize_key( (string) $row['post_type'] ) : 'post';
		$slug = isset( $row['post_name'] ) ? sanitize_title( (string) $row['post_name'] ) : '';

		return [
			'id'        => $post_id,
			'title'     => $title,
			'slug'      => $slug,
			'type'      => '' !== $type ? $type : 'post',
			'edit_link' => $this->edit_link( $post_id ),
			'page'      => 'meta',
		];
	}

	/**
	 * Build an admin edit link for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function edit_link( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		return esc_url_raw( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
	}

	/**
	 * Count words in plain text, including non-English scripts.
	 *
	 * @param string $content Raw post content.
	 * @return int
	 */
	private function word_count( string $content ): int {
		$plain = trim( wp_strip_all_tags( $content ) );
		if ( '' === $plain ) {
			return 0;
		}

		$parts = preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * Whether a plugin custom table is installed.
	 *
	 * @param string $table Prefixed table name.
	 * @return bool
	 */
	private function table_exists( string $table ): bool {
		if ( '' === $table ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return $found === $table;
	}

	/**
	 * Return a safe posts table name.
	 *
	 * @return string
	 */
	private function posts_table(): string {
		return $this->safe_identifier( (string) $this->wpdb->posts );
	}

	/**
	 * Return a safe postmeta table name.
	 *
	 * @return string
	 */
	private function postmeta_table(): string {
		return $this->safe_identifier( (string) $this->wpdb->postmeta );
	}

	/**
	 * Return a prefixed custom table name, or an empty string when unknown.
	 *
	 * @param string $key Logical Schema table key.
	 * @return string
	 */
	private function custom_table( string $key ): string {
		if ( ! isset( Schema::TABLE_SUFFIXES[ $key ] ) ) {
			return '';
		}

		return $this->safe_identifier( Schema::get_table_name( $key, (string) $this->wpdb->prefix ) );
	}

	/**
	 * Allow only simple SQL identifiers.
	 *
	 * @param string $identifier Table or column identifier.
	 * @return string
	 */
	private function safe_identifier( string $identifier ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
			return '';
		}

		return $identifier;
	}

	/**
	 * Clamp list limits so a chat reply stays short.
	 *
	 * @param int $limit Requested limit.
	 * @return int
	 */
	private function clamp_limit( int $limit ): int {
		return max( 1, min( 20, $limit ) );
	}

	/**
	 * Normalize wpdb rows to a list of associative arrays.
	 *
	 * @param mixed $rows Raw get_results() payload.
	 * @return list<array<string, mixed>>
	 */
	private function normalize_rows( $rows ): array {
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$normalized[] = $row;
			} elseif ( is_object( $row ) ) {
				$normalized[] = get_object_vars( $row );
			}
		}

		return $normalized;
	}
}
