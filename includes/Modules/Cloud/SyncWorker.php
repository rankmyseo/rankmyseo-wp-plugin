<?php
/**
 * Dispatches the RankMySEO sync queue over WP-Cron or Action Scheduler.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

/**
 * Sends queued inventory and events in small batches with backoff.
 */
final class SyncWorker {
	/**
	 * Cron and Action Scheduler hook.
	 */
	public const HOOK = 'rankmyseo_process_sync_queue';

	/**
	 * Short lock so two workers do not send the same rows.
	 */
	public const LOCK = 'rankmyseo_cloud_sync_lock';

	/**
	 * Rows per cloud request. Stays well under the 100-row and 256 KB API caps.
	 */
	private const BATCH = 20;

	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * HTTP client.
	 *
	 * @var CloudClient
	 */
	private CloudClient $client;

	/**
	 * Local queue.
	 *
	 * @var SyncQueue
	 */
	private SyncQueue $queue;

	/**
	 * Set when an authenticated response means this token should stop calling.
	 *
	 * @var bool
	 */
	private bool $auth_stopped = false;

	/**
	 * Constructor.
	 *
	 * @param TokenVault  $vault  Credential vault.
	 * @param CloudClient $client HTTP client.
	 * @param SyncQueue   $queue  Local queue.
	 */
	public function __construct( TokenVault $vault, CloudClient $client, SyncQueue $queue ) {
		$this->vault  = $vault;
		$this->client = $client;
		$this->queue  = $queue;
	}

	/**
	 * Register the cron callback and schedule it only while connected.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Connected sites sync small batches; nothing runs while disconnected.
		add_action( self::HOOK, array( $this, 'process_cron' ) );
		$this->ensure_schedule();
	}

	/**
	 * Add a five-minute cron interval.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_schedule( array $schedules ): array {
		$schedules['rankmyseo_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes (RankMySEO)', 'rankmyseo' ),
		);
		return $schedules;
	}

	/**
	 * Schedule background sync while connected, and remove it otherwise.
	 *
	 * @return void
	 */
	public function ensure_schedule(): void {
		if ( ! $this->vault->is_connected() ) {
			$this->unschedule();
			return;
		}

		$has      = $this->scheduler( 'has_scheduled_action' );
		$schedule = $this->scheduler( 'schedule_recurring_action' );
		if ( null !== $has && null !== $schedule ) {
			if ( ! $has( self::HOOK, array(), 'rankmyseo' ) ) {
				$schedule( time() + 120, 5 * MINUTE_IN_SECONDS, self::HOOK, array(), 'rankmyseo' );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 120, 'rankmyseo_five_minutes', self::HOOK );
		}
	}

	/**
	 * Remove scheduled sync jobs.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		$unschedule = $this->scheduler( 'unschedule_all_actions' );
		if ( null !== $unschedule ) {
			$unschedule( self::HOOK, array(), 'rankmyseo' );
		}
	}

	/**
	 * Resolve an optional Action Scheduler callback.
	 *
	 * The name is concatenated so static analysis does not require the plugin.
	 *
	 * @param string $suffix Function suffix after as_.
	 * @return callable|null
	 */
	private function scheduler( string $suffix ): ?callable {
		$function = 'as_' . $suffix;
		if ( function_exists( $function ) && is_callable( $function ) ) {
			return $function;
		}
		return null;
	}

	/**
	 * Cron callback. Discards the sync summary.
	 *
	 * @return void
	 */
	public function process_cron(): void {
		$this->process();
	}

	/**
	 * Queue a fresh inventory snapshot and send due rows now.
	 *
	 * @param list<array<string, mixed>> $posts Inventory rows.
	 * @return array<string, mixed>
	 */
	public function sync_now( array $posts ): array {
		if ( ! $this->vault->is_connected() ) {
			return $this->summary( false, false, 0, __( 'Connect RankMySEO before syncing this site.', 'rankmyseo' ) );
		}
		foreach ( $posts as $post ) {
			if ( is_array( $post ) ) {
				$this->queue->enqueue_inventory( $post );
			}
		}
		$this->queue->release_all_pending();
		return $this->process();
	}

