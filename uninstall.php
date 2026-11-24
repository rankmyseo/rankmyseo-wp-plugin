<?php
/**
 * Plugin uninstall cleanup.
 *
 * Removes options, transients, cron hooks, user and object meta, capabilities,
 * and custom tables only when delete_data_on_uninstall is enabled.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! class_exists( \RankMySEO\Core\Uninstaller::class ) ) {
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
	} else {
		spl_autoload_register(
			static function ( string $class_name ): void {
				$prefix = 'RankMySEO\\';

				if ( 0 !== strpos( $class_name, $prefix ) ) {
					return;
				}

				$relative_class = substr( $class_name, strlen( $prefix ) );
				$file_path      = __DIR__ . '/includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

				if ( is_readable( $file_path ) ) {
					require_once $file_path;
				}
			}
		);
	}
}

$rankmyseo_settings = get_option( 'rankmyseo_settings', [] );

if ( ! \RankMySEO\Core\Uninstaller::should_delete( $rankmyseo_settings ) ) {
	return;
}

global $wpdb;

if ( $wpdb instanceof wpdb ) {
	\RankMySEO\Core\Uninstaller::purge( $wpdb );
}
