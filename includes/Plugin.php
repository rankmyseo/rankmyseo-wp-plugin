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
use RankMySEO\Modules\ModuleManager;
use RankMySEO\Modules\Stubs\AiQueueModule;
use RankMySEO\Modules\Stubs\AnalyzerModule;
use RankMySEO\Modules\Stubs\CloudModule;
use RankMySEO\Modules\Meta\ConflictGuard;
use RankMySEO\Modules\Meta\DocumentBuilder;
use RankMySEO\Modules\Meta\MetaModule;
use RankMySEO\Modules\Meta\MetaStore;
use RankMySEO\Modules\Meta\TitleSettings;
use RankMySEO\Modules\Stubs\ImporterModule;
use RankMySEO\Modules\Redirects\RedirectsModule;
use RankMySEO\Modules\Schema\SchemaModule;
use RankMySEO\Modules\Sitemaps\IndexNow;
use RankMySEO\Modules\Sitemaps\RobotsTxt;
use RankMySEO\Modules\Sitemaps\SitemapSettings;
use RankMySEO\Modules\Sitemaps\SitemapsModule;
use RankMySEO\Modules\Sitemaps\WordPressSitemapSource;
use RankMySEO\Modules\Sitemaps\WpIndexNowTransport;
use RankMySEO\Rest\MetaController;
use RankMySEO\Rest\RestRegistry;
use RankMySEO\Rest\SitemapsController;
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

		$this->container->singleton(
			SitemapSettings::class,
			static function (): SitemapSettings {
				return new SitemapSettings();
			}
		);

		$this->container->singleton(
			IndexNow::class,
			static function ( Container $container ): IndexNow {
				$sitemap_settings = $container->get( SitemapSettings::class );
				assert( $sitemap_settings instanceof SitemapSettings );
				return new IndexNow( $sitemap_settings, new WpIndexNowTransport() );
			}
		);

		$this->container->singleton(
			RobotsTxt::class,
			static function ( Container $container ): RobotsTxt {
				$sitemap_settings = $container->get( SitemapSettings::class );
				assert( $sitemap_settings instanceof SitemapSettings );
				return new RobotsTxt( $sitemap_settings );
			}
		);

		$this->container->singleton(
			WordPressSitemapSource::class,
			static function ( Container $container ): WordPressSitemapSource {
				$sitemap_settings = $container->get( SitemapSettings::class );
				assert( $sitemap_settings instanceof SitemapSettings );
				return new WordPressSitemapSource( $sitemap_settings );
			}
		);

		$this->container->singleton(
			SitemapsController::class,
			static function ( Container $container ): SitemapsController {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				$sitemap_settings = $container->get( SitemapSettings::class );
				assert( $sitemap_settings instanceof SitemapSettings );
				$indexnow = $container->get( IndexNow::class );
				assert( $indexnow instanceof IndexNow );
				$robots = $container->get( RobotsTxt::class );
				assert( $robots instanceof RobotsTxt );
				$source = $container->get( WordPressSitemapSource::class );
				assert( $source instanceof WordPressSitemapSource );
				return new SitemapsController( $settings, $sitemap_settings, $indexnow, $robots, $source );
			}
		);

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
			TitleSettings::class,
			static function ( Container $container ): TitleSettings {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				return new TitleSettings( $settings );
			}
		);

		$this->container->singleton(
			MetaController::class,
			static function ( Container $container ): MetaController {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				$titles = $container->get( TitleSettings::class );
				assert( $titles instanceof TitleSettings );

				return new MetaController(
					$settings,
					$titles,
					new MetaStore(),
					new ConflictGuard( $titles ),
					new DocumentBuilder()
				);
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
				$sitemaps_controller = $container->get( SitemapsController::class );
				assert( $sitemaps_controller instanceof SitemapsController );
				$meta_controller = $container->get( MetaController::class );
				assert( $meta_controller instanceof MetaController );

				return new RestRegistry(
					[
						$settings_controller,
						$status_controller,
						$ui_preferences_controller,
						$sitemaps_controller,
						$meta_controller,
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
			if ( $module instanceof SchemaModule ) {
				$module->register_integrations();
			}
		}
	}
}
