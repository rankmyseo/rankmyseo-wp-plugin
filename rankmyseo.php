<?php
/**
 * Plugin Name:       RankMySEO
 * Plugin URI:        https://rankmyseo.com
 * Description:       Standalone WordPress SEO plugin with Simple and Advanced modes, optional RankMySEO Cloud insights, and safe human-approved AI actions.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            RankMySEO
 * Author URI:        https://rankmyseo.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rankmyseo
 * Domain Path:       /languages
 *
 * @package RankMySEO
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RANKMYSEO_VERSION', '1.0.0' );
define( 'RANKMYSEO_DB_VERSION', 1 );
define( 'RANKMYSEO_FILE', __FILE__ );
define( 'RANKMYSEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'RANKMYSEO_URL', plugin_dir_url( __FILE__ ) );
define( 'RANKMYSEO_BASENAME', plugin_basename( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'RankMySEO requires PHP 8.0 or higher. Please upgrade PHP to activate the plugin.', 'rankmyseo' )
			);
		}
	);
	return;
}

// Prefer Composer autoloader when present, with a zero-dependency PSR-4 fallback for WP.org builds.
if ( file_exists( RANKMYSEO_DIR . 'vendor/autoload.php' ) ) {
	require_once RANKMYSEO_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'RankMySEO\\';

			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $prefix ) );
			$file_path      = RANKMYSEO_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( is_readable( $file_path ) ) {
				require_once $file_path;
			}
		}
	);
}

register_activation_hook( RANKMYSEO_FILE, [ \RankMySEO\Core\Activator::class, 'activate' ] );
register_deactivation_hook( RANKMYSEO_FILE, [ \RankMySEO\Core\Deactivator::class, 'deactivate' ] );

add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'rankmyseo', false, dirname( RANKMYSEO_BASENAME ) . '/languages' );
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\RankMySEO\Plugin::instance()->boot();
	},
	10
);
