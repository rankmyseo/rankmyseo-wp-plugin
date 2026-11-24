<?php
/**
 * Product Open Graph tags.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

use RankMySEO\Modules\Schema\WooProductReader;

/**
 * Adds product price, currency, availability, brand, and retailer id to the head.
 */
final class ProductOpenGraph {
	/**
	 * Store settings.
	 *
	 * @var WooSettings
	 */
	private WooSettings $settings;

	/**
	 * Optional snapshot provider for tests.
	 *
	 * @var callable|null
	 */
	private $provider;

	/**
	 * Whether RankMySEO's own head block already received these tags.
	 *
	 * @var bool
	 */
	private bool $attached = false;

	/**
	 * Constructor.
	 *
	 * @param WooSettings   $settings Store settings.
	 * @param callable|null $provider Optional `(): ?array` product snapshot.
	 */
	public function __construct( WooSettings $settings, ?callable $provider = null ) {
		$this->settings = $settings;
		$this->provider = $provider;
	}

	/**
	 * Register head filters. No stylesheet or script is added.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'rankmyseo/head/tags', [ $this, 'filter_tags' ] ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public extension point shared with the meta module.
		add_action( 'wp_head', [ $this, 'render_fallback' ], 2 );
	}

	/**
	 * Append product tags and mark the page type as a product.
	 *
	 * @param array<int, mixed> $tags Head tags.
	 * @return array<int, mixed>
	 */
	public function filter_tags( array $tags ): array {
		$this->attached = true;
		$extra          = $this->product_tags();
		if ( [] === $extra ) {
			return $tags;
		}

		foreach ( $tags as $index => $tag ) {
			if ( ! is_array( $tag ) || ! isset( $tag['attrs'] ) || ! is_array( $tag['attrs'] ) ) {
				continue;
			}
			if ( isset( $tag['attrs']['property'] ) && 'og:type' === $tag['attrs']['property'] ) {
				$tags[ $index ]['attrs']['content'] = 'product';
			}
		}

		return array_merge( $tags, $extra );
	}

	/**
	 * Print product tags when the meta module is not already printing the head.
	 *
	 * @return void
	 */
	public function render_fallback(): void {
		if ( $this->attached || $this->another_plugin_owns_head() ) {
			return;
		}

		$html = $this->render( $this->product_tags() );
		if ( '' === $html ) {
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render().
	}

	/**
	 * Product meta tags for a snapshot.
	 *
	 * @param array<string, mixed> $product Product snapshot.
	 * @return list<array{tag: string, escape: string, attrs: array<string, string>}>
	 */
	public static function tags_for_snapshot( array $product ): array {
		$tags = [];

		$price = '';
		if ( isset( $product['price'] ) && is_numeric( $product['price'] ) ) {
			$price = number_format( (float) $product['price'], 2, '.', '' );
		} elseif ( isset( $product['low_price'] ) && is_numeric( $product['low_price'] ) ) {
			$price = number_format( (float) $product['low_price'], 2, '.', '' );
		}

		$currency = strtoupper( (string) ( $product['currency'] ?? '' ) );
		if ( '' !== $price && 1 === preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$tags[] = self::property_tag( 'product:price:amount', $price );
			$tags[] = self::property_tag( 'product:price:currency', $currency );
		}

		$tags[] = self::property_tag( 'product:availability', self::availability( (string) ( $product['availability'] ?? '' ) ) );

		$brand = trim( (string) ( $product['brand'] ?? '' ) );
		if ( '' !== $brand ) {
			$tags[] = self::property_tag( 'product:brand', $brand );
		}

		$item_id = trim( (string) ( $product['sku'] ?? '' ) );
		if ( '' === $item_id ) {
			$item_id = trim( (string) ( $product['gtin'] ?? '' ) );
		}
		if ( '' !== $item_id ) {
			$tags[] = self::property_tag( 'product:retailer_item_id', $item_id );
		}

		return $tags;
	}

	/**
	 * Map a schema availability URL onto a product share value.
	 *
	 * @param string $availability Schema URL or stock status.
	 * @return string
	 */
	public static function availability( string $availability ): string {
		$value = strtolower( $availability );
		if ( str_contains( $value, 'outofstock' ) ) {
			return 'oos';
		}
		if ( str_contains( $value, 'backorder' ) ) {
			return 'pending';
		}

		return 'instock';
	}

	/**
	 * Tags for the current product, or an empty list when the feature is off.
	 *
	 * @return list<array{tag: string, escape: string, attrs: array<string, string>}>
	 */
	private function product_tags(): array {
		if ( ! $this->settings->open_graph_enabled() ) {
			return [];
		}

		$product = $this->snapshot();
		if ( null === $product ) {
			return [];
		}

		return self::tags_for_snapshot( $product );
	}

	/**
	 * Current product snapshot.
	 *
	 * @return array<string, mixed>|null
	 */
	private function snapshot(): ?array {
		if ( null !== $this->provider ) {
			$provided = ( $this->provider )();
			return is_array( $provided ) ? $provided : null;
		}

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return null;
		}

		$product_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( $product_id <= 0 ) {
			return null;
		}

		$reader = new WooProductReader( 'USD', null, new OfferPolicy(), true );
		return $reader->read( $product_id );
	}

	/**
	 * Whether another SEO plugin is already printing social tags.
	 *
	 * @return bool
	 */
	private function another_plugin_owns_head(): bool {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| class_exists( 'WPSEO_Frontend' )
			|| class_exists( 'RankMath' )
			|| class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
	}

	/**
	 * Escape and join product tags.
	 *
	 * @param list<array{tag: string, escape: string, attrs: array<string, string>}> $tags Tags.
	 * @return string
	 */
	private function render( array $tags ): string {
		$lines = [];
		foreach ( $tags as $tag ) {
			$property = $tag['attrs']['property'] ?? '';
			$content  = $tag['attrs']['content'] ?? '';
			if ( '' === $property || '' === $content ) {
				continue;
			}
			$lines[] = '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '" />';
		}

		if ( [] === $lines ) {
			return '';
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * One product property tag.
	 *
	 * @param string $property Property name.
	 * @param string $content  Property value.
	 * @return array{tag: string, escape: string, attrs: array<string, string>}
	 */
	private static function property_tag( string $property, string $content ): array {
		return [
			'tag'    => 'meta',
			'escape' => 'text',
			'attrs'  => [
				'property' => $property,
				'content'  => $content,
			],
		];
	}
}
