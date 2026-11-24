<?php
/**
 * Schema.org JSON-LD module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

use RankMySEO\Modules\AbstractModule;
use RankMySEO\Rest\RestRegistry;

/**
 * Module 3: unified JSON-LD @graph, WooCommerce products, and schema settings.
 */
final class SchemaModule extends AbstractModule {
	/**
	 * Cached schema settings repository.
	 *
	 * @var SchemaSettings|null
	 */
	private ?SchemaSettings $schema_settings = null;

	/**
	 * Cached meta registrar.
	 *
	 * @var SchemaMeta|null
	 */
	private ?SchemaMeta $meta = null;

	/**
	 * Whether admin integrations have been registered.
	 *
	 * @var bool
	 */
	private bool $integrations_ready = false;

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'schema';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Structured Data (Schema)', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Unified Schema.org JSON-LD @graph output for Organization, Person, WebSite, Article, BreadcrumbList, LocalBusiness, and WooCommerce Products.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Help search engines understand your business details, articles, products, and breadcrumbs automatically.', 'rankmyseo' );
	}

	/**
	 * Register front-end output. Called only when the schema module is enabled.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_head', [ $this->frontend(), 'render' ], 5 );
		$this->suppressor()->register();
	}

	/**
	 * Register post meta and REST routes even when front-end output is switched off.
	 *
	 * @return void
	 */
	public function register_integrations(): void {
		if ( $this->integrations_ready ) {
			return;
		}
		$this->integrations_ready = true;

		add_action( 'init', [ $this->meta(), 'register_meta' ] );
		( new ContentBlocks() )->register();

		$registry = $this->container->get( RestRegistry::class );
		if ( $registry instanceof RestRegistry ) {
			$registry->add( $this->controller() );
		}
	}

	/**
	 * Schema settings repository.
	 *
	 * @return SchemaSettings
	 */
	private function schema_settings(): SchemaSettings {
		if ( null === $this->schema_settings ) {
			$this->schema_settings = new SchemaSettings();
		}

		return $this->schema_settings;
	}

	/**
	 * Meta registrar.
	 *
	 * @return SchemaMeta
	 */
	private function meta(): SchemaMeta {
		if ( null === $this->meta ) {
			$this->meta = new SchemaMeta();
		}

		return $this->meta;
	}

	/**
	 * Context factory bound to core settings.
	 *
	 * @return ContextFactory
	 */
	private function contexts(): ContextFactory {
		return new ContextFactory( $this->schema_settings(), null, $this->settings() );
	}

	/**
	 * Front-end printer.
	 *
	 * @return Frontend
	 */
	private function frontend(): Frontend {
		return new Frontend( $this->contexts(), new GraphBuilder(), new ScriptRenderer() );
	}

	/**
	 * WooCommerce duplicate suppressor.
	 *
	 * @return WooCommerceSuppressor
	 */
	private function suppressor(): WooCommerceSuppressor {
		return new WooCommerceSuppressor( $this->schema_settings() );
	}

	/**
	 * REST controller.
	 *
	 * @return SchemaController
	 */
	private function controller(): SchemaController {
		return new SchemaController(
			$this->schema_settings(),
			$this->settings(),
			$this->contexts(),
			new GraphBuilder(),
			new ConflictScanner()
		);
	}
}
