<?php
/**
 * REST API for redirect rules.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\Redirects\RedirectException;
use RankMySEO\Modules\Redirects\RedirectService;
use RankMySEO\Modules\Redirects\RedirectSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * CRUD, URL tester, and CSV import/export for rankmyseo/v1/redirects.
 */
final class RedirectsController extends AbstractController {
	use RestNonce;

	/**
	 * Redirect service.
	 *
	 * @var RedirectService
	 */
	private RedirectService $redirects;

	/**
	 * Redirect settings.
	 *
	 * @var RedirectSettings
	 */
	private RedirectSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param RedirectService  $redirects Redirect service.
	 * @param RedirectSettings $settings  Redirect settings.
	 */
	public function __construct( RedirectService $redirects, RedirectSettings $settings ) {
		$this->redirects = $redirects;
		$this->settings  = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/redirects',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_redirects' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_redirect' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/redirects/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/redirects/test-url',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'test_url' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/redirects/export',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'export_redirects' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/redirects/import',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'import_redirects' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/redirects/bulk',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'bulk_redirects' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/redirects/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_redirect' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_redirect' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/redirects/(?P<id>\d+)/flatten',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'flatten_redirect' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);
	}

	/**
	 * List redirect rules.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_redirects( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$result = $this->redirects->search(
			[
				'search'      => (string) $request->get_param( 'search' ),
				'status_code' => (int) $request->get_param( 'status_code' ),
				'match_type'  => (string) $request->get_param( 'match_type' ),
				'origin'      => (string) $request->get_param( 'origin' ),
				'page'        => $page,
				'per_page'    => $per_page,
				'orderby'     => (string) $request->get_param( 'orderby' ),
				'order'       => (string) $request->get_param( 'order' ),
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
	 * Create a redirect rule.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_redirect( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		try {
			$result = $this->redirects->create( $this->json_body( $request ) );
		} catch ( RedirectException $exception ) {
			return $this->from_exception( $exception );
		}

		return $this->success_response( $result, 201 );
	}

	/**
	 * Update a redirect rule.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_redirect( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		try {
			$result = $this->redirects->update( (int) $request->get_param( 'id' ), $this->json_body( $request ) );
		} catch ( RedirectException $exception ) {
			return $this->from_exception( $exception );
		}

		return $this->success_response( $result );
	}

	/**
	 * Delete a redirect rule.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_redirect( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$deleted = $this->redirects->delete( (int) $request->get_param( 'id' ) );
		if ( ! $deleted ) {
			return $this->error_response(
				'redirect_not_found',
				__( 'That redirect no longer exists.', 'rankmyseo' ),
				404
			);
		}

		return $this->success_response( [ 'deleted' => true ] );
	}

	/**
	 * Flatten a redirect chain.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function flatten_redirect( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		try {
			$result = $this->redirects->flatten( (int) $request->get_param( 'id' ) );
		} catch ( RedirectException $exception ) {
			return $this->from_exception( $exception );
		}

		return $this->success_response( $result );
	}

	/**
	 * Test which rule a URL would hit.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_url( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$body = $this->json_body( $request );
		$url  = trim( (string) ( $body['url'] ?? '' ) );
		if ( '' === $url ) {
			return $this->error_response(
				'redirect_test',
				__( 'Enter a URL to test.', 'rankmyseo' ),
				400
			);
		}

		return $this->success_response( $this->redirects->test_url( $url ) );
	}

	/**
	 * Export redirects as CSV, Apache, or Nginx rules.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_redirects( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$format = sanitize_key( (string) $request->get_param( 'format' ) );
		if ( '' === $format ) {
			$format = 'csv';
		}

		try {
			$export = $this->redirects->export( $format );
		} catch ( RedirectException $exception ) {
			return $this->from_exception( $exception );
		}

		return $this->success_response( $export );
	}

	/**
	 * Import a CSV document.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_redirects( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$body = $this->json_body( $request );
		$csv  = (string) ( $body['csv'] ?? '' );
		if ( '' === trim( $csv ) ) {
			return $this->error_response(
				'redirect_import',
				__( 'Paste a CSV file to import.', 'rankmyseo' ),
				400
			);
		}

		return $this->success_response( $this->redirects->import_csv( $csv ) );
	}

	/**
	 * Bulk delete, activate, or deactivate rules.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_redirects( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		$body = $this->json_body( $request );
		try {
			$count = $this->redirects->bulk(
				sanitize_key( (string) ( $body['action'] ?? '' ) ),
				$this->id_list( $body )
			);
		} catch ( RedirectException $exception ) {
			return $this->from_exception( $exception );
		}

		return $this->success_response(
			[
				'updated' => $count,
			]
		);
	}

	/**
	 * Read redirect settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_settings( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		return $this->success_response(
			[
				'settings' => $this->settings->all(),
			]
		);
	}

	/**
	 * Update redirect settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$nonce = $this->invalid_nonce( $request );
		if ( null !== $nonce ) {
			return $nonce;
		}

		return $this->success_response(
			[
				'settings' => $this->settings->update( $this->json_body( $request ) ),
			]
		);
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

	/**
	 * Map a domain exception to an HTTP error.
	 *
	 * @param RedirectException $exception Validation error.
	 * @return WP_Error
	 */
	private function from_exception( RedirectException $exception ): WP_Error {
		$status = 400;
		if ( 'redirect_not_found' === $exception->error_code() ) {
			$status = 404;
		} elseif ( 'redirect_duplicate' === $exception->error_code() ) {
			$status = 409;
		}

		return $this->error_response( $exception->error_code(), $exception->getMessage(), $status );
	}
}
