<?php
/**
 * Plugin deactivation lifecycle handler.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Core;

/**
 * Handles cleanup on plugin deactivation without deleting user data.
 */
final class Deactivator {
	/**
	 * Scheduled cron hooks registered by RankMySEO modules.
	 *
	 * @var list<string>
	 */
	public const CRON_HOOKS = [
		'rankmyseo_prune_404_logs',
		'rankmyseo_process_sync_queue',
		'rankmyseo_process_indexnow_queue',
		'rankmyseo_run_auto_apply',
	];

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		foreach ( self::CRON_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		delete_option( 'rankmyseo_flush_rewrite_rules' );
		flush_rewrite_rules( false );
	}
}
