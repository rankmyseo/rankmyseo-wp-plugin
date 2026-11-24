<?php
/**
 * Unit tests for the virtual llms.txt guide.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Modules\Sitemaps\IndexNow;
use RankMySEO\Modules\Sitemaps\LlmsCatalog;
use RankMySEO\Modules\Sitemaps\LlmsTxt;
use RankMySEO\Modules\Sitemaps\RobotsTxt;
use RankMySEO\Modules\Sitemaps\SitemapSettings;
use RankMySEO\Modules\Sitemaps\SitemapsModule;
use RankMySEO\Modules\Sitemaps\WpIndexNowTransport;
use RankMySEO_Test_State;

final class LlmsTxtTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		RankMySEO_Test_State::$bloginfo['name']        = 'Acme';
		RankMySEO_Test_State::$bloginfo['description'] = 'Shoes and care';
	}

	public function test_render_groups_indexable_links_and_applies_manual_rules(): void {
		$settings = new SitemapSettings();
		$settings->update(
			[
				'llms_enabled'      => true,
				'llms_intro'        => "# Hand-picked pages.\n",
				'llms_include_urls' => "https://example.com/extra/\nhttps://example.com/about/",
				'llms_exclude_urls' => "https://example.com/hello/\n/secret/",
				'post_types'        => [
					'post' => true,
					'page' => true,
				],
				'llms_post_types'   => [
					'post' => false,
					'page' => true,
				],
			]
		);

		$llms = new LlmsTxt( $settings, $this->catalog(), '/tmp/none', static fn(): bool => false );
		$body = $llms->render();

		$expected = <<<'MD'
# Acme

> Shoes and care

Hand-picked pages.

## Site

- [Acme](https://example.com/)

## Pages

- [About](https://example.com/about/): Our story

## Included links

- [extra](https://example.com/extra/)

MD;
		$this->assertSame( $expected, $body );
		$this->assertSame( 3, $llms->link_count() );
		$this->assertStringNotContainsString( 'Hello', $body );
		$this->assertStringNotContainsString( 'secret', $body );
		$this->assertStringNotContainsString( 'Draft', $body );
	}

	public function test_sitemap_exclusion_and_noindex_boundary_drop_links(): void {
		$settings = new SitemapSettings();
		$settings->update(
			[
				'post_types'      => [
					'post' => false,
					'page' => true,
				],
				'llms_post_types' => [
					'post' => true,
					'page' => true,
				],
			]
		);
		$llms = new LlmsTxt( $settings, $this->catalog() );
		$body = $llms->render();

		$this->assertStringContainsString( 'About', $body );
		$this->assertStringNotContainsString( 'Hello', $body );
		$this->assertStringNotContainsString( 'Draft', $body );
	}

	public function test_physical_file_warning_matches_the_robots_pattern(): void {
		$settings = new SitemapSettings();
		$llms     = new LlmsTxt( $settings, $this->catalog(), '/var/www/html', static fn(): bool => true );

		$this->assertTrue( $llms->has_physical_file() );
		$this->assertSame( '/var/www/html/llms.txt', $llms->physical_path() );
		$warnings = $llms->warnings();
		$this->assertSame( 'physical_file', $warnings[0]['code'] );
		$this->assertStringContainsString( '/var/www/html/llms.txt', $warnings[0]['message'] );
		$this->assertStringContainsString( 'web server', $warnings[0]['message'] );
	}

	public function test_rewrite_is_added_only_when_the_guide_is_on(): void {
		$container = new Container();
		$settings  = new SitemapSettings();
		$container->set( SitemapSettings::class, $settings );
		$container->set( IndexNow::class, new IndexNow( $settings, new WpIndexNowTransport() ) );
		$container->set( RobotsTxt::class, new RobotsTxt( $settings, '/tmp/none', static fn(): bool => false ) );

		$module = new SitemapsModule( $container );
		$module->register_rewrites();
		$patterns = array_column( RankMySEO_Test_State::$rewrites, 'regex' );
		$this->assertNotContains( '^llms\\.txt$', $patterns );

		$settings->update( [ 'llms_enabled' => true ] );
		RankMySEO_Test_State::$rewrites = [];
		$module->register_rewrites();
		$patterns = array_column( RankMySEO_Test_State::$rewrites, 'regex' );
		$this->assertContains( '^llms\\.txt$', $patterns );
	}

	public function test_intro_cannot_inject_a_heading_and_bad_urls_are_dropped(): void {
		$settings = new SitemapSettings();
		$settings->update(
			[
				'llms_intro'        => "<script>alert(1)</script>\n## Injected",
				'llms_include_urls' => "javascript:alert(1)\nhttps://example.com/ok page\nnot a url",
				'llms_exclude_urls' => '/about/',
			]
		);
		$body = ( new LlmsTxt( $settings, $this->catalog() ) )->render();

		$this->assertStringNotContainsString( '<script>', $body );
		$this->assertStringNotContainsString( '## Injected', $body );
		$this->assertStringContainsString( 'Injected', $body );
		$this->assertStringNotContainsString( 'javascript:', $body );
		$this->assertStringNotContainsString( 'About', $body );
	}

	private function catalog(): LlmsCatalog {
		return new class() implements LlmsCatalog {
			public function post_types(): array {
				return [
					'post' => 'Posts',
					'page' => 'Pages',
				];
			}

			public function links_for( string $post_type, int $limit ): array {
				$links = [
					'page' => [
						[
							'title'       => 'About',
							'url'         => 'https://example.com/about/',
							'description' => 'Our story',
						],
					],
					'post' => [
						[
							'title'       => 'Hello',
							'url'         => 'https://example.com/hello/',
							'description' => 'A post',
						],
						[
							'title'       => 'Draft',
							'url'         => 'https://example.com/draft/',
							'description' => 'Unpublished',
						],
					],
				];

				return array_slice( $links[ $post_type ] ?? [], 0, max( 0, $limit ) );
			}
		};
	}
}
