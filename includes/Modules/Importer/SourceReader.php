<?php
/**
 * Read-only access to another plugin's stored SEO data.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Source data the adapters page through. Production uses WordPress; tests use memory.
 */
interface SourceReader {
	/**
	 * Whether one of the plugin files is active.
	 *
	 * @param array<int, string> $files Plugin basenames.
	 * @return bool
	 */
	public function plugin_active( array $files ): bool;

	/**
	 * Count posts that have one of the meta keys set to a non-empty value.
	 *
	 * @param array<int, string> $keys        Meta keys.
	 * @param array<int, int>    $exclude_ids Post IDs to leave out.
	 * @return int
	 */
	public function count_posts_with_meta( array $keys, array $exclude_ids = [] ): int;

	/**
	 * Page post IDs that have one of the meta keys.
	 *
	 * @param array<int, string> $keys        Meta keys.
	 * @param int                $offset      Zero-based offset.
	 * @param int                $limit       Page size.
	 * @param array<int, int>    $exclude_ids Post IDs to leave out.
	 * @return array<int, int>
	 */
	public function post_ids_with_meta( array $keys, int $offset, int $limit, array $exclude_ids = [] ): array;

	/**
	 * Read selected post meta values. Missing keys are omitted.
	 *
	 * @param int                $post_id Post ID.
	 * @param array<int, string> $keys    Meta keys.
	 * @return array<string, mixed>
	 */
	public function post_meta( int $post_id, array $keys ): array;

	/**
	 * Post title for preview labels.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function post_label( int $post_id ): string;

	/**
	 * Count terms that have one of the meta keys set.
	 *
	 * @param array<int, string> $keys Meta keys.
	 * @return int
	 */
	public function count_terms_with_meta( array $keys ): int;

	/**
	 * Page term IDs that have one of the meta keys.
	 *
	 * @param array<int, string> $keys   Meta keys.
	 * @param int                $offset Zero-based offset.
	 * @param int                $limit  Page size.
	 * @return array<int, int>
	 */
	public function term_ids_with_meta( array $keys, int $offset, int $limit ): array;

	/**
	 * Read selected term meta values.
	 *
	 * @param int                $term_id Term ID.
	 * @param array<int, string> $keys    Meta keys.
	 * @return array<string, mixed>
	 */
	public function term_meta( int $term_id, array $keys ): array;

	/**
	 * Term name for preview labels.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public function term_label( int $term_id ): string;

	/**
	 * Read a WordPress option.
	 *
	 * @param string $key Option name.
	 * @return mixed
	 */
	public function option( string $key );

	/**
	 * Whether a plugin table exists. `$suffix` is unprefixed.
	 *
	 * @param string $suffix Table name without the WordPress prefix.
	 * @return bool
	 */
	public function table_exists( string $suffix ): bool;

	/**
	 * Count rows in a plugin table.
	 *
	 * @param string $suffix Table name without the WordPress prefix.
	 * @return int
	 */
	public function count_table( string $suffix ): int;

	/**
	 * Page rows from a plugin table ordered by id.
	 *
	 * @param string $suffix Table name without the WordPress prefix.
	 * @param int    $offset Zero-based offset.
	 * @param int    $limit  Page size.
	 * @return array<int, array<string, mixed>>
	 */
	public function table_rows( string $suffix, int $offset, int $limit ): array;

	/**
	 * Return one integer column from a plugin table.
	 *
	 * @param string $suffix Table name without the WordPress prefix.
	 * @param string $column Allowed column: id, post_id, or term_id.
	 * @return array<int, int>
	 */
	public function table_column( string $suffix, string $column ): array;
}
