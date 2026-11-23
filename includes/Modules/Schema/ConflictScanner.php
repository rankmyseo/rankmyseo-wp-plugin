<?php
/**
 * Detects other plugins that may print a second JSON-LD graph.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Reports schema conflicts in vendor-neutral language.
 */
final class ConflictScanner {
	/**
	 * Optional probe `(string $key): bool` for tests.
	 *
	 * @var callable|null
	 */
	private $probe;

	/**
	 * Constructor.
	 *
	 * @param callable|null $probe Optional environment probe.
	 */
	public function __construct( ?callable $probe = null ) {
		$this->probe = $probe;
	}

	/**
	 * Scan for duplicate structured-data publishers.
	 *
	 * @param SchemaSettings $settings Schema settings.
	 * @return list<array{id: string, level: string, message: string}>
	 */
	public function scan( SchemaSettings $settings ): array {
		$items = [];

		if ( $this->other_seo_plugin_active() ) {
			$items[] = [
				'id'      => 'other-seo-plugin',
				'level'   => 'warning',
				'message' => __( 'Another SEO plugin is active and may also publish structured data. Turn off its schema output so each page has one JSON-LD graph.', 'rankmyseo' ),
			];
		}

		if ( $this->is_active( 'woocommerce' ) ) {
			if ( $settings->is_product_schema_enabled() ) {
				$items[] = [
					'id'      => 'woocommerce',
					'level'   => 'info',
					'message' => __( 'WooCommerce product markup is turned off while RankMySEO product schema is on, so product pages are not described twice.', 'rankmyseo' ),
				];
			} else {
				$items[] = [
					'id'      => 'woocommerce-duplicate',
					'level'   => 'warning',
					'message' => __( 'WooCommerce is still printing its own product details. Turn on automatic product schema to replace that duplicate.', 'rankmyseo' ),
				];
			}
		}

		return $items;
	}

	/**
	 * Whether a known SEO plugin that emits schema is active.
	 *
	 * @return bool
	 */
	private function other_seo_plugin_active(): bool {
		return $this->is_active( 'yoast' ) || $this->is_active( 'rankmath' ) || $this->is_active( 'aioseo' );
	}

	/**
	 * Check one integration key.
	 *
	 * @param string $key Probe key.
	 * @return bool
	 */
	private function is_active( string $key ): bool {
		if ( null !== $this->probe ) {
			return (bool) ( $this->probe )( $key );
		}

		switch ( $key ) {
			case 'yoast':
				return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Frontend' );
			case 'rankmath':
				return class_exists( 'RankMath' );
			case 'aioseo':
				return defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' );
			case 'woocommerce':
				return class_exists( 'WooCommerce' );
			default:
				return false;
		}
	}
}
