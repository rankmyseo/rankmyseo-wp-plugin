<?php
/**
 * Module 1: titles, descriptions, canonicals, robots, and social tags.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

use RankMySEO\Modules\AbstractModule;
use RankMySEO\Modules\Builders\BuilderAssets;
use RankMySEO\Modules\Builders\BuilderConfig;
use RankMySEO\Modules\Builders\BuilderDetector;
use RankMySEO\Modules\Builders\PageBuilderBridge;

/**
 * Registers the meta engine, editor UI, and front-end head output.
 */
final class MetaModule extends AbstractModule {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'meta';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Titles & Meta', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Control SEO titles, meta descriptions, canonical URLs, wp_robots directives, OpenGraph, and social card previews.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Customize how your pages look in Google search results and when shared on social media.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$titles = $this->title_settings();
		$store  = new MetaStore();
		$guard  = new ConflictGuard( $titles );

		( new MetaRegistry() )->register();
		$guard->register();
		( new Frontend( $titles, $store, new DocumentBuilder(), $guard, new QueryReader() ) )->register();
		( new ClassicMetabox( $store, $this->settings(), $titles ) )->register();
		$query = new MissingMetadataQuery( $store );
		( new QuickBulkEdit( $store, new TemplateFill( $titles, new TemplateEngine(), $query ) ) )->register();
		( new TermFields( $store ) )->register();
		( new EditorAssets( $this->settings(), $titles, $store ) )->register();
		( new PageBuilderBridge(
			new BuilderDetector(),
			new BuilderAssets(),
			new BuilderConfig( $this->settings(), $titles, $store )
		) )->register();
	}

	/**
	 * Resolve title settings from the container, or build them from core settings.
	 *
	 * @return TitleSettings
	 */
	private function title_settings(): TitleSettings {
		if ( $this->container->has( TitleSettings::class ) ) {
			$titles = $this->container->get( TitleSettings::class );
			if ( $titles instanceof TitleSettings ) {
				return $titles;
			}
		}

		return new TitleSettings( $this->settings() );
	}
}
