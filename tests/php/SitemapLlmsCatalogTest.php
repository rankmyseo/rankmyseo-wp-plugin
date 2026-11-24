<?php
/**
 * Titles for llms.txt links that are archives rather than posts.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Modules\Sitemaps\SitemapLlmsCatalog;
use RankMySEO\Modules\Sitemaps\SitemapSource;
use RankMySEO_Test_State;
use WP_Post;

final class SitemapLlmsCatalogTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_shop_and_archive_urls_use_titles_instead_of_the_raw_url(): void {
		$shop             = new WP_Post();
		$shop->ID         = 50;
		$shop->post_title = 'Shop';
		$shop->post_name  = 'shop';
		$shop->post_type  = 'page';
		RankMySEO_Test_State::$posts[50]        = $shop;
		RankMySEO_Test_State::$shop_page_id     = 50;

		$page             = new WP_Post();
		$page->ID         = 8;
		$page->post_title = 'About the roastery';
		$page->post_excerpt = 'How the coffee is roasted';
		RankMySEO_Test_State::$posts[8] = $page;
		RankMySEO_Test_State::$permalinks[8] = 'https://example.com/about/';

		RankMySEO_Test_State::$post_type_objects['portfolio'] = (object) [
			'name'        => 'portfolio',
			'has_archive' => true,
			'labels'      => (object) [ 'name' => 'Portfolio' ],
		];

		$source = new class() implements SitemapSource {
			public function providers(): array {
				return [
					[
						'kind'  => 'post',
						'name'  => 'page',
						'label' => 'Pages',
						'slug'  => 'page',
					],
				];
			}

			public function count( string $kind, string $name ): int {
				unset( $kind, $name );
				return 4;
			}

			public function entries( string $kind, string $name, int $page, int $per_page ): array {
				unset( $kind, $name, $page, $per_page );
				return [
					[ 'loc' => 'https://example.com/about/', 'lastmod' => '', 'images' => [] ],
					[ 'loc' => 'https://example.com/shop/', 'lastmod' => '', 'images' => [] ],
					[ 'loc' => 'https://example.com/product/', 'lastmod' => '', 'images' => [] ],
					[ 'loc' => 'https://example.com/portfolio/', 'lastmod' => '', 'images' => [] ],
					[ 'loc' => 'https://example.com/mystery/', 'lastmod' => '', 'images' => [] ],
				];
			}
		};

		$links = ( new SitemapLlmsCatalog( $source ) )->links_for( 'page', 10 );
		$by_url = [];
		foreach ( $links as $link ) {
			$by_url[ $link['url'] ] = $link;
		}

		$this->assertSame( 'About the roastery', $by_url['https://example.com/about/']['title'] );
		$this->assertSame( 'How the coffee is roasted', $by_url['https://example.com/about/']['description'] );
		$this->assertSame( 'Shop', $by_url['https://example.com/shop/']['title'] );
		$this->assertSame( 'Shop', $by_url['https://example.com/product/']['title'] );
		$this->assertSame( 'Portfolio', $by_url['https://example.com/portfolio/']['title'] );
		$this->assertSame( 'https://example.com/mystery/', $by_url['https://example.com/mystery/']['title'] );
		$this->assertStringNotContainsString( 'https://example.com/shop/', $by_url['https://example.com/shop/']['title'] );
	}
}
