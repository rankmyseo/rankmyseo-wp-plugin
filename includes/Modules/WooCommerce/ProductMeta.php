<?php
/**
 * Product and variation SEO meta.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

/**
 * Sanitizes brand, identifier, manufacturer number, and per-product policy overrides.
 */
final class ProductMeta {
	/**
	 * Brand name.
	 */
	public const BRAND = '_rankmyseo_brand';

	/**
	 * GTIN, EAN, UPC, or ISBN stored as digits (ISBN-10 may end in X).
	 */
	public const GTIN = '_rankmyseo_gtin';

	/**
	 * Manufacturer part number.
	 */
	public const MPN = '_rankmyseo_mpn';

	/**
	 * Per-product return policy override.
	 */
	public const RETURN_POLICY = '_rankmyseo_return_policy';

	/**
	 * Per-product shipping details override.
	 */
	public const SHIPPING = '_rankmyseo_shipping_details';

	/**
	 * Override modes.
	 *
	 * @var list<string>
	 */
	public const MODES = [ 'inherit', 'custom', 'none' ];

	/**
	 * Build the meta map saved on a product.
	 *
	 * @param array<string, mixed> $request Request fields.
	 * @return array<string, mixed>
	 */
	public static function fields_from_request( array $request ): array {
		return [
			self::BRAND         => self::sanitize_brand( $request['rankmyseo_brand'] ?? '' ),
			self::GTIN          => self::sanitize_identifier( $request['rankmyseo_gtin'] ?? '' ),
			self::MPN           => self::sanitize_mpn( $request['rankmyseo_mpn'] ?? '' ),
			self::RETURN_POLICY => self::return_override_from_request( $request ),
			self::SHIPPING      => self::shipping_override_from_request( $request ),
		];
	}

	/**
	 * Clean a brand name.
	 *
	 * @param mixed $value Raw brand.
	 * @return string
	 */
	public static function sanitize_brand( $value ): string {
		$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		if ( mb_strlen( $text ) > 100 ) {
			$text = mb_substr( $text, 0, 100 );
		}

		return $text;
	}

	/**
	 * Keep a GTIN-8/12/13/14 or an ISBN-10. Everything else is cleared.
	 *
	 * @param mixed $value Raw identifier.
	 * @return string
	 */
	public static function sanitize_identifier( $value ): string {
		$text = strtoupper( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) );
		$text = str_replace( [ ' ', '-' ], '', $text );
		if ( 1 === preg_match( '/^(?:\d{8}|\d{12}|\d{13}|\d{14}|\d{9}[\dX])$/', $text ) ) {
			return $text;
		}

