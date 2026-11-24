<?php
/**
 * Unit tests for the Schema.org JSON-LD graph engine.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\Schema\ConflictScanner;
use RankMySEO\Modules\Schema\ContextFactory;
use RankMySEO\Modules\Schema\Frontend;
use RankMySEO\Modules\Schema\GraphBuilder;
use RankMySEO\Modules\Schema\SchemaContext;
use RankMySEO\Modules\Schema\SchemaController;
use RankMySEO\Modules\Schema\SchemaMeta;
use RankMySEO\Modules\Schema\SchemaModule;
use RankMySEO\Modules\Schema\SchemaSettings;
use RankMySEO\Modules\Schema\ScriptRenderer;
use RankMySEO\Modules\Schema\WooCommerceSuppressor;
use RankMySEO\Modules\Schema\WooProductReader;
use RankMySEO\Rest\RestRegistry;
use RankMySEO_Test_State;
use WP_Comment;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_Term;

/**
 * @covers \RankMySEO\Modules\Schema\GraphBuilder
 */
final class SchemaGraphTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_homepage_graph_links_organization_website_webpage_and_breadcrumb(): void {
		$document = ( new GraphBuilder() )->build( $this->homepage_context() );

		$this->assertSame( 'https://schema.org', $document['@context'] );
		$organization = $this->node( $document, 'Organization' );
		$website      = $this->node( $document, 'WebSite' );
		$webpage      = $this->node( $document, 'WebPage' );
		$breadcrumb   = $this->node( $document, 'BreadcrumbList' );

