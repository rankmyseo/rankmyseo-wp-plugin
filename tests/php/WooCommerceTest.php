<?php
/**
 * WooCommerce store SEO pack.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\Schema\GraphBuilder;
use RankMySEO\Modules\Schema\SchemaContext;
use RankMySEO\Modules\WooCommerce\CatalogPaths;
use RankMySEO\Modules\WooCommerce\CatalogRobots;
use RankMySEO\Modules\WooCommerce\OfferPolicy;
use RankMySEO\Modules\WooCommerce\PermalinkBases;
use RankMySEO\Modules\WooCommerce\ProductDataTab;
use RankMySEO\Modules\WooCommerce\ProductMeta;
use RankMySEO\Modules\WooCommerce\ProductOpenGraph;
use RankMySEO\Modules\WooCommerce\WooCommerceController;
use RankMySEO\Modules\WooCommerce\WooCommerceModule;
use RankMySEO\Modules\WooCommerce\WooSettings;
use RankMySEO\Modules\Schema\WooProductReader;
use RankMySEO_Test_State;
use WP_Post;
use WP_REST_Request;

final class WooCommerceTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_settings_reject_bad_values_and_keep_hygiene_off(): void {
		$settings = new WooSettings();
		$clean    = $settings->sanitize(
			[
				'return_country'            => 'USA',
				'return_days'               => -4,
				'return_category'           => 'Nope',
				'return_method'             => 'Teleport',
				'return_fees'               => 'StoreCredit',
				'shipping_rate'             => '-3',
				'shipping_currency'         => 'US',
				'canonicalize_shop_filters' => 'yes',
			]
		);

		$this->assertSame( '', $clean['return_country'] );
		$this->assertSame( 0, $clean['return_days'] );
		$this->assertSame( 'MerchantReturnFiniteReturnWindow', $clean['return_category'] );
		$this->assertSame( 'ReturnByMail', $clean['return_method'] );
		$this->assertSame( 'FreeReturn', $clean['return_fees'] );
		$this->assertSame( '', $clean['shipping_rate'] );
		$this->assertSame( '', $clean['shipping_currency'] );
		$this->assertTrue( $clean['canonicalize_shop_filters'] );
		$this->assertFalse( $clean['noindex_shop_filters'] );
		$this->assertFalse( $clean['noindex_out_of_stock'] );
		$this->assertFalse( $clean['remove_product_base'] );
		$this->assertTrue( $clean['variation_offers'] );
	}

	public function test_turning_on_base_removal_captures_the_woocommerce_base(): void {
		RankMySEO_Test_State::$options['woocommerce_permalinks'] = [
			'product_base'  => 'shop',
			'category_base' => 'collections',
		];

		$captured = ( new WooSettings() )->update(
			[
				'remove_product_base'  => true,
				'remove_category_base' => true,
				'legacy_product_base'  => 'product',
				'legacy_category_base' => 'product-category',
			]
		);

		$this->assertSame( 'shop', $captured['legacy_product_base'] );
		$this->assertSame( 'collections', $captured['legacy_category_base'] );
	}

	public function test_an_edited_legacy_base_is_kept(): void {
		RankMySEO_Test_State::$options['woocommerce_permalinks'] = [
			'product_base' => 'shop',
		];

		$saved = ( new WooSettings() )->update(
			[
				'remove_product_base' => true,
				'legacy_product_base' => 'items',
			]
		);

		$this->assertSame( 'items', $saved['legacy_product_base'] );
	}

	public function test_product_meta_sanitizes_identifiers_and_policy_modes(): void {
		$fields = ProductMeta::fields_from_request(
			[
				'rankmyseo_brand'          => '  Acme <b>Co</b> ',
				'rankmyseo_gtin'           => '1234-5678',
				'rankmyseo_mpn'            => 'AB-12/9!',
				'rankmyseo_return_mode'    => 'custom',
				'rankmyseo_return_country' => 'us',
				'rankmyseo_return_days'    => 400,
				'rankmyseo_shipping_mode'  => 'nope',
				'rankmyseo_shipping_rate'  => '0',
			]
		);

		$this->assertSame( 'Acme Co', $fields[ ProductMeta::BRAND ] );
		$this->assertSame( '12345678', $fields[ ProductMeta::GTIN ] );
		$this->assertSame( 'AB-12/9', $fields[ ProductMeta::MPN ] );
		$this->assertSame( 'custom', $fields[ ProductMeta::RETURN_POLICY ]['mode'] );
		$this->assertSame( 'US', $fields[ ProductMeta::RETURN_POLICY ]['country'] );
		$this->assertSame( 365, $fields[ ProductMeta::RETURN_POLICY ]['days'] );
		$this->assertSame( 'inherit', $fields[ ProductMeta::SHIPPING ]['mode'] );
		$this->assertSame( '0.00', $fields[ ProductMeta::SHIPPING ]['rate'] );
		$this->assertSame( '', ProductMeta::sanitize_identifier( '12' ) );
		$this->assertSame( '123456789X', ProductMeta::sanitize_identifier( '123456789x' ) );
	}

	public function test_product_tab_save_requires_a_nonce_and_edit_access(): void {
		$product = new Woo_Fake_Product( 9, 'simple', '12', 'MUG', 'instock' );
		$tab     = new ProductDataTab(
			[
				'rankmyseo_product_seo_nonce' => 'nonce-rankmyseo_product_seo',
				'rankmyseo_brand'             => 'Acme',
				'rankmyseo_gtin'              => 'nope',
				'rankmyseo_mpn'               => 'M-1',
				'rankmyseo_return_mode'       => 'none',
			]
		);

		RankMySEO_Test_State::$nonce_valid = false;
		$tab->save_product( $product );
		$this->assertArrayNotHasKey( ProductMeta::BRAND, $product->meta );

		RankMySEO_Test_State::$nonce_valid                  = true;
		RankMySEO_Test_State::$current_user_caps['edit_posts'] = false;
		$tab->save_product( $product );
		$this->assertArrayNotHasKey( ProductMeta::BRAND, $product->meta );

		RankMySEO_Test_State::$current_user_caps['edit_posts'] = true;
		$tab->save_product( $product );
		$this->assertSame( 'Acme', $product->meta[ ProductMeta::BRAND ] );
		$this->assertSame( '', $product->meta[ ProductMeta::GTIN ] );
		$this->assertSame( 'none', $product->meta[ ProductMeta::RETURN_POLICY ]['mode'] );

		$valid = new ProductDataTab(
			[
				'rankmyseo_product_seo_nonce' => 'nonce-rankmyseo_product_seo',
				'rankmyseo_gtin'              => '00012345678905',
			]
		);
		$valid->save_product( $product );
		$this->assertSame( '00012345678905', $product->meta[ ProductMeta::GTIN ] );
		$this->assertSame( '00012345678905', $product->meta['_global_unique_id'] );

		$variation = new ProductDataTab(
			[
				'security'                 => 'nonce-save-variations',
				'rankmyseo_variation_gtin' => [ '11111114' ],
				'rankmyseo_variation_mpn'  => [ 'VAR-1' ],
			]
		);
		$variation->save_variation( 15, 0 );
		$this->assertSame( '11111114', get_post_meta( 15, ProductMeta::GTIN, true ) );
		$this->assertSame( 'VAR-1', get_post_meta( 15, ProductMeta::MPN, true ) );
	}

	public function test_product_tab_registers_admin_hooks_and_prints_fields(): void {
		$tab = new ProductDataTab();
		$tab->register();
		$this->assertArrayHasKey( 'woocommerce_product_data_tabs', RankMySEO_Test_State::$filters );
		$this->assertArrayHasKey( 'woocommerce_save_product_variation', RankMySEO_Test_State::$actions );

		$post               = new WP_Post();
		$post->ID           = 0;
		$post->post_excerpt = 'A wool hat.';
		RankMySEO_Test_State::$posts[0] = $post;

		ob_start();
		$tab->render_panel();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Barcode (GTIN, EAN, UPC, or ISBN)', $html );
		$this->assertStringContainsString( 'name="rankmyseo_product_seo_nonce"', $html );
	}

	public function test_variable_offers_follow_parent_stock_and_product_policy(): void {
		$store = new WooSettings();
		$store->update(
			[
				'return_policy_enabled' => false,
				'shipping_enabled'      => true,
				'shipping_country'      => 'US',
				'shipping_rate'         => '4.5',
				'shipping_currency'     => 'USD',
			]
		);

		$blue   = new Woo_Fake_Product( 11, 'variation', '10', 'BLUE', 'instock', [], [ ProductMeta::GTIN => '12345678' ] );
		$red    = new Woo_Fake_Product( 12, 'variation', '20', 'RED', 'outofstock' );
		$parent = new Woo_Fake_Product(
			10,
			'variable',
			'',
			'SHIRT',
			'instock',
			[ $blue, $red ],
			[
				ProductMeta::RETURN_POLICY => [
					'mode'     => 'custom',
					'country'  => 'DE',
					'category' => 'MerchantReturnFiniteReturnWindow',
					'days'     => 14,
					'method'   => 'ReturnByMail',
					'fees'     => 'FreeReturn',
				],
			]
		);

		$reader   = new WooProductReader(
			'USD',
			static function ( int $id ) use ( $parent ): ?object {
				return 10 === $id ? $parent : null;
			},
			new OfferPolicy( $store ),
			true
		);
		$snapshot = $reader->read( 10 );
		$this->assertNotNull( $snapshot );
		$this->assertCount( 2, $snapshot['variations'] );
		$this->assertSame( '10', $snapshot['variations'][0]['price'] );
		$this->assertSame( '12345678', $snapshot['variations'][0]['gtin'] );
		$this->assertSame( 'https://schema.org/InStock', $snapshot['availability'] );
		$this->assertSame( 'DE', $snapshot['return_policy']['applicableCountry'] );
		$this->assertSame( '4.50', $snapshot['shipping_details']['shippingRate']['value'] );

		$parent->stock = 'outofstock';
		$sold_out      = $reader->read( 10 );
		$this->assertSame( 'https://schema.org/OutOfStock', $sold_out['variations'][0]['availability'] );
		$this->assertSame( 'https://schema.org/OutOfStock', $sold_out['availability'] );

		$parent->meta[ ProductMeta::RETURN_POLICY ]['mode'] = 'none';
		$parent->stock                                      = 'instock';
		$hidden = $reader->read( 10 );
		$this->assertArrayNotHasKey( 'return_policy', $hidden );
		$this->assertArrayHasKey( 'shipping_details', $hidden );

		$plain = ( new WooProductReader(
			'USD',
			static function ( int $id ) use ( $parent ): ?object {
				return 10 === $id ? $parent : null;
			}
		) )->read( 10 );
		$this->assertArrayNotHasKey( 'variations', $plain );
		$this->assertSame( '10', $plain['low_price'] );
		$this->assertSame( '20', $plain['high_price'] );
	}

	public function test_graph_emits_variation_offers_with_return_and_shipping(): void {
		$context               = new SchemaContext();
		$context->home_url     = 'https://example.com/';
		$context->permalink    = 'https://example.com/shirt/';
		$context->schema_type  = 'Product';
		$context->title        = 'Shirt';
		$context->product      = [
			'name'             => 'Shirt',
			'currency'         => 'USD',
			'variations'       => [
				[
					'price'        => '10',
					'sku'          => 'S',
					'gtin'         => '12345678',
					'availability' => 'https://schema.org/InStock',
					'url'          => 'https://example.com/shirt/?size=s',
				],
				[
					'price'        => '20',
					'sku'          => 'L',
					'gtin'         => '9780306406157',
					'availability' => 'https://schema.org/OutOfStock',
					'url'          => 'https://example.com/shirt/?size=l',
				],
			],
			'return_policy'    => [
				'@type'             => 'MerchantReturnPolicy',
				'applicableCountry' => 'US',
			],
			'shipping_details' => [
				'@type' => 'OfferShippingDetails',
			],
		];

		$offer = ( new GraphBuilder() )->build( $context );
		$node  = $this->product_offers( $offer );
		$this->assertSame( 'AggregateOffer', $node['@type'] );
		$this->assertSame( '10.00', $node['lowPrice'] );
		$this->assertSame( '20.00', $node['highPrice'] );
		$this->assertSame( 2, $node['offerCount'] );
		$this->assertSame( 'https://schema.org/InStock', $node['availability'] );
		$this->assertSame( 'Offer', $node['offers'][0]['@type'] );
		$this->assertSame( 'S', $node['offers'][0]['sku'] );
		$this->assertSame( '12345678', $node['offers'][0]['gtin'] );
		$this->assertSame( '9780306406157', $node['offers'][1]['isbn'] );
		$this->assertSame( 'US', $node['hasMerchantReturnPolicy']['applicableCountry'] );
		$this->assertSame( 'OfferShippingDetails', $node['offers'][0]['shippingDetails']['@type'] );
	}

	public function test_catalog_urls_strip_filters_and_noindex_only_when_enabled(): void {
		$clean = CatalogPaths::strip_tracked_params(
			'https://example.com/shop/?orderby=price&min_price=1&max_price=9&filter_color=blue&query_type_color=or&ref=newsletter'
		);
		$this->assertStringNotContainsString( 'orderby', $clean );
		$this->assertStringNotContainsString( 'min_price', $clean );
		$this->assertStringNotContainsString( 'filter_color', $clean );
		$this->assertStringNotContainsString( 'query_type_color', $clean );
		$this->assertStringContainsString( 'ref=newsletter', $clean );

		$settings = new WooSettings();
		$context  = [
			'shop_archive' => true,
			'query'        => [ 'orderby' => 'date' ],
			'product'      => false,
			'out_of_stock' => false,
			'url'          => 'https://example.com/shop/',
		];
		$robots   = new CatalogRobots( $settings, static fn(): array => $context );
		$this->assertFalse( $robots->should_noindex( $context ) );
		$this->assertSame( 'https://example.com/shop/?orderby=date', $robots->filter_canonical( 'https://example.com/shop/?orderby=date' ) );

		$settings->update(
			[
				'noindex_shop_filters'      => true,
				'canonicalize_shop_filters' => true,
				'noindex_out_of_stock'      => true,
			]
		);
		$this->assertTrue( $robots->should_noindex( $context ) );
		$this->assertSame( 'https://example.com/shop/', $robots->filter_canonical( 'https://example.com/shop/?orderby=date' ) );

		$sold_out = [
			'shop_archive' => false,
			'query'        => [],
			'product'      => true,
			'out_of_stock' => true,
		];
		$this->assertTrue( $robots->should_noindex( $sold_out ) );
	}

	public function test_permalink_base_strips_links_and_redirects_the_old_base(): void {
		$settings = new WooSettings();
		$settings->update(
			[
				'remove_product_base' => true,
				'legacy_product_base' => 'product',
			]
		);
		$bases = new PermalinkBases(
			$settings,
			static function ( string $path, string $kind ): ?string {
				if ( 'product' === $kind && 'mug' === $path ) {
					return 'https://example.com/mug/';
				}
				return null;
			}
		);

		$post = (object) [ 'post_type' => 'product' ];
		$this->assertSame( 'https://example.com/mug/', $bases->filter_post_link( 'https://example.com/product/mug/', $post ) );
		$this->assertSame( 'https://example.com/mug/', $bases->redirect_target( '/product/mug/' ) );
		$this->assertNull( $bases->redirect_target( '/product/missing/' ) );
		$this->assertNull( $bases->redirect_target( 'https://example.com/mug/' ) );

		$resolved = $bases->filter_request( [ 'name' => 'mug' ] );
		$this->assertSame( 'product', $resolved['post_type'] );
		$untouched = $bases->filter_request(
			[
				'name'     => 'about',
				'pagename' => 'about',
			]
		);
		$this->assertSame( 'about', $untouched['pagename'] );
		$this->assertArrayNotHasKey( 'post_type', $untouched );
	}

	public function test_open_graph_tags_describe_price_stock_brand_and_sku(): void {
		$tags = ProductOpenGraph::tags_for_snapshot(
			[
				'price'        => '12.5',
				'currency'     => 'eur',
				'availability' => 'https://schema.org/OutOfStock',
				'brand'        => 'Acme',
				'sku'          => 'MUG-1',
			]
		);
		$map = [];
		foreach ( $tags as $tag ) {
			$map[ $tag['attrs']['property'] ] = $tag['attrs']['content'];
		}

		$this->assertSame( '12.50', $map['product:price:amount'] );
		$this->assertSame( 'EUR', $map['product:price:currency'] );
		$this->assertSame( 'oos', $map['product:availability'] );
		$this->assertSame( 'Acme', $map['product:brand'] );
		$this->assertSame( 'MUG-1', $map['product:retailer_item_id'] );
		$this->assertSame( 'pending', ProductOpenGraph::availability( 'https://schema.org/BackOrder' ) );
		$this->assertSame( 'instock', ProductOpenGraph::availability( 'https://schema.org/InStock' ) );
	}

	public function test_module_registers_nothing_when_woocommerce_is_inactive(): void {
		$module = new WooCommerceModule( new Container() );
		$module->register();
		$this->assertSame( [], RankMySEO_Test_State::$actions );
		$this->assertFalse( WooCommerceModule::is_woocommerce_active() );
	}

	public function test_settings_endpoint_saves_without_woocommerce(): void {
		$controller = new WooCommerceController( new WooSettings(), new Settings() );
		$read       = $controller->get_settings()->get_data();
		$this->assertFalse( $read['woocommerceActive'] );
		$this->assertTrue( $read['moduleEnabled'] );
		$this->assertTrue( $read['settings']['variation_offers'] );

		$request = new WP_REST_Request();
		$request->set_json_params(
			[
				'noindex_out_of_stock' => true,
				'return_country'       => 'usa',
			]
		);
		$saved = $controller->update_settings( $request )->get_data();
		$this->assertTrue( $saved['updated'] );
		$this->assertTrue( $saved['settings']['noindex_out_of_stock'] );
		$this->assertSame( '', $saved['settings']['return_country'] );
	}

	public function test_analyzer_signals_include_sku_gtin_excerpt_and_gallery_alts(): void {
		$post               = new WP_Post();
		$post->ID           = 8;
		$post->post_type    = 'product';
		$post->post_excerpt = '<p>A short wool hat for cold mornings in the city.</p>';
		RankMySEO_Test_State::$posts[8]    = $post;
		RankMySEO_Test_State::$post_meta[8] = [
			'_sku'                   => 'HAT-1',
			ProductMeta::GTIN        => '',
			'_global_unique_id'      => '12345678',
			'_product_image_gallery' => '3,4',
		];
		RankMySEO_Test_State::$post_meta[3] = [ '_wp_attachment_image_alt' => 'Red hat' ];
		RankMySEO_Test_State::$post_meta[4] = [ '_wp_attachment_image_alt' => '' ];

		$signals = ProductMeta::signals_for_post( 8 );
		$this->assertSame( 'HAT-1', $signals['sku'] );
		$this->assertSame( '12345678', $signals['gtin'] );
		$this->assertSame( 'A short wool hat for cold mornings in the city.', $signals['shortDescription'] );
		$this->assertSame( [ 'Red hat', '' ], $signals['galleryAlts'] );
	}

	/**
	 * Read a product object directly. The public reader only accepts an ID and a loader.
	 *
	 * @param array<string, mixed> $document Graph document.
	 * @return array<string, mixed>
	 */
	private function product_offers( array $document ): array {
		foreach ( $document['@graph'] as $node ) {
			if ( is_array( $node ) && isset( $node['@type'] ) && 'Product' === $node['@type'] ) {
				return is_array( $node['offers'] ) ? $node['offers'] : [];
			}
		}

		$this->fail( 'Product node was missing.' );
	}
}