	/**
	 * Send due inventory and events. Does not call the network while disconnected.
	 *
	 * @return array<string, mixed>
	 */
	public function process(): array {
		if ( ! $this->vault->is_connected() ) {
			return $this->summary( true, false, 0, '' );
		}
		if ( get_transient( self::LOCK ) ) {
			return $this->summary( true, false, 0, __( 'A sync is already running.', 'rankmyseo' ) );
		}

		set_transient( self::LOCK, '1', 55 );
		$this->auth_stopped = false;
		try {
			$this->queue->reclaim_stale();
			$rows = $this->queue->claim_due( 80 );
			if ( array() === $rows ) {
				return $this->summary( true, false, 0, '' );
			}

			$inventory = array();
			$events    = array();
			foreach ( $rows as $row ) {
				$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
				if ( ! is_array( $payload ) ) {
					$this->queue->complete( (int) $row['id'] );
					continue;
				}
				$item = array(
					'id'   => (int) $row['id'],
					'body' => $payload,
				);
				if ( 'inventory' === ( $row['job_type'] ?? '' ) ) {
					$inventory[] = $item;
				} else {
					$events[] = $item;
				}
			}

			$processed = $this->dispatch( 'inventory', $inventory );
			// @phpstan-ignore if.alwaysFalse (dispatch() sets this from the HTTP status)
			if ( $this->auth_stopped ) {
				foreach ( $events as $item ) {
					$this->queue->release( $item['id'] );
				}
				return $this->summary( false, true, $processed, (string) $this->vault->public_state()['last_error'] );
			}
			$processed += $this->dispatch( 'event', $events );
			// @phpstan-ignore if.alwaysFalse (dispatch() sets this from the HTTP status)
			if ( $this->auth_stopped ) {
				return $this->summary( false, true, $processed, (string) $this->vault->public_state()['last_error'] );
			}
			if ( $processed > 0 ) {
				$this->vault->update_public(
					array(
						'last_sync_at' => gmdate( 'c' ),
						'last_error'   => '',
						'health'       => 'connected',
					)
				);
			}
			return $this->summary( true, true, $processed, '' );
		} finally {
			delete_transient( self::LOCK );
		}
	}

	/**
	 * Send one job type in batches.
	 *
	 * @param string                                           $kind  inventory or event.
	 * @param list<array{id: int, body: array<string, mixed>}> $items Claimed rows.
	 * @return int Rows completed.
	 */
	private function dispatch( string $kind, array $items ): int {
		if ( array() === $items || $this->auth_stopped ) {
			if ( $this->auth_stopped ) {
				foreach ( $items as $item ) {
					$this->queue->release( $item['id'] );
				}
			}
			return 0;
		}

		$done = 0;
		foreach ( array_chunk( $items, self::BATCH ) as $chunk ) {
			if ( $this->auth_stopped ) {
				foreach ( $chunk as $item ) {
					$this->queue->release( $item['id'] );
				}
				continue;
			}
			$bodies = array();
			$ids    = array();
			foreach ( $chunk as $item ) {
				$bodies[] = $item['body'];
				$ids[]    = $item['id'];
			}
			$result = 'inventory' === $kind
				? $this->client->sync_inventory( $bodies )
				: $this->client->sync_events( $bodies );

			if ( $result->ok ) {
				foreach ( $ids as $id ) {
					$this->queue->complete( $id );
				}
				$done += count( $ids );
				continue;
			}

			if ( $this->auth_failed( $result ) ) {
				$this->auth_stopped = true;
			}
			if ( ! $result->attempted || $this->auth_stopped ) {
				foreach ( $ids as $id ) {
					$this->queue->release( $id );
				}
				continue;
			}

			$this->vault->update_public(
				array(
					'last_error' => $result->message,
					'health'     => 'degraded',
				)
			);
			foreach ( $ids as $id ) {
				$this->queue->schedule_retry( $id, $result->retry_after );
			}
		}
		return $done;
	}

	/**
	 * Whether this response means the site token should stop calling.
	 *
	 * A plan limit is a full account, not a broken connection.
	 *
	 * @param CloudResult $result HTTP result.
	 * @return bool
	 */
	private function auth_failed( CloudResult $result ): bool {
		if ( 401 === $result->status ) {
			return true;
		}
		if ( 403 === $result->status && 'plan_limit' !== $result->error ) {
			return true;
		}
		return false;
	}

	/**
	 * Shape a sync summary for REST.
	 *
	 * @param bool   $ok        Whether the worker finished without a reconnect.
	 * @param bool   $attempted Whether HTTP was used.
	 * @param int    $processed Rows completed.
	 * @param string $message   Optional message.
	 * @return array<string, mixed>
	 */
	private function summary( bool $ok, bool $attempted, int $processed, string $message ): array {
		return array(
			'ok'        => $ok,
			'attempted' => $attempted,
			'processed' => $processed,
			'message'   => $message,
			'state'     => $this->vault->public_state(),
		);
	}
}