		$this->assertSame( 'https://example.com/#organization', $organization['@id'] );
		$this->assertSame( 'https://example.com/#website', $website['@id'] );
		$this->assertSame( 'https://example.com/#webpage', $webpage['@id'] );
		$this->assertSame( 'https://example.com/#breadcrumb', $breadcrumb['@id'] );
		$this->assertSame( 'https://example.com/#organization', $website['publisher']['@id'] );
		$this->assertSame( 'https://example.com/#website', $webpage['isPartOf']['@id'] );
		$this->assertSame( 'https://example.com/#breadcrumb', $webpage['breadcrumb']['@id'] );
		$this->assertSame( 'https://example.com/logo.png', $organization['logo']['url'] );
		$this->assertSame( [ 'https://example.com/social' ], $organization['sameAs'] );
		$this->assertSame( 'SearchAction', $website['potentialAction']['@type'] );
		$this->assertSame(
			'https://example.com/?s={search_term_string}',
			$website['potentialAction']['target']['urlTemplate']
		);
		$this->assertSame( 1, $breadcrumb['itemListElement'][0]['position'] );
		$this->assertSame( 'Home', $breadcrumb['itemListElement'][0]['name'] );
	}

	public function test_person_identity_uses_person_id_and_image(): void {
		$context               = $this->homepage_context();
		$context->entity_type  = 'person';
		$document              = ( new GraphBuilder() )->build( $context );
		$person                = $this->node( $document, 'Person' );

		$this->assertSame( 'https://example.com/#person', $person['@id'] );
		$this->assertSame( 'https://example.com/logo.png', $person['image']['url'] );
		$this->assertArrayNotHasKey( 'logo', $person );
		$this->assertSame( 'https://example.com/#person', $this->node( $document, 'WebSite' )['publisher']['@id'] );
	}

	public function test_article_node_links_author_dates_image_and_word_count(): void {
		$context                    = $this->homepage_context();
		$context->permalink         = 'https://example.com/hello-world';
		$context->schema_type       = 'BlogPosting';
		$context->title             = 'Hello World';
		$context->description       = 'A short post';
		$context->date_published    = '2026-01-01T00:00:00+00:00';
		$context->date_modified     = '2026-01-02T00:00:00+00:00';
		$context->word_count        = 120;
		$context->image_url         = 'https://example.com/cover.jpg';
		$context->article_section   = 'News';
		$context->author_name       = 'Ada Lovelace';
		$context->breadcrumbs       = [
			[
				'name' => 'Home',
				'url'  => 'https://example.com/',
			],
			[
				'name' => 'News',
				'url'  => 'https://example.com/category/news/',
			],
			[
				'name' => 'Hello World',
				'url'  => 'https://example.com/hello-world',
			],
		];

		$document = ( new GraphBuilder() )->build( $context );
		$article  = $this->node( $document, 'BlogPosting' );
		$webpage  = $this->node( $document, 'WebPage' );
		$author   = $this->node_by_id( $document, 'https://example.com/hello-world#author' );
		$crumbs   = $this->node( $document, 'BreadcrumbList' );

		$this->assertSame( 'https://example.com/hello-world#article', $article['@id'] );
		$this->assertSame( 'https://example.com/hello-world#webpage', $article['mainEntityOfPage']['@id'] );
		$this->assertSame( 'https://example.com/#organization', $article['publisher']['@id'] );
		$this->assertSame( 'https://example.com/hello-world#author', $article['author']['@id'] );
		$this->assertSame( 'Ada Lovelace', $author['name'] );
		$this->assertSame( 'Person', $author['@type'] );
		$this->assertSame( 120, $article['wordCount'] );
		$this->assertSame( 'https://example.com/cover.jpg', $article['image']['url'] );
		$this->assertSame( 'News', $article['articleSection'] );
		$this->assertSame( '2026-01-01T00:00:00+00:00', $article['datePublished'] );
		$this->assertSame( 'https://example.com/hello-world#article', $webpage['mainEntity']['@id'] );
		$this->assertSame( 3, count( $crumbs['itemListElement'] ) );
		$this->assertSame( 2, $crumbs['itemListElement'][1]['position'] );
	}

	public function test_none_schema_type_omits_article_and_faq(): void {
		$context              = $this->homepage_context();
		$context->schema_type = 'none';
		$context->author_name = 'Ada';
		$context->faqs        = [
			[
				'question' => 'What is this?',
				'answer'   => 'A page.',
			],
		];

		$document = ( new GraphBuilder() )->build( $context );
		$this->assertNull( $this->find_node( $document, 'BlogPosting' ) );
		$this->assertNull( $this->find_node( $document, 'FAQPage' ) );
		$this->assertNotNull( $this->find_node( $document, 'WebPage' ) );
	}

	public function test_faq_and_local_business_pieces(): void {
		$context                 = $this->homepage_context();
		$context->permalink      = 'https://example.com/visit';
		$context->schema_type    = 'FAQPage';
		$context->faqs           = [
			[
				'question' => 'Where are you?',
				'answer'   => 'Downtown.',
			],
		];
		$context->local_business = [
			'type'          => 'Restaurant',
			'name'          => 'Acme Cafe',
			'telephone'     => '+1-555-0199',
			'price_range'   => '$$',
			'image'         => 'https://example.com/logo.png',
			'street'        => '1 Main St',
			'city'          => 'Austin',
			'region'        => 'TX',
			'postal_code'   => '78701',
			'country'       => 'US',
			'latitude'      => '30.267200',
			'longitude'     => '-97.743100',
			'opening_hours' => [
				[
					'day'    => 'Monday',
					'opens'  => '09:00',
					'closes' => '17:00',
				],
			],
		];

		$document = ( new GraphBuilder() )->build( $context );
		$faq      = $this->node( $document, 'FAQPage' );
		$local    = $this->node( $document, 'Restaurant' );

		$this->assertSame( 'https://example.com/visit#faq', $faq['@id'] );
		$this->assertSame( 'Where are you?', $faq['mainEntity'][0]['name'] );
		$this->assertSame( 'Downtown.', $faq['mainEntity'][0]['acceptedAnswer']['text'] );
		$this->assertSame( 'https://example.com/#localbusiness', $local['@id'] );
		$this->assertSame( '1 Main St', $local['address']['streetAddress'] );
		$this->assertSame( '30.267200', $local['geo']['latitude'] );
		$this->assertSame( '$$', $local['priceRange'] );
		$this->assertSame( 'https://schema.org/Monday', $local['openingHoursSpecification'][0]['dayOfWeek'] );
		$this->assertSame( '09:00', $local['openingHoursSpecification'][0]['opens'] );
		$this->assertSame( 'https://example.com/#organization', $local['parentOrganization']['@id'] );
		$this->assertSame( 'https://example.com/visit#faq', $this->node( $document, 'WebPage' )['mainEntity']['@id'] );
	}

	public function test_simple_and_variable_product_offers(): void {
		$simple                 = $this->homepage_context();
		$simple->permalink      = 'https://example.com/mug';
		$simple->schema_type    = 'Product';
		$simple->title          = 'Mug';
		$simple->product        = [
			'name'         => 'Mug',
			'description'  => 'A ceramic mug',
			'sku'          => 'MUG-1',
			'gtin'         => '12345678',
			'mpn'          => 'MPN-9',
			'brand'        => 'Acme',
			'image'        => 'https://example.com/mug.jpg',
			'currency'     => 'USD',
			'price'        => '12',
			'availability' => 'https://schema.org/InStock',
			'rating_value' => '4.5',
			'review_count' => 2,
			'reviews'      => [
				[
					'author' => 'Sam',
					'body'   => 'Great mug',
					'rating' => '5',
					'date'   => '2026-02-01T00:00:00+00:00',
				],
			],
		];

		$document = ( new GraphBuilder() )->build( $simple );
		$product  = $this->node( $document, 'Product' );
		$this->assertSame( 'https://example.com/mug#product', $product['@id'] );
		$this->assertSame( 'https://example.com/mug#webpage', $product['mainEntityOfPage']['@id'] );
		$this->assertSame( '12345678', $product['gtin'] );
		$this->assertSame( 'MPN-9', $product['mpn'] );
		$this->assertSame( 'Offer', $product['offers']['@type'] );
		$this->assertSame( '12.00', $product['offers']['price'] );
		$this->assertSame( 'USD', $product['offers']['priceCurrency'] );
		$this->assertSame( 'https://schema.org/InStock', $product['offers']['availability'] );
		$this->assertSame( '4.5', $product['aggregateRating']['ratingValue'] );
		$this->assertSame( 2, $product['aggregateRating']['reviewCount'] );
		$this->assertSame( 'Sam', $product['review'][0]['author']['name'] );

		$variable              = $this->homepage_context();
		$variable->schema_type = 'Product';
		$variable->product     = [
			'name'         => 'Shirt',
			'currency'     => 'EUR',
			'low_price'    => '10',
			'high_price'   => '40',
			'offer_count'  => 3,
			'availability' => 'https://schema.org/InStock',
		];
		$variable_doc = ( new GraphBuilder() )->build( $variable );
		$offer        = $this->node( $variable_doc, 'Product' )['offers'];
		$this->assertSame( 'AggregateOffer', $offer['@type'] );
		$this->assertSame( '10.00', $offer['lowPrice'] );
		$this->assertSame( '40.00', $offer['highPrice'] );
		$this->assertSame( 3, $offer['offerCount'] );
	}

	public function test_woocommerce_reader_maps_product_and_reviews(): void {
		RankMySEO_Test_State::$thumbnails[9] = 'https://example.com/mug.jpg';
		$comment                             = new WP_Comment();
		$comment->comment_ID                 = 4;
		$comment->comment_author             = 'Sam';
		$comment->comment_content            = '<p>Great mug</p>';
		$comment->comment_date_gmt           = '2026-02-01 00:00:00';
		RankMySEO_Test_State::$comments      = [ $comment ];
		RankMySEO_Test_State::$comment_meta  = [ 4 => [ 'rating' => '5' ] ];

		$product = new class() {
			public function get_name(): string {
				return 'Mug';
			}
			public function get_sku(): string {
				return 'MUG-1';
			}
			public function get_short_description(): string {
				return 'A mug';
			}
			public function get_description(): string {
				return '';
			}
			public function get_type(): string {
				return 'simple';
			}
			public function get_price(): string {
				return '12.5';
			}
			public function get_stock_status(): string {
				return 'instock';
			}
			public function is_in_stock(): bool {
				return true;
			}
			public function get_average_rating(): string {
				return '4.5';
			}
			public function get_review_count(): int {
				return 1;
			}
			public function get_image_id(): int {
				return 9;
			}
			public function get_global_unique_id(): string {
				return '12345670';
			}
			/** @return mixed */
			public function get_meta( string $key, bool $single = true ) {
				unset( $single );
				$map = [
					'_mpn'   => 'MPN-9',
					'_brand' => 'Acme',
				];
				return $map[ $key ] ?? '';
			}
		};

		$reader   = new WooProductReader( 'USD', static fn() => $product );
		$snapshot = $reader->read( 15 );

		$this->assertIsArray( $snapshot );
		$this->assertSame( 'Mug', $snapshot['name'] );
		$this->assertSame( 'MUG-1', $snapshot['sku'] );
		$this->assertSame( '12345670', $snapshot['gtin'] );
		$this->assertSame( 'MPN-9', $snapshot['mpn'] );
		$this->assertSame( 'Acme', $snapshot['brand'] );
		$this->assertSame( 'https://example.com/mug.jpg', $snapshot['image'] );
		$this->assertSame( '12.5', $snapshot['price'] );
		$this->assertSame( 'https://schema.org/InStock', $snapshot['availability'] );
		$this->assertSame( 'Sam', $snapshot['reviews'][0]['author'] );
		$this->assertSame( 'Great mug', $snapshot['reviews'][0]['body'] );
		$this->assertSame( '5', $snapshot['reviews'][0]['rating'] );
	}

	public function test_woocommerce_suppressor_detaches_duplicate_product_markup(): void {
		$settings = new SchemaSettings();
		$settings->update( [ 'woocommerce_product' => true ] );

		$structured                = new class() {
			public function generate_product_data(): void {}
			public function output_structured_data(): void {}
		};
		$wc                        = new \stdClass();
		$wc->structured_data       = $structured;
		$suppressor                = new WooCommerceSuppressor(
			$settings,
			static function () use ( $wc ) {
				return $wc;
			}
		);

		$suppressor->maybe_detach();
		$this->assertSame( [], $suppressor->suppress_product_data( [ 'name' => 'Mug' ] ) );

		$hooks = array_map(
			static fn( array $row ): string => $row['hook'],
			RankMySEO_Test_State::$removed_actions
		);
		$this->assertContains( 'woocommerce_single_product_summary', $hooks );
		$this->assertContains( 'wp_footer', $hooks );

		$settings->update( [ 'woocommerce_product' => false ] );
		RankMySEO_Test_State::$removed_actions = [];
		$inactive                              = new WooCommerceSuppressor( $settings, static fn() => $wc );
		$inactive->maybe_detach();
		$this->assertSame( [], RankMySEO_Test_State::$removed_actions );
		$this->assertSame( [ 'name' => 'Mug' ], $inactive->suppress_product_data( [ 'name' => 'Mug' ] ) );
	}

	public function test_filters_can_edit_a_piece_and_append_to_the_graph(): void {
		add_filter(
			'rankmyseo_schema_piece_organization',
			static function ( array $piece ): array {
				$piece['name'] = 'Filtered Org';
				return $piece;
			}
		);
		add_filter(
			'rankmyseo_schema_graph',
			static function ( array $pieces ): array {
				$pieces[] = [
					'@type' => 'Thing',
					'name'  => 'Extra',
				];
				return $pieces;
			}
		);

		$document = ( new GraphBuilder() )->build( $this->homepage_context() );
		$this->assertSame( 'Filtered Org', $this->node( $document, 'Organization' )['name'] );
		$this->assertSame( 'Extra', $this->node( $document, 'Thing' )['name'] );
	}

	public function test_script_renderer_escapes_closing_script_sequences(): void {
		$context             = $this->homepage_context();
		$context->site_name  = 'Hello </script><script>alert(1)</script>';
		$document            = ( new GraphBuilder() )->build( $context );
		$html                = ( new ScriptRenderer() )->render( $document );

		$this->assertStringStartsWith( '<script type="application/ld+json">', $html );
		$this->assertStringEndsWith( '</script>', $html );
		$this->assertStringContainsString( '<\/script', $html );
		$this->assertSame( 1, substr_count( strtolower( $html ), '</script>' ) );
		$this->assertStringNotContainsString( '\\u002f', strtolower( $html ) );
	}

	public function test_custom_json_override_replaces_the_graph_and_invalid_json_is_discarded(): void {
		$context               = $this->homepage_context();
		$context->custom_graph = [
			[
				'@type' => 'Thing',
				'@id'   => 'https://example.com/#custom',
				'name'  => 'Custom',
			],
		];
		$document = ( new GraphBuilder() )->build( $context );
		$this->assertCount( 1, $document['@graph'] );
		$this->assertSame( 'Custom', $document['@graph'][0]['name'] );

		$this->assertSame( '', SchemaMeta::sanitize_custom( 'not-json' ) );
		$this->assertSame( 'BlogPosting', SchemaMeta::sanitize_type( 'BlogPosting' ) );
		$this->assertSame( 'HowTo', SchemaMeta::sanitize_type( 'HowTo' ) );
		$this->assertSame( '', SchemaMeta::sanitize_type( '<script>nope' ) );

		$encoded = SchemaMeta::sanitize_custom( '{"@type":"Thing","name":"Safe </script>"}' );
		$this->assertNotSame( '', $encoded );
		$decoded = SchemaMeta::decode_graph( $encoded );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'Thing', $decoded[0]['@type'] );

		$faq = SchemaMeta::sanitize_faq(
			[
				[
					'question' => 'Why?',
					'answer'   => '<b>Because</b>',
				],
			]
		);
		$pairs = json_decode( $faq, true );
		$this->assertSame( 'Because', $pairs[0]['answer'] );
	}

	public function test_settings_sanitize_local_business_and_conflicts(): void {
		$settings = new SchemaSettings();
		$saved    = $settings->update(
			[
				'entity_type'            => 'person',
				'logo_url'               => 'javascript:alert(1)',
				'same_as'                => "https://example.com/a\nnot a url",
				'local_business_enabled' => true,
				'local_business_type'    => 'Dentist',
				'opening_hours'          => [
					[
						'day'    => 'Monday',
						'opens'  => '09:00',
						'closes' => '17:00',
					],
					[
						'day'    => 'Funday',
						'opens'  => '09:00',
						'closes' => '17:00',
					],
				],
				'geo_latitude'           => '91',
				'geo_longitude'          => '-97.5',
				'post_type_schema'       => [
					'post' => 'NewsArticle',
					'page' => 'nope',
				],
			]
		);

		$this->assertSame( 'person', $saved['entity_type'] );
		$this->assertSame( '', $saved['logo_url'] );
		$this->assertSame( [ 'https://example.com/a' ], $saved['same_as'] );
		$this->assertSame( 'Dentist', $saved['local_business_type'] );
		$this->assertCount( 1, $saved['opening_hours'] );
		$this->assertSame( '', $saved['geo_latitude'] );
		$this->assertSame( '-97.5', $saved['geo_longitude'] );
		$this->assertSame( 'NewsArticle', $saved['post_type_schema']['post'] );
		$this->assertSame( 'WebPage', $saved['post_type_schema']['page'] );

		$scanner = new ConflictScanner(
			static function ( string $key ): bool {
				return 'woocommerce' === $key;
			}
		);
		$messages = $scanner->scan( $settings );
		$this->assertSame( 'info', $messages[0]['level'] );

		$settings->update( [ 'woocommerce_product' => false ] );
		$warning = $scanner->scan( $settings );
		$this->assertSame( 'warning', $warning[0]['level'] );
	}

	public function test_context_factory_builds_an_article_with_cross_references(): void {
		$post                     = new WP_Post();
		$post->ID                 = 42;
		$post->post_title         = 'Hello World';
		$post->post_name          = 'hello-world';
		$post->post_content       = 'One two three four';
		$post->post_excerpt       = 'A short post';
		$post->post_type          = 'post';
		$post->post_author        = 7;
		$post->post_date_gmt      = '2026-01-01 00:00:00';
		$post->post_modified_gmt  = '2026-01-02 00:00:00';
		RankMySEO_Test_State::$posts[42]       = $post;
		RankMySEO_Test_State::$permalinks[42]  = 'https://example.com/hello-world';
		RankMySEO_Test_State::$thumbnails[42]  = 'https://example.com/cover.jpg';
		RankMySEO_Test_State::$users[7]        = [ 'display_name' => 'Ada Lovelace' ];
		$term                                  = new WP_Term();
		$term->name                            = 'News';
		$term->slug                            = 'news';
		RankMySEO_Test_State::$categories[42]  = [ $term ];
		RankMySEO_Test_State::$post_meta[42]   = [
			SchemaMeta::META_FAQ => '[{"question":"Why?","answer":"Because."}]',
		];

		$settings = new SchemaSettings();
		$settings->update(
			[
				'name'        => 'Acme Co',
				'entity_type' => 'organization',
			]
		);

		$context  = ( new ContextFactory( $settings ) )->for_post( 42 );
		$document = ( new GraphBuilder() )->build( $context );
		$article  = $this->node( $document, 'BlogPosting' );

		$this->assertSame( 'Hello World', $article['headline'] );
		$this->assertSame( 4, $article['wordCount'] );
		$this->assertSame( 'News', $article['articleSection'] );
		$this->assertSame( 'https://example.com/hello-world#author', $article['author']['@id'] );
		$this->assertSame( 'https://example.com/#organization', $article['publisher']['@id'] );
		$this->assertSame( 'https://example.com/hello-world#webpage', $article['mainEntityOfPage']['@id'] );
		$this->assertNotNull( $this->find_node( $document, 'FAQPage' ) );
		$this->assertSame( 'News', $context->breadcrumbs[1]['name'] );
	}

	public function test_per_post_schema_override_changes_the_json_ld_type(): void {
		$post               = new WP_Post();
		$post->ID           = 43;
		$post->post_title   = 'Brew guide';
		$post->post_name    = 'brew-guide';
		$post->post_type    = 'post';
		$post->post_excerpt = 'A short guide';
		$post->post_content = '<ol><li>Heat the kettle</li><li>Bloom the grounds</li></ol>';
		RankMySEO_Test_State::$posts[43] = $post;
		RankMySEO_Test_State::$post_meta[43][ SchemaMeta::META_TYPE ] = 'HowTo';

		$settings = new SchemaSettings();
		$settings->update( [ 'name' => 'Acme Co' ] );
		$context = ( new ContextFactory( $settings ) )->for_post( 43 );
		$this->assertSame( 'HowTo', $context->schema_type );

		$document = ( new GraphBuilder() )->build( $context );
		$howto    = $this->node( $document, 'HowTo' );
		$webpage  = $this->node( $document, 'WebPage' );
		$this->assertSame( 'Brew guide', $howto['name'] );
		$this->assertSame( 'Heat the kettle', $howto['step'][0]['name'] );
		$this->assertSame( 'Bloom the grounds', $howto['step'][1]['text'] );
		$this->assertSame( $howto['@id'], $webpage['mainEntity']['@id'] );
		$this->assertNull( $this->find_node( $document, 'BlogPosting' ) );

		RankMySEO_Test_State::$post_meta[43][ SchemaMeta::META_TYPE ] = 'Article';
		$article_context = ( new ContextFactory( $settings ) )->for_post( 43 );
		$article_graph   = ( new GraphBuilder() )->build( $article_context );
		$this->assertSame( 'Article', $article_context->schema_type );
		$this->assertNotNull( $this->find_node( $article_graph, 'Article' ) );
		$this->assertNull( $this->find_node( $article_graph, 'BlogPosting' ) );
		$this->assertNull( $this->find_node( $article_graph, 'HowTo' ) );

		RankMySEO_Test_State::$post_meta[43][ SchemaMeta::META_TYPE ] = 'none';
		$none = ( new GraphBuilder() )->build( ( new ContextFactory( $settings ) )->for_post( 43 ) );
		$this->assertNull( $this->find_node( $none, 'HowTo' ) );
		$this->assertNull( $this->find_node( $none, 'Article' ) );
		$this->assertNotNull( $this->find_node( $none, 'WebPage' ) );
	}

	public function test_frontend_prints_json_ld_and_skips_admin(): void {
		$settings = new SchemaSettings();
		$settings->update( [ 'name' => 'Acme Co' ] );
		$frontend = new Frontend( new ContextFactory( $settings ), new GraphBuilder(), new ScriptRenderer() );

		ob_start();
		$frontend->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'application/ld+json', $html );
		$this->assertStringContainsString( 'https://example.com/#website', $html );

		RankMySEO_Test_State::$is_admin = true;
		ob_start();
		$frontend->render();
		$admin_html = (string) ob_get_clean();
		$this->assertSame( '', $admin_html );
	}

	public function test_rest_preview_settings_and_meta_registration(): void {
		$core     = new Settings();
		$schema   = new SchemaSettings();
		$schema->update( [ 'name' => 'Acme Co', 'entity_type' => 'organization' ] );
		$contexts = new ContextFactory( $schema, null, $core );
		$controller = new SchemaController( $schema, $core, $contexts, new GraphBuilder(), new ConflictScanner() );

		$controller->register_routes();
		$routes = array_map(
			static fn( array $entry ): string => $entry['namespace'] . $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);
		$this->assertContains( 'rankmyseo/v1/schema/preview', $routes );
		$this->assertContains( 'rankmyseo/v1/schema/settings', $routes );
		$this->assertContains( 'rankmyseo/v1/schema/validate', $routes );

		$preview = $controller->get_preview( new WP_REST_Request() );
		$data    = $preview->get_data();
		$this->assertSame( 0, $data['postId'] );
		$this->assertSame( 'https://schema.org', $data['graph']['@context'] );
		$this->assertNotEmpty( $data['graph']['@graph'] );

		$missing = new WP_REST_Request();
		$missing->set_param( 'post_id', 99 );
		$error = $controller->get_preview( $missing );
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'rankmyseo_schema_not_found', $error->get_error_code() );

		$update = new WP_REST_Request();
		$update->set_json_params( [ 'name' => 'Updated Co', 'search_action' => false ] );
		$saved = $controller->update_settings( $update )->get_data();
		$this->assertSame( 'Updated Co', $saved['settings']['name'] );
		$this->assertFalse( $saved['settings']['search_action'] );

		$validate = new WP_REST_Request();
		$validate->set_json_params( [ 'json' => '{"@type":"Thing","name":"Ok"}' ] );
		$valid = $controller->validate_json( $validate )->get_data();
		$this->assertTrue( $valid['valid'] );

		$invalid = new WP_REST_Request();
		$invalid->set_json_params( [ 'json' => '{oops' ] );
		$rejected = $controller->validate_json( $invalid )->get_data();
		$this->assertFalse( $rejected['valid'] );

		( new SchemaMeta() )->register_meta();
		$keys = array_column( RankMySEO_Test_State::$registered_meta, 'meta_key' );
		$this->assertContains( SchemaMeta::META_TYPE, $keys );
		$this->assertContains( SchemaMeta::META_CUSTOM, $keys );
		foreach ( RankMySEO_Test_State::$registered_meta as $registered ) {
			$this->assertTrue( $registered['args']['show_in_rest'] );
			$this->assertSame( 'post', $registered['object_type'] );
		}

		$container = new Container();
		$container->set( Settings::class, $core );
		$registry = new RestRegistry();
		$container->set( RestRegistry::class, $registry );
		$module = new SchemaModule( $container );
		$module->register();
		$module->register_integrations();
		$this->assertNotEmpty( RankMySEO_Test_State::$actions['wp_head'] );
		$this->assertNotEmpty( RankMySEO_Test_State::$actions['wp'] );
		$this->assertTrue( $module->is_ready() );
	}

	/**
	 * Build a homepage context.
	 */
	private function homepage_context(): SchemaContext {
		$context                   = new SchemaContext();
		$context->home_url         = 'https://example.com/';
		$context->permalink        = 'https://example.com/';
		$context->site_name        = 'Acme Co';
		$context->site_description = 'We make things';
		$context->entity_type      = 'organization';
		$context->logo_url         = 'https://example.com/logo.png';
		$context->telephone        = '+1-555-0100';
		$context->same_as          = [ 'https://example.com/social' ];
		$context->search_enabled   = true;
		$context->language         = 'en-US';
		$context->title            = 'Acme Co';
		$context->description      = 'We make things';
		$context->schema_type      = 'WebPage';
		$context->webpage_type     = 'WebPage';
		$context->breadcrumbs      = [
			[
				'name' => 'Home',
				'url'  => 'https://example.com/',
			],
		];

		return $context;
	}

	/**
	 * Find a node by @type.
	 *
	 * @param array<string, mixed> $document Graph document.
	 * @param string               $type     Schema @type.
	 * @return array<string, mixed>
	 */
	private function node( array $document, string $type ): array {
		$found = $this->find_node( $document, $type );
		$this->assertNotNull( $found, 'Missing @type ' . $type );
		return $found;
	}

	/**
	 * Find a node by @type or return null.
	 *
	 * @param array<string, mixed> $document Graph document.
	 * @param string               $type     Schema @type.
	 * @return array<string, mixed>|null
	 */
	private function find_node( array $document, string $type ): ?array {
		$graph = $document['@graph'] ?? [];
		if ( ! is_array( $graph ) ) {
			return null;
		}
		foreach ( $graph as $node ) {
			if ( is_array( $node ) && ( $node['@type'] ?? '' ) === $type ) {
				return $node;
			}
		}

		return null;
	}

	/**
	 * Find a node by @id.
	 *
	 * @param array<string, mixed> $document Graph document.
	 * @param string               $id       Node @id.
	 * @return array<string, mixed>
	 */
	private function node_by_id( array $document, string $id ): array {
		foreach ( $document['@graph'] as $node ) {
			if ( is_array( $node ) && ( $node['@id'] ?? '' ) === $id ) {
				return $node;
			}
		}

		$this->fail( 'Missing @id ' . $id );
	}
}
