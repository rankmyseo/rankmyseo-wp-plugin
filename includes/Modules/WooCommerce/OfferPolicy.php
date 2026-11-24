<?php
/**
 * Store and per-product return and shipping schema.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

/**
 * Builds MerchantReturnPolicy and OfferShippingDetails from saved settings.
 */
final class OfferPolicy {
	/**
	 * Settings repository.
	 *
	 * @var WooSettings
	 */
	private WooSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param WooSettings|null $settings Optional settings repository.
	 */
	public function __construct( ?WooSettings $settings = null ) {
		$this->settings = $settings ?? new WooSettings();
	}

	/**
	 * Whether variable products should list one offer per option.
	 *
	 * @return bool
	 */
	public function variation_offers_enabled(): bool {
		return $this->settings->variation_offers_enabled();
	}

	/**
	 * Schema nodes for one product, after applying its override.
	 *
	 * @param object $product Product object.
	 * @return array{return_policy: array<string, mixed>, shipping_details: array<string, mixed>}
	 */
	public function for_product( object $product ): array {
		$settings = $this->settings->all();

		return [
			'return_policy'    => $this->return_node( $settings, ProductMeta::read_array( $product, ProductMeta::RETURN_POLICY ) ),
			'shipping_details' => $this->shipping_node( $settings, ProductMeta::read_array( $product, ProductMeta::SHIPPING ) ),
		];
	}

	/**
	 * MerchantReturnPolicy node, or an empty array when the policy is incomplete.
	 *
	 * @param array<string, mixed> $settings Store settings.
	 * @param array<string, mixed> $override Product override.
	 * @return array<string, mixed>
	 */
	public function return_node( array $settings, array $override = [] ): array {
		$mode = isset( $override['mode'] ) ? sanitize_key( (string) $override['mode'] ) : 'inherit';
		if ( 'none' === $mode ) {
			return [];
		}

		$source = $settings;
		if ( 'custom' === $mode ) {
			$source = $this->overlay_return( $settings, $override );
		} elseif ( empty( $settings['return_policy_enabled'] ) ) {
			return [];
		}

		$country = strtoupper( (string) ( $source['return_country'] ?? '' ) );
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return [];
		}

		$category = (string) ( $source['return_category'] ?? '' );
		if ( ! in_array( $category, WooSettings::RETURN_CATEGORIES, true ) ) {
			return [];
		}

		$days = (int) ( $source['return_days'] ?? 0 );
		if ( 'MerchantReturnFiniteReturnWindow' === $category && $days <= 0 ) {
			return [];
		}

		$node = [
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => $country,
			'returnPolicyCategory' => 'https://schema.org/' . $category,
		];

		if ( 'MerchantReturnFiniteReturnWindow' === $category ) {
			$node['merchantReturnDays'] = $days;
		}

		$method = (string) ( $source['return_method'] ?? '' );
		if ( in_array( $method, WooSettings::RETURN_METHODS, true ) ) {
			$node['returnMethod'] = 'https://schema.org/' . $method;
		}

		$fees = (string) ( $source['return_fees'] ?? '' );
		if ( in_array( $fees, WooSettings::RETURN_FEES, true ) ) {
			$node['returnFees'] = 'https://schema.org/' . $fees;
		}

		$url = (string) ( $source['return_policy_url'] ?? '' );
		if ( '' !== $url ) {
			$node['merchantReturnLink'] = $url;
		}

