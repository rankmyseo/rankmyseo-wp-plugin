<?php
/**
 * REST API for the migration importer.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\Importer\ImportRequest;
use RankMySEO\Modules\Importer\ImportService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Detect, preview, import, roll back, and list SEO migrations.
 */
final class ImporterController extends AbstractController {
	/**
	 * Import service.
	 *
	 * @var ImportService
	 */
	private ImportService $service;

	/**
	 * Constructor.
	 *
	 * @param ImportService $service Import service.
	 */
	public function __construct( ImportService $service ) {
		$this->service = $service;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/importer/detect',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'detect' ],
				'permission_callback' => [ $this, 'check_manage_permission' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/importer/preview',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'check_manage_permission' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/importer/run',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run' ],
				'permission_callback' => [ $this, 'check_manage_permission' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/importer/rollback',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rollback' ],
				'permission_callback' => [ $this, 'check_manage_permission' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/importer/history',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'history' ],
				'permission_callback' => [ $this, 'check_manage_permission' ],
			]
		);
	}

	/**
	 * Handle GET /rankmyseo/v1/importer/detect.
	 *
	 * @return WP_REST_Response
	 */
	public function detect(): WP_REST_Response {
		return $this->success_response( $this->service->detect_all() );
	}

	/**
	 * Handle POST /rankmyseo/v1/importer/preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview( WP_REST_Request $request ) {
		return $this->respond( $this->service->preview( $this->request_from( $this->params( $request ) ) ) );
	}

	/**
	 * Handle POST /rankmyseo/v1/importer/run.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run( WP_REST_Request $request ) {
		return $this->respond( $this->service->run( $this->request_from( $this->params( $request ) ) ) );
	}

	/**
	 * Handle POST /rankmyseo/v1/importer/rollback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rollback( WP_REST_Request $request ) {
		$params = $this->params( $request );
		return $this->respond( $this->service->rollback( sanitize_text_field( (string) ( $params['import_id'] ?? '' ) ) ) );
	}

	/**
	 * Handle GET /rankmyseo/v1/importer/history.
	 *
	 * @return WP_REST_Response
	 */
	public function history(): WP_REST_Response {
		return $this->success_response( $this->service->history() );
	}

	/**
	 * Merge JSON and form params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function params( WP_REST_Request $request ): array {
		$params = [];
		if ( method_exists( $request, 'get_body_params' ) ) {
			$body = $request->get_body_params();
			if ( is_array( $body ) ) {
				$params = $body;
			}
		}
		if ( method_exists( $request, 'get_json_params' ) ) {
			$json = $request->get_json_params();
			if ( is_array( $json ) ) {
				$params = array_merge( $params, $json );
			}
		}
		return $params;
	}

	/**
	 * Build an import request from REST params.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @return ImportRequest
	 */
	private function request_from( array $params ): ImportRequest {
		$request                    = new ImportRequest();
		$request->source            = sanitize_key( (string) ( $params['source'] ?? '' ) );
		$request->cursor            = $this->cursor( (string) ( $params['cursor'] ?? '' ) );
		$request->import_id         = strtolower( sanitize_text_field( (string) ( $params['import_id'] ?? '' ) ) );
		$request->batch_size        = (int) ( $params['batch_size'] ?? 100 );
		$request->overwrite         = $this->flag( $params, 'overwrite', false );
		$request->include_posts     = $this->flag( $params, 'include_posts', true );
		$request->include_terms     = $this->flag( $params, 'include_terms', true );
		$request->include_redirects = $this->flag( $params, 'include_redirects', true );
		$request->include_settings  = $this->flag( $params, 'include_settings', true );
		return $request;
	}

	/**
	 * Keep only a phase cursor.
	 *
	 * @param string $cursor Raw cursor.
	 * @return string
	 */
	private function cursor( string $cursor ): string {
		$cursor = sanitize_text_field( $cursor );
		if ( 1 === preg_match( '/^(settings|posts|terms|redirects):\d+$/', $cursor ) ) {
			return $cursor;
		}
		return '';
	}

	/**
	 * Read a boolean flag.
	 *
	 * @param array<string, mixed> $params  Params.
	 * @param string               $key     Param name.
	 * @param bool                 $fallback Value when the param is absent.
	 * @return bool
	 */
	private function flag( array $params, string $key, bool $fallback ): bool {
		if ( ! array_key_exists( $key, $params ) ) {
			return $fallback;
		}

		$value = $params[ $key ];
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 1 === (int) $value;
		}

		$text = strtolower( trim( (string) $value ) );
		if ( in_array( $text, [ '1', 'true', 'yes', 'on' ], true ) ) {
			return true;
		}
		if ( in_array( $text, [ '0', 'false', 'no', 'off' ], true ) ) {
			return false;
		}
		return $fallback;
	}

	/**
	 * Turn a service payload into a REST response.
	 *
	 * @param array<string, mixed> $payload Service payload.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond( array $payload ) {
		if ( isset( $payload['error'] ) && is_array( $payload['error'] ) ) {
			return $this->error_response(
				(string) ( $payload['error']['code'] ?? 'rankmyseo_import_error' ),
				(string) ( $payload['error']['message'] ?? __( 'The import could not continue.', 'rankmyseo' ) ),
				(int) ( $payload['error']['status'] ?? 400 )
			);
		}

		return $this->success_response( $payload );
	}
}
