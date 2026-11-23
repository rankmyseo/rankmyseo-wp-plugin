<?php
/**
 * Reads and writes RankMySEO meta, options, redirects, and import snapshots.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

use RankMySEO\Database\Schema;

/**
 * Production destination store.
 */
final class WordPressSiteStore implements SiteStore {
	/**
	 * Read one RankMySEO meta value. Null means the key is not stored.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @return string|null
	 */
	public function get_meta( string $object_type, int $object_id, string $key ): ?string {
		$type = $this->meta_type( $object_type );
		if ( '' === $type || ! metadata_exists( $type, $object_id, $key ) ) {
			return null;
		}

		$value = 'post' === $type ? get_post_meta( $object_id, $key, true ) : get_term_meta( $object_id, $key, true );
		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value );
			return is_string( $encoded ) ? $encoded : null;
		}
		if ( null === $value || false === $value ) {
			return null;
		}
		return (string) $value;
	}

	/**
	 * Store one meta value.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @param string $value       Sanitized value.
	 * @return void
	 */
	public function set_meta( string $object_type, int $object_id, string $key, string $value ): void {
		if ( 'post' === $object_type ) {
			update_post_meta( $object_id, $key, $value );
			return;
		}
		if ( 'term' === $object_type ) {
			update_term_meta( $object_id, $key, $value );
		}
	}

	/**
	 * Remove one meta value created by an import.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @return void
	 */
	public function delete_meta( string $object_type, int $object_id, string $key ): void {
		if ( 'post' === $object_type ) {
			delete_post_meta( $object_id, $key );
			return;
		}
		if ( 'term' === $object_type ) {
			delete_term_meta( $object_id, $key );
		}
	}

	/**
	 * Read an option. Null when it has never been saved.
	 *
	 * @param string $key Option name.
	 * @return mixed
	 */
	public function get_option( string $key ) {
		if ( ! $this->is_option_key( $key ) ) {
			return null;
		}

		$value = get_option( $key, null );
		if ( false === $value ) {
			return null;
		}
		return $value;
	}

	/**
	 * Replace an option value.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Stored value.
	 * @return void
	 */
	public function set_option( string $key, $value ): void {
		if ( ! $this->is_option_key( $key ) ) {
			return;
		}

		$autoload = MetaCatalog::OPTION_HISTORY === $key ? false : null;
		update_option( $key, $value, $autoload );
	}

	/**
	 * Delete an option that did not exist before the import.
	 *
	 * @param string $key Option name.
	 * @return void
	 */
	public function delete_option( string $key ): void {
		if ( ! $this->is_option_key( $key ) ) {
			return;
		}
		delete_option( $key );
	}

	/**
	 * Insert a redirect and return its new ID.
	 *
	 * @param array<string, mixed> $row Redirect columns without an ID.
	 * @return int
	 */
	public function insert_redirect( array $row ): int {
		global $wpdb;

		$table = Schema::get_table_name( 'redirects', $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			$this->redirect_columns( $row ),
			[ '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find a redirect by its source hash.
	 *
	 * @param string $hash SHA-256 source hash.
	 * @return array<string, mixed>|null
	 */
	public function find_redirect_by_hash( string $hash ): ?array {
		global $wpdb;

		$table = Schema::get_table_name( 'redirects', $wpdb->prefix );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE source_hash = %s ORDER BY id ASC LIMIT 1", $hash ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? $this->normalize_redirect( $row ) : null;
	}

	/**
	 * Replace a redirect row, including its ID.
	 *
	 * @param array<string, mixed> $row Full redirect row.
	 * @return void
	 */
	public function update_redirect( array $row ): void {
		global $wpdb;

		$id = (int) ( $row['id'] ?? 0 );
		if ( $id < 1 ) {
			return;
		}

		$table = Schema::get_table_name( 'redirects', $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			$this->redirect_columns( $row ),
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Delete a redirect created by an import.
	 *
	 * @param int $id Redirect ID.
	 * @return void
	 */
	public function delete_redirect( int $id ): void {
		if ( $id < 1 ) {
			return;
		}

		global $wpdb;
		$table = Schema::get_table_name( 'redirects', $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
	}

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
	public function insert_backup( string $batch_id, string $source, string $object_type, int $object_id, array $previous ): void {
		global $wpdb;

		$encoded = wp_json_encode( $previous );
		if ( ! is_string( $encoded ) ) {
			return;
		}

		$table = Schema::get_table_name( 'import_backups', $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'batch_id'      => $batch_id,
				'source_plugin' => substr( sanitize_key( $source ), 0, 24 ),
				'object_type'   => substr( sanitize_key( $object_type ), 0, 24 ),
				'object_id'     => max( 0, $object_id ),
				'previous_data' => $encoded,
				'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s' ]
		);
	}

	/**
	 * Return backup rows for an import, oldest first.
	 *
	 * @param string $batch_id Import ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function backups( string $batch_id ): array {
		global $wpdb;

		$table = Schema::get_table_name( 'import_backups', $wpdb->prefix );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE batch_id = %s ORDER BY id ASC", $batch_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$backups = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$decoded = json_decode( (string) ( $row['previous_data'] ?? '' ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$backups[] = [
				'id'            => (int) ( $row['id'] ?? 0 ),
				'batch_id'      => (string) ( $row['batch_id'] ?? '' ),
				'source_plugin' => (string) ( $row['source_plugin'] ?? '' ),
				'object_type'   => (string) ( $row['object_type'] ?? '' ),
				'object_id'     => (int) ( $row['object_id'] ?? 0 ),
				'previous'      => $decoded,
			];
		}

		return $backups;
	}

	/**
	 * Remove backup rows after a successful rollback.
	 *
	 * @param string $batch_id Import ID.
	 * @return void
	 */
	public function delete_backups( string $batch_id ): void {
		global $wpdb;

		$table = Schema::get_table_name( 'import_backups', $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, [ 'batch_id' => $batch_id ], [ '%s' ] );
	}

	/**
	 * Map post or term to a metadata type.
	 *
	 * @param string $object_type Object type.
	 * @return string
	 */
	private function meta_type( string $object_type ): string {
		if ( 'post' === $object_type || 'term' === $object_type ) {
			return $object_type;
		}
		return '';
	}

	/**
	 * Whether the importer may read or write this option.
	 *
	 * @param string $key Option name.
	 * @return bool
	 */
	private function is_option_key( string $key ): bool {
		return in_array(
			$key,
			[
				MetaCatalog::OPTION_CORE,
				MetaCatalog::OPTION_TITLES,
				MetaCatalog::OPTION_SCHEMA,
				MetaCatalog::OPTION_HISTORY,
				MetaCatalog::OPTION_REDIRECTS,
			],
			true
		);
	}

	/**
	 * Redirect columns in insert order.
	 *
	 * @param array<string, mixed> $row Redirect row.
	 * @return array<string, mixed>
	 */
	private function redirect_columns( array $row ): array {
		$target = $row['target_url'] ?? null;
		if ( '' === $target ) {
			$target = null;
		}

		return [
			'source_path'   => substr( (string) ( $row['source_path'] ?? '' ), 0, 512 ),
			'source_hash'   => substr( (string) ( $row['source_hash'] ?? '' ), 0, 64 ),
			'match_type'    => substr( (string) ( $row['match_type'] ?? 'exact' ), 0, 16 ),
			'target_url'    => null === $target ? null : substr( (string) $target, 0, 1024 ),
			'status_code'   => (int) ( $row['status_code'] ?? 301 ),
			'is_active'     => (int) ( $row['is_active'] ?? 1 ),
			'hits'          => (int) ( $row['hits'] ?? 0 ),
			'last_hit_at'   => $row['last_hit_at'] ?? null,
			'source_origin' => substr( (string) ( $row['source_origin'] ?? 'import' ), 0, 24 ),
			'created_at'    => (string) ( $row['created_at'] ?? gmdate( 'Y-m-d H:i:s' ) ),
			'updated_at'    => gmdate( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * Cast a redirect row into stable types.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function normalize_redirect( array $row ): array {
		$target = $row['target_url'] ?? null;
		if ( '' === $target ) {
			$target = null;
		}

		return [
			'id'            => (int) ( $row['id'] ?? 0 ),
			'source_path'   => (string) ( $row['source_path'] ?? '' ),
			'source_hash'   => (string) ( $row['source_hash'] ?? '' ),
			'match_type'    => (string) ( $row['match_type'] ?? 'exact' ),
			'target_url'    => $target,
			'status_code'   => (int) ( $row['status_code'] ?? 301 ),
			'is_active'     => (int) ( $row['is_active'] ?? 1 ),
			'hits'          => (int) ( $row['hits'] ?? 0 ),
			'last_hit_at'   => $row['last_hit_at'] ?? null,
			'source_origin' => (string) ( $row['source_origin'] ?? 'import' ),
			'created_at'    => (string) ( $row['created_at'] ?? '' ),
			'updated_at'    => (string) ( $row['updated_at'] ?? '' ),
		];
	}
}
