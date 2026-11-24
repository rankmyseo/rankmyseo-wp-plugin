<?php
/**
 * One-click RankMySEO pairing, rotation, and disconnect.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

use RankMySEO\Core\Settings;
use RankMySEO\Modules\ModuleManager;

/**
 * Turns an administrator click into a paired site without exposing credentials.
 */
final class PairingService {
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
	 * Sync worker, scheduled after a successful pair.
	 *
	 * @var SyncWorker
	 */
	private SyncWorker $worker;

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Module manager. May be null in isolated tests.
	 *
	 * @var ModuleManager|null
	 */
	private ?ModuleManager $modules;

	/**
	 * Constructor.
	 *
	 * @param TokenVault         $vault    Credential vault.
	 * @param CloudClient        $client   HTTP client.
	 * @param SyncWorker         $worker   Sync worker.
	 * @param Settings           $settings Plugin settings.
	 * @param ModuleManager|null $modules  Optional module manager.
	 */
	public function __construct(
		TokenVault $vault,
		CloudClient $client,
		SyncWorker $worker,
		Settings $settings,
		?ModuleManager $modules = null
	) {
		$this->vault    = $vault;
		$this->client   = $client;
		$this->worker   = $worker;
		$this->settings = $settings;
		$this->modules  = $modules;
	}

