<?php
/**
 * WooCommerce store SEO module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

use RankMySEO\Modules\AbstractModule;
use RankMySEO\Rest\RestRegistry;

/**
 * Product identifiers, offers, catalog URLs, and product share tags.
 *
 * Front-end hooks are registered only while WooCommerce itself is active.
 * The settings API stays available so choices are kept for when the shop plugin is switched on.
 */
final class WooCommerceModule extends AbstractModule {
	/**
	 * Cached store settings.
	 *
	 * @var WooSettings|null
	 */
	private ?WooSettings $store_settings = null;

	/**
	 * Whether the REST route has been registered.
	 *
	 * @var bool
	 */
	private bool $integrations_ready = false;

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'woocommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Store', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Product identifiers, variation offers, return and shipping details, catalog URL rules, and product share tags. Nothing is sent off the site.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Help shoppers and search engines understand your products, prices, and sold-out items.', 'rankmyseo' );
	}

	/**
	 * Whether WooCommerce is loaded. Unit tests stay inactive because the class is not defined there.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Register shop hooks when WooCommerce is active.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		$settings = $this->store_settings();
		( new ProductDataTab() )->register();
		( new CatalogRobots( $settings ) )->register();
		( new PermalinkBases( $settings ) )->register();
		( new ProductOpenGraph( $settings ) )->register();
	}

	/**
	 * Register the settings route even when WooCommerce is inactive.
	 *
	 * @return void
	 */
	public function register_integrations(): void {
		if ( $this->integrations_ready ) {
			return;
		}
		$this->integrations_ready = true;

		$registry = $this->container->get( RestRegistry::class );
		if ( $registry instanceof RestRegistry ) {
			$registry->add( new WooCommerceController( $this->store_settings(), $this->settings() ) );
		}
	}

	/**
	 * Store settings repository.
	 *
	 * @return WooSettings
	 */
	private function store_settings(): WooSettings {
		if ( null === $this->store_settings ) {
			$this->store_settings = new WooSettings();
		}

		return $this->store_settings;
	}
}
