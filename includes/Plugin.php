<?php
/**
 * Main plugin bootstrap singleton.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO;

use RankMySEO\Admin\AdminMenu;
use RankMySEO\Core\Settings;
use RankMySEO\Database\Migrator;
use RankMySEO\Modules\Cloud\CloudModule;
use RankMySEO\Modules\Cloud\CloudServices;
use RankMySEO\Modules\ModuleManager;
use RankMySEO\Modules\Stubs\AiQueueModule;
use RankMySEO\Modules\Stubs\AnalyzerModule;
use RankMySEO\Modules\Stubs\ImporterModule;
use RankMySEO\Modules\Stubs\MetaModule;
use RankMySEO\Modules\Stubs\RedirectsModule;
use RankMySEO\Modules\Stubs\SchemaModule;
use RankMySEO\Modules\Stubs\SitemapsModule;
use RankMySEO\Rest\CloudController;
use RankMySEO\Rest\RestRegistry;
use RankMySEO\Rest\SettingsController;
use RankMySEO\Rest\StatusController;
use RankMySEO\Rest\UiPreferencesController;

/**
 * Singleton entry point wiring the DI container, modules, REST API, and admin UI.
 */
final class Plugin {
	/**
	 * Singleton plugin instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether the plugin has already booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 *
	 * @param Container|null $container Optional custom container (useful in tests).
	 */
	private function __construct( ?Container $container = null ) {
		$this->container = $container ?? new Container();
		$this->register_services();
	}

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reset the singleton instance (intended for unit tests).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Boot the plugin once per request.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$migrator = $this->container->get( Migrator::class );
		assert( $migrator instanceof Migrator );
		if ( $migrator->needs_migration() ) {
			$migrator->migrate();
		}

		$this->register_default_modules();

		$module_manager = $this->container->get( ModuleManager::class );
		assert( $module_manager instanceof ModuleManager );
		$module_manager->boot();

		$rest_registry = $this->container->get( RestRegistry::class );
		assert( $rest_registry instanceof RestRegistry );
		$rest_registry->register();

		if ( is_admin() ) {
			$admin_menu = $this->container->get( AdminMenu::class );
			assert( $admin_menu instanceof AdminMenu );
			$admin_menu->register();
		}

		if ( get_option( 'rankmyseo_flush_rewrite_rules' ) ) {
			add_action(
				'init',
				static function (): void {
					flush_rewrite_rules( false );
					delete_option( 'rankmyseo_flush_rewrite_rules' );
				},
				99
			);
		}

		/**
		 * Fires after RankMySEO core services, modules, and REST routes have booted.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'rankmyseo_booted', $this );
	}

	/**
	 * Get the DI container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Get the settings manager.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		$settings = $this->container->get( Settings::class );
		assert( $settings instanceof Settings );
		return $settings;
	}

	/**
	 * Get the module manager.
	 *
	 * @return ModuleManager
	 */
	public function modules(): ModuleManager {
		$manager = $this->container->get( ModuleManager::class );
		assert( $manager instanceof ModuleManager );
		return $manager;
	}

	/**
	 * Register core services in the DI container.
	 *
	 * @return void
	 */
	private function register_services(): void {
		$this->container->singleton(
			Settings::class,
			static function (): Settings {
				return new Settings();
			}
		);

		$this->container->singleton(
			Migrator::class,
			static function (): Migrator {
				global $wpdb;
				return new Migrator( $wpdb );
			}
		);

		$this->container->singleton(
			ModuleManager::class,
			static function ( Container $container ): ModuleManager {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				return new ModuleManager( $settings );
			}
		);

		CloudServices::register( $this->container );

		$this->container->singleton(
			SettingsController::class,
			static function ( Container $container ): SettingsController {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				$modules = $container->get( ModuleManager::class );
				assert( $modules instanceof ModuleManager );
				return new SettingsController( $settings, $modules );
			}
		);

		$this->container->singleton(
			StatusController::class,
			static function ( Container $container ): StatusController {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				$modules = $container->get( ModuleManager::class );
				assert( $modules instanceof ModuleManager );
				$migrator = $container->get( Migrator::class );
				assert( $migrator instanceof Migrator );
				return new StatusController( $settings, $modules, $migrator );
			}
		);

		$this->container->singleton(
			UiPreferencesController::class,
			static function ( Container $container ): UiPreferencesController {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				return new UiPreferencesController( $settings );
			}
		);

		$this->container->singleton(
			CloudController::class,
			static function ( Container $container ): CloudController {
				$vault = $container->get( \RankMySEO\Modules\Cloud\TokenVault::class );
				assert( $vault instanceof \RankMySEO\Modules\Cloud\TokenVault );
				$pairing = $container->get( \RankMySEO\Modules\Cloud\PairingService::class );
				assert( $pairing instanceof \RankMySEO\Modules\Cloud\PairingService );
				$worker = $container->get( \RankMySEO\Modules\Cloud\SyncWorker::class );
				assert( $worker instanceof \RankMySEO\Modules\Cloud\SyncWorker );
				$insights = $container->get( \RankMySEO\Modules\Cloud\InsightsCache::class );
				assert( $insights instanceof \RankMySEO\Modules\Cloud\InsightsCache );
				return new CloudController( $vault, $pairing, $worker, $insights );
			}
		);

		$this->container->singleton(
			RestRegistry::class,
			static function ( Container $container ): RestRegistry {
				$settings_controller = $container->get( SettingsController::class );
				assert( $settings_controller instanceof SettingsController );
				$status_controller = $container->get( StatusController::class );
				assert( $status_controller instanceof StatusController );
				$ui_preferences_controller = $container->get( UiPreferencesController::class );
				assert( $ui_preferences_controller instanceof UiPreferencesController );
				$cloud_controller = $container->get( CloudController::class );
				assert( $cloud_controller instanceof CloudController );

				return new RestRegistry(
					[
						$settings_controller,
						$status_controller,
						$ui_preferences_controller,
						$cloud_controller,
					]
				);
			}
		);

		$this->container->singleton(
			AdminMenu::class,
			static function ( Container $container ): AdminMenu {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				return new AdminMenu( $settings );
			}
		);
	}

	/**
	 * Register built-in modules.
	 *
	 * Subsequent PRs replace stub implementations while preserving module IDs
	 * and lifecycle contracts.
	 *
	 * @return void
	 */
	private function register_default_modules(): void {
		$manager = $this->container->get( ModuleManager::class );
		assert( $manager instanceof ModuleManager );

		$modules = [
			new MetaModule( $this->container ),
			new AnalyzerModule( $this->container ),
			new SchemaModule( $this->container ),
			new SitemapsModule( $this->container ),
			new RedirectsModule( $this->container ),
			new ImporterModule( $this->container ),
			new CloudModule( $this->container ),
			new AiQueueModule( $this->container ),
		];

		/**
		 * Filter the list of registered RankMySEO modules before booting.
		 *
		 * @param array<int, \RankMySEO\Modules\ModuleInterface> $modules   Registered modules.
		 * @param Container                                      $container Service container.
		 */
		$modules = apply_filters( 'rankmyseo_modules', $modules, $this->container );

		foreach ( $modules as $module ) {
			$manager->add( $module );
		}
	}
}
