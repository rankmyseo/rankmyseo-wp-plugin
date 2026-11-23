<?php
/**
 * Local mirror of RankMySEO Cloud actions.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Database\Schema;
use wpdb;

/**
 * Reads and writes wp_rankmyseo_ai_actions.
 */
final class ActionRepository {
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
	 * Insert or refresh a cloud action. Applied rows are not overwritten.
	 *
	 * @param array<string, mixed> $cloud Action object from RankMySEO.
	 * @return int Local row ID, or 0 when the payload is unusable.
	 */
	public function upsert_cloud( array $cloud ): int {
		$cloud_id = isset( $cloud['id'] ) && is_string( $cloud['id'] ) ? trim( $cloud['id'] ) : '';
		if ( '' === $cloud_id ) {
			$cloud_id = $this->uuid();
		}
		$type = isset( $cloud['actionType'] ) && is_string( $cloud['actionType'] ) ? $cloud['actionType'] : '';
		if ( ! ActionCatalog::is_known( $type ) ) {
			return 0;
		}

		$after      = $this->object_value( $cloud['after'] ?? $cloud['proposed'] ?? array() );
		$before     = $this->object_value( $cloud['before'] ?? array() );
		$cloud_risk = isset( $cloud['riskTier'] ) && is_string( $cloud['riskTier'] ) ? $cloud['riskTier'] : '';
		$risk       = ActionCatalog::stricter_risk( ActionCatalog::risk_for( $type, $after ), $cloud_risk );

		$existing = $this->find_by_cloud_id( $cloud_id );
		if ( null !== $existing && 'pending' !== $existing['status'] && 'stale' !== $existing['status'] ) {
			return (int) $existing['id'];
		}

		$row = array(
			'cloud_action_id'  => substr( $cloud_id, 0, 36 ),
			'source_kind'      => $this->source_kind( $cloud ),
			'action_type'      => $type,
			'wp_post_id'       => $this->post_id( $cloud ),
			'target_url'       => $this->clip( isset( $cloud['targetUrl'] ) && is_string( $cloud['targetUrl'] ) ? $cloud['targetUrl'] : '', 1024 ),
			'base_hash'        => $this->hash_value( $cloud['baseHash'] ?? null ),
			'before_payload'   => $this->encode( $before ),
			'proposed_payload' => $this->encode( $after ),
			'rationale'        => $this->clip( isset( $cloud['rationale'] ) && is_string( $cloud['rationale'] ) ? sanitize_text_field( $cloud['rationale'] ) : '', 5000 ),
			'risk_tier'        => $risk,
			'status'           => 'pending',
			'created_at'       => $this->now(),
		);

		if ( null !== $existing ) {
			unset( $row['created_at'] );
			$this->update_columns( (int) $existing['id'], $row );
			return (int) $existing['id'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $this->wpdb->insert( $this->table(), $row );
		if ( false === $inserted ) {
			return 0;
		}
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Store a local pending action that already has a cloud id.
	 *
	 * @param array<string, mixed> $fields Column-oriented fields using public names.
	 * @return int
	 */
	public function insert_local( array $fields ): int {
		return $this->upsert_cloud( $fields );
	}

	/**
	 * Find one action by local ID.
	 *
	 * @param int $id Local ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$sql = "SELECT * FROM {$this->table()} WHERE id = %d LIMIT %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, $id, 1 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $prepared, ARRAY_A );
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Find one action by cloud UUID.
	 *
	 * @param string $cloud_id Cloud action id.
	 * @return array<string, mixed>|null
	 */
	public function find_by_cloud_id( string $cloud_id ): ?array {
		$sql = "SELECT * FROM {$this->table()} WHERE cloud_action_id = %s LIMIT %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, $cloud_id, 1 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $prepared, ARRAY_A );
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * List actions, optionally filtered by status and type.
	 *
	 * @param string $status Status filter, or empty.
	 * @param string $type   Type filter, or empty.
	 * @return list<array<string, mixed>>
	 */
	public function list_actions( string $status = '', string $type = '' ): array {
		$sql = "SELECT * FROM {$this->table()} WHERE id > %d";
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
			$item = $this->hydrate( $row );
			if ( '' !== $status && $item['status'] !== $status ) {
				continue;
			}
			if ( '' !== $type && $item['action_type'] !== $type ) {
				continue;
			}
			$items[] = $item;
		}

		usort(
			$items,
			static function ( array $left, array $right ): int {
				return strcmp( (string) $right['created_at'], (string) $left['created_at'] );
			}
		);
		return $items;
	}

	/**
	 * Replace columns on one row.
	 *
	 * @param int                  $id      Local ID.
	 * @param array<string, mixed> $columns Columns.
	 * @return void
	 */
	public function update_columns( int $id, array $columns ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table(), $columns, array( 'id' => $id ) );
	}

	/**
	 * Public JSON shape for the admin REST API.
	 *
	 * @param array<string, mixed> $action Hydrated action.
	 * @return array<string, mixed>
	 */
	public function present( array $action ): array {
		$before = is_array( $action['before'] ) ? $action['before'] : array();
		$after  = is_array( $action['after'] ) ? $action['after'] : array();
		$type   = (string) $action['action_type'];
		$post   = isset( $action['wp_post_id'] ) ? (int) $action['wp_post_id'] : 0;

		return array(
			'id'              => (int) $action['id'],
			'cloud_action_id' => (string) $action['cloud_action_id'],
			'action_type'     => $type,
			'risk_tier'       => (string) $action['risk_tier'],
			'status'          => (string) $action['status'],
			'wp_post_id'      => $post > 0 ? $post : null,
			'target_url'      => (string) $action['target_url'],
			'base_hash'       => (string) $action['base_hash'],
			'before'          => $before,
			'after'           => $after,
			'rationale'       => (string) $action['rationale'],
			'source'          => (string) $action['source_kind'],
			'applied_by'      => (string) $action['applied_by'],
			'title'           => ActionCatalog::label( $type ),
			'before_text'     => PayloadSanitizer::preview( $type, $before ),
			'after_text'      => PayloadSanitizer::preview( $type, $after ),
			'created_at'      => (string) $action['created_at'],
			'resolved_at'     => '' !== (string) $action['resolved_at'] ? (string) $action['resolved_at'] : null,
		);
	}

	/**
	 * Prefixed table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return Schema::get_table_name( 'ai_actions', $this->wpdb->prefix );
	}

	/**
	 * Decode a database row.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$post = isset( $row['wp_post_id'] ) ? (int) $row['wp_post_id'] : 0;
		return array(
			'id'              => (int) ( $row['id'] ?? 0 ),
			'cloud_action_id' => (string) ( $row['cloud_action_id'] ?? '' ),
			'source_kind'     => (string) ( $row['source_kind'] ?? '' ),
			'action_type'     => (string) ( $row['action_type'] ?? '' ),
			'wp_post_id'      => $post > 0 ? $post : null,
			'target_url'      => (string) ( $row['target_url'] ?? '' ),
			'base_hash'       => (string) ( $row['base_hash'] ?? '' ),
			'before'          => $this->decode( $row['before_payload'] ?? '' ),
			'after'           => $this->decode( $row['proposed_payload'] ?? '' ),
			'rationale'       => (string) ( $row['rationale'] ?? '' ),
			'risk_tier'       => (string) ( $row['risk_tier'] ?? 'low' ),
			'status'          => (string) ( $row['status'] ?? 'pending' ),
			'applied_by'      => (string) ( $row['applied_by'] ?? '' ),
			'wp_actor_id'     => (int) ( $row['wp_actor_id'] ?? 0 ),
			'wp_revision_id'  => (int) ( $row['wp_revision_id'] ?? 0 ),
			'error_message'   => (string) ( $row['error_message'] ?? '' ),
			'created_at'      => (string) ( $row['created_at'] ?? '' ),
			'resolved_at'     => (string) ( $row['resolved_at'] ?? '' ),
		);
	}

	/**
	 * JSON-decode an object payload.
	 *
	 * @param mixed $value Stored JSON.
	 * @return array<string, mixed>
	 */
	private function decode( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * JSON-encode an object.
	 *
	 * @param array<string, mixed> $value Payload.
	 * @return string
	 */
	private function encode( array $value ): string {
		$json = wp_json_encode( $value );
		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * Coerce a JSON object.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>
	 */
	private function object_value( $value ): array {
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Cloud source mapped onto the local column.
	 *
	 * @param array<string, mixed> $cloud Cloud action.
	 * @return string
	 */
	private function source_kind( array $cloud ): string {
		$source = isset( $cloud['source'] ) && is_string( $cloud['source'] ) ? sanitize_key( $cloud['source'] ) : 'page_edit';
		if ( '' === $source ) {
			return 'page_edit';
		}
		return substr( $source, 0, 32 );
	}

	/**
	 * Positive post ID or null.
	 *
	 * @param array<string, mixed> $cloud Cloud action.
	 * @return int|null
	 */
	private function post_id( array $cloud ): ?int {
		if ( ! isset( $cloud['wpPostId'] ) || ! is_numeric( $cloud['wpPostId'] ) ) {
			return null;
		}
		$id = (int) $cloud['wpPostId'];
		return $id > 0 ? $id : null;
	}

	/**
	 * Store a hash only when it looks like one.
	 *
	 * @param mixed $value Raw hash.
	 * @return string|null
	 */
	private function hash_value( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$clean = strtolower( trim( $value ) );
		if ( 1 !== preg_match( '/^[a-f0-9]{32,64}$/', $clean ) ) {
			return null;
		}
		return $clean;
	}

	/**
	 * Clip a string to a column width.
	 *
	 * @param string $value Text.
	 * @param int    $max   Max bytes.
	 * @return string
	 */
	private function clip( string $value, int $max ): string {
		if ( strlen( $value ) <= $max ) {
			return $value;
		}
		return substr( $value, 0, $max );
	}

	/**
	 * Current GMT timestamp.
	 *
	 * @return string
	 */
	private function now(): string {
		$now = current_time( 'mysql', true );
		return is_string( $now ) ? $now : gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * UUID for a locally created row.
	 *
	 * @return string
	 */
	private function uuid(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ord( $bytes[6] ) & 0x0f | 0x40 );
		$bytes[8] = chr( ord( $bytes[8] ) & 0x3f | 0x80 );
		$hex      = bin2hex( $bytes );
		return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
	}
}
