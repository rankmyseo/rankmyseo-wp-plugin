<?php
/**
 * Append-only audit log for AI actions.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Database\Schema;
use wpdb;

/**
 * Writes immutable rows to wp_rankmyseo_audit_logs.
 */
final class AuditLog {
	public const APPLIED     = 'ai_action_applied';
	public const AUTO        = 'ai_auto_applied';
	public const REJECTED    = 'ai_action_rejected';
	public const ROLLED_BACK = 'ai_action_rolled_back';
	public const FAILED      = 'ai_action_failed';
	public const POLICY      = 'ai_policy_updated';
	public const WEBHOOK     = 'ai_webhook_received';

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
	 * Append one audit row. Rows are never updated.
	 *
	 * @param string               $event      Event type.
	 * @param string               $actor_type user, auto_apply, or cloud_sync.
	 * @param string               $summary    Short sentence.
	 * @param string               $object_type Object type.
	 * @param int                  $object_id  Object ID.
	 * @param array<string, mixed> $context    Extra JSON context.
	 * @return int
	 */
	public function record( string $event, string $actor_type, string $summary, string $object_type = 'action', int $object_id = 0, array $context = array() ): int {
		$json = wp_json_encode( $context );
		$now  = current_time( 'mysql', true );
		$user = get_current_user_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $this->wpdb->insert(
			$this->table(),
			array(
				'event_type'   => substr( sanitize_key( $event ), 0, 64 ),
				'actor_type'   => substr( sanitize_key( $actor_type ), 0, 24 ),
				'wp_user_id'   => $user > 0 ? $user : null,
				'object_type'  => substr( sanitize_key( $object_type ), 0, 32 ),
				'object_id'    => $object_id > 0 ? $object_id : null,
				'summary'      => substr( sanitize_text_field( $summary ), 0, 255 ),
				'context_json' => is_string( $json ) ? $json : '{}',
				'created_at'   => is_string( $now ) ? $now : gmdate( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $ok ) {
			return 0;
		}
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Recent audit rows, newest first.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 50 ): array {
		$limit = max( 1, min( 200, $limit ) );
		$sql   = "SELECT * FROM {$this->table()} WHERE id > %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$items = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$context = array();
			if ( isset( $row['context_json'] ) && is_string( $row['context_json'] ) ) {
				$parsed = json_decode( $row['context_json'], true );
				if ( is_array( $parsed ) ) {
					$context = $parsed;
				}
			}
			$items[] = array(
				'id'          => (int) ( $row['id'] ?? 0 ),
				'event_type'  => (string) ( $row['event_type'] ?? '' ),
				'actor_type'  => (string) ( $row['actor_type'] ?? '' ),
				'wp_user_id'  => isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : null,
				'object_type' => (string) ( $row['object_type'] ?? '' ),
				'object_id'   => isset( $row['object_id'] ) ? (int) $row['object_id'] : null,
				'summary'     => (string) ( $row['summary'] ?? '' ),
				'context'     => $context,
				'created_at'  => (string) ( $row['created_at'] ?? '' ),
			);
		}

		usort(
			$items,
			static function ( array $left, array $right ): int {
				return strcmp( (string) $right['created_at'], (string) $left['created_at'] );
			}
		);
		return array_slice( $items, 0, $limit );
	}

	/**
	 * Count auto-apply events since a GMT timestamp.
	 *
	 * @param string $since MySQL datetime.
	 * @return int
	 */
	public function count_auto_since( string $since ): int {
		$sql = "SELECT id, event_type, created_at FROM {$this->table()} WHERE event_type = %s AND created_at >= %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, self::AUTO, $since );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		return is_array( $rows ) ? count( $rows ) : 0;
	}

	/**
	 * Prefixed table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return Schema::get_table_name( 'audit_logs', $this->wpdb->prefix );
	}
}