	/**
	 * Start pairing and store the poll secret encrypted on this site.
	 *
	 * @return array<string, mixed>
	 */
	public function start(): array {
		$site_url  = (string) home_url( '/' );
		$site_name = sanitize_text_field( (string) get_bloginfo( 'name' ) );
		if ( '' === $site_name ) {
			$host      = wp_parse_url( $site_url, PHP_URL_HOST );
			$site_name = is_string( $host ) ? $host : 'WordPress';
		}
		if ( strlen( $site_name ) > 80 ) {
			$site_name = substr( $site_name, 0, 80 );
		}

		global $wp_version;
		$result = $this->client->start_pair(
			$site_url,
			$site_name,
			$this->version_string( is_string( $wp_version ) ? $wp_version : '' ),
			$this->version_string( defined( 'RANKMYSEO_VERSION' ) ? (string) RANKMYSEO_VERSION : '1.0.0' )
		);

		if ( ! $result->ok ) {
			return $this->failure( $result );
		}

		$code     = isset( $result->data['code'] ) ? sanitize_text_field( (string) $result->data['code'] ) : '';
		$pair_url = isset( $result->data['pairUrl'] ) ? esc_url_raw( (string) $result->data['pairUrl'] ) : '';
		$poll     = isset( $result->data['pollToken'] ) ? (string) $result->data['pollToken'] : '';
		$expires  = isset( $result->data['expiresAt'] ) ? sanitize_text_field( (string) $result->data['expiresAt'] ) : '';
		$scheme   = wp_parse_url( $pair_url, PHP_URL_SCHEME );

		if ( '' === $code || '' === $poll || ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'https', 'http' ), true ) ) {
			return array(
				'ok'      => false,
				'status'  => 502,
				'error'   => 'invalid_response',
				'message' => __( 'RankMySEO did not return a connection code. Try again.', 'rankmyseo' ),
			);
		}

		try {
			$this->vault->save_secrets( array( 'poll_token' => $poll ) );
		} catch ( \RuntimeException $exception ) {
			return array(
				'ok'      => false,
				'status'  => 500,
				'error'   => 'vault',
				'message' => $exception->getMessage(),
			);
		}

		$this->vault->update_public(
			array(
				'pairing_code'       => $code,
				'pairing_url'        => $pair_url,
				'pairing_expires_at' => $expires,
				'site_name'          => $site_name,
				'site_url'           => $site_url,
			)
		);

		return array(
			'ok'          => true,
			'status'      => 200,
			'pair_status' => 'pending',
			'state'       => $this->vault->public_state(),
		);
	}

	/**
	 * Poll the stored code. On approval, store the token and webhook secret.
	 *
	 * @return array<string, mixed>
	 */
	public function poll(): array {
		$state = $this->vault->public_state();
		$code  = is_array( $state['pairing'] ) ? (string) ( $state['pairing']['code'] ?? '' ) : '';
		$poll  = $this->vault->get_secret( 'poll_token' );
		if ( '' === $code || '' === $poll ) {
			return array(
				'ok'      => false,
				'status'  => 409,
				'error'   => 'rankmyseo_cloud_pair',
				'message' => __( 'Start a connection before checking approval.', 'rankmyseo' ),
			);
		}

		$result = $this->client->poll_pair( $code, $poll );
		if ( ! $result->ok ) {
			return $this->failure( $result );
		}

		$pair_status = isset( $result->data['status'] ) ? sanitize_key( (string) $result->data['status'] ) : '';
		if ( 'pending' === $pair_status ) {
			if ( isset( $result->data['expiresAt'] ) && is_string( $result->data['expiresAt'] ) ) {
				$this->vault->update_public( array( 'pairing_expires_at' => $result->data['expiresAt'] ) );
			}
			return array(
				'ok'          => true,
				'status'      => 200,
				'pair_status' => 'pending',
				'state'       => $this->vault->public_state(),
			);
		}

		if ( 'approved' === $pair_status ) {
			return $this->store_approval( $result->data );
		}

		$this->clear_pairing();
		return array(
			'ok'          => true,
			'status'      => 200,
			'pair_status' => '' !== $pair_status ? $pair_status : 'expired',
			'state'       => $this->vault->public_state(),
		);
	}

	/**
	 * Rotate the bearer token and store the replacement.
	 *
	 * @return array<string, mixed>
	 */
	public function rotate(): array {
		$result = $this->client->rotate_token();
		if ( ! $result->ok ) {
			return $this->failure( $result );
		}
		$token  = isset( $result->data['token'] ) ? (string) $result->data['token'] : '';
		$prefix = isset( $result->data['prefix'] ) ? (string) $result->data['prefix'] : '';
		if ( ! str_starts_with( $token, 'rms_wp_' ) ) {
			return array(
				'ok'      => false,
				'status'  => 502,
				'error'   => 'invalid_response',
				'message' => __( 'RankMySEO did not return a replacement token. The current connection is unchanged.', 'rankmyseo' ),
			);
		}
		if ( 1 !== preg_match( '/^rms_wp_[A-Za-z0-9_-]{6}$/', $prefix ) ) {
			$prefix = $this->vault->prefix_from_token( $token );
		}
		try {
			$this->vault->save_secrets( array( 'token' => $token ) );
		} catch ( \RuntimeException $exception ) {
			return array(
				'ok'      => false,
				'status'  => 500,
				'error'   => 'vault',
				'message' => $exception->getMessage(),
			);
		}
		$this->vault->update_public(
			array(
				'connected'          => true,
				'reconnect_required' => false,
				'health'             => 'connected',
				'token_prefix'       => $prefix,
				'last_error'         => '',
			)
		);
		return array(
			'ok'     => true,
			'status' => 200,
			'state'  => $this->vault->public_state(),
		);
	}

	/**
	 * Tell RankMySEO this site disconnected, then wipe local credentials and cache.
	 *
	 * @return array<string, mixed>
	 */
	public function disconnect(): array {
		if ( $this->vault->has_token() ) {
			$this->client->disconnect();
		}
		$this->vault->wipe();
		$this->worker->unschedule();
		return array(
			'ok'     => true,
			'status' => 200,
			'state'  => $this->vault->public_state(),
		);
	}

	/**
	 * Persist an approved pairing response.
	 *
	 * @param array<string, mixed> $data Poll body.
	 * @return array<string, mixed>
	 */
	private function store_approval( array $data ): array {
		$token  = isset( $data['token'] ) ? (string) $data['token'] : '';
		$secret = isset( $data['webhookSecret'] ) ? (string) $data['webhookSecret'] : '';
		if ( ! str_starts_with( $token, 'rms_wp_' ) || '' === $secret ) {
			return array(
				'ok'      => false,
				'status'  => 502,
				'error'   => 'invalid_response',
				'message' => __( 'RankMySEO approved the connection but did not finish it. Start again.', 'rankmyseo' ),
			);
		}

		try {
			$this->vault->save_secrets(
				array(
					'token'          => $token,
					'webhook_secret' => $secret,
					'poll_token'     => '',
				)
			);
		} catch ( \RuntimeException $exception ) {
			return array(
				'ok'      => false,
				'status'  => 500,
				'error'   => 'vault',
				'message' => $exception->getMessage(),
			);
		}

		$this->vault->update_public(
			array(
				'connected'          => true,
				'reconnect_required' => false,
				'health'             => 'connected',
				'connection_id'      => isset( $data['connectionId'] ) ? (string) $data['connectionId'] : '',
				'project_id'         => isset( $data['projectId'] ) ? (string) $data['projectId'] : '',
				'site_url'           => isset( $data['siteUrl'] ) ? (string) $data['siteUrl'] : '',
				'token_prefix'       => $this->vault->prefix_from_token( $token ),
				'pairing_code'       => '',
				'pairing_url'        => '',
				'pairing_expires_at' => '',
				'last_error'         => '',
			)
		);

		$view = $this->client->get_connection();
		if ( $view->ok && isset( $view->data['connection'] ) && is_array( $view->data['connection'] ) ) {
			$this->apply_connection_view( $view->data['connection'] );
		}

		$this->settings->set_module_enabled( 'cloud', true );
		if ( null !== $this->modules ) {
			$this->modules->boot_one( 'cloud' );
		}
		$this->worker->ensure_schedule();

		return array(
			'ok'          => true,
			'status'      => 200,
			'pair_status' => 'approved',
			'state'       => $this->vault->public_state(),
		);
	}

	/**
	 * Copy non-secret fields from GET /wp/v1/connection.
	 *
	 * @param array<string, mixed> $connection Connection view.
	 * @return void
	 */
	private function apply_connection_view( array $connection ): void {
		$plan   = isset( $connection['plan'] ) && is_array( $connection['plan'] ) ? $connection['plan'] : array();
		$prefix = isset( $connection['prefix'] ) ? (string) $connection['prefix'] : '';
		$this->vault->update_public(
			array(
				'connection_id' => $connection['id'] ?? '',
				'project_id'    => $connection['projectId'] ?? '',
				'site_url'      => $connection['siteUrl'] ?? '',
				'site_host'     => $connection['siteHost'] ?? '',
				'site_name'     => $connection['siteName'] ?? '',
				'domain'        => $connection['domain'] ?? '',
				'plan_id'       => $plan['id'] ?? '',
				'plan_label'    => $plan['label'] ?? '',
				'token_prefix'  => $prefix,
				'health'        => 'connected',
			)
		);
	}

	/**
	 * Drop an unfinished pairing session.
	 *
	 * @return void
	 */
	private function clear_pairing(): void {
		try {
			$this->vault->save_secrets( array( 'poll_token' => '' ) );
		} catch ( \RuntimeException $exception ) {
			unset( $exception );
		}
		$this->vault->update_public(
			array(
				'pairing_code'       => '',
				'pairing_url'        => '',
				'pairing_expires_at' => '',
			)
		);
	}

	/**
	 * Map a failed HTTP result to a REST-safe error. Never includes secrets.
	 *
	 * @param CloudResult $result HTTP result.
	 * @return array<string, mixed>
	 */
	private function failure( CloudResult $result ): array {
		$status = $result->status;
		if ( $status < 400 || $status > 599 ) {
			$status = 502;
		}
		if ( 401 === $status || 403 === $status ) {
			$status = 409;
		}
		$error = 'plan_limit' === $result->error ? 'rankmyseo_cloud_plan_limit' : 'rankmyseo_cloud_request';
		if ( 429 === $result->status ) {
			$error  = 'rankmyseo_cloud_rate_limited';
			$status = 429;
		}
		return array(
			'ok'          => false,
			'status'      => $status,
			'error'       => $error,
			'message'     => '' !== $result->message ? $result->message : __( 'RankMySEO could not complete that request.', 'rankmyseo' ),
			'retry_after' => $result->retry_after,
			'state'       => $this->vault->public_state(),
		);
	}

	/**
	 * Keep a version string inside the cloud contract.
	 *
	 * @param string $value Raw version.
	 * @return string
	 */
	private function version_string( string $value ): string {
		$clean = preg_replace( '/[^\w.\-]/', '', $value );
		if ( ! is_string( $clean ) || '' === $clean ) {
			return '0';
		}
		if ( strlen( $clean ) > 40 ) {
			$clean = substr( $clean, 0, 40 );
		}
		return $clean;
	}
}
