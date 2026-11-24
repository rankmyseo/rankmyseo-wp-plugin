<?php
/**
 * Unit tests for titles, meta sanitization, robots, and head tags.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\Meta\ClassicMetabox;
use RankMySEO\Modules\Meta\ConflictGuard;
use RankMySEO\Modules\Meta\DocumentBuilder;
use RankMySEO\Modules\Meta\HeadDocument;
use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\MetaRegistry;
use RankMySEO\Modules\Meta\MetaSanitizer;
use RankMySEO\Modules\Meta\MetaStore;
use RankMySEO\Modules\Meta\QueryReader;
use RankMySEO\Modules\Meta\QuerySnapshot;
use RankMySEO\Modules\Schema\SchemaMeta;
use RankMySEO\Modules\Meta\QuickBulkEdit;
use RankMySEO\Modules\Meta\Robots;
use RankMySEO\Modules\Meta\TemplateContext;
use RankMySEO\Modules\Meta\TemplateDefaults;
use RankMySEO\Modules\Meta\TemplateEngine;
use RankMySEO\Modules\Meta\TitleSettings;
use RankMySEO\Rest\MetaController;
use RankMySEO_Test_State;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_Term;

final class MetaEngineTest extends TestCase {
	private DocumentBuilder $builder;

	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		$_POST    = [];
		$_REQUEST = [];
		$this->builder = new DocumentBuilder( new TemplateEngine() );
	}

	public function test_shop_archive_uses_the_shop_page_seo_fields(): void {
		$shop                     = new WP_Post();
		$shop->ID                 = 50;
		$shop->post_title         = 'Shop';
		$shop->post_name          = 'shop';
		$shop->post_type          = 'page';
		$shop->post_content       = 'Coffee gear';
		$shop->post_excerpt       = 'Beans and kettles';
		RankMySEO_Test_State::$posts[50] = $shop;
		RankMySEO_Test_State::$shop_page_id = 50;
		RankMySEO_Test_State::$post_meta[50] = [
			MetaKeys::TITLE          => 'Shop Artisan Roast coffee and brew gear',
			MetaKeys::DESCRIPTION    => 'Beans, drippers, and kettles.',
			MetaKeys::CANONICAL      => 'https://example.com/shop/',
			MetaKeys::OG_TITLE       => 'Shop the roastery',
			MetaKeys::OG_DESCRIPTION => 'Social beans',
			MetaKeys::TWITTER_TITLE  => 'Shop on X',
			MetaKeys::ROBOTS         => [
				'noindex'  => false,
				'nofollow' => true,
			],
		];

		RankMySEO_Test_State::$is_post_type_archive   = true;
		RankMySEO_Test_State::$query_vars['post_type'] = 'product';
		$snapshot = ( new QueryReader() )->current();
		$this->assertSame( 'singular', $snapshot->kind );
		$this->assertSame( 50, $snapshot->object_id );
		$this->assertSame( 'page', $snapshot->post_type );

		$meta      = ( new MetaStore() )->read_post( $snapshot->object_id );
		$templates = ( new TitleSettings( new Settings() ) )->all();
		$this->assertSame(
			'Shop Artisan Roast coffee and brew gear',
			$this->builder->title( $snapshot, $meta, $templates, '|', 'Artisan Roast Coffee', '' )
		);
		$this->assertSame(
			'Beans, drippers, and kettles.',
			$this->builder->description( $snapshot, $meta, $templates, '|', 'Artisan Roast Coffee', '' )
		);
		$head = $this->builder->head( $snapshot, $meta, $templates, '|', 'Artisan Roast Coffee', '' );
		$this->assertSame( 'https://example.com/shop/', $head->canonical );
		$this->assertSame( 'Shop the roastery', $head->og_title );
		$this->assertSame( 'Social beans', $head->og_description );
		$this->assertSame( 'Shop on X', $head->twitter_title );
		$robots = $this->builder->robots( $snapshot, $meta, $templates );
		$this->assertFalse( $robots['noindex'] );
		$this->assertTrue( $robots['nofollow'] );

		RankMySEO_Test_State::$is_post_type_archive = false;
		RankMySEO_Test_State::$is_shop              = true;
		$via_shop = ( new QueryReader() )->current();
		$this->assertSame( 50, $via_shop->object_id );
		$this->assertSame( 'singular', $via_shop->kind );

		RankMySEO_Test_State::$shop_page_id = 0;
		RankMySEO_Test_State::$is_shop      = false;
		RankMySEO_Test_State::$is_post_type_archive = true;
		$archive = ( new QueryReader() )->current();
		$this->assertSame( 'post_type_archive', $archive->kind );
		$this->assertSame( 0, $archive->object_id );
	}

	public function test_template_engine_replaces_tokens_and_drops_empty_separators(): void {
		$engine  = new TemplateEngine();
		$context = TemplateContext::from_array(
			[
				'title'    => 'Pricing <strong>Plans</strong>',
				'sep'      => '|',
				'sitename' => 'Acme',
				'excerpt'  => 'Compare [price] <em>plans</em> today.',
				'page'     => '',
			]
		);

		$this->assertSame(
			'Pricing Plans | Acme',
			$engine->replace( '%title% %sep% %sitename%', $context )
		);
		$this->assertSame(
			'Pricing Plans',
			$engine->replace( '%title% %sep% %page%', $context )
		);
		$this->assertSame(
			'Compare plans today.',
			$engine->replace( '%excerpt%', $context )
		);
		$this->assertSame(
			'Pricing Plans %unknown%',
			$engine->replace( '%title% %unknown%', $context )
		);
	}

	public function test_template_engine_inserts_year_and_page_number(): void {
		$snapshot                     = $this->post_snapshot();
		$snapshot->page               = 2;
		$snapshot->current_year       = '2026';
		$templates                    = ( new TitleSettings( new Settings() ) )->all();
		$templates['post_types']['post']['title'] = '%title% %sep% %currentyear%';

		$title = $this->builder->title( $snapshot, [ 'robots' => null ], $templates, '|', 'Acme', 'Tagline' );

		$this->assertSame( 'Hello | 2026 | Page 2', $title );
	}

	public function test_sanitizer_strips_markup_and_rejects_unsafe_urls(): void {
		$this->assertSame(
			'Clean title',
			MetaSanitizer::sanitize_title( '  Clean   <b>title</b> [short]  ' )
		);
		$this->assertSame( '', MetaSanitizer::sanitize_canonical( 'javascript:alert(1)' ) );
		$this->assertSame( '', MetaSanitizer::sanitize_canonical( 'data:text/html,hi' ) );
		$this->assertSame(
			'https://example.com/pricing',
			MetaSanitizer::sanitize_canonical( 'https://example.com/pricing' )
		);

		$keywords = MetaSanitizer::sanitize_keywords( 'alpha, beta, alpha, gamma, delta, epsilon, zeta' );
		$this->assertSame( [ 'alpha', 'beta', 'gamma', 'delta', 'epsilon' ], $keywords );
		$this->assertSame( 'summary', MetaSanitizer::sanitize_twitter_card( 'summary' ) );
		$this->assertSame( 'summary_large_image', MetaSanitizer::sanitize_twitter_card( 'nope' ) );
	}

	public function test_robots_directives_respect_site_wide_noindex(): void {
		$robots = Robots::sanitize(
			[
				'noindex'           => true,
				'nofollow'          => 'yes',
				'max_snippet'       => 40,
				'max_image_preview' => 'large',
				'max_video_preview' => 12,
				'ignore_me'         => true,
			]
		);

		$public = Robots::apply( [], $robots, true );
		$this->assertTrue( $public['noindex'] );
		$this->assertTrue( $public['nofollow'] );
		$this->assertSame( '40', $public['max-snippet'] );
		$this->assertSame( 'large', $public['max-image-preview'] );
		$this->assertSame( '12', $public['max-video-preview'] );
		$this->assertArrayNotHasKey( 'ignore_me', $public );

		$hidden = Robots::apply(
			[
				'noindex'  => true,
				'nofollow' => true,
			],
			Robots::sanitize( [ 'noindex' => false ] ),
			false
		);
		$this->assertTrue( $hidden['noindex'] );
		$this->assertArrayNotHasKey( 'max-snippet', $hidden );
	}

	public function test_head_document_prints_each_tag_once(): void {
		$snapshot              = $this->post_snapshot();
		$snapshot->canonical   = 'https://example.com/hello';
		$snapshot->locale      = 'en_US';
		$snapshot->published   = '2026-01-02T03:04:05+00:00';
		$snapshot->modified    = '2026-02-03T04:05:06+00:00';
		$snapshot->featured_image = 'https://example.com/featured.jpg';
		$templates             = ( new TitleSettings( new Settings() ) )->all();
		$meta                  = [
			'robots'        => null,
			'og_image'      => 'https://example.com/og.jpg',
			'twitter_card'  => 'summary',
			'canonical'     => 'https://example.com/custom',
		];

		$html = $this->builder->head( $snapshot, $meta, $templates, '|', 'Acme', 'Tagline' )->render();

		$this->assertSame( 1, substr_count( $html, 'name="description"' ) );
		$this->assertSame( 1, substr_count( $html, 'rel="canonical"' ) );
		$this->assertStringContainsString( 'href="https://example.com/custom"', $html );
		$this->assertStringContainsString( 'property="og:title"', $html );
		$this->assertStringContainsString( 'property="og:image" content="https://example.com/og.jpg"', $html );
		$this->assertStringContainsString( 'property="article:published_time"', $html );
		$this->assertStringContainsString( 'name="twitter:card" content="summary"', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '<!-- RankMySEO Meta -->', $html );
	}

	public function test_type_default_noindex_applies_until_a_page_sets_robots(): void {
		$snapshot  = $this->post_snapshot();
		$templates = ( new TitleSettings( new Settings() ) )->all();
		$templates['post_types']['post']['noindex'] = true;

		$inherited = $this->builder->robots( $snapshot, [ 'robots' => null ], $templates );
		$this->assertTrue( $inherited['noindex'] );

		$explicit = $this->builder->robots(
			$snapshot,
			[ 'robots' => Robots::sanitize( [ 'noindex' => false ] ) ],
			$templates
		);
		$this->assertFalse( $explicit['noindex'] );

		$missing                  = new QuerySnapshot();
		$missing->kind            = 'not_found';
		$not_found                = $this->builder->robots( $missing, [ 'robots' => null ], $templates );
		$this->assertTrue( $not_found['noindex'] );
	}

	public function test_registry_exposes_meta_in_rest_with_auth_callbacks(): void {
		$registry = new MetaRegistry();
		$registry->register_keys();

		$keys = [];
		foreach ( RankMySEO_Test_State::$registered_meta as $entry ) {
			$keys[ $entry['object_type'] . ':' . $entry['key'] ] = $entry['args'];
		}

		$this->assertArrayHasKey( 'post:' . MetaKeys::TITLE, $keys );
		$this->assertArrayHasKey( 'post:' . MetaKeys::ROBOTS, $keys );
		$this->assertArrayHasKey( 'term:' . MetaKeys::TWITTER_CARD, $keys );
		$this->assertTrue( $keys[ 'post:' . MetaKeys::TITLE ]['show_in_rest'] );
		$this->assertSame( [ MetaRegistry::class, 'auth_post' ], $keys[ 'post:' . MetaKeys::TITLE ]['auth_callback'] );
		$this->assertSame( [ MetaRegistry::class, 'auth_term' ], $keys[ 'term:' . MetaKeys::DESCRIPTION ]['auth_callback'] );

		RankMySEO_Test_State::$current_user_caps = [];
		$this->assertFalse( MetaRegistry::auth_post( false, MetaKeys::TITLE, 5 ) );
		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$this->assertTrue( MetaRegistry::auth_post( false, MetaKeys::TITLE, 5 ) );
		$this->assertTrue( MetaRegistry::auth_term( false, MetaKeys::TITLE, 3 ) );
	}

	public function test_classic_save_requires_nonce_and_edit_capability(): void {
		$settings = new Settings();
		$titles   = new TitleSettings( $settings );
		$store    = new MetaStore();
		$metabox  = new ClassicMetabox( $store, $settings, $titles );
		$post     = new WP_Post();
		$post->ID = 9;

		RankMySEO_Test_State::$nonces[ 'rankmyseo_save_meta_9' ] = 'good-nonce';
		$_POST = [
			'rankmyseo_meta_nonce' => 'good-nonce',
			'rankmyseo_title'      => 'Saved <b>title</b>',
			'rankmyseo_description'=> 'A description',
			'rankmyseo_canonical'  => 'javascript:alert(1)',
			'rankmyseo_robots'     => '{"noindex":true}',
		];

		RankMySEO_Test_State::$current_user_caps = [];
		$metabox->save_post( 9, $post );
		$this->assertSame( '', get_post_meta( 9, MetaKeys::TITLE, true ) );

		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$_POST['rankmyseo_meta_nonce']           = 'bad-nonce';
		$metabox->save_post( 9, $post );
		$this->assertSame( '', get_post_meta( 9, MetaKeys::TITLE, true ) );

		$_POST['rankmyseo_meta_nonce'] = 'good-nonce';
		$metabox->save_post( 9, $post );
		$this->assertSame( 'Saved title', get_post_meta( 9, MetaKeys::TITLE, true ) );
		$this->assertSame( '', get_post_meta( 9, MetaKeys::CANONICAL, true ) );
		$_POST['rankmyseo_schema_type'] = 'HowTo';
		$metabox->save_post( 9, $post );
		$this->assertSame( 'HowTo', get_post_meta( 9, SchemaMeta::META_TYPE, true ) );
		$_POST['rankmyseo_schema_type'] = '';
		$metabox->save_post( 9, $post );
		$this->assertSame( '', get_post_meta( 9, SchemaMeta::META_TYPE, true ) );
		$robots = get_post_meta( 9, MetaKeys::ROBOTS, true );
		$this->assertIsArray( $robots );
		$this->assertTrue( $robots['noindex'] );
	}

	public function test_quick_and_bulk_edit_honor_nonces(): void {
		$store = new MetaStore();
		$store->write_post(
			4,
			[
				'title'         => 'Keep me',
				'og_title'      => 'Social title',
				'twitter_card'  => 'summary',
			]
		);

		$editor = new QuickBulkEdit( $store );
		RankMySEO_Test_State::$nonces['inlineeditnonce'] = 'inline-ok';
		$_POST = [
			'rankmyseo_quick_edit'    => '1',
			'_inline_edit'            => 'inline-ok',
			'rankmyseo_qe_title'      => 'Quick title',
			'rankmyseo_qe_description'=> 'Quick description',
			'rankmyseo_qe_focus'      => 'pricing plans',
			'rankmyseo_qe_noindex'    => '1',
		];

		$editor->save_post( 4 );
		$this->assertSame( 'Quick title', get_post_meta( 4, MetaKeys::TITLE, true ) );
		$this->assertSame( 'Social title', get_post_meta( 4, MetaKeys::OG_TITLE, true ) );
		$this->assertSame( 'pricing plans', get_post_meta( 4, MetaKeys::FOCUS_KEYWORD, true ) );

		RankMySEO_Test_State::$nonces['bulk-posts'] = 'bulk-ok';
		$_POST                                      = [];
		$_REQUEST                                   = [
			'rankmyseo_bulk_edit'    => '1',
			'rankmyseo_bulk_noindex' => '0',
			'_wpnonce'               => 'bulk-ok',
		];
		$editor->save_post( 4 );
		$robots = get_post_meta( 4, MetaKeys::ROBOTS, true );
		$this->assertFalse( $robots['noindex'] );
		$this->assertSame( 'Quick title', get_post_meta( 4, MetaKeys::TITLE, true ) );
	}

	public function test_rest_permissions_and_preview_endpoint(): void {
		$settings = new Settings();
		$titles   = new TitleSettings( $settings );
		$controller = new MetaController(
			$settings,
			$titles,
			new MetaStore(),
			new ConflictGuard( $titles, false ),
			new DocumentBuilder()
		);

		$controller->register_routes();
		$routes = array_map(
			static fn( array $entry ): string => $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);
		$this->assertContains( '/meta/settings', $routes );
		$this->assertContains( '/meta/preview', $routes );
		$this->assertContains( '/meta/posts/(?P<id>\d+)', $routes );

		RankMySEO_Test_State::$current_user_caps = [];
		$request                                 = new WP_REST_Request();
		$request->set_param( 'id', 8 );
		$denied = $controller->check_post_permission( $request );
		$this->assertInstanceOf( WP_Error::class, $denied );
		$this->assertSame( 403, $denied->get_error_data()['status'] );

		$term_denied = $controller->check_term_permission( $request );
		$this->assertInstanceOf( WP_Error::class, $term_denied );

		RankMySEO_Test_State::$current_user_caps = [
			'edit_posts'       => true,
			'rankmyseo_manage' => true,
		];
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$this->assertTrue( $controller->check_post_permission( $request ) );

		$post     = new WP_Post();
		$post->ID = 8;
		$post->post_type = 'page';
		RankMySEO_Test_State::$posts[8] = $post;

		$write = new WP_REST_Request();
		$write->set_param( 'id', 8 );
		$write->set_json_params(
			[
				'title'       => 'REST title',
				'description' => 'REST description',
			]
		);
		$response = $controller->update_post_meta( $write );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'REST title', $response->get_data()['meta']['title'] );
		$this->assertSame( '', $response->get_data()['meta']['schema_type'] );

		$write->set_json_params(
			[
				'title'       => 'REST title',
				'schema_type' => 'FAQPage',
			]
		);
		$schema_response = $controller->update_post_meta( $write );
		$this->assertSame( 'FAQPage', $schema_response->get_data()['meta']['schema_type'] );
		$this->assertSame( 'FAQPage', get_post_meta( 8, SchemaMeta::META_TYPE, true ) );

		$preview = new WP_REST_Request();
		$preview->set_json_params(
			[
				'title'       => '%title% %sep% %sitename%',
				'description' => '%excerpt%',
				'variables'   => [
					'title'   => 'Pricing',
					'excerpt' => 'Plans for teams',
				],
			]
		);
		$preview_response = $controller->preview( $preview );
		$this->assertSame( 'Pricing | Test Site', $preview_response->get_data()['title'] );
		$this->assertSame( 'Plans for teams', $preview_response->get_data()['description'] );
	}

	public function test_conflict_guard_pauses_head_until_forced(): void {
		$titles = new TitleSettings( new Settings() );
		$guard  = new ConflictGuard( $titles, true );

		$this->assertTrue( $guard->has_conflict() );
		$this->assertTrue( $guard->should_pause_head() );

		$titles->set_force_head( true );
		$this->assertFalse( $guard->should_pause_head() );
	}

	public function test_term_meta_round_trip_and_settings_templates(): void {
		$store = new MetaStore();
		$store->write_term(
			3,
			[
				'title'       => '%term_title% %sep% %sitename%',
				'description' => 'Category copy',
			]
		);
		$read = $store->read_term( 3 );
		$this->assertSame( '%term_title% %sep% %sitename%', $read['title'] );
		$this->assertNull( $store->read_term( 99 )['robots'] );

		$settings = new Settings();
		$titles   = new TitleSettings( $settings );
		$saved    = $titles->update(
			[
				'separator' => '·',
				'homepage'  => [
					'title'       => '%sitename%',
					'description' => 'Welcome',
				],
				'post_types' => [
					'post' => [
						'title'       => '%title%',
						'description' => '%excerpt%',
						'noindex'     => true,
					],
				],
			]
		);

		$this->assertSame( '·', $saved['separator'] );
		$this->assertSame( '·', $settings->get( 'title_separator' ) );
		$this->assertSame( '%sitename%', $saved['homepage']['title'] );
		$this->assertTrue( $saved['post_types']['post']['noindex'] );
		$this->assertArrayHasKey( 'post', $saved['post_types'] );
	}

	public function test_head_filter_cannot_inject_a_second_description_or_script(): void {
		$document              = new HeadDocument();
		$document->description = 'Only once';
		$document->og_title    = 'Hello';
		$html                  = $document->render();

		$this->assertSame( 1, substr_count( $html, 'name="description"' ) );
		$this->assertStringNotContainsString( '<script', $html );
	}

	private function post_snapshot(): QuerySnapshot {
		$snapshot            = new QuerySnapshot();
		$snapshot->kind      = 'singular';
		$snapshot->post_type = 'post';
		$snapshot->object_id = 1;
		$snapshot->title     = 'Hello';
		$snapshot->excerpt   = 'A short excerpt.';
		$snapshot->canonical = 'https://example.com/hello';
		return $snapshot;
	}
}
