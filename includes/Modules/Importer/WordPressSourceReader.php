<?php
/**
 * Reads another plugin's data from WordPress tables and options.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Production source reader. It never writes.
 */
final class WordPressSourceReader implements SourceReader {
	/**
	 * Plugin tables this reader is allowed to query.
	 *
	 * @var array<int, string>
	 */
	private const TABLES = [
		'aioseo_posts',
		'aioseo_terms',
		'aioseo_redirects',
		'rank_math_redirections',
	];

	/**
	 * Columns this reader is allowed to select.
	 *
	 * @var array<int, string>
	 */
	private const COLUMNS = [
		'id',
		'post_id',
		'term_id',
	];

	/**
	 * Whether one of the plugin files is active.
	 *
	 * @param array<int, string> $files Plugin basenames.
	 * @return bool
	 */
	public function plugin_active( array $files ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			return false;
		}

		foreach ( $files as $file ) {
			if ( is_plugin_active( $file ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Count posts that have one of the meta keys set to a non-empty value.
	 *
	 * @param array<int, string> $keys        Meta keys.
	 * @param array<int, int>    $exclude_ids Post IDs to leave out.
	 * @return int
	 */
	public function count_posts_with_meta( array $keys, array $exclude_ids = [] ): int {
		return $this->count_meta( 'post', $keys, $exclude_ids );
	}

	/**
	 * Page post IDs that have one of the meta keys.
	 *
	 * @param array<int, string> $keys        Meta keys.
	 * @param int                $offset      Zero-based offset.
	 * @param int                $limit       Page size.
	 * @param array<int, int>    $exclude_ids Post IDs to leave out.
	 * @return array<int, int>
	 */
	public function post_ids_with_meta( array $keys, int $offset, int $limit, array $exclude_ids = [] ): array {
		return $this->meta_ids( 'post', $keys, $offset, $limit, $exclude_ids );
	}

	/**
	 * Read selected post meta values. Missing keys are omitted.
	 *
	 * @param int                $post_id Post ID.
	 * @param array<int, string> $keys    Meta keys.
	 * @return array<string, mixed>
	 */
	public function post_meta( int $post_id, array $keys ): array {
		$found = [];
		foreach ( $keys as $key ) {
			if ( ! metadata_exists( 'post', $post_id, $key ) ) {
				continue;
			}
			$found[ $key ] = get_post_meta( $post_id, $key, true );
		}
		return $found;
	}

	/**
	 * Post title for preview labels.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function post_label( int $post_id ): string {
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post ) {
			return sanitize_text_field( (string) $post->post_title );
		}
		return '';
	}

	/**
	 * Count terms that have one of the meta keys set.
	 *
	 * @param array<int, string> $keys Meta keys.
	 * @return int
	 */
	public function count_terms_with_meta( array $keys ): int {
		return $this->count_meta( 'term', $keys, [] );
	}

	/**
	 * Page term IDs that have one of the meta keys.
	 *
	 * @param array<int, string> $keys   Meta keys.
	 * @param int                $offset Zero-based offset.
	 * @param int                $limit  Page size.
	 * @return array<int, int>
	 */
	public function term_ids_with_meta( array $keys, int $offset, int $limit ): array {
		return $this->meta_ids( 'term', $keys, $offset, $limit, [] );
	}

	/**
	 * Read selected term meta values.
	 *
	 * @param int                $term_id Term ID.
	 * @param array<int, string> $keys    Meta keys.
	 * @return array<string, mixed>
	 */
	public function term_meta( int $term_id, array $keys ): array {
		$found = [];
		foreach ( $keys as $key ) {
			if ( ! metadata_exists( 'term', $term_id, $key ) ) {
				continue;
			}
			$found[ $key ] = get_term_meta( $term_id, $key, true );
		}
		return $found;
	}

	/**
	 * Term name for preview labels.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public function term_label( int $term_id ): string {
		$term = get_term( $term_id );
		if ( $term instanceof \WP_Term ) {
			return sanitize_text_field( (string) $term->name );
		}
		return '';
	}

	/**
	 * Read a WordPress option.
	 *
	 * @param string $key Option name.
	 * @return mixed
	 */
	public function option( string $key ) {
		$value = get_option( $key, null );
		if ( false === $value ) {
			return null;
		}
		return $value;
	}

	/**
	 * Whether a plugin table exists. `$suffix` is unprefixed.
	 *
	 * @param string $suffix Table name without the WordPress prefix.
	 * @return bool
	 */
	public function table_exists( string $suffix ): bool {
		$table = $this->table_name( $suffix );
		if ( '' === $table ) {
			return false;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	/**
	 * Count rows in a plugin table.
	 *
	 * @param string $suffix Table name without the WordPress prefix.
	 * @return int
	 */
	public function count_table( string $suffix ): int {
		$table = $this->table_name( $suffix );
		if ( '' === $table ) {
			return 0;
		}

		global $wpdb;
		// Table name is an allowlisted suffix plus the WordPress prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		return (int) $count;
	}

	/**
	 * Page rows from a plugin table ordered by id.
	 *
	 * @param string $suffix Table name without the WordPress prefix.
	 * @param int    $offset Zero-based offset.
	 * @param int    $limit  Page size.
	 * @return array<int, array<string, mixed>>
	 */
	public function table_rows( string $suffix, int $offset, int $limit ): array {
		$table = $this->table_name( $suffix );
		if ( '' === $table ) {
			return [];
		}

		global $wpdb;
		// Table name is an allowlisted suffix. Placeholders match the limit and offset.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql  = $wpdb->prepare(
			"SELECT * FROM `{$table}` ORDER BY id ASC LIMIT %d OFFSET %d",
			max( 1, $limit ),
			max( 0, $offset )
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$clean = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$clean[] = $row;
			}
		}
		return $clean;
	}

	/**
	 * Return one integer column from a plugin table.
	 *
	 * @param string $suffix Table name without the WordPress prefix.
	 * @param string $column Allowed column: id, post_id, or term_id.
	 * @return array<int, int>
	 */
	public function table_column( string $suffix, string $column ): array {
		$table = $this->table_name( $suffix );
		if ( '' === $table || ! in_array( $column, self::COLUMNS, true ) ) {
			return [];
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values = $wpdb->get_col( "SELECT `{$column}` FROM `{$table}`" );
		if ( ! is_array( $values ) ) {
			return [];
		}

		$ids = [];
		foreach ( $values as $value ) {
			$id = (int) $value;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Count objects that have one of the meta keys.
	 *
	 * @param string             $object_type post or term.
	 * @param array<int, string> $keys        Meta keys.
	 * @param array<int, int>    $exclude_ids IDs to leave out.
	 * @return int
	 */
	private function count_meta( string $object_type, array $keys, array $exclude_ids ): int {
		global $wpdb;

		$table = 'post' === $object_type ? $wpdb->postmeta : $wpdb->termmeta;
		$id    = 'post' === $object_type ? 'post_id' : 'term_id';
		if ( [] === $keys || ! is_string( $table ) || '' === $table ) {
			return 0;
		}

		$args          = $keys;
		$key_slots     = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$exclude_sql   = '';
		$exclude_clean = $this->clean_ids( $exclude_ids );
		if ( [] !== $exclude_clean ) {
			$exclude_sql = ' AND ' . $id . ' NOT IN (' . implode( ', ', array_fill( 0, count( $exclude_clean ), '%d' ) ) . ')';
			$args        = array_merge( $args, $exclude_clean );
		}

		// Identifiers are allowlisted. Placeholder count matches $args.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT {$id}) FROM `{$table}` WHERE meta_key IN ({$key_slots}) AND meta_value <> ''{$exclude_sql}",
				...$args
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return (int) $count;
	}

	/**
	 * Page object IDs that have one of the meta keys.
	 *
	 * @param string             $object_type post or term.
	 * @param array<int, string> $keys        Meta keys.
	 * @param int                $offset      Offset.
	 * @param int                $limit       Limit.
	 * @param array<int, int>    $exclude_ids IDs to leave out.
	 * @return array<int, int>
	 */
	private function meta_ids( string $object_type, array $keys, int $offset, int $limit, array $exclude_ids ): array {
		global $wpdb;

		$table = 'post' === $object_type ? $wpdb->postmeta : $wpdb->termmeta;
		$id    = 'post' === $object_type ? 'post_id' : 'term_id';
		if ( [] === $keys || $limit < 1 || ! is_string( $table ) || '' === $table ) {
			return [];
		}

		$args          = $keys;
		$key_slots     = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$exclude_sql   = '';
		$exclude_clean = $this->clean_ids( $exclude_ids );
		if ( [] !== $exclude_clean ) {
			$exclude_sql = ' AND ' . $id . ' NOT IN (' . implode( ', ', array_fill( 0, count( $exclude_clean ), '%d' ) ) . ')';
			$args        = array_merge( $args, $exclude_clean );
		}
		$args[] = max( 1, $limit );
		$args[] = max( 0, $offset );

		// Identifiers are allowlisted. Placeholder count matches $args.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$id} FROM `{$table}` WHERE meta_key IN ({$key_slots}) AND meta_value <> ''{$exclude_sql} ORDER BY {$id} ASC LIMIT %d OFFSET %d",
				...$args
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! is_array( $ids ) ) {
			return [];
		}

		$clean = [];
		foreach ( $ids as $value ) {
			$object_id = (int) $value;
			if ( $object_id > 0 ) {
				$clean[] = $object_id;
			}
		}
		return $clean;
	}

	/**
	 * Prefixed table name for an allowlisted suffix.
	 *
	 * @param string $suffix Unprefixed table suffix.
	 * @return string
	 */
	private function table_name( string $suffix ): string {
		if ( ! in_array( $suffix, self::TABLES, true ) ) {
			return '';
		}

		global $wpdb;
		return $wpdb->prefix . $suffix;
	}

	/**
	 * Keep positive integer IDs.
	 *
	 * @param array<int, int> $ids Raw IDs.
	 * @return array<int, int>
	 */
	private function clean_ids( array $ids ): array {
		$clean = [];
		foreach ( $ids as $id ) {
			$value = (int) $id;
			if ( $value > 0 ) {
				$clean[] = $value;
			}
		}
		return $clean;
	}
}
