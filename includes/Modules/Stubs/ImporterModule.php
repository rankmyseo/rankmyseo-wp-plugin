<?php
/**
 * Stub module for One-Click SEO Migration Importer.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Stubs;

use RankMySEO\Modules\AbstractModule;

/**
 * Placeholder registration for Module 6 (implemented in PR 7).
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
		return __( 'Dry-run preview, chunked import, and one-click rollback from Yoast SEO, Rank Math, and AIOSEO without deleting source data.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Safely copy your existing titles, descriptions, and redirects from Yoast SEO, Rank Math, or AIOSEO with one-click undo.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_ready(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Implemented in Module 6 PR.
	}
}
