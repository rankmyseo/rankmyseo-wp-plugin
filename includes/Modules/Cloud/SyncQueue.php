<?php
/**
 * Local queue for RankMySEO inventory and event sync.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

use RankMySEO\Database\Schema;
use wpdb;

/**
 * Writes post events into wp_rankmyseo_sync_queue while the site is connected.
 */
final class SyncQueue {
	/**
	 * Event names accepted by POST /wp/v1/sync/events.
	 *
	 * @var list<string>
	 */
	public const EVENT_TYPES = array(
		'post_published',
		'post_updated',
		'post_deleted',
		'slug_changed',
		'not_found',
		'404_summary',
	);

	/**
	 * Stop retrying a row after this many attempts.
	 */
	public const MAX_ATTEMPTS = 8;

	/**
	 * WordPress database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Credential vault. Enqueue is a no-op while disconnected.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * Clock returning a unix timestamp. Tests inject a fixed clock.
	 *
	 * @var callable():int
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param wpdb                $wpdb  WordPress database.
	 * @param TokenVault          $vault Credential vault.
	 * @param callable():int|null $clock Optional unix clock.
	 */
	public function __construct( wpdb $wpdb, TokenVault $vault, ?callable $clock = null ) {
		$this->wpdb  = $wpdb;
		$this->vault = $vault;
		$this->clock = $clock ?? static function (): int {
			return time();
		};
	}

	/**
	 * Queue one post for the next inventory batch.
	 *
	 * @param array<string, mixed> $post Post payload.
	 * @return bool True when a row was inserted or updated.
	 */
	public function enqueue_inventory( array $post ): bool {
		if ( ! $this->vault->is_connected() ) {
			return false;
		}
		$normalized = $this->normalize_post( $post );
		if ( null === $normalized ) {
			return false;
		}
		return $this->upsert( 'inventory', (int) $normalized['wpPostId'], 'inventory', $normalized );
	}

	/**
	 * Queue one site event.
	 *
	 * @param array<string, mixed> $event Event payload.
	 * @return bool True when a row was inserted or updated.
	 */
	public function enqueue_event( array $event ): bool {
		if ( ! $this->vault->is_connected() ) {
			return false;
		}
		$normalized = $this->normalize_event( $event );
		if ( null === $normalized ) {
			return false;
		}
		$post_id = isset( $normalized['wpPostId'] ) ? (int) $normalized['wpPostId'] : 0;
		return $this->upsert( 'event', $post_id, (string) $normalized['type'], $normalized );
	}

	/**
	 * Make every pending row due immediately.
	 *
	 * @return void
	 */
	public function release_all_pending(): void {
		$rows = $this->select(
			"SELECT id FROM {$this->table_name()} WHERE status = %s LIMIT %d",
			array( 'pending', 500 )
		);
		$now  = $this->mysql();
		foreach ( $rows as $row ) {
			$this->update_row(
				(int) $row['id'],
				array(
					'scheduled_at' => $now,
				)
			);
		}
	}

	/**
	 * Claim due pending rows and mark them processing.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function claim_due( int $limit ): array {
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->select(
			"SELECT id, job_type, entity_type, entity_id, dedupe_key, payload_json, status, attempts, scheduled_at, locked_at FROM {$this->table_name()} WHERE status = %s AND scheduled_at <= %s ORDER BY id ASC LIMIT %d",
			array( 'pending', $this->mysql(), $limit )
		);
		$now   = $this->mysql();
		foreach ( $rows as $index => $row ) {
			$this->update_row(
				(int) $row['id'],
				array(
					'status'    => 'processing',
					'locked_at' => $now,
				)
			);
			$rows[ $index ]['status'] = 'processing';
		}
		return $rows;
	}

	/**
	 * Return processing rows whose lock is older than the given age to pending.
	 *
	 * @param int $seconds Lock age in seconds.
	 * @return void
	 */
	public function reclaim_stale( int $seconds = 600 ): void {
		$rows   = $this->select(
			"SELECT id, locked_at FROM {$this->table_name()} WHERE status = %s LIMIT %d",
			array( 'processing', 100 )
		);
		$cutoff = $this->mysql( $this->now_ts() - max( 60, $seconds ) );
		foreach ( $rows as $row ) {
			$locked = isset( $row['locked_at'] ) ? (string) $row['locked_at'] : '';
			if ( '' === $locked || strcmp( $locked, $cutoff ) > 0 ) {
				continue;
			}
			$this->update_row(
				(int) $row['id'],
				array(
					'status' => 'pending',
				)
			);
		}
	}

	/**
	 * Mark a row completed.
	 *
	 * @param int $id Row ID.
	 * @return void
	 */
	public function complete( int $id ): void {
		$this->update_row(
			$id,
			array(
				'status'       => 'completed',
				'completed_at' => $this->mysql(),
			)
		);
	}

