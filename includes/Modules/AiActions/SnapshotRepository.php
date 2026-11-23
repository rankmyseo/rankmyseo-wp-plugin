<?php
/**
 * Immutable pre-change snapshots for one-click rollback.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Database\Schema;
use wpdb;

/**
 * Reads and writes wp_rankmyseo_ai_snapshots.
 */
final class SnapshotRepository {
	/**
	 * WordPress database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb Database handle.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Store the exact state captured before a change.
	 *
	 * @param int                  $action_id Local action ID.
	 * @param array<string, mixed> $snapshot  Snapshot document.
	 * @param int|null             $post_id   Related post.
	 * @param int                  $revision  WordPress revision ID.
	 * @return int Snapshot row ID.
	 */
	public function insert( int $action_id, array $snapshot, ?int $post_id, int $revision = 0 ): int {
		$json = wp_json_encode( $snapshot );
		$now  = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $this->wpdb->insert(
			$this->table(),
			array(
				'action_id'      => $action_id,
				'wp_post_id'     => $post_id,
				'wp_revision_id' => $revision > 0 ? $revision : null,
				'object_type'    => isset( $snapshot['object_type'] ) && is_string( $snapshot['object_type'] ) ? $snapshot['object_type'] : 'post',
				'snapshot_json'  => is_string( $json ) ? $json : '{}',
				'created_at'     => is_string( $now ) ? $now : gmdate( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $ok ) {
			return 0;
		}
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Latest snapshot for an action.
	 *
	 * @param int $action_id Local action ID.
	 * @return array<string, mixed>|null
	 */
	public function latest_for_action( int $action_id ): ?array {
		$sql = "SELECT * FROM {$this->table()} WHERE action_id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, $action_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) || array() === $rows ) {
			return null;
		}
		$row = $rows[ count( $rows ) - 1 ];
		if ( ! is_array( $row ) ) {
			return null;
		}
		$decoded = array();
		if ( isset( $row['snapshot_json'] ) && is_string( $row['snapshot_json'] ) ) {
			$parsed = json_decode( $row['snapshot_json'], true );
			if ( is_array( $parsed ) ) {
				$decoded = $parsed;
			}
		}
		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'action_id'      => (int) ( $row['action_id'] ?? 0 ),
			'wp_post_id'     => isset( $row['wp_post_id'] ) ? (int) $row['wp_post_id'] : 0,
			'wp_revision_id' => isset( $row['wp_revision_id'] ) ? (int) $row['wp_revision_id'] : 0,
			'object_type'    => (string) ( $row['object_type'] ?? 'post' ),
			'snapshot'       => $decoded,
			'restored_at'    => (string) ( $row['restored_at'] ?? '' ),
		);
	}

	/**
	 * Replace the stored JSON after a created entity id is known.
	 *
	 * @param int                  $id       Snapshot ID.
	 * @param array<string, mixed> $snapshot Updated document.
	 * @param int                  $revision Revision ID.
	 * @return void
	 */
	public function update_document( int $id, array $snapshot, int $revision = 0 ): void {
		$json = wp_json_encode( $snapshot );
		$data = array(
			'snapshot_json' => is_string( $json ) ? $json : '{}',
		);
		if ( $revision > 0 ) {
			$data['wp_revision_id'] = $revision;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Mark a snapshot restored. The JSON itself is left unchanged.
	 *
	 * @param int $id Snapshot ID.
	 * @return void
	 */
	public function mark_restored( int $id ): void {
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table(),
			array( 'restored_at' => is_string( $now ) ? $now : gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => $id )
		);
	}

	/**
	 * Prefixed table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return Schema::get_table_name( 'ai_snapshots', $this->wpdb->prefix );
	}
}