/**
 * Small product stand-in. It is not the WooCommerce class.
 */
final class Woo_Fake_Product {
	/**
	 * @param list<object>         $children Variations.
	 * @param array<string, mixed> $meta     Meta values.
	 */
	public function __construct(
		public int $id,
		public string $type,
		public string $price,
		public string $sku,
		public string $stock,
		public array $children = [],
		public array $meta = []
	) {
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_type(): string {
		return $this->type;
	}

	public function get_name(): string {
		return 'Shirt';
	}

	public function get_sku(): string {
		return $this->sku;
	}

	public function get_price(): string {
		return $this->price;
	}

	public function get_stock_status(): string {
		return $this->stock;
	}

	public function is_in_stock(): bool {
		return 'outofstock' !== $this->stock;
	}

	/**
	 * @return list<object>
	 */
	public function get_children(): array {
		return $this->children;
	}

	/**
	 * @param mixed $single Unused.
	 * @return mixed
	 */
	public function get_meta( string $key, $single = true ) {
		unset( $single );
		return $this->meta[ $key ] ?? '';
	}

	public function get_permalink(): string {
		return 'https://example.com/?variation=' . $this->id;
	}

	/**
	 * @param mixed $value Meta value.
	 */
	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}

	public function set_global_unique_id( string $value ): void {
		$this->meta['_global_unique_id'] = $value;
	}

	/**
	 * @return array<string, array<int|string, float>>
	 */
	public function get_variation_prices(): array {
		$prices = [];
		foreach ( $this->children as $child ) {
			if ( is_object( $child ) && method_exists( $child, 'get_price' ) && is_numeric( $child->get_price() ) ) {
				$prices[ (string) $child->get_id() ] = (float) $child->get_price();
			}
		}

		return [ 'price' => $prices ];
	}
}