	/**
	 * Return a claimed row to pending without counting a failure.
	 *
	 * @param int $id Row ID.
	 * @return void
	 */
	public function release( int $id ): void {
		$this->update_row(
			$id,
			array(
				'status'       => 'pending',
				'scheduled_at' => $this->mysql(),
			)
		);
	}

	/**
	 * Schedule a failed row with exponential backoff, honoring Retry-After.
	 *
	 * @param int $id          Row ID.
	 * @param int $retry_after Seconds from a 429 response. Zero when absent.
	 * @return void
	 */
	public function schedule_retry( int $id, int $retry_after ): void {
		$row = $this->find( $id );
		if ( null === $row ) {
			return;
		}
		$attempts = (int) ( $row['attempts'] ?? 0 ) + 1;
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->update_row(
				$id,
				array(
					'status'     => 'failed',
					'attempts'   => $attempts,
					'last_error' => 'retry_exhausted',
				)
			);
			return;
		}
		$delay = $this->retry_delay( $attempts, $retry_after );
		$this->update_row(
			$id,
			array(
				'status'       => 'pending',
				'attempts'     => $attempts,
				'scheduled_at' => $this->mysql( $this->now_ts() + $delay ),
				'last_error'   => '',
			)
		);
	}

	/**
	 * Backoff in seconds. Attempt 1 waits 60s, then doubles, capped at one hour.
	 * A larger Retry-After value wins, up to six hours.
	 *
	 * @param int $attempts    Attempt count after the failure.
	 * @param int $retry_after Retry-After seconds.
	 * @return int
	 */
	public function retry_delay( int $attempts, int $retry_after ): int {
		$shift = min( 6, max( 1, $attempts ) );
		$exp   = (int) min( 3600, 30 * ( 2 ** $shift ) );
		return (int) min( 21600, max( $exp, max( 0, $retry_after ) ) );
	}

	/**
	 * Load one queue row.
	 *
	 * @param int $id Row ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		$rows = $this->select(
			"SELECT id, job_type, entity_type, entity_id, dedupe_key, payload_json, status, attempts, scheduled_at, locked_at, last_error FROM {$this->table_name()} WHERE id = %d LIMIT %d",
			array( $id, 1 )
		);
		return $rows[0] ?? null;
	}

	/**
	 * Count rows by status. Used by tests and the manual sync response.
	 *
	 * @param string $status Queue status.
	 * @return int
	 */
	public function count_status( string $status ): int {
		$rows = $this->select(
			"SELECT id FROM {$this->table_name()} WHERE status = %s LIMIT %d",
			array( $status, 500 )
		);
		return count( $rows );
	}

	/**
	 * Insert or refresh the pending row for a dedupe key.
	 *
	 * @param string               $job_type  inventory or event.
	 * @param int                  $entity_id Post ID, or 0.
	 * @param string               $type      Event or inventory type.
	 * @param array<string, mixed> $payload   JSON payload.
	 * @return bool
	 */
	private function upsert( string $job_type, int $entity_id, string $type, array $payload ): bool {
		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) ) {
			return false;
		}
		$dedupe   = hash( 'sha256', $job_type . '|' . $entity_id . '|' . $type );
		$existing = $this->select(
			"SELECT id FROM {$this->table_name()} WHERE dedupe_key = %s AND status = %s LIMIT %d",
			array( $dedupe, 'pending', 1 )
		);
		$now      = $this->mysql();
		if ( isset( $existing[0]['id'] ) ) {
			$this->update_row(
				(int) $existing[0]['id'],
				array(
					'payload_json' => $json,
					'scheduled_at' => $now,
					'entity_id'    => $entity_id,
				)
			);
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $this->wpdb->insert(
			$this->table_name(),
			array(
				'job_type'     => $job_type,
				'entity_type'  => 'post',
				'entity_id'    => $entity_id,
				'dedupe_key'   => $dedupe,
				'payload_json' => $json,
				'status'       => 'pending',
				'attempts'     => 0,
				'scheduled_at' => $now,
				'created_at'   => $now,
			)
		);
		return false !== $inserted;
	}

	/**
	 * Normalize an inventory post to the cloud contract.
	 *
	 * @param array<string, mixed> $post Raw post.
	 * @return array<string, mixed>|null
	 */
	private function normalize_post( array $post ): ?array {
		$post_id = isset( $post['wpPostId'] ) ? (int) $post['wpPostId'] : 0;
		$url     = $this->http_url( $post['url'] ?? '' );
		$type    = $this->slug_piece( $post['postType'] ?? '', 32 );
		$status  = $this->slug_piece( $post['status'] ?? '', 20 );
		if ( $post_id < 1 || '' === $url || '' === $type || '' === $status ) {
			return null;
		}

		$row   = array(
			'wpPostId' => $post_id,
			'url'      => $url,
			'postType' => $type,
			'status'   => $status,
		);
		$title = $this->clip( $post['title'] ?? null, 300 );
		if ( null !== $title ) {
			$row['title'] = $title;
		}
		$description = $this->clip( $post['metaDescription'] ?? null, 400 );
		if ( null !== $description ) {
			$row['metaDescription'] = $description;
		}
		$keyword = $this->clip( $post['focusKeyword'] ?? null, 200 );
		if ( null !== $keyword ) {
			$row['focusKeyword'] = $keyword;
		}
		if ( array_key_exists( 'seoScore', $post ) && null !== $post['seoScore'] && '' !== $post['seoScore'] ) {
			$row['seoScore'] = max( 0, min( 100, (int) $post['seoScore'] ) );
		}
		$hash = $this->clip( $post['contentHash'] ?? null, 128 );
		if ( null !== $hash ) {
			$row['contentHash'] = $hash;
		}
		return $row;
	}

	/**
	 * Normalize an event to the cloud contract.
	 *
	 * @param array<string, mixed> $event Raw event.
	 * @return array<string, mixed>|null
	 */
	private function normalize_event( array $event ): ?array {
		$type = isset( $event['type'] ) ? (string) $event['type'] : '';
		if ( ! in_array( $type, self::EVENT_TYPES, true ) ) {
			return null;
		}
		$row = array( 'type' => $type );
		if ( isset( $event['wpPostId'] ) ) {
			$post_id = (int) $event['wpPostId'];
			if ( $post_id > 0 ) {
				$row['wpPostId'] = $post_id;
			}
		}
		foreach ( array(
			'url'         => 'url',
			'previousUrl' => 'previousUrl',
		) as $key => $target ) {
			if ( ! isset( $event[ $key ] ) ) {
				continue;
			}
			$url = $this->http_url( $event[ $key ] );
			if ( '' !== $url ) {
				$row[ $target ] = $url;
			}
		}
		if ( isset( $event['status'] ) ) {
			$status = $this->slug_piece( $event['status'], 20 );
			if ( '' !== $status ) {
				$row['status'] = $status;
			}
		}
		if ( isset( $event['postType'] ) ) {
			$post_type = $this->slug_piece( $event['postType'], 32 );
			if ( '' !== $post_type ) {
				$row['postType'] = $post_type;
			}
		}
		$title = $this->clip( $event['title'] ?? null, 300 );
		if ( null !== $title ) {
			$row['title'] = $title;
		}
		if ( in_array( $type, array( 'post_published', 'post_updated', 'slug_changed' ), true ) ) {
			if ( empty( $row['wpPostId'] ) || empty( $row['url'] ) ) {
				return null;
			}
		}
		if ( 'post_deleted' === $type && empty( $row['wpPostId'] ) && empty( $row['url'] ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * Update one row by primary key.
	 *
	 * @param int                  $id   Row ID.
	 * @param array<string, mixed> $data Column values.
	 * @return void
	 */
	private function update_row( int $id, array $data ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table_name(),
			$data,
			array( 'id' => $id )
		);
	}

	/**
	 * Run a prepared SELECT against the sync queue.
	 *
	 * @param string            $sql  SQL with placeholders. The table name is interpolated from Schema.
	 * @param array<int, mixed> $args Placeholder values.
	 * @return list<array<string, mixed>>
	 */
	private function select( string $sql, array $args ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are bound below; the table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, ...$args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$clean = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$clean[] = $row;
			}
		}
		return $clean;
	}

	/**
	 * Prefixed sync queue table name.
	 *
	 * @return string
	 */
	private function table_name(): string {
		return Schema::get_table_name( 'sync_queue', $this->wpdb->prefix );
	}

	/**
	 * Current unix time from the injected clock.
	 *
	 * @return int
	 */
	private function now_ts(): int {
		return (int) call_user_func( $this->clock );
	}

	/**
	 * Format a UTC timestamp for MySQL.
	 *
	 * @param int|null $timestamp Unix time. Null uses the clock.
	 * @return string
	 */
	private function mysql( ?int $timestamp = null ): string {
		return gmdate( 'Y-m-d H:i:s', $timestamp ?? $this->now_ts() );
	}

	/**
	 * Clip optional text.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 * @return string|null Null when the field was omitted.
	 */
	private function clip( $value, int $max ): ?string {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$text = sanitize_text_field( (string) $value );
		if ( strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max );
		}
		return $text;
	}

	/**
	 * Normalize a post type or status slug.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	private function slug_piece( $value, int $max ): string {
		$text = strtolower( sanitize_key( is_scalar( $value ) ? (string) $value : '' ) );
		if ( strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max );
		}
		if ( 1 !== preg_match( '/^[a-z0-9_-]{1,32}$/', $text ) ) {
			return '';
		}
		return $text;
	}

	/**
	 * Accept an http(s) URL.
	 *
	 * @param mixed $value Raw URL.
	 * @return string
	 */
	private function http_url( $value ): string {
		$url = esc_url_raw( is_scalar( $value ) ? (string) $value : '' );
		if ( '' === $url ) {
			return '';
		}
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'https', 'http' ), true ) ) {
			return '';
		}
		if ( strlen( $url ) > 2048 ) {
			return '';
		}
		return $url;
	}
}
