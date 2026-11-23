<?php
/**
 * REST controller for RankMySEO Cloud connection, sync, and insights.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\Cloud\InsightsCache;
use RankMySEO\Modules\Cloud\PairingService;
use RankMySEO\Modules\Cloud\PostPayloadBuilder;
use RankMySEO\Modules\Cloud\SyncWorker;
use RankMySEO\Modules\Cloud\TokenVault;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Admin endpoints under rankmyseo/v1/cloud. Responses never include credentials.
 */
final class CloudController extends AbstractController {
	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * Pairing flow.
	 *
	 * @var PairingService
	 */
	private PairingService $pairing;

	/**
	 * Sync worker.
	 *
	 * @var SyncWorker
	 */
	private SyncWorker $worker;

	/**
	 * Insights cache.
	 *
	 * @var InsightsCache
	 */
	private InsightsCache $insights;

	/**
	 * Constructor.
	 *
	 * @param TokenVault     $vault    Credential vault.
	 * @param PairingService $pairing  Pairing flow.
	 * @param SyncWorker     $worker   Sync worker.
	 * @param InsightsCache  $insights Insights cache.
	 */
	public function __construct(
		TokenVault $vault,
		PairingService $pairing,
		SyncWorker $worker,
		InsightsCache $insights
	) {
		$this->vault    = $vault;
		$this->pairing  = $pairing;
		$this->worker   = $worker;
		$this->insights = $insights;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$permission = array( $this, 'check_manage_permission' );

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/pair/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_pair' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/pair/poll',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'poll_pair' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/rotate-token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rotate_token' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_now' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/insights',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_insights' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Local connection status. Does not call RankMySEO.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status(): WP_REST_Response {
		return $this->success_response( $this->vault->public_state() );
	}

	/**
	 * Start one-click pairing.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_pair() {
		return $this->from_service( $this->pairing->start() );
	}

	/**
	 * Poll the pairing session stored on this site.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function poll_pair() {
		return $this->from_service( $this->pairing->poll() );
	}

	/**
	 * Disconnect this site and wipe local credentials.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function disconnect() {
		return $this->from_service( $this->pairing->disconnect() );
	}

	/**
	 * Rotate the site token.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rotate_token() {
		return $this->from_service( $this->pairing->rotate() );
	}

	/**
	 * Queue a fresh inventory snapshot and send due rows.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync_now() {
		$result = $this->worker->sync_now( PostPayloadBuilder::collect( 100 ) );
		if ( empty( $result['ok'] ) ) {
			return $this->error_response(
				'rankmyseo_cloud_sync',
				(string) ( $result['message'] ?? __( 'Connect RankMySEO before syncing this site.', 'rankmyseo' ) ),
				409,
				array( 'state' => $result['state'] ?? array() )
			);
		}
		return $this->success_response(
			array(
				'processed' => (int) ( $result['processed'] ?? 0 ),
				'message'   => (string) ( $result['message'] ?? '' ),
				'state'     => $result['state'] ?? array(),
			)
		);
	}

	/**
	 * Return cached insights. Pass refresh=1 to bypass the 15 minute cache.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_insights( WP_REST_Request $request ): WP_REST_Response {
		$refresh = $request->get_param( 'refresh' );
		$force   = true === $refresh || 1 === $refresh || '1' === $refresh || 'true' === $refresh;
		return $this->success_response( $this->insights->get( $force ) );
	}

	/**
	 * Map a pairing result to a REST response. Upstream 401 and 403 become 409.
	 *
	 * @param array<string, mixed> $result Service result.
	 * @return WP_REST_Response|WP_Error
	 */
	private function from_service( array $result ) {
		$state = isset( $result['state'] ) && is_array( $result['state'] ) ? $result['state'] : $this->vault->public_state();
		if ( ! empty( $result['ok'] ) ) {
			$payload = array( 'state' => $state );
			if ( isset( $result['pair_status'] ) ) {
				$payload['pair_status'] = (string) $result['pair_status'];
			}
			return $this->success_response( $payload );
		}

		$status = (int) ( $result['status'] ?? 502 );
		if ( 401 === $status || 403 === $status ) {
			$status = 409;
		}
		$extra = array( 'state' => $state );
		if ( ! empty( $result['retry_after'] ) ) {
			$extra['retry_after'] = (int) $result['retry_after'];
		}
		return $this->error_response(
			(string) ( $result['error'] ?? 'rankmyseo_cloud_request' ),
			(string) ( $result['message'] ?? __( 'RankMySEO could not complete that request.', 'rankmyseo' ) ),
			$status,
			$extra
		);
	}
}
