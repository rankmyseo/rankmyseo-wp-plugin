<?php
/**
 * RankMySEO data the importer reads and writes.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Destination storage. Nothing here deletes another plugin's rows.
 */
interface SiteStore {
	/**
	 * Read one RankMySEO meta value. Null means the key is not stored.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @return string|null
	 */
	public function get_meta( string $object_type, int $object_id, string $key ): ?string;

	/**
	 * Store one meta value.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @param string $value       Sanitized value.
	 * @return void
	 */
	public function set_meta( string $object_type, int $object_id, string $key, string $value ): void;

	/**
	 * Remove one meta value created by an import.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @return void
	 */
	public function delete_meta( string $object_type, int $object_id, string $key ): void;

	/**
	 * Read an option. Null when it has never been saved.
	 *
	 * @param string $key Option name.
	 * @return mixed
	 */
	public function get_option( string $key );

	/**
	 * Replace an option value.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Stored value.
	 * @return void
	 */
	public function set_option( string $key, $value ): void;

	/**
	 * Delete an option that did not exist before the import.
	 *
	 * @param string $key Option name.
	 * @return void
	 */
	public function delete_option( string $key ): void;

	/**
	 * Insert a redirect and return its new ID.
	 *
	 * @param array<string, mixed> $row Redirect columns without an ID.
	 * @return int
	 */
	public function insert_redirect( array $row ): int;

	/**
	 * Find a redirect by its source hash.
	 *
	 * @param string $hash SHA-256 source hash.
	 * @return array<string, mixed>|null
	 */
	public function find_redirect_by_hash( string $hash ): ?array;

	/**
	 * Replace a redirect row, including its ID.
	 *
	 * @param array<string, mixed> $row Full redirect row.
	 * @return void
	 */
	public function update_redirect( array $row ): void;

	/**
	 * Delete a redirect created by an import.
	 *
	 * @param int $id Redirect ID.
	 * @return void
	 */
	public function delete_redirect( int $id ): void;

	/**
	 * Store the previous RankMySEO value for one object.
	 *
	 * @param string               $batch_id    Import ID.
	 * @param string               $source      yoast, rankmath, or aioseo.
	 * @param string               $object_type post, term, option, or redirect.
	 * @param int                  $object_id   Object ID.
	 * @param array<string, mixed> $previous    Previous state.
	 * @return void
	 */
	public function insert_backup( string $batch_id, string $source, string $object_type, int $object_id, array $previous ): void;

	/**
	 * Return backup rows for an import, oldest first.
	 *
	 * @param string $batch_id Import ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function backups( string $batch_id ): array;

	/**
	 * Remove backup rows after a successful rollback.
	 *
	 * @param string $batch_id Import ID.
	 * @return void
	 */
	public function delete_backups( string $batch_id ): void;
}
