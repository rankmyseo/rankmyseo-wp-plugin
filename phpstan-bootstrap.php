<?php
/**
 * PHPStan bootstrap constants for static analysis.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

if ( ! defined( 'RANKMYSEO_VERSION' ) ) {
	define( 'RANKMYSEO_VERSION', '0.1.0' );
}

if ( ! defined( 'RANKMYSEO_DB_VERSION' ) ) {
	define( 'RANKMYSEO_DB_VERSION', 1 );
}

if ( ! defined( 'RANKMYSEO_FILE' ) ) {
	define( 'RANKMYSEO_FILE', __DIR__ . '/rankmyseo.php' );
}

if ( ! defined( 'RANKMYSEO_DIR' ) ) {
	define( 'RANKMYSEO_DIR', __DIR__ . '/' );
}

if ( ! defined( 'RANKMYSEO_URL' ) ) {
	define( 'RANKMYSEO_URL', 'https://example.com/wp-content/plugins/rankmyseo/' );
}

if ( ! defined( 'RANKMYSEO_BASENAME' ) ) {
	define( 'RANKMYSEO_BASENAME', 'rankmyseo/rankmyseo.php' );
}
