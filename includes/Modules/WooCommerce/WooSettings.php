<?php
/**
 * WooCommerce store SEO settings.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

/**
 * Stores shop SEO choices outside the core settings option.
 *
 * Catalog URL changes default to off so a store keeps its current links until an admin opts in.
 */
final class WooSettings {
	/**
	 * Option key. Compact enough to autoload.
	 */
	public const OPTION_KEY = 'rankmyseo_woocommerce_settings';

	/**
	 * Return window types accepted from settings.
	 *
	 * @var list<string>
	 */
	public const RETURN_CATEGORIES = [
		'MerchantReturnFiniteReturnWindow',
		'MerchantReturnNotPermitted',
		'MerchantReturnUnlimitedWindow',
	];

	/**
	 * How a shopper can send an item back.
	 *
	 * @var list<string>
	 */
	public const RETURN_METHODS = [
		'ReturnByMail',
		'ReturnInStore',
		'ReturnAtKiosk',
	];

	/**
	 * Who pays the cost of a return.
	 *
	 * @var list<string>
	 */
	public const RETURN_FEES = [
		'FreeReturn',
		'ReturnFeesCustomerResponsibility',
		'ReturnShippingFees',
		'OriginalShippingFees',
	];

	/**
	 * Typed defaults. URL hygiene stays off.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'variation_offers'          => true,
			'product_open_graph'        => true,
			'canonicalize_shop_filters' => false,
			'noindex_shop_filters'      => false,
			'noindex_out_of_stock'      => false,
			'remove_product_base'       => false,
			'remove_category_base'      => false,
			'legacy_product_base'       => 'product',
			'legacy_category_base'      => 'product-category',
			'return_policy_enabled'     => false,
			'return_country'            => '',
			'return_category'           => 'MerchantReturnFiniteReturnWindow',
			'return_days'               => 30,
			'return_method'             => 'ReturnByMail',
			'return_fees'               => 'FreeReturn',
			'return_policy_url'         => '',
			'shipping_enabled'          => false,
			'shipping_rate'             => '',
			'shipping_currency'         => '',
			'shipping_country'          => '',
			'shipping_handling_min'     => 0,
			'shipping_handling_max'     => 0,
			'shipping_transit_min'      => 0,
			'shipping_transit_max'      => 0,
		];
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return $this->sanitize( $stored );
	}

	/**
	 * Persist a partial update and remember the previous permalink base when removal is turned on.
	 *
	 * @param array<string, mixed> $values Incoming values.
	 * @return array<string, mixed>
	 */
	public function update( array $values ): array {
		$before    = $this->all();
		$sanitized = $this->sanitize( $values, $before );
		$sanitized = $this->capture_legacy_bases( $before, $sanitized, $values );
		update_option( self::OPTION_KEY, $sanitized, true );

		return $sanitized;
	}

	/**
	 * Whether each product option should become its own offer.
	 *
	 * @return bool
	 */
	public function variation_offers_enabled(): bool {
		return (bool) $this->all()['variation_offers'];
	}

	/**
	 * Whether product pages should add product share tags.
	 *
	 * @return bool
	 */
	public function open_graph_enabled(): bool {
		return (bool) $this->all()['product_open_graph'];
	}

