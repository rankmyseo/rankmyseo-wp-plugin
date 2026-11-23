<?php
/**
 * Plugin uninstall cleanup.
 *
 * Removes RankMySEO options, user preferences, post meta, capabilities,
 * and custom database tables unless the administrator opted to keep data.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$rankmyseo_settings = get_option( 'rankmyseo_settings', [] );

if ( is_array( $rankmyseo_settings ) && ! empty( $rankmyseo_settings['keep_data_on_uninstall'] ) ) {
	return;
}

// Register lightweight PSR-4 autoloader so uninstall works even without vendor/.
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

global $wpdb;

\RankMySEO\Core\Capabilities::remove_caps();

$rankmyseo_migrator = new \RankMySEO\Database\Migrator( $wpdb );
$rankmyseo_migrator->drop_tables();

delete_option( \RankMySEO\Core\Settings::OPTION_KEY );
delete_option( \RankMySEO\Core\OnboardingStore::OPTION_COMPLETED );
delete_option( \RankMySEO\Core\OnboardingStore::OPTION_IDENTITY );
delete_option( \RankMySEO\Database\Migrator::OPTION_DB_VERSION );
delete_option( 'rankmyseo_flush_rewrite_rules' );
delete_option( \RankMySEO\Modules\Sitemaps\SitemapSettings::OPTION_KEY );
delete_option( \RankMySEO\Modules\Sitemaps\SitemapSettings::ROBOTS_OPTION_KEY );
delete_option( \RankMySEO\Modules\Sitemaps\SitemapSettings::LOG_OPTION_KEY );
delete_option( \RankMySEO\Modules\Sitemaps\SitemapSettings::QUEUE_OPTION_KEY );
delete_option( \RankMySEO\Modules\Sitemaps\SitemapSettings::CACHE_GEN_OPTION_KEY );
delete_option( \RankMySEO\Modules\Sitemaps\SitemapSettings::ATTEMPTS_OPTION_KEY );
delete_option( 'rankmyseo_cloud_connection' );
delete_option( 'rankmyseo_cloud_settings' );
delete_transient( 'rankmyseo_cloud_insights' );
delete_transient( 'rankmyseo_cloud_sync_lock' );

delete_metadata( 'user', 0, \RankMySEO\Core\Settings::USER_META_UI_MODE, '', true );
delete_metadata( 'user', 0, \RankMySEO\Core\Settings::USER_META_WIZARD_DISMISSED, '', true );

// Clean up plugin post meta keys prefixed with _rankmyseo_.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_rankmyseo_' ) . '%'
	)
);
