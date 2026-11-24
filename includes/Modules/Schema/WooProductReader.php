<?php
/**
 * Reads a WooCommerce product into a schema snapshot.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

use RankMySEO\Modules\WooCommerce\OfferPolicy;
use WP_Comment;

/**
 * Maps a WooCommerce product object to the graph builder's product array.
 */
final class WooProductReader {
	/**
	 * Fallback ISO currency code.
	 *
	 * @var string
	 */
	private string $currency;

	/**
	 * Optional product loader used by tests. Receives a post ID and returns a product object or null.
	 *
	 * @var callable|null
	 */
	private $loader;

	/**
	 * Return and shipping policy builder. Null leaves existing snapshots unchanged.
	 *
	 * @var OfferPolicy|null
	 */
	private ?OfferPolicy $policies;

	/**
	 * Whether a variable product should list one offer per option.
	 *
	 * @var bool
	 */
	private bool $variation_offers;

	/**
	 * Constructor.
	 *
	 * @param string           $currency         Fallback currency when WooCommerce has none.
	 * @param callable|null    $loader           Optional loader `(int $post_id): ?object`.
	 * @param OfferPolicy|null $policies         Optional return and shipping policies.
	 * @param bool             $variation_offers Whether to emit one offer per variation.
	 */
	public function __construct( string $currency = 'USD', ?callable $loader = null, ?OfferPolicy $policies = null, bool $variation_offers = false ) {
		$this->currency         = $currency;
		$this->loader           = $loader;
		$this->policies         = $policies;
		$this->variation_offers = $variation_offers;
	}

	/**
	 * Read a product snapshot, or null when WooCommerce has no product for the post.
	 *
	 * @param int $post_id Product post ID.
	 * @return array<string, mixed>|null
	 */
	public function read( int $post_id ): ?array {
		$product = $this->load_product( $post_id );
		if ( ! is_object( $product ) ) {
			return null;
		}

		$currency = $this->currency;
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$shop = get_woocommerce_currency();
			if ( is_string( $shop ) && 1 === preg_match( '/^[A-Za-z]{3}$/', $shop ) ) {
				$currency = strtoupper( $shop );
			}
		}

		$snapshot = [
			'name'         => $this->call_string( $product, 'get_name' ),
			'description'  => $this->description( $product ),
			'sku'          => $this->call_string( $product, 'get_sku' ),
			'gtin'         => $this->gtin( $product ),
			'mpn'          => $this->first_meta( $product, [ '_rankmyseo_mpn', '_mpn' ] ),
			'brand'        => $this->first_meta( $product, [ '_rankmyseo_brand', '_brand' ] ),
			'image'        => $this->image_url( $product ),
			'currency'     => $currency,
			'availability' => $this->availability( $product ),
			'rating_value' => $this->rating_value( $product ),
			'review_count' => $this->call_int( $product, 'get_review_count' ),
			'reviews'      => $this->reviews( $post_id ),
			'price'        => '',
			'low_price'    => '',
			'high_price'   => '',
			'offer_count'  => 0,
		];

		if ( 'variable' === $this->call_string( $product, 'get_type' ) && $this->variation_offers ) {
			$children = $this->variation_offers( $product, (string) $snapshot['availability'] );
			if ( count( $children ) > 1 ) {
				$prices                   = $this->offer_prices( $children );
				$snapshot['low_price']    = (string) min( $prices );
				$snapshot['high_price']   = (string) max( $prices );
				$snapshot['offer_count']  = count( $children );
				$snapshot['variations']   = $children;
				$snapshot['availability'] = $this->summarize_availability( $children, (string) $snapshot['availability'] );
				return $this->with_policies( $snapshot, $product );
			}
			if ( 1 === count( $children ) ) {
				$snapshot['price']        = (string) $children[0]['price'];
				$snapshot['availability'] = (string) $children[0]['availability'];
				$snapshot['variations']   = $children;
				return $this->with_policies( $snapshot, $product );
			}
		}

		if ( 'variable' === $this->call_string( $product, 'get_type' ) ) {
			$prices = $this->variation_prices( $product );
			if ( count( $prices ) > 1 ) {
				$snapshot['low_price']   = (string) min( $prices );
				$snapshot['high_price']  = (string) max( $prices );
				$snapshot['offer_count'] = count( $prices );
				return $this->with_policies( $snapshot, $product );
			}
		}

		$snapshot['price'] = $this->call_string( $product, 'get_price' );