		return '';
	}

	/**
	 * Keep a short manufacturer part number.
	 *
	 * @param mixed $value Raw part number.
	 * @return string
	 */
	public static function sanitize_mpn( $value ): string {
		$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		$text = preg_replace( '/[^A-Za-z0-9 ._\-\/]/', '', $text ) ?? '';
		$text = trim( $text );
		if ( mb_strlen( $text ) > 70 ) {
			$text = mb_substr( $text, 0, 70 );
		}

		return $text;
	}

	/**
	 * Sanitized return override stored on the product.
	 *
	 * @param array<string, mixed> $request Request fields.
	 * @return array<string, mixed>
	 */
	public static function return_override_from_request( array $request ): array {
		$mode = self::mode( $request['rankmyseo_return_mode'] ?? 'inherit' );

		return [
			'mode'     => $mode,
			'country'  => self::country( $request['rankmyseo_return_country'] ?? '' ),
			'category' => self::choice( $request['rankmyseo_return_category'] ?? '', WooSettings::RETURN_CATEGORIES, 'MerchantReturnFiniteReturnWindow' ),
			'days'     => self::bounded_int( $request['rankmyseo_return_days'] ?? 30, 0, 365 ),
			'method'   => self::choice( $request['rankmyseo_return_method'] ?? '', WooSettings::RETURN_METHODS, 'ReturnByMail' ),
			'fees'     => self::choice( $request['rankmyseo_return_fees'] ?? '', WooSettings::RETURN_FEES, 'FreeReturn' ),
			'url'      => esc_url_raw( trim( (string) ( $request['rankmyseo_return_url'] ?? '' ) ) ),
		];
	}

	/**
	 * Sanitized shipping override stored on the product.
	 *
	 * @param array<string, mixed> $request Request fields.
	 * @return array<string, mixed>
	 */
	public static function shipping_override_from_request( array $request ): array {
		$min_handling = self::bounded_int( $request['rankmyseo_shipping_handling_min'] ?? 0, 0, 60 );
		$max_handling = self::bounded_int( $request['rankmyseo_shipping_handling_max'] ?? 0, 0, 60 );
		$min_transit  = self::bounded_int( $request['rankmyseo_shipping_transit_min'] ?? 0, 0, 60 );
		$max_transit  = self::bounded_int( $request['rankmyseo_shipping_transit_max'] ?? 0, 0, 60 );

		return [
			'mode'         => self::mode( $request['rankmyseo_shipping_mode'] ?? 'inherit' ),
			'country'      => self::country( $request['rankmyseo_shipping_country'] ?? '' ),
			'rate'         => self::rate( $request['rankmyseo_shipping_rate'] ?? '' ),
			'currency'     => self::currency( $request['rankmyseo_shipping_currency'] ?? '' ),
			'handling_min' => min( $min_handling, $max_handling > 0 ? $max_handling : $min_handling ),
			'handling_max' => max( $min_handling, $max_handling ),
			'transit_min'  => min( $min_transit, $max_transit > 0 ? $max_transit : $min_transit ),
			'transit_max'  => max( $min_transit, $max_transit ),
		];
	}

	/**
	 * Read an override array from a product object or post meta.
	 *
	 * @param object $product Product or variation.
	 * @param string $key     Meta key.
	 * @return array<string, mixed>
	 */
	public static function read_array( object $product, string $key ): array {
		if ( method_exists( $product, 'get_meta' ) ) {
			$value = $product->get_meta( $key, true );
			if ( is_array( $value ) ) {
				return $value;
			}
		}

		if ( method_exists( $product, 'get_id' ) && function_exists( 'get_post_meta' ) ) {
			$id = $product->get_id();
			if ( is_numeric( $id ) ) {
				$stored = get_post_meta( (int) $id, $key, true );
				if ( is_array( $stored ) ) {
					return $stored;
				}
			}
		}

		return [];
	}

	/**
	 * Signals the on-page checker reads for one product.
	 *
	 * @param int $post_id Product post ID.
	 * @return array{sku: string, gtin: string, shortDescription: string, galleryAlts: list<string>}
	 */
	public static function signals_for_post( int $post_id ): array {
		$sku  = get_post_meta( $post_id, '_sku', true );
		$gtin = get_post_meta( $post_id, self::GTIN, true );
		if ( ! is_string( $gtin ) || '' === trim( $gtin ) ) {
			$gtin = get_post_meta( $post_id, '_global_unique_id', true );
		}

		$excerpt = '';
		$post    = get_post( $post_id );
		if ( $post instanceof \WP_Post ) {
			$excerpt = wp_strip_all_tags( (string) $post->post_excerpt );
		}

		$alts    = [];
		$gallery = get_post_meta( $post_id, '_product_image_gallery', true );
		if ( is_string( $gallery ) && '' !== $gallery ) {
			foreach ( explode( ',', $gallery ) as $attachment_id ) {
				$attachment_id = (int) $attachment_id;
				if ( $attachment_id <= 0 ) {
					continue;
				}
				$alt    = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$alts[] = is_string( $alt ) ? $alt : '';
			}
		}

		return [
			'sku'              => is_scalar( $sku ) ? trim( (string) $sku ) : '',
			'gtin'             => is_scalar( $gtin ) ? trim( (string) $gtin ) : '',
			'shortDescription' => trim( $excerpt ),
			'galleryAlts'      => $alts,
		];
	}

	/**
	 * Keep inherit, custom, or none.
	 *
	 * @param mixed $value Raw mode.
	 * @return string
	 */
	private static function mode( $value ): string {
		$mode = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
		if ( in_array( $mode, self::MODES, true ) ) {
			return $mode;
		}

		return 'inherit';
	}

	/**
	 * Two-letter country or empty.
	 *
	 * @param mixed $value Raw country.
	 * @return string
	 */
	private static function country( $value ): string {
		$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) ?? '' );
		if ( 1 === preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return $country;
		}

		return '';
	}

	/**
	 * Three-letter currency or empty.
	 *
	 * @param mixed $value Raw currency.
	 * @return string
	 */
	private static function currency( $value ): string {
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) ?? '' );
		if ( 1 === preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			return $currency;
		}

		return '';
	}

	/**
	 * Non-negative price or empty.
	 *
	 * @param mixed $value Raw price.
	 * @return string
	 */
	private static function rate( $value ): string {
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
	 * Pick an allowed token.
	 *
	 * @param mixed    $value    Raw token.
	 * @param string[] $allowed  Allowed tokens.
	 * @param string   $fallback Fallback token.
	 * @return string
	 */
	private static function choice( $value, array $allowed, string $fallback ): string {
		$token = preg_replace( '/[^A-Za-z]/', '', (string) $value ) ?? '';
		if ( in_array( $token, $allowed, true ) ) {
			return $token;
		}

		return $fallback;
	}

	/**
	 * Clamp an integer.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Inclusive minimum.
	 * @param int   $max   Inclusive maximum.
	 * @return int
	 */
	private static function bounded_int( $value, int $min, int $max ): int {
		$number = is_numeric( $value ) ? (int) $value : $min;
		if ( $number < $min ) {
			return $min;
		}
		if ( $number > $max ) {
			return $max;
		}

		return $number;
	}
}
