<?php
/**
 * PHPStan bootstrap constants for static analysis.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

if ( ! defined( 'RANKMYSEO_VERSION' ) ) {
	define( 'RANKMYSEO_VERSION', '1.0.0' );
}

if ( ! defined( 'RANKMYSEO_DB_VERSION' ) ) {
	define( 'RANKMYSEO_DB_VERSION', 2 );
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

if ( ! function_exists( 'wc_get_page_id' ) ) {
	/**
	 * PHPStan stub. WooCommerce provides the real function at runtime.
	 *
	 * @param string $page Page key such as shop.
	 * @return int
	 */
	function wc_get_page_id( string $page ): int {
		unset( $page );
		return 0;
	}
}

if ( ! function_exists( 'is_shop' ) ) {
	/**
	 * PHPStan stub. WooCommerce provides the real function at runtime.
	 *
	 * @return bool
	 */
	function is_shop(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_product' ) ) {
	/**
	 * PHPStan stub. WooCommerce provides the real function at runtime.
	 *
	 * @return bool
	 */
	function is_product(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_product_category' ) ) {
	/**
	 * PHPStan stub. WooCommerce provides the real function at runtime.
	 *
	 * @return bool
	 */
	function is_product_category(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_product_tag' ) ) {
	/**
	 * PHPStan stub. WooCommerce provides the real function at runtime.
	 *
	 * @return bool
	 */
	function is_product_tag(): bool {
		return false;
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * PHPStan stub. WooCommerce provides the real function at runtime.
	 *
	 * @param mixed $the_product Product ID or object.
	 * @return object|null
	 */
	function wc_get_product( $the_product = false ) {
		unset( $the_product );
		return null;
	}
}

if ( ! function_exists( 'WC' ) ) {
	/**
	 * PHPStan stub. WooCommerce provides the real function at runtime.
	 *
	 * @return object
	 */
	function WC(): object {
		return new stdClass();
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	/**
	 * PHPStan stub. WooCommerce provides the real function at runtime.
	 *
	 * @return string
	 */
	function get_woocommerce_currency(): string {
		return 'USD';
	}
}
