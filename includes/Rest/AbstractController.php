<?php
/**
 * Base REST API controller for RankMySEO endpoints.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Core\Capabilities;
use WP_Error;
use WP_REST_Response;

/**
 * Abstract base controller under the rankmyseo/v1 namespace.
 */
abstract class AbstractController {
	/**
	 * REST API namespace.
	 */
	public const REST_NAMESPACE = 'rankmyseo/v1';

	/**
	 * Register REST routes for this controller.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Permission callback requiring rankmyseo_manage capability.
	 *
	 * @return bool|WP_Error
	 */
	public function check_manage_permission() {
		if ( Capabilities::current_user_can_manage() ) {
			return true;
		}

		return new WP_Error(
			'rankmyseo_forbidden',
			__( 'You do not have permission to manage RankMySEO settings.', 'rankmyseo' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Permission callback requiring edit_posts capability (for per-user UI preferences).
	 *
	 * @return bool|WP_Error
	 */
	public function check_edit_posts_permission() {
		if ( current_user_can( 'edit_posts' ) || Capabilities::current_user_can_manage() ) {
			return true;
		}

		return new WP_Error(
			'rankmyseo_forbidden',
			__( 'You do not have permission to update RankMySEO user preferences.', 'rankmyseo' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Permission callback requiring rankmyseo_approve_ai capability.
	 *
	 * @return bool|WP_Error
	 */
	public function check_approve_ai_permission() {
		if ( Capabilities::current_user_can_approve_ai() ) {
			return true;
		}

		return new WP_Error(
			'rankmyseo_forbidden',
			__( 'You do not have permission to review or approve AI actions.', 'rankmyseo' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Build a standard JSON success response.
	 *
	 * @param array<string, mixed> $data   Response payload.
	 * @param int                  $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success_response( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Build a standard WP_Error response.
	 *
	 * @param string               $code    Machine-readable error code.
	 * @param string               $message Human-readable error message.
	 * @param int                  $status  HTTP status code.
	 * @param array<string, mixed> $extra   Optional extra data fields.
	 * @return WP_Error
	 */
	protected function error_response( string $code, string $message, int $status = 400, array $extra = [] ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array_merge( [ 'status' => $status ], $extra )
		);
	}
}
