<?php
/**
 * Unit tests for the local link index, orphan report, and suggestions.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Core\OnboardingStore;
use RankMySEO\Core\Settings;
use RankMySEO\Database\Migrator;
use RankMySEO\Database\Schema;
use RankMySEO\Modules\Analyzer\AnalyzerModule;
use RankMySEO\Modules\Chat\LocalAssistant;
use RankMySEO\Modules\Chat\SiteHealth;
use RankMySEO\Modules\Chat\SiteIndex;
use RankMySEO\Modules\Links\LinkIndexer;
use RankMySEO\Modules\Links\LinkRepository;
use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\TitleSettings;
use RankMySEO\Modules\ModuleManager;
use RankMySEO\Rest\DashboardController;
use RankMySEO\Rest\LinksController;
use RankMySEO\Rest\RestRegistry;
use RankMySEO_Test_State;
use wpdb;
use WP_Post;
use WP_REST_Request;
use WP_Term;

final class LinksTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_counts_incoming_links_and_ignores_unindexed_pages(): void {
		$source = $this->post( 1, 'Garden tools', 'garden-tools', '<p><a href="/hand-forks/">Forks</a></p>' );
		$this->post( 2, 'Hand forks', 'hand-forks' );
		$this->post( 3, 'Gloves', 'gloves' );
		$indexer = $this->indexer();

		$this->assertSame( 0, $indexer->orphan_count() );

		$indexer->index_post( $source );
		$this->assertSame(
			[
				'outgoing' => 1,
				'incoming' => 0,
			],
			$this->counts( 1 )
		);
		$this->assertSame(
			[
				'outgoing' => 0,
				'incoming' => 1,
			],
			$this->counts( 2 )
		);
		$this->assertSame( [ 1 ], array_column( $indexer->list_orphans( 10 ), 'id' ) );

		$source->post_content = '<p><a href="/garden-tools/">Self</a> <a href="https://example.org/away">Away</a></p>';
		$indexer->index_post( $source );
		$this->assertSame( 0, $this->counts( 1 )['outgoing'] );
		$this->assertSame( 0, $this->counts( 2 )['incoming'] );
	}

	public function test_orphans_skip_noindex_and_reindex_is_chunked(): void {
		$this->post( 4, 'Visible', 'visible' );
		$hidden = $this->post( 5, 'Hidden', 'hidden' );
		update_post_meta( 5, MetaKeys::ROBOTS, [ 'noindex' => true ] );
		$indexer = $this->indexer();

		$first = $indexer->reindex( 0, 1 );
		$this->assertSame( 1, $first['processed'] );
		$this->assertFalse( $first['done'] );
		$second = $indexer->reindex( $first['nextOffset'], 1 );
		$this->assertTrue( $second['done'] );
		$this->assertSame( 2, $second['total'] );

		$ids = array_column( $indexer->list_orphans( 10 ), 'id' );
		$this->assertContains( 4, $ids );
		$this->assertNotContains( 5, $ids );

		$hidden->post_status = 'draft';
		$indexer->on_save( 5, $hidden );
		$this->assertSame(
			[
				'outgoing' => 0,
				'incoming' => 0,
			],
			$this->counts( 5 )
		);
	}

	public function test_suggestions_prefer_shared_keywords_cornerstone_and_orphans(): void {
		$this->post( 10, 'Garden tools', 'garden-tools' );
		$this->post( 11, 'Pruning saw', 'pruning-saw' );
		$this->post( 12, 'City news', 'city-news' );
		update_post_meta( 10, MetaKeys::FOCUS_KEYWORD, 'garden tools' );
		update_post_meta( 11, MetaKeys::FOCUS_KEYWORD, 'running shoes' );
		update_post_meta( 11, MetaKeys::CORNERSTONE, '1' );
		update_post_meta( 12, MetaKeys::FOCUS_KEYWORD, 'weather' );
		$term                                 = new WP_Term();
		$term->slug                           = 'yard';
		RankMySEO_Test_State::$categories[10] = [ $term ];
		RankMySEO_Test_State::$categories[11] = [ $term ];

		$indexer = $this->indexer();
		$indexer->reindex( 0, 20 );
		$rows = $indexer->suggestions(
			[
				'post_id'   => 10,
				'focus'     => 'run shoe',
				'secondary' => [],
				'title'     => 'Garden tools',
				'slug'      => 'garden-tools',
			]
		);
		$this->assertSame( 11, $rows[0]['id'] );
		$this->assertContains( 'shared_keyword', $rows[0]['reasons'] );
		$this->assertContains( 'cornerstone', $rows[0]['reasons'] );
		$this->assertTrue( $rows[0]['orphan'] );
		$this->assertGreaterThan( $rows[1]['score'], $rows[0]['score'] );
	}

	public function test_rest_routes_and_singular_copy(): void {
		$settings  = new Settings();
		$container = new Container();
		$indexer   = $this->indexer();
		$container->set( Settings::class, $settings );
		$container->set( TitleSettings::class, new TitleSettings( $settings ) );
		$container->set( LinkIndexer::class, $indexer );
		$registry = new RestRegistry();
		$container->set( RestRegistry::class, $registry );
		$module = new AnalyzerModule( $container );
		$module->register();
		$registry->register_routes();

		$routes = array_map(
			static fn( array $entry ): string => $entry['namespace'] . $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);
		$this->assertContains( 'rankmyseo/v1/links/orphans', $routes );
		$this->assertContains( 'rankmyseo/v1/links/suggestions', $routes );
		$this->assertContains( 'rankmyseo/v1/links/reindex', $routes );

		$controller                              = new LinksController( $indexer );
		RankMySEO_Test_State::$current_user_caps = [];
		$this->assertInstanceOf( \WP_Error::class, $controller->check_manage_permission() );
		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$this->assertTrue( $controller->check_edit_posts_permission() );

		$this->post( 21, 'Solo page', 'solo-page' );
		$request = new WP_REST_Request();
		$request->set_param( 'offset', 0 );
		$request->set_param( 'limit', 20 );
		$indexed = $controller->post_reindex( $request )->get_data();
		$this->assertSame( 1, $indexed['processed'] );
		$this->assertSame( 1, $controller->get_orphans()->get_data()['count'] );

		$suggest = new WP_REST_Request();
		$suggest->set_param( 'post_id', 21 );
		$suggest->set_param( 'focus', 'solo page' );
		$suggest->set_param( 'secondary', 'extra phrase, extra phrase' );
		$this->assertIsArray( $controller->get_suggestions( $suggest )->get_data()['suggestions'] );

		$health = SiteHealth::summarize(
			[
				'blog_public'          => true,
				'onboarding_completed' => true,
				'missing_descriptions' => 1,
				'missing_titles'       => 1,
				'thin_posts'           => 1,
				'low_scores'           => 0,
				'open_404s'            => 1,
				'orphans'              => 1,
				'sitemaps_enabled'     => true,
				'tables_healthy'       => true,
			]
		);
		$this->assertStringContainsString( '1 published item is missing a search description.', $health['summary'] );
		$this->assertStringContainsString( '1 published item is missing an SEO title.', $health['summary'] );
		$this->assertStringContainsString( '1 published item looks thin.', $health['summary'] );
		$this->assertStringContainsString( '1 broken link is still open.', $health['summary'] );
		$this->assertStringContainsString( '1 published page has no incoming links.', $health['summary'] );

		update_option( OnboardingStore::OPTION_COMPLETED, 1 );
		$dashboard = new DashboardController(
			new LocalAssistant( new OneCountIndex(), new Settings(), new OnboardingStore( new Settings() ), new Migrator( new wpdb(), static function (): void {} ) ),
			new OneCountIndex(),
			new OnboardingStore( new Settings() ),
			new ModuleManager( new Settings() ),
			new Migrator( new wpdb(), static function (): void {} )
		);
		$titles    = array_column( $dashboard->get_summary()->get_data()['fixes'], 'title' );
		$this->assertContains( '1 published item is missing a search description', $titles );
		$this->assertContains( '1 published item looks thin', $titles );
		$this->assertContains( '1 broken link needs a redirect', $titles );
		$this->assertContains( '1 published item has a low SEO score', $titles );
	}

	public function test_assistant_answers_orphan_questions(): void {
		$this->post( 30, 'Lonely page', 'lonely-page' );
		$indexer = $this->indexer();
		$indexer->reindex( 0, 20 );
		$settings  = new Settings();
		$assistant = new LocalAssistant(
			new OneCountIndex(),
			$settings,
			new OnboardingStore( $settings ),
			new Migrator( new wpdb(), static function (): void {} ),
			$indexer
		);
		$this->assertSame( 'orphans', $assistant->detect_intent( 'Show orphan pages' ) );
		$this->assertSame( 'site_health', $assistant->detect_intent( 'What should I fix first?' ) );
		$reply = $assistant->respond( 'Which pages have no incoming links?' );
		$this->assertSame( 'orphans', $reply['intent'] );
		$this->assertStringContainsString( '1 published page has no links', $reply['explanation'] );
		$this->assertSame( 'Lonely page', $reply['items'][0]['title'] );
	}

	public function test_schema_registers_link_tables_at_version_two(): void {
		$this->assertSame( 2, Schema::DB_VERSION );
		$names = Schema::get_table_names( 'wp_' );
		$this->assertArrayHasKey( 'link_edges', $names );
		$this->assertArrayHasKey( 'link_counts', $names );
		$sql = implode( "\n", Schema::get_schema_sql( 'wp_', 'DEFAULT CHARSET utf8mb4' ) );
		$this->assertStringContainsString( 'wp_rankmyseo_link_edges', $sql );
		$this->assertStringContainsString( 'wp_rankmyseo_link_counts', $sql );
	}

	private ?wpdb $db = null;

	private function indexer(): LinkIndexer {
		$this->db = new wpdb();
		return new LinkIndexer( new LinkRepository( $this->db ) );
	}

	/**
	 * @return array{outgoing: int, incoming: int}
	 */
	private function counts( int $post_id ): array {
		return ( new LinkRepository( $this->db ?? new wpdb() ) )->counts_for( $post_id );
	}

	private function post( int $id, string $title, string $slug, string $content = '' ): WP_Post {
		$post                               = new WP_Post();
		$post->ID                           = $id;
		$post->post_title                   = $title;
		$post->post_name                    = $slug;
		$post->post_type                    = 'post';
		$post->post_status                  = 'publish';
		$post->post_content                 = $content;
		RankMySEO_Test_State::$posts[ $id ] = $post;
		return $post;
	}
}

