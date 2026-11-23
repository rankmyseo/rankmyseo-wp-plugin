<?php
/**
 * Stops WooCommerce from printing a second Product JSON-LD block.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Detaches WC_Structured_Data product output while RankMySEO Product schema is enabled.
 */
final class WooCommerceSuppressor {
	/**
	 * Schema settings.
	 *
	 * @var SchemaSettings
	 */
	private SchemaSettings $settings;

	/**
	 * Optional resolver returning the WooCommerce main instance. Tests inject this.
	 *
	 * @var callable|null
	 */
	private $wc_resolver;

	/**
	 * Constructor.
	 *
	 * @param SchemaSettings $settings    Schema settings.
	 * @param callable|null  $wc_resolver Optional `(): ?object` WooCommerce resolver.
	 */
	public function __construct( SchemaSettings $settings, ?callable $wc_resolver = null ) {
		$this->settings    = $settings;
		$this->wc_resolver = $wc_resolver;
	}

	/**
	 * Hook suppression into the request.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp', [ $this, 'maybe_detach' ] );
		add_filter( 'woocommerce_structured_data_product', [ $this, 'suppress_product_data' ], 99 );
	}

	/**
	 * Remove WooCommerce's product structured-data callbacks when our Product schema is on.
	 *
	 * @return void
	 */
	public function maybe_detach(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$wc = $this->woocommerce();
		if ( ! is_object( $wc ) ) {
			return;
		}

		$structured_data = $this->structured_data( $wc );
		if ( null === $structured_data ) {
			return;
		}

		$this->detach( $structured_data );
	}

	/**
	 * Remove the known WooCommerce structured data hooks.
	 *
	 * @param object $structured_data WC_Structured_Data instance.
	 * @return void
	 */
	public function detach( object $structured_data ): void {
		remove_action( 'woocommerce_single_product_summary', [ $structured_data, 'generate_product_data' ], 60 );
		remove_action( 'wp_footer', [ $structured_data, 'output_structured_data' ], 10 );
	}

	/**
	 * Empty WooCommerce's product structured-data payload when our Product schema is on.
	 *
	 * @param mixed $data Product data WooCommerce was about to print.
	 * @return mixed
	 */
	public function suppress_product_data( $data ) {
		if ( ! $this->is_active() ) {
			return $data;
		}

		return [];
	}

	/**
	 * Whether RankMySEO should replace WooCommerce product schema.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->settings->is_product_schema_enabled();
	}

	/**
	 * Resolve the WooCommerce instance.
	 *
	 * @return object|null
	 */
	private function woocommerce(): ?object {
		if ( null !== $this->wc_resolver ) {
			$resolved = ( $this->wc_resolver )();
			return is_object( $resolved ) ? $resolved : null;
		}

		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$wc = WC();
		return is_object( $wc ) ? $wc : null;
	}

	/**
	 * Read the structured_data property without assuming a concrete WooCommerce class.
	 *
	 * @param object $wc WooCommerce instance.
	 * @return object|null
	 */
	private function structured_data( object $wc ): ?object {
		if ( ! isset( $wc->structured_data ) || ! is_object( $wc->structured_data ) ) {
			return null;
		}

		return $wc->structured_data;
	}
}
