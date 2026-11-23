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
		self::clear_scheduled_hooks();

		delete_option( 'rankmyseo_flush_rewrite_rules' );
		flush_rewrite_rules( false );
	}

	/**
	 * Clear every RankMySEO cron hook and matching Action Scheduler jobs.
	 *
	 * @return void
	 */
	public static function clear_scheduled_hooks(): void {
		foreach ( self::CRON_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		if ( function_exists( 'get_option' ) ) {
			$cron = get_option( 'cron' );
			if ( is_array( $cron ) ) {
				foreach ( $cron as $hooks ) {
					if ( ! is_array( $hooks ) ) {
						continue;
					}
					foreach ( array_keys( $hooks ) as $hook ) {
						if ( is_string( $hook ) && 0 === strpos( $hook, 'rankmyseo_' ) ) {
							wp_clear_scheduled_hook( $hook );
						}
					}
				}
			}
		}

		self::unschedule_action_scheduler( 'unschedule_all_actions' );
	}

	/**
	 * Unschedule one Action Scheduler hook when that plugin is active.
	 *
	 * @param string $suffix Function suffix after as_.
	 * @return void
	 */
	private static function unschedule_action_scheduler( string $suffix ): void {
		$function = 'as_' . $suffix;
		if ( function_exists( $function ) && is_callable( $function ) ) {
			$function( 'rankmyseo_process_sync_queue', array(), 'rankmyseo' );
		}
	}
}