/**
 * One of every counted problem, so singular copy can be asserted.
 */
final class OneCountIndex implements SiteIndex {
	public function count_missing_titles(): int {
		return 1;
	}

	public function list_missing_titles( int $limit ): array {
		unset( $limit );
		return [];
	}

	public function count_missing_descriptions(): int {
		return 1;
	}

	public function list_missing_descriptions( int $limit ): array {
		unset( $limit );
		return [];
	}

	public function count_low_scores( int $threshold ): int {
		unset( $threshold );
		return 1;
	}

	public function list_low_scores( int $threshold, int $limit ): array {
		unset( $threshold, $limit );
		return [];
	}

	public function count_thin_posts( int $min_words ): int {
		unset( $min_words );
		return 1;
	}

	public function list_thin_posts( int $min_words, int $limit ): array {
		unset( $min_words, $limit );
		return [];
	}

	public function count_open_404s(): int {
		return 1;
	}

	public function list_open_404s( int $limit ): array {
		unset( $limit );
		return [];
	}

	public function redirect_counts(): array {
		return [
			'total'  => 0,
			'active' => 0,
		];
	}

	public function get_post( int $post_id ): ?array {
		unset( $post_id );
		return null;
	}

	public function find_posts( string $lookup, int $limit ): array {
		unset( $lookup, $limit );
		return [];
	}
}
