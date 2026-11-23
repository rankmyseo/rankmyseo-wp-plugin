<?php
/**
 * Stub module for On-Page Titles, Meta Descriptions, and Social Tags.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Stubs;

use RankMySEO\Modules\AbstractModule;

/**
 * Placeholder registration for Module 1 (implemented in PR 2).
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
		return __( 'On-Page & Titles', 'rankmyseo' );
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
	public function is_ready(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Implemented in Module 1 PR.
	}
}
