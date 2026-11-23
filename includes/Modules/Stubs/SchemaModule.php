<?php
/**
 * Stub module for Schema.org JSON-LD Structured Data.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Stubs;

use RankMySEO\Modules\AbstractModule;

/**
 * Placeholder registration for Module 3 (implemented in PR 4).
 */
final class SchemaModule extends AbstractModule {
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
	 * {@inheritDoc}
	 */
	public function is_ready(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Implemented in Module 3 PR.
	}
}
