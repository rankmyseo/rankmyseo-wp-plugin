<?php
/**
 * Stub module for XML Sitemaps, IndexNow, and Crawl Controls.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Stubs;

use RankMySEO\Modules\AbstractModule;

/**
 * Placeholder registration for Module 4 (implemented in PR 5).
 */
final class SitemapsModule extends AbstractModule {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'sitemaps';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Sitemaps & Crawl', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Paginated XML sitemaps, virtual IndexNow key and async pings, robots.txt editor, and crawl hygiene controls.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Keep an up-to-date map of your site for search engines and notify them when you publish new pages.', 'rankmyseo' );
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
		// Implemented in Module 4 PR.
	}
}
