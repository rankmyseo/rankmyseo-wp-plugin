<?php
/**
 * Unit tests for the shared breadcrumb trail and visible markup.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Modules\Schema\BreadcrumbRenderer;
use RankMySEO\Modules\Schema\BreadcrumbTrail;
use RankMySEO\Modules\Schema\ContentBlocks;
use RankMySEO_Test_State;
use WP_Post;
use WP_Term;

final class BreadcrumbsTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		require_once dirname( __DIR__, 2 ) . '/includes/template-functions.php';
	}

	public function test_page_hierarchy_and_category_parents_share_one_trail(): void {
		$parent              = $this->post( 10, 'Guides', 'page', 'guides' );
		$child               = $this->post( 11, 'Shoes', 'page', 'shoes', 10 );
		RankMySEO_Test_State::$posts[10] = $parent;
		RankMySEO_Test_State::$posts[11] = $child;

		$trail = ( new BreadcrumbTrail() )->for_post( $child );
		$this->assertSame( [ 'Home', 'Guides', 'Shoes' ], array_column( $trail, 'name' ) );

		$root                    = new WP_Term();
		$root->term_id           = 3;
		$root->name              = 'News';
		$root->slug              = 'news';
		$root->taxonomy          = 'category';
		$child_term              = new WP_Term();
		$child_term->term_id     = 4;
		$child_term->name        = 'Local';
		$child_term->slug        = 'local';
		$child_term->taxonomy    = 'category';
		$child_term->parent      = 3;
		RankMySEO_Test_State::$terms[3] = $root;
		$article                 = $this->post( 12, 'Hello', 'post', 'hello' );
		RankMySEO_Test_State::$posts[12]      = $article;
		RankMySEO_Test_State::$categories[12] = [ $child_term ];

		$post_trail = ( new BreadcrumbTrail() )->for_post( $article );
		$this->assertSame( [ 'Home', 'News', 'Local', 'Hello' ], array_column( $post_trail, 'name' ) );
		$this->assertSame( 'https://example.com/category/news/', $post_trail[1]['url'] );
	}

	public function test_search_archive_author_and_date_trails(): void {
		RankMySEO_Test_State::$is_search    = true;
		RankMySEO_Test_State::$search_query = 'boots';
		$search                             = ( new BreadcrumbTrail() )->for_request();
		$this->assertSame( 'Search results for “boots”', $search[1]['name'] );

		RankMySEO_Test_State::reset();
		RankMySEO_Test_State::$is_category    = true;
		RankMySEO_Test_State::$queried_object = $this->term( 8, 'News', 'news' );
		$archive                              = ( new BreadcrumbTrail() )->for_request();
		$this->assertSame( [ 'Home', 'News' ], array_column( $archive, 'name' ) );

		RankMySEO_Test_State::reset();
		RankMySEO_Test_State::$is_author         = true;
		RankMySEO_Test_State::$queried_object_id = 7;
		RankMySEO_Test_State::$queried_object    = (object) [ 'display_name' => 'Ada' ];
		$author                                  = ( new BreadcrumbTrail() )->for_request();
		$this->assertSame( 'Ada', $author[1]['name'] );
		$this->assertSame( 'https://example.com/author/7/', $author[1]['url'] );

		RankMySEO_Test_State::reset();
		RankMySEO_Test_State::$is_date    = true;
		RankMySEO_Test_State::$query_vars = [
			'year'     => 2026,
			'monthnum' => 3,
			'day'      => 4,
		];
		$date = ( new BreadcrumbTrail() )->for_request();
		$this->assertSame( [ 'Home', '2026', 'March', '4' ], array_column( $date, 'name' ) );
	}

	public function test_woocommerce_shop_and_product_trails(): void {
		$shop            = $this->post( 20, 'Shop', 'page', 'shop' );
		$product         = $this->post( 21, 'Runner', 'product', 'runner' );
		RankMySEO_Test_State::$posts[20] = $shop;
		RankMySEO_Test_State::$posts[21] = $product;
		RankMySEO_Test_State::$shop_page_id = 20;
		$term            = $this->term( 9, 'Running', 'running', 'product_cat' );
		RankMySEO_Test_State::$post_terms[21]['product_cat'] = [ $term ];

		$product_trail = ( new BreadcrumbTrail() )->for_post( $product );
		$this->assertSame( [ 'Home', 'Shop', 'Running', 'Runner' ], array_column( $product_trail, 'name' ) );

		RankMySEO_Test_State::$is_product_category = true;
		RankMySEO_Test_State::$queried_object      = $term;
		$archive                                   = ( new BreadcrumbTrail() )->for_request();
		$this->assertSame( [ 'Home', 'Shop', 'Running' ], array_column( $archive, 'name' ) );

		RankMySEO_Test_State::$is_product_category = false;
		RankMySEO_Test_State::$is_shop             = true;
		$shop_trail                                = ( new BreadcrumbTrail() )->for_request();
		$this->assertSame( [ 'Home', 'Shop' ], array_column( $shop_trail, 'name' ) );
	}

	public function test_visible_html_shortcode_and_template_tag(): void {
		$parent = $this->post( 29, 'Guides', 'page', 'guides' );
		$post   = $this->post( 30, 'Current', 'page', 'current', 29 );
		RankMySEO_Test_State::$posts[29]         = $parent;
		RankMySEO_Test_State::$posts[30]         = $post;
		RankMySEO_Test_State::$is_singular       = true;
		RankMySEO_Test_State::$queried_object_id = 30;

		$html = ( new BreadcrumbRenderer() )->html(
			( new BreadcrumbTrail() )->for_request(),
			[
				'separator'    => '>',
				'show_home'    => true,
				'show_current' => true,
			]
		);

		$this->assertStringContainsString( '<nav class="rankmyseo-breadcrumbs" aria-label="Breadcrumb"><ol>', $html );
		$this->assertStringContainsString( 'aria-hidden="true">&gt;</span>', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringContainsString( '>Current</span>', $html );
		$this->assertArrayHasKey( 'rankmyseo-breadcrumbs', RankMySEO_Test_State::$enqueued_styles );

		$hidden = ( new BreadcrumbRenderer() )->html(
			[
				[
					'name' => 'Home',
					'url'  => 'https://example.com/',
				],
				[
					'name' => 'Current',
					'url'  => 'https://example.com/current/',
				],
			],
			[
				'show_home'    => false,
				'show_current' => false,
			]
		);
		$this->assertSame( '', $hidden );

		$blocks = new ContentBlocks();
		$blocks->register_blocks();
		$this->assertArrayHasKey( 'rankmyseo/breadcrumbs', RankMySEO_Test_State::$block_types );
		$shortcode = $blocks->shortcode(
			[
				'separator'    => '/',
				'show_home'    => '1',
				'show_current' => '0',
			]
		);
		$this->assertStringContainsString( '>Home</a>', $shortcode );
		$this->assertStringContainsString( 'Guides', $shortcode );
		$this->assertStringNotContainsString( 'Current', $shortcode );

		$returned = rankmyseo_breadcrumbs(
			[
				'echo'      => false,
				'separator' => '/',
			]
		);
		$this->assertStringContainsString( 'aria-current="page"', $returned );
	}

	private function post( int $id, string $title, string $type, string $slug, int $parent = 0 ): WP_Post {
		$post              = new WP_Post();
		$post->ID          = $id;
		$post->post_title  = $title;
		$post->post_type   = $type;
		$post->post_name   = $slug;
		$post->post_parent = $parent;
		$post->post_status = 'publish';
		return $post;
	}

	private function term( int $id, string $name, string $slug, string $taxonomy = 'category' ): WP_Term {
		$term           = new WP_Term();
		$term->term_id  = $id;
		$term->name     = $name;
		$term->slug     = $slug;
		$term->taxonomy = $taxonomy;
		return $term;
	}
}
