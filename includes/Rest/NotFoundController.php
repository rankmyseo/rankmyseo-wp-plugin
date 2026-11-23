<?php
/**
 * REST API for the 404 monitor.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\Redirects\Monitor404;
use RankMySEO\Modules\Redirects\RedirectException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * List, ignore, delete, and convert 404 rows under rankmyseo/v1/404-logs.
 */
final class NotFoundController extends AbstractController {
	use RestNonce;

	/**
	 * 404 monitor.
	 *
	 * @var Monitor404
	 */
	private Monitor404 $monitor;

	/**
	 * Constructor.
	 *
	 * @param Monitor404 $monitor 404 monitor.
	 */
	public function __construct( Monitor404 $monitor ) {
		$this->monitor = $monitor;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/404-logs',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_logs' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/404-logs/bulk',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'bulk_logs' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/404-logs/(?P<id>\d+)',
			[
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_log' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/404-logs/(?P<id>\d+)/ignore',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'ignore_log' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/404-logs/(?P<id>\d+)/redirect',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'redirect_log' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);
	}

	/**
	 * List 404 log rows.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_logs( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$result = $this->monitor->search(
			[
				'status'   => (string) $request->get_param( 'status' ),
				'search'   => (string) $request->get_param( 'search' ),
				'page'     => $page,
				'per_page' => $per_page,
				'orderby'  => (string) $request->get_param( 'orderby' ),
				'order'    => (string) $request->get_param( 'order' ),
			]
		);

		return $this->success_response(
			[
				'items'    => $result['items'],
				'total'    => $result['total'],
				'page'     => $page,
				'per_page' => min( 100, $per_page ),
			]
		);
	}

	/**
	 * Ignore one 404 row.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function ignore_log( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$ignored = $this->monitor->ignore( (int) $request->get_param( 'id' ) );
		if ( ! $ignored ) {
			return $this->error_response(
				'not_found_missing',
				__( 'That broken page is no longer in the log.', 'rankmyseo' ),
				404
			);
		}

		return $this->success_response( [ 'ignored' => true ] );
	}

	/**
	 * Delete one 404 row.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_log( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$deleted = $this->monitor->delete( (int) $request->get_param( 'id' ) );
		if ( ! $deleted ) {
			return $this->error_response(
				'not_found_missing',
				__( 'That broken page is no longer in the log.', 'rankmyseo' ),
				404
			);
		}

		return $this->success_response( [ 'deleted' => true ] );
	}

	/**
	 * Create a redirect from a 404 row.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function redirect_log( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$body   = $this->json_body( $request );
		$target = trim( (string) ( $body['target_url'] ?? $body['target'] ?? '' ) );
		$status = (int) ( $body['status_code'] ?? 301 );
		if ( $status < 1 ) {
			$status = 301;
		}

		try {
			$result = $this->monitor->convert_to_redirect( (int) $request->get_param( 'id' ), $target, $status );
		} catch ( RedirectException $exception ) {
			$code = $exception->error_code();
			$http = 'not_found_missing' === $code ? 404 : 400;
			return $this->error_response( $code, $exception->getMessage(), $http );
		}

		return $this->success_response( $result, 201 );
	}

	/**
	 * Bulk ignore or delete 404 rows.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_logs( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$body = $this->json_body( $request );
		try {
			$count = $this->monitor->bulk(
				sanitize_key( (string) ( $body['action'] ?? '' ) ),
				$this->id_list( $body )
			);
		} catch ( RedirectException $exception ) {
			return $this->error_response( $exception->error_code(), $exception->getMessage(), 400 );
		}

		return $this->success_response( [ 'updated' => $count ] );
	}

	/**
	 * Read a JSON or form body.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return array<string, mixed>
	 */
	private function json_body( WP_REST_Request $request ): array {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) || [] === $payload ) {
			$payload = $request->get_body_params();
		}

		return is_array( $payload ) ? $payload : [];
	}

	/**
	 * Collect positive IDs from a bulk payload.
	 *
	 * @param array<string, mixed> $body Request body.
	 * @return list<int>
	 */
	private function id_list( array $body ): array {
		$ids = $body['ids'] ?? [];
		if ( ! is_array( $ids ) ) {
			return [];
		}

		$clean = [];
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}

		return $clean;
	}
}
