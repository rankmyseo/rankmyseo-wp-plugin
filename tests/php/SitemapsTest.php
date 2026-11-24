<?php
/**
 * Unit tests for XML sitemaps, IndexNow, and virtual robots.txt.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\Sitemaps\IndexNow;
use RankMySEO\Modules\Sitemaps\LlmsTxt;
use RankMySEO\Modules\Sitemaps\SitemapLlmsCatalog;
use RankMySEO\Modules\Sitemaps\RobotsMeta;
use RankMySEO\Modules\Sitemaps\RobotsTxt;
use RankMySEO\Modules\Sitemaps\SitemapCache;
use RankMySEO\Modules\Sitemaps\SitemapDocuments;
use RankMySEO\Modules\Sitemaps\SitemapLinks;
use RankMySEO\Modules\Sitemaps\SitemapSettings;
use RankMySEO\Modules\Sitemaps\SitemapSource;
use RankMySEO\Modules\Sitemaps\SitemapsModule;
use RankMySEO\Modules\Sitemaps\WordPressSitemapSource;
use RankMySEO\Modules\Sitemaps\WpIndexNowTransport;
use RankMySEO\Modules\Sitemaps\XmlRenderer;
use RankMySEO\Rest\SitemapsController;
use RankMySEO_Test_State;
use WP_Error;
use WP_REST_Request;
use wpdb;

final class SitemapsTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_xml_escapes_urls_and_stays_well_formed(): void {
		$renderer = new XmlRenderer();
		$xml      = $renderer->render_urlset(
			[
				[
					'loc'     => 'https://example.com/a&b<tag>"quote"',
					'lastmod' => '2026-11-23T00:00:00+00:00',
					'images'  => [ 'https://example.com/img.png?a=1&b=2' ],
				],
			],
			'https://example.com/rankmyseo-sitemap.xsl'
		);

		$this->assertStringContainsString( 'https://example.com/a&amp;b&lt;tag&gt;&quot;quote&quot;', $xml );
		$this->assertStringContainsString( 'https://example.com/img.png?a=1&amp;b=2', $xml );
		$this->assertStringContainsString( '<image:image>', $xml );

		$document = new \DOMDocument();
		$this->assertTrue( $document->loadXML( $xml ) );
		$this->assertSame( 'urlset', $document->documentElement->localName );
	}

	public function test_index_xml_lists_paginated_child_sitemaps(): void {
		$settings = new SitemapSettings();
		$settings->update( [ 'max_urls' => 1000 ] );
		$source = new class() implements SitemapSource {
			public function providers(): array {
				return [
					[
						'kind'  => 'post',
						'name'  => 'post',
						'label' => 'Posts',
						'slug'  => 'post',
					],
				];
			}

			public function count( string $kind, string $name ): int {
				unset( $kind, $name );
				return 2500;
			}

			public function entries( string $kind, string $name, int $page, int $per_page ): array {
				unset( $kind, $name, $per_page );
				return [
					[
						'loc'     => 'https://example.com/page-' . $page,
						'lastmod' => '2026-11-23T00:00:00+00:00',
						'images'  => [],
					],
				];
			}
		};

		$documents = new SitemapDocuments( $source, $settings, new XmlRenderer(), new SitemapCache( $settings ) );
		$index     = $documents->render_index();

		$this->assertStringContainsString( 'https://example.com/post-sitemap.xml', $index );
		$this->assertStringContainsString( 'https://example.com/post-sitemap2.xml', $index );
		$this->assertStringContainsString( 'https://example.com/post-sitemap3.xml', $index );
		$this->assertStringNotContainsString( 'post-sitemap4.xml', $index );

		$child = $documents->render_child( 'post', 2 );
		$this->assertIsString( $child );
		$this->assertStringContainsString( 'https://example.com/page-2', $child );

		$document = new \DOMDocument();
		$this->assertTrue( $document->loadXML( $index ) );
	}

	public function test_pagination_slice_and_page_count(): void {
		$items = range( 1, 2500 );
		$this->assertSame( 3, SitemapLinks::page_count( 2500, 1000 ) );
		$this->assertSame( 0, SitemapLinks::page_count( 0, 1000 ) );
		$this->assertCount( 1000, SitemapLinks::slice( $items, 1, 1000 ) );
		$this->assertCount( 500, SitemapLinks::slice( $items, 3, 1000 ) );
		$this->assertSame( [], SitemapLinks::slice( $items, 4, 1000 ) );
		$this->assertSame( 'https://example.com/post-sitemap.xml', SitemapLinks::child_url( 'post', 1 ) );
		$this->assertSame( 'https://example.com/post-sitemap2.xml', SitemapLinks::child_url( 'post', 2 ) );
	}

	public function test_noindex_password_and_excluded_ids_are_left_out(): void {
		$type_map = [ 'post' => true, 'page' => true ];
		$excluded = [ 9 ];

		$this->assertTrue( RobotsMeta::is_noindex( '{"noindex":true,"nofollow":false}' ) );
		$this->assertTrue( RobotsMeta::is_noindex( '{"noindex":true}' ) );
		$this->assertTrue( RobotsMeta::is_noindex( '{"noindex": true}' ) );
		$this->assertTrue( RobotsMeta::is_noindex( serialize( [ 'noindex' => true ] ) ) );
		$this->assertFalse( RobotsMeta::is_noindex( '{"noindex":false}' ) );
		$this->assertFalse( RobotsMeta::is_noindex( '' ) );

		$included = array_values(
			array_filter(
				[
					[ 'id' => 1, 'password' => '', 'status' => 'publish', 'type' => 'post', 'robots' => '' ],
					[ 'id' => 2, 'password' => '', 'status' => 'publish', 'type' => 'post', 'robots' => '{"noindex":true}' ],
					[ 'id' => 3, 'password' => 'secret', 'status' => 'publish', 'type' => 'post', 'robots' => '' ],
					[ 'id' => 9, 'password' => '', 'status' => 'publish', 'type' => 'post', 'robots' => '' ],
					[ 'id' => 4, 'password' => '', 'status' => 'draft', 'type' => 'post', 'robots' => '' ],
					[ 'id' => 5, 'password' => '', 'status' => 'publish', 'type' => 'attachment', 'robots' => '' ],
				],
				static function ( array $post ) use ( $type_map, $excluded ): bool {
					return RobotsMeta::should_include_post( $post, $type_map, $excluded );
				}
			)
		);

		$this->assertCount( 1, $included );
		$this->assertSame( 1, $included[0]['id'] );

		$page = SitemapLinks::slice( $included, 1, 1000 );
		$this->assertSame( [ 1 ], array_map( static fn( array $post ): int => (int) $post['id'], $page ) );
	}

	public function test_post_query_excludes_password_protected_and_noindex_rows(): void {
		$settings = new SitemapSettings();
		$settings->update(
			[
				'excluded_post_ids' => [ 42, 43 ],
				'post_types'        => [ 'post' => true ],
			]
		);
		$wpdb   = new wpdb();
		$source = new WordPressSitemapSource( $settings, $wpdb );

		$this->assertSame( 0, $source->count( 'post', 'post' ) );
		$sql = (string) end( $wpdb->queries );
		$this->assertStringContainsString( "p.post_password = ''", $sql );
		$this->assertStringContainsString( "p.post_status = 'publish'", $sql );
		$this->assertStringContainsString( '_rankmyseo_robots', $sql );
		$this->assertStringContainsString( 'noindex', $sql );
		$this->assertStringContainsString( 'NOT IN (42,43)', $sql );
	}

	public function test_indexnow_stays_silent_until_an_admin_enables_it(): void {
		$settings  = new SitemapSettings();
		$indexnow  = new IndexNow( $settings, new WpIndexNowTransport() );

		$this->assertFalse( $indexnow->is_enabled() );
		$key = $settings->ensure_key();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $key );
		$this->assertNull( $indexnow->virtual_key_body( $key ) );

		$indexnow->enqueue( 'https://example.com/hello' );
		$settings->save_queue( [ 'https://example.com/already-queued' ] );
		$indexnow->process_queue();

		$this->assertSame( 0, RankMySEO_Test_State::$remote_posts );
		$this->assertSame( [], RankMySEO_Test_State::$cron );

		$ping = $indexnow->ping_url( 'https://example.com/hello' );
		$this->assertInstanceOf( WP_Error::class, $ping );
		$this->assertSame( 'rankmyseo_indexnow_disabled', $ping->get_error_code() );
		$this->assertSame( 0, RankMySEO_Test_State::$remote_posts );
	}

	public function test_indexnow_virtual_key_and_debounced_ping_when_enabled(): void {
		$settings = new SitemapSettings();
		$settings->update( [ 'indexnow_enabled' => true ] );
		$indexnow = new IndexNow( $settings, new WpIndexNowTransport() );
		$key      = $settings->ensure_key();

		$this->assertSame( $key, $indexnow->virtual_key_body( $key ) );
		$this->assertNull( $indexnow->virtual_key_body( str_repeat( 'a', 32 ) ) );

		$indexnow->enqueue( 'https://example.com/hello' );
		$indexnow->enqueue( 'https://example.com/hello' );
		$indexnow->enqueue( 'https://evil.example/nope' );

		$this->assertSame( [ 'https://example.com/hello' ], $settings->get_queue() );
		$this->assertCount( 1, RankMySEO_Test_State::$cron[ IndexNow::CRON_HOOK ] );
		$this->assertSame( 0, RankMySEO_Test_State::$remote_posts );

		$indexnow->process_queue();
		$this->assertSame( 1, RankMySEO_Test_State::$remote_posts );
		$payload = json_decode( RankMySEO_Test_State::$remote_bodies[0], true );
		$this->assertIsArray( $payload );
		$this->assertSame( 'example.com', $payload['host'] );
		$this->assertSame( $key, $payload['key'] );
		$this->assertSame( [ 'https://example.com/hello' ], $payload['urlList'] );
		$this->assertNotEmpty( $settings->get_log() );

		$manual = $indexnow->ping_url( 'https://not-this-site.test/page' );
		$this->assertInstanceOf( WP_Error::class, $manual );
		$this->assertSame( 'rankmyseo_invalid_url', $manual->get_error_code() );
	}

	public function test_robots_txt_appends_sitemap_and_warns(): void {
		$settings = new SitemapSettings();
		$robots   = new RobotsTxt( $settings, '/tmp/rankmyseo-missing', static fn(): bool => false );

		$body = $robots->render();
		$this->assertStringContainsString( "Disallow: /wp-admin/\n", $body );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap_index.xml', $body );
		$this->assertFalse( $robots->blocks_entire_site( $body ) );
		$this->assertFalse( $robots->has_physical_file() );
		$this->assertSame( [], $robots->warnings() );

		$physical = new RobotsTxt(
			$settings,
			'/var/www/html',
			static function ( string $path ): bool {
				return str_ends_with( $path, 'robots.txt' );
			}
		);
		$this->assertTrue( $physical->has_physical_file() );
		$codes = array_column( $physical->warnings(), 'code' );
		$this->assertContains( 'physical_file', $codes );

		$preview = $robots->preview_custom( "User-agent: *\nDisallow: /\n" );
		$this->assertTrue( $preview['blocks_entire_site'] );
		$this->assertContains( 'disallow_all', array_column( $preview['warnings'], 'code' ) );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap_index.xml', $preview['content'] );
		$this->assertSame( 1, preg_match_all( '/^Sitemap:/m', $preview['content'] ) );

		$filtered = $robots->filter_robots_txt( 'ignored', '0' );
		$this->assertStringContainsString( 'Disallow: /', $filtered );
		$this->assertStringContainsString( 'Sitemap: https://example.com/sitemap_index.xml', $filtered );
	}

	public function test_register_disables_core_sitemaps_and_adds_rewrites(): void {
		$container = new Container();
		$settings  = new SitemapSettings();
		$container->set( SitemapSettings::class, $settings );
		$container->set( IndexNow::class, new IndexNow( $settings, new WpIndexNowTransport() ) );
		$container->set( RobotsTxt::class, new RobotsTxt( $settings, '/tmp/none', static fn(): bool => false ) );

		$module = new SitemapsModule( $container );
		$this->assertTrue( $module->is_ready() );
		$module->register();

		$this->assertContains( '__return_false', RankMySEO_Test_State::$filters['wp_sitemaps_enabled'] );
		$module->register_rewrites();

		$patterns = array_column( RankMySEO_Test_State::$rewrites, 'regex' );
		$this->assertContains( '^sitemap_index\\.xml$', $patterns );
		$this->assertContains( '^rankmyseo-sitemap\\.xsl$', $patterns );
		$this->assertNotContains( '^([a-f0-9]{32})\\.txt$', $patterns );
		$this->assertNotContains( '^llms\\.txt$', $patterns );

		$settings->update( [ 'indexnow_enabled' => true ] );
		RankMySEO_Test_State::$rewrites = [];
		$module->register_rewrites();
		$patterns = array_column( RankMySEO_Test_State::$rewrites, 'regex' );
		$this->assertContains( '^([a-f0-9]{32})\\.txt$', $patterns );
	}

	public function test_rest_exposes_status_flush_and_blocks_ping_while_disabled(): void {
		$core     = new Settings();
		$settings = new SitemapSettings();
		$indexnow = new IndexNow( $settings, new WpIndexNowTransport() );
		$robots   = new RobotsTxt( $settings, '/tmp/none', static fn(): bool => true );
		$source     = new WordPressSitemapSource( $settings, new wpdb() );
		$llms       = new LlmsTxt( $settings, new SitemapLlmsCatalog( $source ), '/tmp/none', static fn(): bool => true );
		$controller = new SitemapsController( $core, $settings, $indexnow, $robots, $llms, $source );

		$controller->register_routes();
		$routes = array_map(
			static fn( array $entry ): string => $entry['namespace'] . $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);
		$this->assertContains( 'rankmyseo/v1/sitemaps/status', $routes );
		$this->assertContains( 'rankmyseo/v1/sitemaps/flush', $routes );
		$this->assertContains( 'rankmyseo/v1/sitemaps/indexnow/ping', $routes );
		$this->assertContains( 'rankmyseo/v1/sitemaps/robots', $routes );
		$this->assertContains( 'rankmyseo/v1/sitemaps/llms/test', $routes );

		$status = $controller->get_status()->get_data();
		$this->assertTrue( $status['module_enabled'] );
		$this->assertFalse( $status['indexnow']['enabled'] );
		$this->assertSame( 'https://api.indexnow.org/indexnow', $status['indexnow']['endpoint'] );
		$this->assertTrue( $status['robots']['physical_file'] );
		$this->assertContains( 'physical_file', array_column( $status['robots']['warnings'], 'code' ) );
		$this->assertFalse( $status['llms']['enabled'] );
		$this->assertTrue( $status['llms']['physical_file'] );
		$this->assertContains( 'physical_file', array_column( $status['llms']['warnings'], 'code' ) );

		$request = new WP_REST_Request();
		$request->set_json_params(
			[
				'indexnow_enabled'   => false,
				'max_urls'           => 50,
				'hide_utility_pages' => true,
				'post_types'         => [
					'post'    => true,
					'page'    => false,
					'product' => true,
				],
			]
		);
		$updated = $controller->update_settings( $request )->get_data();
		$this->assertSame( 100, $updated['settings']['max_urls'] );
		$page = null;
		foreach ( $updated['status']['post_types'] as $row ) {
			if ( 'page' === $row['name'] ) {
				$page = $row;
			}
		}
		$this->assertIsArray( $page );
		$this->assertFalse( $page['included'] );
		$this->assertTrue( $updated['status']['hide_utility_pages'] );

		$ping = $controller->ping( $request );
		$this->assertInstanceOf( WP_Error::class, $ping );
		$this->assertSame( 0, RankMySEO_Test_State::$remote_posts );

		$flushed = $controller->flush_cache()->get_data();
		$this->assertTrue( $flushed['flushed'] );
		$this->assertGreaterThan( 1, $flushed['generation'] );
	}
}
