<?php
/**
 * Opt-in IndexNow key file and debounced submissions.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

use WP_Error;
use WP_Post;

/**
 * Queues public URL changes and submits them only when an admin has enabled IndexNow.
 */
final class IndexNow {
	/**
	 * Cron hook that drains the debounced queue. Also listed in Deactivator::CRON_HOOKS.
	 */
	public const CRON_HOOK = 'rankmyseo_process_indexnow_queue';

	/**
	 * Seconds to wait after the latest edit before the first submission.
	 */
	public const DEBOUNCE_SECONDS = 60;

	/**
	 * URLs sent in one IndexNow request.
	 */
	public const BATCH_SIZE = 100;

	/**
	 * Settings repository.
	 *
	 * @var SitemapSettings
	 */
	private SitemapSettings $settings;

	/**
	 * HTTP transport.
	 *
	 * @var IndexNowTransport
	 */
	private IndexNowTransport $transport;

	/**
	 * Constructor.
	 *
	 * @param SitemapSettings   $settings  Settings repository.
	 * @param IndexNowTransport $transport HTTP transport.
	 */
	public function __construct( SitemapSettings $settings, IndexNowTransport $transport ) {
		$this->settings  = $settings;
		$this->transport = $transport;
	}

	/**
	 * Whether IndexNow is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->settings->is_indexnow_enabled();
	}

	/**
	 * Body of the virtual key file when the request matches the stored key.
	 *
	 * Returns null when IndexNow is off or the key does not match, so the request can 404.
	 *
	 * @param string $requested Key from the URL.
	 * @return string|null
	 */
	public function virtual_key_body( string $requested ): ?string {
		if ( ! $this->is_enabled() ) {
			return null;
		}

		$requested = strtolower( $requested );
		if ( ! SitemapSettings::is_valid_key( $requested ) ) {
			return null;
		}

		$key = $this->settings->ensure_key();
		if ( ! hash_equals( $key, $requested ) ) {
			return null;
		}

		return $key;
	}

	/**
	 * Queue a public URL. Does nothing, including scheduling, while IndexNow is off.
	 *
	 * @param string $url Absolute URL on this site.
	 * @return void
	 */
	public function enqueue( string $url ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$clean = $this->sanitize_local_url( $url );
		if ( '' === $clean ) {
			return;
		}

		$queue = $this->settings->get_queue();
		if ( ! in_array( $clean, $queue, true ) ) {
			$queue[] = $clean;
			if ( count( $queue ) > 10000 ) {
				$queue = array_slice( $queue, -10000 );
			}
			$this->settings->save_queue( $queue );
		}

		$this->schedule( self::DEBOUNCE_SECONDS );
	}

	/**
	 * Drain one batch. Never calls the transport while IndexNow is disabled.
	 *
	 * @return void
	 */
	public function process_queue(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$queue = $this->settings->get_queue();
		if ( [] === $queue ) {
			$this->settings->reset_attempts();
			return;
		}

		$batch  = array_slice( $queue, 0, self::BATCH_SIZE );
		$result = $this->transport->submit( $this->payload( $batch ) );
		$this->remember( $result, $batch );

		if ( $result['ok'] ) {
			$this->settings->save_queue( array_values( array_slice( $queue, count( $batch ) ) ) );
			$this->settings->reset_attempts();
			if ( [] !== $this->settings->get_queue() ) {
				$this->schedule( self::DEBOUNCE_SECONDS, true );
			}
			return;
		}

		$attempts = $this->settings->increment_attempts();
		$delay    = min( 3600, self::DEBOUNCE_SECONDS * ( 2 ** min( $attempts, 6 ) ) );
		$this->schedule( $delay, true );
	}

	/**
	 * Submit one URL immediately. Refuses without an HTTP call when IndexNow is off.
	 *
	 * @param string $url Absolute URL.
	 * @return array{ok: bool, code: int, message: string}|WP_Error
	 */
	public function ping_url( string $url ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error(
				'rankmyseo_indexnow_disabled',
				__( 'IndexNow is off, so this URL was not sent. Turn IndexNow on to notify search engines.', 'rankmyseo' ),
				[ 'status' => 400 ]
			);
		}

		$clean = $this->sanitize_local_url( $url );
		if ( '' === $clean ) {
			return new WP_Error(
				'rankmyseo_invalid_url',
				__( 'Enter a public URL on this site.', 'rankmyseo' ),
				[ 'status' => 400 ]
			);
		}

