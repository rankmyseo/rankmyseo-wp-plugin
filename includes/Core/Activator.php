<?php
/**
 * Plugin activation lifecycle handler.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Core;

use RankMySEO\Database\Migrator;

/**
 * Handles activation tasks: defaults, capabilities, schema migrations, and rewrite flush.
 */
final class Activator {
	/**
	 * Run on plugin activation.
	 *
	 * Performs strictly local initialization with zero external HTTP calls
	 * and no forced admin redirects.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$settings = new Settings();
		$settings->ensure_defaults();

		Capabilities::add_caps();

		global $wpdb;
		$migrator = new Migrator( $wpdb );
		$migrator->migrate();

		update_option( 'rankmyseo_flush_rewrite_rules', 1, false );
	}
}
