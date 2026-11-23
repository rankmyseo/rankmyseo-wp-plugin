<?php
/**
 * AI Approval Queue module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Modules\AbstractModule;

/**
 * Registers the approval queue in the module catalog.
 *
 * REST routes and the auto-apply cron live on AiActionsController.
 */
final class AiQueueModule extends AbstractModule {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'ai_queue';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'AI Approval Queue', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Review AI-proposed SEO updates with side-by-side diffs, immutable rollback snapshots, WP revisions, audit logs, and opt-in auto-apply policies.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Review suggested SEO improvements one by one with clear before-and-after previews and one-click undo.', 'rankmyseo' );
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
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function requires_cloud(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Routes and the auto-apply cron are registered by AiActionsController.
	}
}
