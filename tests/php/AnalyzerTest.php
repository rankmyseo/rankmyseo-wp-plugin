<?php
/**
 * Unit tests for the on-page score, cannibalization route, and list column.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\Analyzer\AdminColumn;
use RankMySEO\Modules\Analyzer\AnalyzerMeta;
use RankMySEO\Modules\Analyzer\AnalyzerModule;
use RankMySEO\Modules\Analyzer\CannibalizationController;
use RankMySEO\Modules\Analyzer\ScoreCalculator;
use RankMySEO\Modules\Analyzer\ScorePersister;
use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\MetaRegistry;
use RankMySEO\Modules\Meta\MetaStore;
use RankMySEO\Modules\Meta\QuickBulkEdit;
use RankMySEO\Modules\Meta\TitleSettings;
use RankMySEO\Rest\RestRegistry;
use RankMySEO_Test_State;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;

final class AnalyzerTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		$_GET = [];
	}

	public function test_score_meta_registers_without_duplicating_the_focus_keyword(): void {
		( new MetaRegistry() )->register_keys();
		( new AnalyzerMeta() )->register_keys();

		$focus = array_values(
			array_filter(
				RankMySEO_Test_State::$registered_meta,
				static fn( array $entry ): bool => MetaKeys::FOCUS_KEYWORD === ( $entry['key'] ?? '' ) && 'post' === ( $entry['object'] ?? '' )
			)
		);
		$this->assertCount( 1, $focus );

		$score = $this->registered( MetaKeys::SEO_SCORE, 'post' );
		$this->assertNotNull( $score );
		$this->assertSame( 'integer', $score['args']['type'] );
		$this->assertTrue( $score['args']['show_in_rest'] );

		$summary = $this->registered( AnalyzerMeta::SUMMARY, 'page' );
		$this->assertNotNull( $summary );
		$this->assertSame( 'string', $summary['args']['type'] );
		$this->assertTrue( $summary['args']['show_in_rest'] );
		$this->assertSame( 84, AnalyzerMeta::sanitize_score( 84 ) );
		$this->assertSame( 0, AnalyzerMeta::sanitize_score( -4 ) );
		$this->assertSame( 100, AnalyzerMeta::sanitize_score( 140 ) );
		$this->assertSame( '', AnalyzerMeta::sanitize_summary( 'not-json' ) );
		$this->assertStringContainsString(
			'good',
			AnalyzerMeta::sanitize_summary(
				[
					'band'  => 'good',
					'score' => 90,
				]
			)
		);
	}

	public function test_analyzer_registers_the_focus_keyword_when_meta_has_not(): void {
		( new AnalyzerMeta() )->register_keys();
		$this->assertNotNull( $this->registered( MetaKeys::FOCUS_KEYWORD, 'post' ) );
		$this->assertNotNull( $this->registered( MetaKeys::SEO_SCORE, 'page' ) );
	}

	public function test_server_score_matches_the_weighted_bands(): void {
		$calculator = new ScoreCalculator();
		$empty      = $calculator->analyze(
			[
				'focus_keyword'    => '',
				'seo_title'        => '',
				'meta_description' => '',
				'slug'             => '',
				'content_html'     => '',
			]
		);
		$max        = 0;
		foreach ( $empty['checks'] as $check ) {
			$max += (int) $check['max_score'];
		}
		$this->assertSame( 100, $max );
		$this->assertSame( 'no_keyword', $empty['band'] );

		$strong = $calculator->analyze( $this->strong_input() );
		$this->assertGreaterThanOrEqual( 80, $strong['score'] );
		$this->assertSame( 'good', $strong['band'] );
		$this->assertGreaterThanOrEqual( 300, $strong['summary']['word_count'] );

		$thin = $calculator->analyze(
			[
				'focus_keyword'    => 'pricing',
				'seo_title'        => 'Hi',
				'meta_description' => '',
				'slug'             => 'hello',
				'content_html'     => '<p>Hi.</p>',
				'locale'           => 'en_US',
			]
		);
		$this->assertLessThan( 50, $thin['score'] );
		$this->assertSame( 'poor', $thin['band'] );

		$stuffed = $calculator->analyze(
			[
				'focus_keyword'    => 'tools',
				'seo_title'        => 'Tools',
				'meta_description' => '',
				'slug'             => 'tools',
				'content_html'     => '<p>' . str_repeat( 'tools ', 40 ) . '</p>',
				'locale'           => 'en_US',
			]
		);
		$density = $this->check( $stuffed, 'keyword_density' );
		$this->assertSame( 'critical', $density['status'] );
		$this->assertTrue( $density['metrics']['stuffing'] );

		$indonesian = $calculator->analyze(
			array_merge(
				$this->strong_input(),
				[ 'locale' => 'id_ID' ]
			)
		);
		$reading    = $this->check( $indonesian, 'readability' );
		$this->assertSame( 3, $reading['score'] );
		$this->assertNull( $indonesian['summary']['flesch_reading_ease'] );

		$payload = $calculator->summary_payload( $strong );
		$this->assertSame( $strong['score'], $payload['score'] );
		$this->assertCount( 20, $payload['checks'] );
	}

	public function test_save_post_persists_a_score_and_skips_unauthorized_edits(): void {
		$post                           = new WP_Post();
		$post->ID                       = 5;
		$post->post_title               = 'Garden tools';
		$post->post_name                = 'garden-tools';
		$post->post_type                = 'post';
		$post->post_status              = 'publish';
		$post->post_excerpt             = 'Garden tools that help small yards stay tidy. Compare pruners, gloves, and hand tools for everyday planting and weekend care today.';
		$post->post_content             = (string) $this->strong_input()['content_html'];
		RankMySEO_Test_State::$posts[5] = $post;
		update_post_meta( 5, MetaKeys::FOCUS_KEYWORD, 'garden tools' );
		update_post_meta( 5, MetaKeys::TITLE, 'Garden tools for small city yards' );
		update_post_meta( 5, MetaKeys::DESCRIPTION, $post->post_excerpt );

		$persister = new ScorePersister( new TitleSettings( new Settings() ) );
		$persister->save_post( 5, $post );

		$score = get_post_meta( 5, MetaKeys::SEO_SCORE, true );
		$this->assertGreaterThanOrEqual( 80, (int) $score );
		$summary = json_decode( (string) get_post_meta( 5, AnalyzerMeta::SUMMARY, true ), true );
		$this->assertIsArray( $summary );
		$this->assertSame( 'good', $summary['band'] );

		RankMySEO_Test_State::$current_user_caps = [];
		$post->post_content                      = '<p>Hi.</p>';
		$persister->save_post( 5, $post );
		$this->assertSame( $score, get_post_meta( 5, MetaKeys::SEO_SCORE, true ) );
	}

	public function test_cannibalization_endpoint_lists_other_published_pages(): void {
		$this->seed_post( 7, 'Pricing', 'pricing', 'publish', 'team pricing' );
		$this->seed_post( 8, 'Old pricing', 'old-pricing', 'publish', 'Team Pricing' );
		$this->seed_post( 9, 'Draft pricing', 'draft-pricing', 'draft', 'team pricing' );
		$this->seed_post( 10, 'About', 'about', 'publish', 'about us' );

		$controller = new CannibalizationController();
		$request    = new WP_REST_Request();
		$request->set_param( 'keyword', 'team pricing' );
		$request->set_param( 'exclude_post_id', 7 );
		$response = $controller->get_matches( $request );
		$data     = $response->get_data();

		$this->assertSame( 1, $data['count'] );
		$this->assertSame( 8, $data['matches'][0]['id'] );
		$this->assertSame( 'Old pricing', $data['matches'][0]['title'] );
		$this->assertSame( 'https://example.com/wp-admin/post.php?post=8&action=edit', $data['matches'][0]['editLink'] );

		$request->set_param( 'keyword', '' );
		$this->assertSame( 0, $controller->get_matches( $request )->get_data()['count'] );

		RankMySEO_Test_State::$current_user_caps = [];
		$denied                                  = $controller->check_permission();
		$this->assertInstanceOf( WP_Error::class, $denied );
		$this->assertSame( 403, $denied->get_error_data()['status'] );

		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$this->assertTrue( $controller->check_permission() );
	}

	public function test_module_registers_the_cannibalization_route(): void {
		$settings  = new Settings();
		$container = new Container();
		$container->set( Settings::class, $settings );
		$container->set( TitleSettings::class, new TitleSettings( $settings ) );
		$registry = new RestRegistry();
		$container->set( RestRegistry::class, $registry );

		$module = new AnalyzerModule( $container );
		$this->assertTrue( $module->is_ready() );
		$module->register();
		$registry->register_routes();

		$routes = array_map(
			static fn( array $entry ): string => $entry['namespace'] . $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);
		$this->assertContains( 'rankmyseo/v1/analyzer/cannibalization', $routes );
	}

	public function test_admin_column_sorts_filters_and_renders_a_score_pill(): void {
		RankMySEO_Test_State::$is_admin = true;
		$column                         = new AdminColumn();
		$this->assertSame( 'rankmyseo_seo_score', $column->sortable_columns( [] )['rankmyseo'] );

		$query = new WP_Query();
		$query->set( 'orderby', 'rankmyseo_seo_score' );
		$column->apply_query( $query, 'needs_work' );
		$this->assertSame( 'meta_value_num', $query->get( 'orderby' ) );
		$this->assertSame( MetaKeys::SEO_SCORE, $query->get( 'meta_key' ) );
		$meta_query = $query->get( 'meta_query' );
		$this->assertSame( '<', $meta_query[0]['compare'] );
		$this->assertSame( 80, $meta_query[0]['value'] );

		$missing = new WP_Query();
		$column->apply_query( $missing, 'missing_keyword' );
		$this->assertSame( 'OR', $missing->get( 'meta_query' )['relation'] );
		$this->assertSame( MetaKeys::FOCUS_KEYWORD, $missing->get( 'meta_query' )[0]['key'] );

		$_GET['rankmyseo_seo_filter'] = 'needs_work';
		$this->assertSame( 'needs_work', $column->requested_filter() );

		ob_start();
		$column->render_filter( 'post' );
		$filter = (string) ob_get_clean();
		$this->assertStringContainsString( 'Needs SEO work', $filter );
		$this->assertStringContainsString( 'Missing focus keyword', $filter );
		$this->assertStringContainsString( 'selected="selected"', $filter );

		update_post_meta( 3, MetaKeys::SEO_SCORE, 84 );
		update_post_meta( 3, MetaKeys::FOCUS_KEYWORD, 'garden tools' );
		ob_start();
		( new QuickBulkEdit( new MetaStore() ) )->render_column( 'rankmyseo', 3 );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'rankmyseo-score-pill is-good', $html );
		$this->assertStringContainsString( '84', $html );
		$this->assertStringContainsString( 'Good', $html );
		$this->assertStringContainsString( 'garden tools', $html );
		$this->assertStringNotContainsString( 'rankmyseo-cornerstone', $html );

		update_post_meta( 3, MetaKeys::CORNERSTONE, '1' );
		ob_start();
		( new QuickBulkEdit( new MetaStore() ) )->render_column( 'rankmyseo', 3 );
		$badge = (string) ob_get_clean();
		$this->assertStringContainsString( 'rankmyseo-cornerstone', $badge );
		$this->assertStringContainsString( 'Cornerstone', $badge );

		$_GET['rankmyseo_seo_filter'] = 'cornerstone';
		$this->assertSame( 'cornerstone', $column->requested_filter() );
		$filtered = new WP_Query();
		$column->apply_query( $filtered, 'cornerstone' );
		$this->assertSame( MetaKeys::CORNERSTONE, $filtered->get( 'meta_query' )[0]['key'] );
		$this->assertSame( '1', $filtered->get( 'meta_query' )[0]['value'] );
	}

	public function test_secondary_keywords_and_english_word_forms_change_the_score(): void {
		$calculator = new ScoreCalculator();
		$primary    = $calculator->analyze( $this->strong_input() );
		$blended    = $calculator->analyze(
			array_merge(
				$this->strong_input(),
				[ 'secondary_keywords' => [ 'unrelated quartz mineral' ] ]
			)
		);
		$this->assertSame( $primary['score'], $blended['primary_score'] );
		$this->assertCount( 2, $blended['keywords'] );
		$this->assertSame( 'secondary', $blended['keywords'][1]['role'] );
		$this->assertSame( 'critical', $blended['keywords'][1]['status'] );
		$this->assertSame( (int) round( $primary['score'] * 0.8 ), $blended['score'] );
		$this->assertCount( 20, $blended['checks'] );

		$english = $calculator->analyze(
			[
				'focus_keyword'    => 'running shoes',
				'seo_title'        => 'Run shoe guide for city trails today',
				'meta_description' => '',
				'slug'             => 'running-shoes',
				'locale'           => 'en_US',
				'content_html'     => '<h1>Running shoes</h1><p>Running shoes help.</p>',
			]
		);
		$this->assertSame( 'pass', $this->check( $english, 'keyword_in_title' )['status'] );

		$synonym = $calculator->analyze(
			[
				'focus_keyword'    => 'buy',
				'seo_title'        => 'Purchase garden tools today',
				'meta_description' => '',
				'slug'             => 'buy',
				'locale'           => 'en_US',
				'content_html'     => '<p>Purchase one set.</p>',
			]
		);
		$this->assertTrue( $this->check( $synonym, 'keyword_in_title' )['metrics']['present'] );

		$indonesian = $calculator->analyze(
			[
				'focus_keyword'    => 'buy',
				'seo_title'        => 'Purchase garden tools today',
				'meta_description' => '',
				'slug'             => 'buy',
				'locale'           => 'id_ID',
				'content_html'     => '<p>Purchase one set. Buy stays exact.</p>',
			]
		);
		$this->assertFalse( $this->check( $indonesian, 'keyword_in_title' )['metrics']['present'] );
		$this->assertTrue( $this->check( $indonesian, 'keyword_in_intro' )['metrics']['present'] );

		$forms = new \RankMySEO\Modules\Analyzer\WordForms();
		$this->assertTrue( $forms->contains( 'Visit the city', 'cities', true ) );
		$this->assertFalse( $forms->contains( 'Visit the city', 'cities', false ) );
	}

	public function test_cornerstone_pages_need_more_words_and_links(): void {
		$input                 = $this->strong_input();
		$input['content_html'] = '<h1>Garden tools</h1><p>Garden tools help a small yard.</p>';
		$calculator            = new ScoreCalculator();
		$normal                = $calculator->analyze( $input );
		$pillar                = $calculator->analyze( array_merge( $input, [ 'cornerstone' => true ] ) );
		$this->assertSame( 300, $this->check( $normal, 'word_count' )['metrics']['target'] );
		$this->assertSame( 900, $this->check( $pillar, 'word_count' )['metrics']['target'] );
		$this->assertSame( 'critical', $this->check( $pillar, 'internal_links' )['status'] );
		$this->assertSame( 0, $this->check( $pillar, 'internal_links' )['score'] );

		$linked = $calculator->analyze(
			array_merge(
				$input,
				[
					'cornerstone'  => true,
					'content_html' => $input['content_html'] . '<p><a href="/a">A</a> <a href="/b">B</a> <a href="/c">C</a></p>',
				]
			)
		);
		$this->assertSame( 3, $this->check( $linked, 'internal_links' )['score'] );
		$this->assertSame( 'pass', $this->check( $linked, 'internal_links' )['status'] );
	}

	/**
	 * Strong page used by both the calculator and the save fallback.
	 *
	 * @return array<string, mixed>
	 */
	private function strong_input(): array {
		$sentence = 'Keep beds tidy with sharp edges and steady hands.';
		$chunks   = [ 'Garden tools make weekend planting easier.' ];
		while ( count( preg_split( '/\s+/', implode( ' ', $chunks ) ) ?: [] ) < 320 ) {
			$chunks[] = $sentence;
		}
		$body = implode( ' ', $chunks );

		return [
			'focus_keyword'    => 'garden tools',
			'seo_title'        => 'Garden tools for small city yards',
			'meta_description' => 'Garden tools that help small yards stay tidy. Compare pruners, gloves, and hand tools for everyday planting and weekend care today.',
			'slug'             => 'garden-tools',
			'post_type'        => 'post',
			'locale'           => 'en_US',
			'noindex'          => false,
			'site_url'         => 'https://example.com',
			'content_html'     => '<h1>Garden tools</h1><p>' . $body . '</p><h2>Garden tools for beds</h2><p>Choose a hand fork before you buy a large set.</p><h2>Care</h2><p>Wipe blades after each job.</p><img alt="Garden tools on a bench" src="/tools.jpg" /><a href="/gloves">Gloves</a><a href="https://example.org/guide">Guide</a>',
		];
	}

	/**
	 * Find one registered meta entry.
	 *
	 * @param string $key    Meta key.
	 * @param string $object Post type.
	 * @return array<string, mixed>|null
	 */
	private function registered( string $key, string $object ): ?array {
		foreach ( RankMySEO_Test_State::$registered_meta as $entry ) {
			if ( ( $entry['key'] ?? '' ) === $key && ( $entry['object'] ?? '' ) === $object ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * One check from a calculator result.
	 *
	 * @param array<string, mixed> $result Analyzer result.
	 * @param string               $id     Check id.
	 * @return array<string, mixed>
	 */
	private function check( array $result, string $id ): array {
		foreach ( $result['checks'] as $check ) {
			if ( is_array( $check ) && ( $check['id'] ?? '' ) === $id ) {
				return $check;
			}
		}
		$this->fail( 'Missing check ' . $id );
	}

	/**
	 * Store a post and its focus keyword.
	 *
	 * @param int    $id      Post ID.
	 * @param string $title   Title.
	 * @param string $slug    Slug.
	 * @param string $status  Status.
	 * @param string $keyword Focus keyword.
	 * @return void
	 */
	private function seed_post( int $id, string $title, string $slug, string $status, string $keyword ): void {
		$post                               = new WP_Post();
		$post->ID                           = $id;
		$post->post_title                   = $title;
		$post->post_name                    = $slug;
		$post->post_status                  = $status;
		$post->post_type                    = 'page';
		RankMySEO_Test_State::$posts[ $id ] = $post;
		update_post_meta( $id, MetaKeys::FOCUS_KEYWORD, $keyword );
	}
}
