<?php
/**
 * Migration importer module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

use RankMySEO\Modules\AbstractModule;
use RankMySEO\Rest\ImporterController;
use RankMySEO\Rest\RestRegistry;

/**
 * Registers the non-destructive SEO migration importer.
 */
final class ImporterModule extends AbstractModule {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'importer';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Migration Importer', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Dry-run preview, chunked import, and one-click rollback from Yoast SEO, Rank Math, and All in One SEO without deleting source data.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Safely copy your existing titles, descriptions, and redirects from Yoast SEO, Rank Math, or All in One SEO with one-click undo.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$registry = $this->container->get( RestRegistry::class );
		if ( ! $registry instanceof RestRegistry ) {
			return;
		}

		$reader = new WordPressSourceReader();
		$store  = new WordPressSiteStore();
		$registry->add(
			new ImporterController(
				new ImportService( new AdapterFactory( $reader, $store ), $store )
			)
		);
	}
}
