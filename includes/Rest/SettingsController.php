<?php
/**
 * REST controller for plugin settings and module toggles.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Core\Settings;
use RankMySEO\Modules\ModuleManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes GET and POST /rankmyseo/v1/settings.
 */
final class SettingsController extends AbstractController {
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
	 * @param Settings      $settings Settings repository.
	 * @param ModuleManager $modules  Module manager.
	 */
	public function __construct( Settings $settings, ModuleManager $modules ) {
		$this->settings = $settings;
		$this->modules  = $modules;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
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
					'args'                => $this->get_update_args(),
				],
			]
		);
	}

	/**
	 * Handle GET /rankmyseo/v1/settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return $this->success_response(
			[
				'settings' => $this->settings->all(),
				'modules'  => $this->modules->get_catalog(),
				'defaults' => $this->settings->get_defaults(),
			]
		);
	}

	/**
	 * Handle POST /rankmyseo/v1/settings.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) || empty( $payload ) ) {
			$payload = $request->get_body_params();
		}

		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		$updated = $this->settings->update( $payload );

		return $this->success_response(
			[
				'updated'  => true,
				'settings' => $updated,
				'modules'  => $this->modules->get_catalog(),
			]
		);
	}

	/**
	 * Define REST argument schema for settings updates.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_update_args(): array {
		return [
			'default_ui_mode'        => [
				'type' => 'string',
				'enum' => Settings::ALLOWED_UI_MODES,
			],
			'site_type'              => [
				'type' => 'string',
				'enum' => Settings::ALLOWED_SITE_TYPES,
			],
			'site_name'              => [
				'type' => 'string',
			],
			'title_separator'        => [
				'type' => 'string',
				'enum' => Settings::ALLOWED_SEPARATORS,
			],
			'keep_data_on_uninstall' => [
				'type' => 'boolean',
			],
			'telemetry_opt_in'       => [
				'type' => 'boolean',
			],
			'wizard_completed'       => [
				'type' => 'boolean',
			],
			'wizard_dismissed'       => [
				'type' => 'boolean',
			],
			'modules'                => [
				'type' => 'object',
			],
		];
	}
}
