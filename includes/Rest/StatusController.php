<?php
/**
 * REST controller for plugin health and environment status.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Core\Capabilities;
use RankMySEO\Core\Settings;
use RankMySEO\Database\Migrator;
use RankMySEO\Modules\Builders\BuilderDetector;
use RankMySEO\Modules\Cloud\TokenVault;
use RankMySEO\Modules\ModuleManager;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes GET /rankmyseo/v1/status.
 */
final class StatusController extends AbstractController {
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
	 * Database migrator.
	 *
	 * @var Migrator
	 */
	private Migrator $migrator;

	/**
	 * Page builder detector.
	 *
	 * @var BuilderDetector
	 */
	private BuilderDetector $builders;

	/**
	 * Constructor.
	 *
	 * @param Settings             $settings Settings repository.
	 * @param ModuleManager        $modules  Module manager.
	 * @param Migrator             $migrator Database migrator.
	 * @param BuilderDetector|null $builders Optional builder detector.
	 */
	public function __construct( Settings $settings, ModuleManager $modules, Migrator $migrator, ?BuilderDetector $builders = null ) {
		$this->settings = $settings;
		$this->modules  = $modules;
		$this->migrator = $migrator;
		$this->builders = $builders ?? new BuilderDetector();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/status',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_status' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);
	}

	/**
	 * Handle GET /rankmyseo/v1/status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status(): WP_REST_Response {
		global $wp_version;

		$catalog        = $this->modules->get_catalog();
		$enabled_count  = 0;
		$total_modules  = count( $catalog );
		$tables         = $this->migrator->verify_tables();
		$tables_healthy = true;

		foreach ( $catalog as $module ) {
			if ( ! empty( $module['enabled'] ) ) {
				++$enabled_count;
			}
		}

		foreach ( $tables as $table ) {
			if ( empty( $table['exists'] ) ) {
				$tables_healthy = false;
				break;
			}
		}

		$blog_public     = '0' !== (string) get_option( 'blog_public', '1' );
		$cloud_connected = ( new TokenVault() )->is_connected();

		return $this->success_response(
			[
				'plugin_version' => defined( 'RANKMYSEO_VERSION' ) ? RANKMYSEO_VERSION : '1.0.0',
				'php_version'    => PHP_VERSION,
				'wp_version'     => is_string( $wp_version ) ? $wp_version : '',
				'site'           => [
					'name'        => (string) get_bloginfo( 'name' ),
					'url'         => (string) home_url( '/' ),
					'blog_public' => $blog_public,
				],
				'database'       => [
					'installed_version' => $this->migrator->get_installed_version(),
					'target_version'    => $this->migrator->get_target_version(),
					'needs_migration'   => $this->migrator->needs_migration(),
					'tables_healthy'    => $tables_healthy,
					'tables'            => $tables,
				],
				'modules'        => [
					'enabled_count' => $enabled_count,
					'total_count'   => $total_modules,
					'items'         => $catalog,
				],
				'cloud'          => [
					'connected' => $cloud_connected,
					'tier'      => $cloud_connected ? 'cloud' : 'local',
				],
				'onboarding'     => [
					'wizard_completed' => (bool) $this->settings->get( 'wizard_completed', false ),
					'wizard_dismissed' => (bool) $this->settings->get( 'wizard_dismissed', false ),
				],
				'capabilities'   => [
					'can_manage'     => Capabilities::current_user_can_manage(),
					'can_approve_ai' => Capabilities::current_user_can_approve_ai(),
				],
				'builders'       => $this->builders->status(),
			]
		);
	}
}
