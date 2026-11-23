<?php
/**
 * Stub module for Redirects Manager and 404 Monitor.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Stubs;

use RankMySEO\Modules\AbstractModule;

/**
 * Placeholder registration for Module 5 (implemented in PR 6).
 */
final class RedirectsModule extends AbstractModule {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'redirects';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Redirects & 404s', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( '301/302/307/410/451 redirects with O(1) hash lookup, regex safety guards, slug-change protection, and deduplicated 404 logging.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Spot broken links and send visitors to the right page automatically when a URL changes.', 'rankmyseo' );
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
		// Implemented in Module 5 PR.
	}
}
