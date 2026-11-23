<?php
/**
 * Module 2: real-time on-page score, saved score, and cannibalization check.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

use RankMySEO\Core\Settings;
use RankMySEO\Modules\AbstractModule;
use RankMySEO\Modules\Meta\TitleSettings;
use RankMySEO\Rest\RestRegistry;

/**
 * Registers score meta, the save fallback, the list column, and the REST check.
 */
final class AnalyzerModule extends AbstractModule {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'analyzer';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'On-Page Analyzer', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Real-time keyword, snippet, structure, and readability scoring with a cannibalization check.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Live writing checklist and 0–100 score inside the editor to help each post target your main keyword.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		( new AnalyzerMeta() )->register();
		( new ScorePersister( $this->title_settings() ) )->register();
		( new AdminColumn() )->register();

		$controller = new CannibalizationController();
		if ( $this->container->has( RestRegistry::class ) ) {
			$rest = $this->container->get( RestRegistry::class );
			if ( $rest instanceof RestRegistry ) {
				$rest->add( $controller );
				return;
			}
		}

		add_action( 'rest_api_init', [ $controller, 'register_routes' ] );
	}

	/**
	 * Title settings from the container, or a fresh instance.
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
