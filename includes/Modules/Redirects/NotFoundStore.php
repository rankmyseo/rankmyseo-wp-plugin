<?php
/**
 * Storage contract for deduplicated 404 logs.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Persistence operations used by the 404 monitor.
 */
interface NotFoundStore {
	/**
	 * Find a log row by primary key.
	 *
	 * @param int $id Log ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array;

	/**
	 * Find a log row by its unique path hash.
	 *
	 * @param string $hash SHA-256 path hash.
	 * @return array<string, mixed>|null
	 */
	public function find_by_hash( string $hash ): ?array;

	/**
	 * Insert a log row or increment hits for an existing path.
	 *
	 * Ignored rows are left unchanged.
	 *
	 * @param string $path       Normalized request path.
	 * @param string $hash       SHA-256 path hash.
	 * @param string $query      Query string.
	 * @param string $referer    Referring URL.
	 * @param string $user_agent User agent.
	 * @param string $now        MySQL datetime in GMT.
	 * @return int Log ID.
	 */
	public function upsert( string $path, string $hash, string $query, string $referer, string $user_agent, string $now ): int;

	/**
	 * Search log rows for the admin table.
	 *
	 * @param array<string, mixed> $filters Filter, paging, and sort options.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function search( array $filters ): array;

	/**
	 * Update a log row status.
	 *
	 * @param int    $id     Log ID.
	 * @param string $status open, redirected, or ignored.
	 * @return bool
	 */
	public function set_status( int $id, string $status ): bool;

	/**
	 * Delete one log row.
	 *
	 * @param int $id Log ID.
	 * @return bool
	 */
	public function delete( int $id ): bool;

	/**
	 * Delete many log rows.
	 *
	 * @param int[] $ids Log IDs.
	 * @return int
	 */
	public function delete_ids( array $ids ): int;

	/**
	 * Mark many log rows ignored.
	 *
	 * @param int[] $ids Log IDs.
	 * @return int
	 */
	public function ignore_ids( array $ids ): int;

	/**
	 * Delete rows last seen before the cutoff.
	 *
	 * @param string $cutoff_mysql MySQL datetime in GMT.
	 * @return int
	 */
	public function delete_older_than( string $cutoff_mysql ): int;

	/**
	 * Delete the oldest rows until only $max_rows remain.
	 *
	 * @param int $max_rows Maximum rows to keep.
	 * @return int
	 */
	public function trim_to( int $max_rows ): int;
}