	/**
	 * Sanitize incoming values onto a base state.
	 *
	 * @param array<string, mixed> $input Raw values.
	 * @param array<string, mixed> $base  Existing state.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input, array $base = [] ): array {
		$state = array_merge( $this->defaults(), $base );

		foreach (
			[
				'variation_offers',
				'product_open_graph',
				'canonicalize_shop_filters',
				'noindex_shop_filters',
				'noindex_out_of_stock',
				'remove_product_base',
				'remove_category_base',
				'return_policy_enabled',
				'shipping_enabled',
			] as $flag
		) {
			if ( array_key_exists( $flag, $input ) ) {
				$state[ $flag ] = $this->to_bool( $input[ $flag ] );
			}
		}

		if ( array_key_exists( 'legacy_product_base', $input ) ) {
			$base_slug = $this->sanitize_base( $input['legacy_product_base'] );
			if ( '' !== $base_slug ) {
				$state['legacy_product_base'] = $base_slug;
			}
		}

		if ( array_key_exists( 'legacy_category_base', $input ) ) {
			$base_slug = $this->sanitize_base( $input['legacy_category_base'] );
			if ( '' !== $base_slug ) {
				$state['legacy_category_base'] = $base_slug;
			}
		}

		if ( array_key_exists( 'return_country', $input ) ) {
			$state['return_country'] = $this->sanitize_country( $input['return_country'] );
		}

		if ( array_key_exists( 'return_category', $input ) ) {
			$category = $this->enum_token( $input['return_category'] );
			if ( in_array( $category, self::RETURN_CATEGORIES, true ) ) {
				$state['return_category'] = $category;
			}
		}

		if ( array_key_exists( 'return_days', $input ) ) {
			$state['return_days'] = $this->bounded_int( $input['return_days'], 0, 365 );
		}

		if ( array_key_exists( 'return_method', $input ) ) {
			$method = $this->enum_token( $input['return_method'] );
			if ( in_array( $method, self::RETURN_METHODS, true ) ) {
				$state['return_method'] = $method;
			}
		}

		if ( array_key_exists( 'return_fees', $input ) ) {
			$fees = $this->enum_token( $input['return_fees'] );
			if ( in_array( $fees, self::RETURN_FEES, true ) ) {
				$state['return_fees'] = $fees;
			}
		}

		if ( array_key_exists( 'return_policy_url', $input ) ) {
			$state['return_policy_url'] = esc_url_raw( trim( (string) $input['return_policy_url'] ) );
		}

		if ( array_key_exists( 'shipping_rate', $input ) ) {
			$state['shipping_rate'] = $this->sanitize_rate( $input['shipping_rate'] );
		}

		if ( array_key_exists( 'shipping_currency', $input ) ) {
			$state['shipping_currency'] = $this->sanitize_currency( $input['shipping_currency'] );
		}

		if ( array_key_exists( 'shipping_country', $input ) ) {
			$state['shipping_country'] = $this->sanitize_country( $input['shipping_country'] );
		}

		$state['shipping_handling_min'] = $this->day_field( $input, $state, 'shipping_handling_min' );
		$state['shipping_handling_max'] = $this->day_field( $input, $state, 'shipping_handling_max' );
		$state['shipping_transit_min']  = $this->day_field( $input, $state, 'shipping_transit_min' );
		$state['shipping_transit_max']  = $this->day_field( $input, $state, 'shipping_transit_max' );
		$state                          = $this->order_day_pair( $state, 'shipping_handling_min', 'shipping_handling_max' );
		$state                          = $this->order_day_pair( $state, 'shipping_transit_min', 'shipping_transit_max' );

		return $state;
	}

	/**
	 * Remember the shop's current base when an admin first turns removal on.
	 *
	 * @param array<string, mixed> $before Saved settings.
	 * @param array<string, mixed> $after  Sanitized settings.
	 * @param array<string, mixed> $input  Raw input, used to see whether the admin edited the base.
	 * @return array<string, mixed>
	 */
	private function capture_legacy_bases( array $before, array $after, array $input ): array {
		$after = $this->capture_one_base(
			$before,
			$after,
			$input,
			'remove_product_base',
			'legacy_product_base',
			'product_base'
		);

		return $this->capture_one_base(
			$before,
			$after,
			$input,
			'remove_category_base',
			'legacy_category_base',
			'category_base'
		);
	}

	/**
	 * Capture one WooCommerce permalink base.
	 *
	 * @param array<string, mixed> $before        Saved settings.
	 * @param array<string, mixed> $after         Sanitized settings.
	 * @param array<string, mixed> $input         Raw input.
	 * @param string               $toggle        Removal toggle key.
	 * @param string               $legacy_key    Stored base key.
	 * @param string               $permalink_key WooCommerce permalink key.
	 * @return array<string, mixed>
	 */
	private function capture_one_base( array $before, array $after, array $input, string $toggle, string $legacy_key, string $permalink_key ): array {
		$turning_on = empty( $before[ $toggle ] ) && ! empty( $after[ $toggle ] );
		if ( ! $turning_on ) {
			return $after;
		}

		$posted = array_key_exists( $legacy_key, $input ) ? $this->sanitize_base( $input[ $legacy_key ] ) : '';
		$edited = '' !== $posted && $posted !== (string) $before[ $legacy_key ];
		if ( $edited ) {
			return $after;
		}

		$after[ $legacy_key ] = $this->read_wc_base( $permalink_key, (string) $after[ $legacy_key ] );

		return $after;
	}