		return $node;
	}

	/**
	 * OfferShippingDetails node, or an empty array when the rate or destination is missing.
	 *
	 * @param array<string, mixed> $settings Store settings.
	 * @param array<string, mixed> $override Product override.
	 * @return array<string, mixed>
	 */
	public function shipping_node( array $settings, array $override = [] ): array {
		$mode = isset( $override['mode'] ) ? sanitize_key( (string) $override['mode'] ) : 'inherit';
		if ( 'none' === $mode ) {
			return [];
		}

		$source = $settings;
		if ( 'custom' === $mode ) {
			$source = $this->overlay_shipping( $settings, $override );
		} elseif ( empty( $settings['shipping_enabled'] ) ) {
			return [];
		}

		$country = strtoupper( (string) ( $source['shipping_country'] ?? '' ) );
		$rate    = (string) ( $source['shipping_rate'] ?? '' );
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $country ) || '' === $rate || ! is_numeric( $rate ) ) {
			return [];
		}

		$currency = strtoupper( (string) ( $source['shipping_currency'] ?? '' ) );
		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$currency = $this->shop_currency();
		}

		$node = [
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => [
				'@type'    => 'MonetaryAmount',
				'value'    => number_format( (float) $rate, 2, '.', '' ),
				'currency' => $currency,
			],
			'shippingDestination' => [
				'@type'          => 'DefinedRegion',
				'addressCountry' => $country,
			],
		];

		$delivery = $this->delivery_time( $source );
		if ( [] !== $delivery ) {
			$node['deliveryTime'] = $delivery;
		}

		return $node;
	}

	/**
	 * Overlay a custom return override onto the store policy.
	 *
	 * @param array<string, mixed> $settings Store settings.
	 * @param array<string, mixed> $override Product override.
	 * @return array<string, mixed>
	 */
	private function overlay_return( array $settings, array $override ): array {
		$settings['return_policy_enabled'] = true;
		$map                               = [
			'country'  => 'return_country',
			'category' => 'return_category',
			'days'     => 'return_days',
			'method'   => 'return_method',
			'fees'     => 'return_fees',
			'url'      => 'return_policy_url',
		];
		foreach ( $map as $from => $to ) {
			if ( ! array_key_exists( $from, $override ) ) {
				continue;
			}
			$value = $override[ $from ];
			if ( is_string( $value ) && '' === trim( $value ) ) {
				continue;
			}
			$settings[ $to ] = $value;
		}

		return $settings;
	}

	/**
	 * Overlay a custom shipping override onto the store policy.
	 *
	 * @param array<string, mixed> $settings Store settings.
	 * @param array<string, mixed> $override Product override.
	 * @return array<string, mixed>
	 */
	private function overlay_shipping( array $settings, array $override ): array {
		$settings['shipping_enabled'] = true;
		$map                          = [
			'country'      => 'shipping_country',
			'rate'         => 'shipping_rate',
			'currency'     => 'shipping_currency',
			'handling_min' => 'shipping_handling_min',
			'handling_max' => 'shipping_handling_max',
			'transit_min'  => 'shipping_transit_min',
			'transit_max'  => 'shipping_transit_max',
		];
		foreach ( $map as $from => $to ) {
			if ( ! array_key_exists( $from, $override ) ) {
				continue;
			}
			$value = $override[ $from ];
			if ( ( is_string( $value ) && '' === trim( $value ) ) || ( is_int( $value ) && 0 === $value && str_contains( $from, '_' ) && ! str_contains( $from, 'rate' ) ) ) {
				continue;
			}
			$settings[ $to ] = $value;
		}

		return $settings;
	}

	/**
	 * Handling and transit times, omitted when both are unset.
	 *
	 * @param array<string, mixed> $source Policy fields.
	 * @return array<string, mixed>
	 */
	private function delivery_time( array $source ): array {
		$handling = $this->quantitative(
			(int) ( $source['shipping_handling_min'] ?? 0 ),
			(int) ( $source['shipping_handling_max'] ?? 0 )
		);
		$transit  = $this->quantitative(
			(int) ( $source['shipping_transit_min'] ?? 0 ),
			(int) ( $source['shipping_transit_max'] ?? 0 )
		);
		if ( [] === $handling && [] === $transit ) {
			return [];
		}

		$node = [
			'@type' => 'ShippingDeliveryTime',
		];
		if ( [] !== $handling ) {
			$node['handlingTime'] = $handling;
		}
		if ( [] !== $transit ) {
			$node['transitTime'] = $transit;
		}

		return $node;
	}

	/**
	 * A day range, or empty when both ends are zero.
	 *
	 * @param int $min Minimum days.
	 * @param int $max Maximum days.
	 * @return array<string, mixed>
	 */
	private function quantitative( int $min, int $max ): array {
		if ( $min <= 0 && $max <= 0 ) {
			return [];
		}
		if ( $min <= 0 ) {
			$min = $max;
		}
		if ( $max <= 0 ) {
			$max = $min;
		}

		return [
			'@type'    => 'QuantitativeValue',
			'minValue' => min( $min, $max ),
			'maxValue' => max( $min, $max ),
			'unitCode' => 'DAY',
		];
	}

	/**
	 * Shop currency, or USD when WooCommerce has not set one.
	 *
	 * @return string
	 */
	private function shop_currency(): string {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$shop = get_woocommerce_currency();
			if ( is_string( $shop ) && 1 === preg_match( '/^[A-Za-z]{3}$/', $shop ) ) {
				return strtoupper( $shop );
			}
		}

		return 'USD';
	}
}
