<?php
/**
 * Tests for Elementor and Divi editor integration.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\Analyzer\AnalyzerMeta;
use RankMySEO\Modules\Builders\BreadcrumbHtml;
use RankMySEO\Modules\Builders\BuilderAssets;
use RankMySEO\Modules\Builders\BuilderConfig;
use RankMySEO\Modules\Builders\BuilderDetector;
use RankMySEO\Modules\Builders\BuilderScreen;
use RankMySEO\Modules\Builders\Elementor\BreadcrumbsWidget;
use RankMySEO\Modules\Builders\PageBuilderBridge;
use RankMySEO\Modules\Meta\ConflictGuard;
use RankMySEO\Modules\Meta\DocumentBuilder;
use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\MetaStore;
use RankMySEO\Modules\Meta\TitleSettings;
use RankMySEO\Modules\Schema\BreadcrumbTrail;
use RankMySEO\Rest\MetaController;
use RankMySEO_Test_State;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_Term;

final class BuildersTest extends TestCase {
	/**
	 * Temporary plugin directories created by a test.
	 *
	 * @var list<string>
	 */
	private array $temp_dirs = [];

	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		$_GET = [];
	}

	protected function tearDown(): void {
		$_GET = [];
		foreach ( $this->temp_dirs as $dir ) {
			$this->remove_dir( $dir );
		}
		$this->temp_dirs = [];
	}

	public function test_breadcrumb_trail_matches_schema_for_posts_and_pages(): void {
		$parent               = new WP_Post();
		$parent->ID           = 5;
		$parent->post_title   = 'Parent <b>Page</b>';
		$parent->post_type    = 'page';
		$parent->post_name    = 'parent';
		$child                = new WP_Post();
		$child->ID            = 6;
		$child->post_title    = 'Child';
		$child->post_type     = 'page';
		$child->post_parent   = 5;
		RankMySEO_Test_State::$posts[5] = $parent;
		RankMySEO_Test_State::$posts[6] = $child;

		$page = BreadcrumbTrail::for_post( $child, 'https://example.com/', 'Child', 'https://example.com/parent/child/' );

		$this->assertSame( 'Home', $page['crumbs'][0]['name'] );
		$this->assertSame( 'https://example.com/', $page['crumbs'][0]['url'] );
		$this->assertSame( 'Parent Page', $page['crumbs'][1]['name'] );
		$this->assertSame( 'https://example.com/parent/', $page['crumbs'][1]['url'] );
		$this->assertSame( 'Child', $page['crumbs'][2]['name'] );
		$this->assertSame( '', $page['section'] );

		$post              = new WP_Post();
		$post->ID          = 7;
		$post->post_type   = 'post';
		$post->post_title  = 'Hello';
		$term              = new WP_Term();
		$term->name        = 'News';
		$term->slug        = 'news';
		RankMySEO_Test_State::$posts[7]      = $post;
		RankMySEO_Test_State::$categories[7] = [ $term ];

		$article = BreadcrumbTrail::for_post( $post, 'https://example.com', 'Hello', 'https://example.com/hello/' );

		$this->assertSame( 'News', $article['section'] );
		$this->assertSame( 'News', $article['crumbs'][1]['name'] );
		$this->assertSame( 'https://example.com/category/news/', $article['crumbs'][1]['url'] );
	}

	public function test_breadcrumb_html_is_accessible_and_escaped(): void {
		$html = BreadcrumbHtml::render(
			[
				[
					'name' => 'Home',
					'url'  => 'https://example.com/',
				],
				[
					'name' => 'Fish & Chips',
					'url'  => 'https://example.com/fish/',
				],
			]
		);

		$this->assertStringContainsString( '<nav class="rankmyseo-breadcrumbs" aria-label="Breadcrumb">', $html );
		$this->assertStringContainsString( '<a href="https://example.com/">Home</a>', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringContainsString( 'Fish &amp; Chips', $html );
		$this->assertStringNotContainsString( '<a href="https://example.com/fish/">Fish', $html );
	}

	public function test_inactive_builders_register_no_editor_hooks_or_scripts(): void {
		$bridge = $this->bridge( false, false, false );
		$bridge->register();

		$this->assertArrayNotHasKey( 'elementor/editor/before_enqueue_scripts', RankMySEO_Test_State::$actions );
		$this->assertArrayNotHasKey( 'elementor/widgets/register', RankMySEO_Test_State::$actions );
		$this->assertArrayNotHasKey( 'et_fb_enqueue_assets', RankMySEO_Test_State::$actions );

		do_action( 'elementor/editor/before_enqueue_scripts' );
		do_action( 'wp_enqueue_scripts' );
		$this->assertSame( [], RankMySEO_Test_State::$enqueued_scripts );
	}

	public function test_elementor_enqueues_only_on_its_editor_hook(): void {
		$dir    = $this->bundle_dir( 'elementor' );
		$bridge = $this->bridge( true, false, true, $dir );
		$bridge->register();

		$this->assertNotEmpty( RankMySEO_Test_State::$actions['elementor/editor/before_enqueue_scripts'] );
		$this->assertArrayNotHasKey( 'et_fb_enqueue_assets', RankMySEO_Test_State::$actions );
		$this->assertSame( [], RankMySEO_Test_State::$enqueued_scripts );

		do_action( 'elementor/editor/before_enqueue_scripts' );

		$this->assertSame( 'rankmyseo-elementor', RankMySEO_Test_State::$enqueued_scripts[0]['handle'] );
		$this->assertSame( 'rankmyseoElementor', RankMySEO_Test_State::$localized_scripts['rankmyseo-elementor']['name'] );
		$data = RankMySEO_Test_State::$localized_scripts['rankmyseo-elementor']['data'];
		$this->assertIsArray( $data );
		$this->assertNotSame( '', $data['nonce'] );
		$this->assertStringContainsString( '/rankmyseo/v1', (string) $data['restRoot'] );
	}

	public function test_elementor_hook_does_not_enqueue_when_the_bundle_is_missing(): void {
		$bridge = $this->bridge( true, false, false );
		$bridge->register();
		do_action( 'elementor/editor/before_enqueue_scripts' );

		$this->assertSame( [], RankMySEO_Test_State::$enqueued_scripts );
	}

	public function test_divi_enqueues_only_inside_the_visual_builder(): void {
		$dir    = $this->bundle_dir( 'divi' );
		$bridge = $this->bridge( false, true, true, $dir );
		$bridge->register();

		$this->assertArrayNotHasKey( 'elementor/editor/before_enqueue_scripts', RankMySEO_Test_State::$actions );
		$this->assertNotEmpty( RankMySEO_Test_State::$actions['et_fb_enqueue_assets'] );

		do_action( 'wp_enqueue_scripts' );
		$this->assertSame( [], RankMySEO_Test_State::$enqueued_scripts );

		$_GET['et_fb'] = '1';
		$_GET['post']  = '15';
		do_action( 'et_fb_enqueue_assets' );
		do_action( 'wp_enqueue_scripts' );

		$this->assertCount( 1, RankMySEO_Test_State::$enqueued_scripts );
		$this->assertSame( 'rankmyseo-divi', RankMySEO_Test_State::$enqueued_scripts[0]['handle'] );
		$this->assertSame( 'rankmyseoDivi', RankMySEO_Test_State::$localized_scripts['rankmyseo-divi']['name'] );
	}

	public function test_divi_screen_flags_ignore_unknown_requests(): void {
		$screen = new BuilderScreen();
		$this->assertFalse( $screen->is_divi_builder_request() );
		$this->assertSame( 0, $screen->edited_post_id() );

		$_GET['et_fb'] = '1';
		$_GET['post']  = '21';
		$this->assertTrue( $screen->is_divi_builder_request() );
		$this->assertSame( 21, $screen->edited_post_id() );
	}

	public function test_elementor_runtime_follows_the_loaded_action(): void {
		$detector = new BuilderDetector();
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			$this->assertFalse( $detector->elementor_loaded() );
		}

		do_action( 'elementor/loaded' );
		$this->assertTrue( ( new BuilderDetector() )->elementor_loaded() );
	}

	public function test_breadcrumb_widget_renders_the_current_post(): void {
		if ( ! class_exists( '\Elementor\Widget_Base', false ) ) {
			eval( 'namespace Elementor; class Widget_Base {}' ); // phpcs:ignore
		}

		$post             = new WP_Post();
		$post->ID         = 31;
		$post->post_type  = 'page';
		$post->post_title = 'Pricing';
		$post->post_name  = 'pricing';
		RankMySEO_Test_State::$posts[31]          = $post;
		RankMySEO_Test_State::$permalinks[31]     = 'https://example.com/pricing/';
		RankMySEO_Test_State::$queried_object_id  = 31;

		$widget = new BreadcrumbsWidget();
		$this->assertSame( 'rankmyseo-breadcrumbs', $widget->get_name() );
		$this->assertSame( 'RankMySEO Breadcrumbs', $widget->get_title() );

		$html = $widget->render_html();
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringContainsString( 'Pricing', $html );
		$this->assertStringContainsString( 'href="https://example.com/"', $html );
		$this->assertStringNotContainsString( 'https://example.com/pricing/', $html );

		$manager = new class() {
			/** @var list<object> */
			public array $widgets = [];

			/**
			 * @param object $widget Widget instance.
			 */
			public function register( object $widget ): void {
				$this->widgets[] = $widget;
			}
		};

		$bridge = $this->bridge( true, false, false );
		$bridge->elementor()->register_widgets( $manager );
		$this->assertCount( 1, $manager->widgets );
		$this->assertInstanceOf( BreadcrumbsWidget::class, $manager->widgets[0] );
	}

	public function test_rest_save_requires_nonce_and_edit_post_and_keeps_meta(): void {
		$controller = $this->controller();
		$post       = new WP_Post();
		$post->ID   = 9;
		$post->post_type = 'post';
		$post->post_title = 'Pricing';
		RankMySEO_Test_State::$posts[9] = $post;

		$nonce   = wp_create_nonce( 'wp_rest' );
		$request = new WP_REST_Request();
		$request->set_param( 'id', 9 );
		$request->set_header( 'X-WP-Nonce', 'wrong' );
		$bad_nonce = $controller->check_post_permission( $request );
		$this->assertInstanceOf( WP_Error::class, $bad_nonce );
		$this->assertSame( 'rankmyseo_invalid_nonce', $bad_nonce->get_error_code() );

		$request->set_header( 'X-WP-Nonce', $nonce );
		RankMySEO_Test_State::$current_user_caps = [];
		$forbidden = $controller->check_post_permission( $request );
		$this->assertInstanceOf( WP_Error::class, $forbidden );
		$this->assertSame( 'rankmyseo_forbidden', $forbidden->get_error_code() );

		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$this->assertTrue( $controller->check_post_permission( $request ) );

		$write = new WP_REST_Request();
		$write->set_param( 'id', 9 );
		$write->set_header( 'X-WP-Nonce', $nonce );
		$write->set_json_params(
			[
				'title'               => 'Builder title',
				'description'         => 'Builder description',
				'canonical'           => 'https://example.com/garden/',
				'focus_keyword'       => 'garden tools',
				'secondary_keywords'  => [ 'spades', 'soil' ],
				'robots'              => [ 'noindex' => true ],
				'og_title'            => 'Share title',
				'twitter_title'       => 'X title',
				'seo_score'           => 140,
				'seo_summary'         => '{"score":88}',
			]
		);

		$response = $controller->update_post_meta( $write );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Builder title', get_post_meta( 9, MetaKeys::TITLE, true ) );
		$this->assertSame( 'Builder description', get_post_meta( 9, MetaKeys::DESCRIPTION, true ) );
		$this->assertSame( 'garden tools', get_post_meta( 9, MetaKeys::FOCUS_KEYWORD, true ) );
		$this->assertSame( [ 'spades', 'soil' ], get_post_meta( 9, MetaKeys::SECONDARY_KEYWORDS, true ) );
		$this->assertSame( 'https://example.com/garden/', get_post_meta( 9, MetaKeys::CANONICAL, true ) );
		$this->assertTrue( get_post_meta( 9, MetaKeys::ROBOTS, true )['noindex'] );
		$this->assertSame( 'Share title', get_post_meta( 9, MetaKeys::OG_TITLE, true ) );
		$this->assertSame( 'X title', get_post_meta( 9, MetaKeys::TWITTER_TITLE, true ) );
		$this->assertSame( 100, get_post_meta( 9, MetaKeys::SEO_SCORE, true ) );
		$this->assertSame( '{"score":88}', get_post_meta( 9, AnalyzerMeta::SUMMARY, true ) );

		$again = new WP_REST_Request();
		$again->set_param( 'id', 9 );
		$again->set_json_params(
			[
				'title'         => 'Renamed title',
				'description'   => 'Builder description',
				'focus_keyword' => 'garden tools',
			]
		);
		$controller->update_post_meta( $again );
		$this->assertSame( 'Renamed title', get_post_meta( 9, MetaKeys::TITLE, true ) );
		$this->assertSame( 100, get_post_meta( 9, MetaKeys::SEO_SCORE, true ) );

		$read = $controller->get_post_meta( $write );
		$this->assertSame( 'Renamed title', $read->get_data()['meta']['title'] );
		$this->assertSame( 'garden tools', $read->get_data()['meta']['focus_keyword'] );
	}

	public function test_detector_status_reports_probed_builders(): void {
		$quiet = ( new BuilderDetector(
			static function (): bool {
				return false;
			},
			static function (): bool {
				return false;
			}
		) )->status();
		$this->assertFalse( $quiet['elementor']['active'] );
		$this->assertFalse( $quiet['divi']['active'] );

		$active = ( new BuilderDetector(
			static function (): bool {
				return true;
			},
			static function (): bool {
				return true;
			}
		) )->status();
		$this->assertTrue( $active['elementor']['detected'] );
		$this->assertTrue( $active['elementor']['active'] );
		$this->assertTrue( $active['divi']['detected'] );
		$this->assertSame( 'Elementor', $active['elementor']['label'] );
		$this->assertSame( 'Divi', $active['divi']['label'] );
	}

	/**
	 * Build a bridge with explicit builder probes.
	 *
	 * @param bool   $elementor Whether Elementor is loaded.
	 * @param bool   $divi      Whether Divi is active.
	 * @param bool   $bundle    Whether to create a readable bundle file.
	 * @param string $dir       Optional bundle directory.
	 */
	private function bridge( bool $elementor, bool $divi, bool $bundle, string $dir = '' ): PageBuilderBridge {
		if ( $bundle && '' === $dir ) {
			$dir = $this->bundle_dir( $elementor ? 'elementor' : 'divi' );
		}
		if ( '' === $dir ) {
			$dir = $this->empty_dir();
		}

		$settings = new Settings();
		return new PageBuilderBridge(
			new BuilderDetector(
				static function () use ( $elementor ): bool {
					return $elementor;
				},
				static function () use ( $divi ): bool {
					return $divi;
				}
			),
			new BuilderAssets( $dir, 'https://example.com/plugin' ),
			new BuilderConfig( $settings, new TitleSettings( $settings ), new MetaStore() )
		);
	}

	private function controller(): MetaController {
		$settings = new Settings();
		$titles   = new TitleSettings( $settings );
		return new MetaController(
			$settings,
			$titles,
			new MetaStore(),
			new ConflictGuard( $titles, false ),
			new DocumentBuilder()
		);
	}

	private function bundle_dir( string $slug ): string {
		$dir = $this->empty_dir();
		if ( ! is_dir( $dir . '/build' ) ) {
			mkdir( $dir . '/build', 0777, true );
		}
		file_put_contents( $dir . '/build/' . $slug . '.js', '/* rankmyseo builder test */' );
		return $dir;
	}

	private function empty_dir(): string {
		$dir = sys_get_temp_dir() . '/rankmyseo-builders-' . uniqid( '', true );
		mkdir( $dir, 0777, true );
		$this->temp_dirs[] = $dir;
		return $dir;
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->remove_dir( $path );
				continue;
			}
			unlink( $path );
		}
		rmdir( $dir );
	}
}
