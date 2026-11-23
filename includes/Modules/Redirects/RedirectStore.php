<?php
/**
 * Storage contract for redirect rules.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Persistence operations used by the redirect service.
 */
interface RedirectStore {
	/**
	 * Find one active exact rule by its source hash.
	 *
	 * @param string $hash SHA-256 source hash.
	 * @return array<string, mixed>|null
	 */
	public function find_active_exact_by_hash( string $hash ): ?array;

	/**
	 * List active rules of one match type, bounded for request-time evaluation.
	 *
	 * @param string $match_type prefix or regex.
	 * @param int    $limit      Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function active_rules( string $match_type, int $limit ): array;

	/**
	 * Find a rule by primary key.
	 *
	 * @param int $id Rule ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array;

	/**
	 * Find a rule with the same source hash and match type.
	 *
	 * @param string $hash      SHA-256 source hash.
	 * @param string $match_type Match type.
	 * @param int    $except_id Rule ID to ignore.
	 * @return array<string, mixed>|null
	 */
	public function find_duplicate( string $hash, string $match_type, int $except_id = 0 ): ?array;

	/**
	 * Insert a rule and return its ID.
	 *
	 * @param array<string, mixed> $row Column values.
	 * @return int
	 */
	public function insert( array $row ): int;

	/**
	 * Update a rule.
	 *
	 * @param int                  $id     Rule ID.
	 * @param array<string, mixed> $fields Columns to update.
	 * @return bool
	 */
	public function update( int $id, array $fields ): bool;

	/**
	 * Delete one rule.
	 *
	 * @param int $id Rule ID.
	 * @return bool
	 */
	public function delete( int $id ): bool;

	/**
	 * Delete many rules.
	 *
	 * @param int[] $ids Rule IDs.
	 * @return int
	 */
	public function delete_ids( array $ids ): int;

	/**
	 * Set the active flag on many rules.
	 *
	 * @param int[] $ids    Rule IDs.
	 * @param bool  $active Target state.
	 * @return int
	 */
	public function set_active_ids( array $ids, bool $active ): int;

	/**
	 * Search rules for the admin table.
	 *
	 * @param array<string, mixed> $filters Filter, paging, and sort options.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function search( array $filters ): array;

	/**
	 * Increment the hit counter when the throttle window has elapsed.
	 *
	 * @param int $id                Rule ID.
	 * @param int $throttle_seconds  Minimum seconds between writes.
	 * @return bool True when a row was updated.
	 */
	public function increment_hits( int $id, int $throttle_seconds ): bool;

	/**
	 * Active exact rules as source path => target URL.
	 *
	 * @param int $except_id Rule ID to omit.
	 * @return array<string, string>
	 */
	public function exact_target_map( int $except_id = 0 ): array;

	/**
	 * Rows needed when retargeting chains after a slug change.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function list_for_retarget(): array;

	/**
	 * All rules for export, capped by the store.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function export_rows(): array;
}
