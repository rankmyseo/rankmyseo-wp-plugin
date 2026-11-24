<?php
/**
 * Main plugin bootstrap singleton.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO;

use RankMySEO\Admin\AdminMenu;
use RankMySEO\Core\OnboardingStore;
use RankMySEO\Core\Settings;
use RankMySEO\Database\Migrator;
use RankMySEO\Modules\AiActions\ActionExecutor;
use RankMySEO\Modules\AiActions\ActionRepository;
use RankMySEO\Modules\AiActions\AiServices;
use RankMySEO\Modules\AiActions\MissingMetadataQueue;
use RankMySEO\Modules\Analyzer\AnalyzerModule;
use RankMySEO\Modules\Chat\ChatModule;
use RankMySEO\Modules\Chat\LocalAssistant;
use RankMySEO\Modules\Chat\WordPressSiteIndex;
use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\CloudModule;
use RankMySEO\Modules\Cloud\EditorInsightReader;
use RankMySEO\Modules\Cloud\InsightsCache;
use RankMySEO\Modules\Cloud\TokenVault;
use RankMySEO\Modules\Cloud\CloudServices;
use RankMySEO\Modules\ModuleManager;
use RankMySEO\Modules\AiActions\AiQueueModule;
use RankMySEO\Modules\Importer\ImporterModule;
use RankMySEO\Modules\Links\LinkIndexer;
use RankMySEO\Modules\Links\LinkRepository;
use RankMySEO\Modules\Meta\ConflictGuard;
use RankMySEO\Modules\Meta\DocumentBuilder;
use RankMySEO\Modules\Meta\MetaModule;
use RankMySEO\Modules\Meta\MetaStore;
use RankMySEO\Modules\Meta\MissingMetadataQuery;
use RankMySEO\Modules\Meta\TemplateEngine;
use RankMySEO\Modules\Meta\TemplateFill;
use RankMySEO\Modules\Meta\TitleSettings;
use RankMySEO\Modules\Redirects\RedirectsModule;
use RankMySEO\Modules\Schema\SchemaModule;
use RankMySEO\Modules\Sitemaps\IndexNow;
use RankMySEO\Modules\Sitemaps\LlmsTxt;
use RankMySEO\Modules\Sitemaps\RobotsTxt;
use RankMySEO\Modules\Sitemaps\SitemapLlmsCatalog;
use RankMySEO\Modules\Sitemaps\SitemapSettings;
use RankMySEO\Modules\Sitemaps\SitemapsModule;
use RankMySEO\Modules\Sitemaps\WordPressSitemapSource;
use RankMySEO\Modules\Sitemaps\WpIndexNowTransport;
use RankMySEO\Rest\AiActionsController;
use RankMySEO\Rest\ChatController;
use RankMySEO\Rest\CloudController;
use RankMySEO\Rest\EditorInsightsController;
use RankMySEO\Rest\MetaController;
use RankMySEO\Rest\MissingMetadataController;
use RankMySEO\Rest\DashboardController;
use RankMySEO\Rest\OnboardingController;
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

		$chat_module = $this->container->get( ChatModule::class );
		assert( $chat_module instanceof ChatModule );
		$chat_module->register();

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
		AiServices::register( $this->container );

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
			LlmsTxt::class,
			static function ( Container $container ): LlmsTxt {
				$sitemap_settings = $container->get( SitemapSettings::class );
				assert( $sitemap_settings instanceof SitemapSettings );
				$source = $container->get( WordPressSitemapSource::class );
				assert( $source instanceof WordPressSitemapSource );
				return new LlmsTxt( $sitemap_settings, new SitemapLlmsCatalog( $source ) );
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
				$llms = $container->get( LlmsTxt::class );
				assert( $llms instanceof LlmsTxt );
				$source = $container->get( WordPressSitemapSource::class );
				assert( $source instanceof WordPressSitemapSource );
				return new SitemapsController( $settings, $sitemap_settings, $indexnow, $robots, $llms, $source );
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
			MissingMetadataController::class,
			static function ( Container $container ): MissingMetadataController {
				$titles = $container->get( TitleSettings::class );
				assert( $titles instanceof TitleSettings );
				$vault = $container->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				$client = $container->get( CloudClient::class );
				assert( $client instanceof CloudClient );
				$actions = $container->get( ActionRepository::class );
				assert( $actions instanceof ActionRepository );
				$executor = $container->get( ActionExecutor::class );
				assert( $executor instanceof ActionExecutor );

				$store = new MetaStore();
				$query = new MissingMetadataQuery( $store );
				$fill  = new TemplateFill( $titles, new TemplateEngine(), $query );
				$queue = new MissingMetadataQueue( $vault, $client, $actions, $executor, $query );

				return new MissingMetadataController( $query, $fill, $queue, $titles, $vault );
			}
		);

		$this->container->singleton(
			EditorInsightsController::class,
			static function ( Container $container ): EditorInsightsController {
				$insights = $container->get( InsightsCache::class );
				assert( $insights instanceof InsightsCache );
				$actions = $container->get( ActionRepository::class );
				assert( $actions instanceof ActionRepository );
				return new EditorInsightsController( new EditorInsightReader( $insights, $actions ) );
			}
		);

		$this->container->singleton(
			OnboardingStore::class,
			static function ( Container $container ): OnboardingStore {
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				return new OnboardingStore( $settings );
			}
		);

		$this->container->singleton(
			WordPressSiteIndex::class,
			static function (): WordPressSiteIndex {
				global $wpdb;
				return new WordPressSiteIndex( $wpdb );
			}
		);

		$this->container->singleton(
			LinkIndexer::class,
			static function (): LinkIndexer {
				global $wpdb;
				return new LinkIndexer( new LinkRepository( $wpdb ) );
			}
		);

		$this->container->singleton(
			LocalAssistant::class,
			static function ( Container $container ): LocalAssistant {
				$index = $container->get( WordPressSiteIndex::class );
				assert( $index instanceof WordPressSiteIndex );
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				$onboarding = $container->get( OnboardingStore::class );
				assert( $onboarding instanceof OnboardingStore );
				$migrator = $container->get( Migrator::class );
				assert( $migrator instanceof Migrator );
				$links = $container->get( LinkIndexer::class );
				assert( $links instanceof LinkIndexer );
				return new LocalAssistant( $index, $settings, $onboarding, $migrator, $links );
			}
		);

		$this->container->singleton(
			OnboardingController::class,
			static function ( Container $container ): OnboardingController {
				$onboarding = $container->get( OnboardingStore::class );
				assert( $onboarding instanceof OnboardingStore );
				$settings = $container->get( Settings::class );
				assert( $settings instanceof Settings );
				$modules = $container->get( ModuleManager::class );
				assert( $modules instanceof ModuleManager );
				return new OnboardingController( $onboarding, $settings, $modules );
			}
		);

		$this->container->singleton(
			DashboardController::class,
			static function ( Container $container ): DashboardController {
				$assistant = $container->get( LocalAssistant::class );
				assert( $assistant instanceof LocalAssistant );
				$index = $container->get( WordPressSiteIndex::class );
				assert( $index instanceof WordPressSiteIndex );
				$onboarding = $container->get( OnboardingStore::class );
				assert( $onboarding instanceof OnboardingStore );
				$modules = $container->get( ModuleManager::class );
				assert( $modules instanceof ModuleManager );
				$migrator = $container->get( Migrator::class );
				assert( $migrator instanceof Migrator );
				$links = $container->get( LinkIndexer::class );
				assert( $links instanceof LinkIndexer );
				return new DashboardController( $assistant, $index, $onboarding, $modules, $migrator, $links );
			}
		);

		$this->container->singleton(
			ChatController::class,
			static function ( Container $container ): ChatController {
				$assistant = $container->get( LocalAssistant::class );
				assert( $assistant instanceof LocalAssistant );
				return new ChatController( $assistant );
			}
		);

		$this->container->singleton(
			ChatModule::class,
			static function ( Container $container ): ChatModule {
				return new ChatModule( $container );
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
				$sitemaps_controller = $container->get( SitemapsController::class );
				assert( $sitemaps_controller instanceof SitemapsController );
				$onboarding_controller = $container->get( OnboardingController::class );
				assert( $onboarding_controller instanceof OnboardingController );
				$dashboard_controller = $container->get( DashboardController::class );
				assert( $dashboard_controller instanceof DashboardController );
				$chat_controller = $container->get( ChatController::class );
				assert( $chat_controller instanceof ChatController );
				$meta_controller = $container->get( MetaController::class );
				assert( $meta_controller instanceof MetaController );
				$missing_controller = $container->get( MissingMetadataController::class );
				assert( $missing_controller instanceof MissingMetadataController );
				$editor_insights_controller = $container->get( EditorInsightsController::class );
				assert( $editor_insights_controller instanceof EditorInsightsController );
				$cloud_controller = $container->get( CloudController::class );
				assert( $cloud_controller instanceof CloudController );
				$ai_controller = $container->get( AiActionsController::class );
				assert( $ai_controller instanceof AiActionsController );

				return new RestRegistry(
					[
						$settings_controller,
						$status_controller,
						$ui_preferences_controller,
						$sitemaps_controller,
						$onboarding_controller,
						$dashboard_controller,
						$chat_controller,
						$meta_controller,
						$missing_controller,
						$editor_insights_controller,
						$cloud_controller,
						$ai_controller,
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
	 * Module IDs stay stable so saved settings keep matching the same features.
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