		return $this->with_policies( $snapshot, $product );
	}

	/**
	 * One offer per variation, capped so a huge catalog cannot bloat the page.
	 *
	 * @param object $product              Variable product.
	 * @param string $parent_availability  Parent availability URL.
	 * @return list<array<string, string>>
	 */
	private function variation_offers( object $product, string $parent_availability ): array {
		if ( ! method_exists( $product, 'get_children' ) ) {
			return [];
		}

		$children = $product->get_children();
		if ( ! is_array( $children ) ) {
			return [];
		}

		$parent_id = $this->call_int( $product, 'get_id' );
		$forced    = str_contains( $parent_availability, 'OutOfStock' );
		$offers    = [];
		foreach ( $children as $child ) {
			if ( count( $offers ) >= 50 ) {
				break;
			}
			$variation = is_object( $child ) ? $child : $this->load_product( (int) $child );
			if ( ! is_object( $variation ) ) {
				continue;
			}
			$variation_id = $this->call_int( $variation, 'get_id' );
			if ( $variation_id > 0 && $variation_id === $parent_id ) {
				continue;
			}
			$price = $this->call_string( $variation, 'get_price' );
			if ( '' === $price || ! is_numeric( $price ) ) {
				continue;
			}

			$gtin = $this->gtin( $variation );
			if ( '' === $gtin ) {
				$gtin = $this->gtin( $product );
			}
			$mpn = $this->first_meta( $variation, [ '_rankmyseo_mpn', '_mpn' ] );
			if ( '' === $mpn ) {
				$mpn = $this->first_meta( $product, [ '_rankmyseo_mpn', '_mpn' ] );
			}

			$offers[] = [
				'price'        => $price,
				'sku'          => $this->call_string( $variation, 'get_sku' ),
				'gtin'         => $gtin,
				'mpn'          => $mpn,
				'availability' => $forced ? 'https://schema.org/OutOfStock' : $this->availability( $variation ),
				'url'          => $this->permalink( $variation ),
			];
		}

		return $offers;
	}

	/**
	 * Numeric prices from variation offers.
	 *
	 * @param list<array<string, string>> $offers Variation offers.
	 * @return list<float>
	 */
	private function offer_prices( array $offers ): array {
		$prices = [];
		foreach ( $offers as $offer ) {
			if ( isset( $offer['price'] ) && is_numeric( $offer['price'] ) ) {
				$prices[] = (float) $offer['price'];
			}
		}

		return [] !== $prices ? $prices : [ 0.0 ];
	}

	/**
	 * In stock when any option can be bought, otherwise backorder, otherwise sold out.
	 *
	 * @param list<array<string, string>> $offers   Variation offers.
	 * @param string                      $fallback Parent availability.
	 * @return string
	 */
	private function summarize_availability( array $offers, string $fallback ): string {
		$in_stock   = false;
		$back_order = false;
		foreach ( $offers as $offer ) {
			$value = (string) ( $offer['availability'] ?? '' );
			if ( str_contains( $value, 'OutOfStock' ) ) {
				continue;
			}
			if ( str_contains( $value, 'BackOrder' ) ) {
				$back_order = true;
				continue;
			}
			if ( str_contains( $value, 'InStock' ) ) {
				$in_stock = true;
			}
		}

		if ( $in_stock ) {
			return 'https://schema.org/InStock';
		}
		if ( $back_order ) {
			return 'https://schema.org/BackOrder';
		}
		if ( [] !== $offers ) {
			return 'https://schema.org/OutOfStock';
		}

		return $fallback;
	}

	/**
	 * Variation permalink.
	 *
	 * @param object $variation Variation product.
	 * @return string
	 */
	private function permalink( object $variation ): string {
		if ( method_exists( $variation, 'get_permalink' ) ) {
			$url = $variation->get_permalink();
			if ( is_string( $url ) ) {
				return $url;
			}
		}

		if ( function_exists( 'get_permalink' ) ) {
			$url = get_permalink( $variation );
			return is_string( $url ) ? $url : '';
		}

		return '';
	}

	/**
	 * Attach return and shipping nodes when a policy builder was provided.
	 *
	 * @param array<string, mixed> $snapshot Product snapshot.
	 * @param object               $product  Product object.
	 * @return array<string, mixed>
	 */
	private function with_policies( array $snapshot, object $product ): array {
		if ( null === $this->policies ) {
			return $snapshot;
		}

		$nodes = $this->policies->for_product( $product );
		if ( isset( $nodes['return_policy'] ) && is_array( $nodes['return_policy'] ) && isset( $nodes['return_policy']['@type'] ) ) {
			$snapshot['return_policy'] = $nodes['return_policy'];
		}
		if ( isset( $nodes['shipping_details'] ) && is_array( $nodes['shipping_details'] ) && isset( $nodes['shipping_details']['@type'] ) ) {
			$snapshot['shipping_details'] = $nodes['shipping_details'];
		}

		return $snapshot;
	}

	/**
	 * Load a product object.
	 *
	 * @param int $post_id Product post ID.
	 * @return object|null
	 */
	private function load_product( int $post_id ): ?object {
		if ( null !== $this->loader ) {
			$loaded = ( $this->loader )( $post_id );
			return is_object( $loaded ) ? $loaded : null;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $post_id );
		return is_object( $product ) ? $product : null;
	}

	/**
	 * Prefer the short description, then the full description.
	 *
	 * @param object $product Product object.
	 * @return string
	 */
	private function description( object $product ): string {
		$short = $this->call_string( $product, 'get_short_description' );
		if ( '' !== $short ) {
			return wp_strip_all_tags( $short );
		}

		return wp_strip_all_tags( $this->call_string( $product, 'get_description' ) );
	}

	/**
	 * Read a GTIN from WooCommerce's global unique id or common meta keys.
	 *
	 * @param object $product Product object.
	 * @return string
	 */
	private function gtin( object $product ): string {
		$global = $this->call_string( $product, 'get_global_unique_id' );
		if ( '' !== $global ) {
			return $global;
		}

		return $this->first_meta( $product, [ '_rankmyseo_gtin', '_gtin', '_global_unique_id' ] );
	}

	/**
	 * First non-empty meta value.
	 *
	 * @param object   $product Product object.
	 * @param string[] $keys    Meta keys in priority order.
	 * @return string
	 */
	private function first_meta( object $product, array $keys ): string {
		if ( ! method_exists( $product, 'get_meta' ) ) {
			return '';
		}

		foreach ( $keys as $key ) {
			$value = $product->get_meta( $key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		return '';
	}

	/**
	 * Featured image URL.
	 *
	 * @param object $product Product object.
	 * @return string
	 */
	private function image_url( object $product ): string {
		$image_id = $this->call_int( $product, 'get_image_id' );
		if ( $image_id <= 0 || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, 'full' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Schema.org availability URL.
	 *
	 * @param object $product Product object.
	 * @return string
	 */
	private function availability( object $product ): string {
		$status = $this->call_string( $product, 'get_stock_status' );
		if ( 'outofstock' === $status ) {
			return 'https://schema.org/OutOfStock';
		}
		if ( 'onbackorder' === $status ) {
			return 'https://schema.org/BackOrder';
		}
		if ( method_exists( $product, 'is_in_stock' ) && false === $product->is_in_stock() ) {
			return 'https://schema.org/OutOfStock';
		}

		return 'https://schema.org/InStock';
	}

	/**
	 * Average rating with one decimal, or an empty string.
	 *
	 * @param object $product Product object.
	 * @return string
	 */
	private function rating_value( object $product ): string {
		$raw = $this->call_string( $product, 'get_average_rating' );
		if ( '' === $raw || ! is_numeric( $raw ) || (float) $raw <= 0 ) {
			return '';
		}

		return number_format( (float) $raw, 1, '.', '' );
	}

	/**
	 * Numeric variation prices.
	 *
	 * @param object $product Variable product.
	 * @return list<float>
	 */
	private function variation_prices( object $product ): array {
		if ( ! method_exists( $product, 'get_variation_prices' ) ) {
			return [];
		}

		$prices = $product->get_variation_prices( true );
		if ( ! is_array( $prices ) || ! isset( $prices['price'] ) || ! is_array( $prices['price'] ) ) {
			return [];
		}

		$values = [];
		foreach ( $prices['price'] as $price ) {
			if ( is_numeric( $price ) ) {
				$values[] = (float) $price;
			}
		}

		return $values;
	}

	/**
	 * Approved product reviews.
	 *
	 * @param int $post_id Product post ID.
	 * @return list<array{author: string, body: string, rating: string, date: string}>
	 */
	private function reviews( int $post_id ): array {
		if ( ! function_exists( 'get_comments' ) ) {
			return [];
		}

		$comments = get_comments(
			[
				'post_id' => $post_id,
				'status'  => 'approve',
				'type'    => 'review',
				'number'  => 5,
			]
		);
		if ( ! is_array( $comments ) ) {
			return [];
		}

		$reviews = [];
		foreach ( $comments as $comment ) {
			if ( ! $comment instanceof WP_Comment ) {
				continue;
			}
			$body = wp_strip_all_tags( (string) $comment->comment_content );
			if ( '' === trim( $body ) ) {
				continue;
			}
			$rating = '';
			if ( function_exists( 'get_comment_meta' ) ) {
				$meta = get_comment_meta( (int) $comment->comment_ID, 'rating', true );
				if ( is_numeric( $meta ) ) {
					$rating = (string) $meta;
				}
			}
			$date = '';
			if ( is_string( $comment->comment_date_gmt ) && '' !== $comment->comment_date_gmt ) {
				$timestamp = strtotime( $comment->comment_date_gmt . ' GMT' );
				if ( false !== $timestamp ) {
					$date = gmdate( 'c', $timestamp );
				}
			}
			$reviews[] = [
				'author' => (string) $comment->comment_author,
				'body'   => $body,
				'rating' => $rating,
				'date'   => $date,
			];
		}

		return $reviews;
	}

	/**
	 * Call a no-argument method and cast the result to string.
	 *
	 * @param object $subject Product object.
	 * @param string $method  Method name.
	 * @return string
	 */
	private function call_string( object $subject, string $method ): string {
		if ( ! method_exists( $subject, $method ) ) {
			return '';
		}

		$value = $subject->{$method}();
		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			return trim( (string) $value );
		}

		return '';
	}

	/**
	 * Call a no-argument method and cast the result to int.
	 *
	 * @param object $subject Product object.
	 * @param string $method  Method name.
	 * @return int
	 */
	private function call_int( object $subject, string $method ): int {
		if ( ! method_exists( $subject, $method ) ) {
			return 0;
		}

		$value = $subject->{$method}();
		return is_numeric( $value ) ? (int) $value : 0;
	}
}
