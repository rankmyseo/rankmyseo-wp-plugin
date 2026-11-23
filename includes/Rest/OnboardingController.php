<?php
/**
 * REST controller for guided setup state and completion.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Core\OnboardingStore;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\ModuleManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes onboarding state and completion under rankmyseo/v1.
 */
final class OnboardingController extends AbstractController {
	/**
	 * Onboarding store.
	 *
	 * @var OnboardingStore
	 */
	private OnboardingStore $onboarding;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $modules;

	/**
	 * Constructor.
	 *
	 * @param OnboardingStore $onboarding Onboarding store.
	 * @param Settings        $settings   Settings repository.
	 * @param ModuleManager   $modules    Module manager.
	 */
	public function __construct( OnboardingStore $onboarding, Settings $settings, ModuleManager $modules ) {
		$this->onboarding = $onboarding;
		$this->settings   = $settings;
		$this->modules    = $modules;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/onboarding/state',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_state' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/onboarding/complete',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'complete' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);
	}

	/**
	 * Handle GET /rankmyseo/v1/onboarding/state.
	 *
	 * @return WP_REST_Response
	 */
	public function get_state(): WP_REST_Response {
		return $this->success_response( $this->onboarding->get_state() );
	}

	/**
	 * Handle POST /rankmyseo/v1/onboarding/complete.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function complete( WP_REST_Request $request ): WP_REST_Response {
		$payload = $this->payload( $request );
		$state   = $this->onboarding->complete( $payload, get_current_user_id() );

		return $this->success_response(
			array_merge(
				$state,
				[
					'onboarding_completed' => true,
					'settings'             => $this->settings->all(),
					'module_catalog'       => $this->modules->get_catalog(),
				]
			)
		);
	}

	/**
	 * Read a JSON or form body.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return array<string, mixed>
	 */
	private function payload( WP_REST_Request $request ): array {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) || empty( $payload ) ) {
			$payload = $request->get_body_params();
		}

		return is_array( $payload ) ? $payload : [];
	}
}
