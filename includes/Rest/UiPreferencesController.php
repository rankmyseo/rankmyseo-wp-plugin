<?php
/**
 * REST controller for per-user UI experience mode and onboarding preferences.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Core\Capabilities;
use RankMySEO\Core\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes GET and POST /rankmyseo/v1/ui-preferences.
 */
final class UiPreferencesController extends AbstractController {
	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/ui-preferences',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_preferences' ],
					'permission_callback' => [ $this, 'check_edit_posts_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_preferences' ],
					'permission_callback' => [ $this, 'check_edit_posts_permission' ],
					'args'                => [
						'ui_mode'          => [
							'type' => 'string',
							'enum' => Settings::ALLOWED_UI_MODES,
						],
						'wizard_dismissed' => [
							'type' => 'boolean',
						],
						'wizard_completed' => [
							'type' => 'boolean',
						],
					],
				],
			]
		);
	}

	/**
	 * Handle GET /rankmyseo/v1/ui-preferences.
	 *
	 * @return WP_REST_Response
	 */
	public function get_preferences(): WP_REST_Response {
		$user_id = get_current_user_id();

		return $this->success_response(
			[
				'ui_mode'          => $this->settings->get_user_ui_mode( $user_id ),
				'default_ui_mode'  => (string) $this->settings->get( 'default_ui_mode', 'simple' ),
				'wizard_completed' => (bool) $this->settings->get( 'wizard_completed', false ),
				'wizard_dismissed' => $this->settings->is_wizard_dismissed_for_user( $user_id ),
				'can_manage'       => Capabilities::current_user_can_manage(),
				'can_approve_ai'   => Capabilities::current_user_can_approve_ai(),
			]
		);
	}

	/**
	 * Handle POST /rankmyseo/v1/ui-preferences.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function update_preferences( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) || empty( $payload ) ) {
			$payload = $request->get_body_params();
		}

		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		if ( array_key_exists( 'ui_mode', $payload ) ) {
			$this->settings->set_user_ui_mode( $user_id, (string) $payload['ui_mode'] );
		}

		if ( array_key_exists( 'wizard_dismissed', $payload ) ) {
			$dismissed = (bool) $payload['wizard_dismissed'];
			$this->settings->set_user_wizard_dismissed( $user_id, $dismissed );

			if ( Capabilities::current_user_can_manage() ) {
				$this->settings->update( [ 'wizard_dismissed' => $dismissed ] );
			}
		}

		if ( array_key_exists( 'wizard_completed', $payload ) && Capabilities::current_user_can_manage() ) {
			$completed = (bool) $payload['wizard_completed'];
			$this->settings->update( [ 'wizard_completed' => $completed ] );
			if ( $completed ) {
				$this->settings->set_user_wizard_dismissed( $user_id, true );
			}
		}

		return $this->get_preferences();
	}
}
