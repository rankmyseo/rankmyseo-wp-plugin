<?php
/**
 * Stub module for Real-Time On-Page SEO Analyzer.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Stubs;

use RankMySEO\Modules\AbstractModule;

/**
 * Placeholder registration for Module 2 (implemented in PR 3).
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
		return __( 'Real-time client-side keyword targeting, snippet length, structure, and readability scoring with cannibalization checks.', 'rankmyseo' );
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
	public function is_ready(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Implemented in Module 2 PR.
	}
}
