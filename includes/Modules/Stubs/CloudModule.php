<?php
/**
 * Stub module for optional RankMySEO Cloud connection.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Stubs;

use RankMySEO\Modules\AbstractModule;

/**
 * Placeholder registration for RankMySEO Cloud integration (implemented in PR 8).
 */
final class CloudModule extends AbstractModule {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'cloud';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'RankMySEO Cloud', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Optional site-scoped pairing to RankMySEO Cloud for tracked keyword rankings, Search Console insights, and inventory sync.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Optionally connect your RankMySEO account to see live Google rankings and Search Console clicks inside WordPress.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled_by_default(): bool {
		return false;
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
	public function requires_cloud(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Implemented in Cloud PR.
	}
}