	/**
	 * Read a base from WooCommerce's own permalink option.
	 *
	 * @param string $key      `product_base` or `category_base`.
	 * @param string $fallback Base to keep when WooCommerce has not saved one.
	 * @return string
	 */
	private function read_wc_base( string $key, string $fallback ): string {
		$option = get_option( 'woocommerce_permalinks', [] );
		if ( ! is_array( $option ) || ! isset( $option[ $key ] ) || ! is_string( $option[ $key ] ) ) {
			return $fallback;
		}

		$clean = $this->sanitize_base( $option[ $key ] );
		return '' !== $clean ? $clean : $fallback;
	}

	/**
	 * Keep a permalink base made of letters, numbers, hyphens, and slashes.
	 *
	 * @param mixed $value Raw base.
	 * @return string
	 */
	private function sanitize_base( $value ): string {
		$base = strtolower( trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), '/' ) );
		$base = (string) preg_replace( '#/+#', '/', $base );
		if ( '' === $base || 1 !== preg_match( '#^[a-z0-9\-/]+$#', $base ) || mb_strlen( $base ) > 80 ) {
			return '';
		}

		return $base;
	}

	/**
	 * Keep a two-letter country code, or empty when it is not one.
	 *
	 * @param mixed $value Raw country.
	 * @return string
	 */
	private function sanitize_country( $value ): string {
		$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) ?? '' );
		if ( 1 === preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return $country;
		}

		return '';
	}

	/**
	 * Keep a three-letter currency code, or empty when it is not one.
	 *
	 * @param mixed $value Raw currency.
	 * @return string
	 */
	private function sanitize_currency( $value ): string {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) ?? '' );
		if ( 1 === preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			return $currency;
		}

		return '';
	}

	/**
	 * Keep a non-negative shipping price with two decimals. Empty means "not set".
	 *
	 * @param mixed $value Raw price.
	 * @return string
	 */
	private function sanitize_rate( $value ): string {
		$raw = trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return '';
		}

		$number = (float) $raw;
		if ( $number < 0 || $number > 1000000 ) {
			return '';
		}

		return number_format( $number, 2, '.', '' );
	}

	/**
	 * Read one day field when the input includes it.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param array<string, mixed> $state Current state.
	 * @param string               $key   Field name.
	 * @return int
	 */
	private function day_field( array $input, array $state, string $key ): int {
		if ( ! array_key_exists( $key, $input ) ) {
			return (int) $state[ $key ];
		}

		return $this->bounded_int( $input[ $key ], 0, 60 );
	}

	/**
	 * Swap a min/max pair when the minimum is greater than the maximum and both are set.
	 *
	 * @param array<string, mixed> $state Current state.
	 * @param string               $min   Minimum key.
	 * @param string               $max   Maximum key.
	 * @return array<string, mixed>
	 */
	private function order_day_pair( array $state, string $min, string $max ): array {
		$low  = (int) $state[ $min ];
		$high = (int) $state[ $max ];
		if ( $low > 0 && 0 === $high ) {
			$state[ $max ] = $low;
		} elseif ( $high > 0 && 0 === $low ) {
			$state[ $min ] = $high;
		} elseif ( $low > $high && $high > 0 ) {
			$state[ $min ] = $high;
			$state[ $max ] = $low;
		}

		return $state;
	}

	/**
	 * Clamp an integer.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Inclusive minimum.
	 * @param int   $max   Inclusive maximum.
	 * @return int
	 */
	private function bounded_int( $value, int $min, int $max ): int {
		$number = is_numeric( $value ) ? (int) $value : 0;
		if ( $number < $min ) {
			return $min;
		}
		if ( $number > $max ) {
			return $max;
		}

		return $number;
	}

	/**
	 * Schema.org token with only letters.
	 *
	 * @param mixed $value Raw token.
	 * @return string
	 */
	private function enum_token( $value ): string {
		return preg_replace( '/[^A-Za-z]/', '', (string) $value ) ?? '';
	}

	/**
	 * Coerce mixed input to a boolean.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, [ '1', 'true', 'yes', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $lower, [ '0', 'false', 'no', 'off', '' ], true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}