		$result = $this->transport->submit( $this->payload( [ $clean ] ) );
		$this->remember( $result, [ $clean ] );
		return $result;
	}

	/**
	 * Queue a URL when a public post is published, updated, or unpublished.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Previous status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		$post_type = (string) $post->post_type;
		if ( ! $this->settings->is_post_type_included( $post_type ) ) {
			return;
		}

		if ( ! function_exists( 'get_permalink' ) ) {
			return;
		}

		$link = get_permalink( $post );
		if ( is_string( $link ) ) {
			$this->enqueue( $link );
		}
	}

	/**
	 * Queue the old and new URLs when a published slug changes.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $after   Post after the update.
	 * @param WP_Post $before  Post before the update.
	 * @return void
	 */
	public function on_post_updated( int $post_id, WP_Post $after, WP_Post $before ): void {
		unset( $post_id );
		if ( ! $this->is_enabled() || ! function_exists( 'get_permalink' ) ) {
			return;
		}

		$before_name = (string) $before->post_name;
		$after_name  = (string) $after->post_name;
		if ( $before_name === $after_name ) {
			return;
		}

		$after_status  = (string) $after->post_status;
		$before_status = (string) $before->post_status;
		if ( 'publish' !== $after_status && 'publish' !== $before_status ) {
			return;
		}

		$post_type = (string) $after->post_type;
		if ( ! $this->settings->is_post_type_included( $post_type ) ) {
			return;
		}

		$new_link = get_permalink( $after );
		$old_link = get_permalink( $before );
		if ( is_string( $new_link ) ) {
			$this->enqueue( $new_link );
		}
		if ( is_string( $old_link ) ) {
			$this->enqueue( $old_link );
		}
	}

	/**
	 * Queue a public URL before its post is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_delete( int $post_id ): void {
		if ( ! $this->is_enabled() || ! function_exists( 'get_post' ) || ! function_exists( 'get_permalink' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$status = (string) $post->post_status;
		$type   = (string) $post->post_type;
		if ( 'publish' !== $status || ! $this->settings->is_post_type_included( $type ) ) {
			return;
		}

		$link = get_permalink( $post );
		if ( is_string( $link ) ) {
			$this->enqueue( $link );
		}
	}

	/**
	 * Clear a pending queue when an admin turns IndexNow off.
	 *
	 * @param bool $was_enabled Previous enabled flag.
	 * @param bool $now_enabled New enabled flag.
	 * @return void
	 */
	public function on_toggled( bool $was_enabled, bool $now_enabled ): void {
		if ( $was_enabled && ! $now_enabled ) {
			$this->settings->save_queue( [] );
			$this->settings->reset_attempts();
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
		}
	}

	/**
	 * Build the IndexNow JSON body.
	 *
	 * @param array<int, string> $urls URLs to submit.
	 * @return array<string, mixed>
	 */
	private function payload( array $urls ): array {
		$key  = $this->settings->ensure_key();
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return [
			'host'        => is_string( $host ) ? $host : '',
			'key'         => $key,
			'keyLocation' => home_url( '/' . $key . '.txt' ),
			'urlList'     => array_values( $urls ),
		];
	}

	/**
	 * Keep a URL only when it is an http(s) URL on this site.
	 *
	 * @param string $url Candidate URL.
	 * @return string
	 */
	private function sanitize_local_url( string $url ): string {
		$clean = esc_url_raw( trim( $url ) );
		if ( '' === $clean ) {
			return '';
		}

		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$url_host  = wp_parse_url( $clean, PHP_URL_HOST );
		if ( ! is_string( $home_host ) || ! is_string( $url_host ) ) {
			return '';
		}

		if ( strtolower( $home_host ) !== strtolower( $url_host ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * Record a submission in the capped log.
	 *
	 * @param array{ok: bool, code: int, message: string} $result Transport result.
	 * @param array<int, string>                          $urls   Submitted URLs.
	 * @return void
	 */
	private function remember( array $result, array $urls ): void {
		$this->settings->append_log(
			[
				'time'    => gmdate( 'c' ),
				'ok'      => (bool) $result['ok'],
				'code'    => (int) $result['code'],
				'message' => mb_substr( (string) $result['message'], 0, 300 ),
				'count'   => count( $urls ),
				'urls'    => array_slice( $urls, 0, 10 ),
			]
		);
	}

	/**
	 * Schedule a single cron event unless one is already waiting.
	 *
	 * @param int  $delay    Seconds from now.
	 * @param bool $force    Replace an existing event. Used after a batch so backoff can move.
	 * @return void
	 */
	private function schedule( int $delay, bool $force = false ): void {
		if ( ! function_exists( 'wp_schedule_single_event' ) || ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}

		if ( ! $force && wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		if ( $force && function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		wp_schedule_single_event( time() + max( 1, $delay ), self::CRON_HOOK );
	}
}
