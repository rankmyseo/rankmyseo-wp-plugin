<?php
/**
 * Tests for guided onboarding, dashboard summary, and the local chat inspector.
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
use RankMySEO\Modules\Chat\ChatModule;
use RankMySEO\Modules\Chat\LocalAssistant;
use RankMySEO\Modules\Chat\SiteHealth;
use RankMySEO\Modules\Chat\SiteIndex;
use RankMySEO\Modules\Chat\WordPressSiteIndex;
use RankMySEO\Modules\ModuleManager;
use RankMySEO\Rest\ChatController;
use RankMySEO\Rest\DashboardController;
use RankMySEO\Rest\OnboardingController;
use RankMySEO_Test_State;
use WP_Error;
use WP_REST_Request;
use wpdb;

final class OnboardingDashboardChatTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_onboarding_routes_and_completion_store_identity_without_settings_logo(): void {
		$settings   = new Settings();
		$onboarding = new OnboardingStore( $settings );
		$modules    = new ModuleManager( $settings );
		$controller = new OnboardingController( $onboarding, $settings, $modules );
		$controller->register_routes();

		$routes = array_map(
			static fn( array $entry ): string => $entry['namespace'] . $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);
		$this->assertContains( 'rankmyseo/v1/onboarding/state', $routes );
		$this->assertContains( 'rankmyseo/v1/onboarding/complete', $routes );

		$state = $controller->get_state()->get_data();
		$this->assertFalse( $state['completed'] );
		$this->assertSame( 'organization', $state['site_identity']['site_type'] );
		$this->assertArrayNotHasKey( 'logo_url', $settings->all() );

		$request = new WP_REST_Request();
		$request->set_json_params(
			[
				'site_type'       => 'person',
				'site_name'       => '<b>Acme & Co</b>',
				'title_separator' => '-',
				'logo_id'         => 15,
				'logo_url'        => 'javascript:alert(1)',
				'skip_cloud'      => true,
				'modules'         => [
					'schema'  => false,
					'meta'    => true,
					'unknown' => true,
				],
			]
		);

		$completed = $controller->complete( $request )->get_data();

		$this->assertTrue( $completed['completed'] );
		$this->assertTrue( $completed['onboarding_completed'] );
		$this->assertSame( 1, get_option( OnboardingStore::OPTION_COMPLETED ) );
		$this->assertSame( 'person', $completed['site_identity']['site_type'] );
		$this->assertSame( 'Acme & Co', $completed['site_identity']['site_name'] );
		$this->assertSame( '-', $completed['site_identity']['title_separator'] );
		$this->assertSame( 15, $completed['site_identity']['logo_id'] );
		$this->assertSame( '', $completed['site_identity']['logo_url'] );
		$this->assertTrue( $completed['site_identity']['skipped_cloud'] );
		$this->assertFalse( $completed['modules']['schema'] );
		$this->assertTrue( $completed['modules']['meta'] );
		$this->assertArrayNotHasKey( 'logo_url', $completed['settings'] );
		$this->assertArrayNotHasKey( 'logo_id', $completed['settings'] );
		$this->assertSame( 'Acme & Co', $completed['settings']['site_name'] );
		$this->assertTrue( $completed['settings']['wizard_completed'] );

		$identity = get_option( OnboardingStore::OPTION_IDENTITY );
		$this->assertIsArray( $identity );
		$this->assertSame( 15, $identity['logo_id'] );
		$this->assertSame( '', $identity['logo_url'] );
	}

	public function test_onboarding_requires_manage_capability(): void {
		$controller = new OnboardingController( new OnboardingStore( new Settings() ), new Settings(), new ModuleManager( new Settings() ) );

		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$result                                  = $controller->check_manage_permission();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_dashboard_summary_scores_local_fixes(): void {
		$settings   = new Settings();
		$onboarding = new OnboardingStore( $settings );
		$index      = new FakeSiteIndex();
		$wpdb       = new wpdb();
		$wpdb->existing_tables = array_values( Schema::get_table_names( 'wp_' ) );
		$migrator   = new Migrator( $wpdb, static function (): void {} );
		$assistant  = new LocalAssistant( $index, $settings, $onboarding, $migrator );
		$modules    = new ModuleManager( $settings );
		$controller = new DashboardController( $assistant, $index, $onboarding, $modules, $migrator );
		$controller->register_routes();

		$routes = array_map(
			static fn( array $entry ): string => $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);
		$this->assertContains( '/dashboard/summary', $routes );

		$data = $controller->get_summary()->get_data();

		$this->assertSame( 62, $data['health']['score'] );
		$this->assertSame( 'needs_work', $data['health']['band'] );
		$this->assertSame( 'Needs work', $data['health']['label'] );
		$this->assertFalse( $data['onboarding_completed'] );
		$this->assertSame( 3, $data['counts']['missing_descriptions'] );
		$this->assertSame( 2, $data['counts']['missing_titles'] );
		$this->assertSame( 4, $data['counts']['thin_posts'] );
		$this->assertSame( 1, $data['counts']['low_scores'] );
		$this->assertSame( 2, $data['counts']['open_404s'] );
		$this->assertSame( 6, $data['counts']['redirects_total'] );
		$this->assertSame( 5, $data['counts']['redirects_active'] );
		$this->assertTrue( $data['audit']['tables_healthy'] );
		$this->assertFalse( $data['cloud']['connected'] );
		$this->assertSame( 'local', $data['cloud']['tier'] );

		$fix_ids = array_column( $data['fixes'], 'id' );
		$this->assertSame(
			[ 'onboarding', 'missing_descriptions', 'missing_titles', 'thin_posts', 'open_404s' ],
			$fix_ids
		);
		$this->assertCount( 5, $data['fixes'] );
		$this->assertSame( 'Find posts missing meta descriptions', $data['fixes'][1]['prompt'] );
	}

	public function test_local_chat_routes_intents_items_and_follow_ups(): void {
		$assistant  = $this->assistant();
		$controller = new ChatController( $assistant );
		$controller->register_routes();

		$this->assertSame( 'missing_descriptions', $assistant->detect_intent( 'Find posts missing meta descriptions' ) );
		$this->assertSame( 'missing_titles', $assistant->detect_intent( 'Find posts missing SEO titles' ) );
		$this->assertSame( 'low_scores', $assistant->detect_intent( 'Show low SEO score posts' ) );
		$this->assertSame( 'thin_posts', $assistant->detect_intent( 'Show thin posts' ) );
		$this->assertSame( 'open_404s', $assistant->detect_intent( 'Show recent unresolved 404s' ) );
		$this->assertSame( 'redirects', $assistant->detect_intent( 'How many redirects do I have?' ) );
		$this->assertSame( 'module_health', $assistant->detect_intent( 'Check sitemap and module health' ) );
		$this->assertSame( 'inspect_post', $assistant->detect_intent( 'Inspect post 42' ) );
		$this->assertSame( 'inspect_post', $assistant->detect_intent( 'Check the page titled "Pricing Guide"' ) );
		$this->assertSame( 'site_health', $assistant->detect_intent( 'What should I fix first?' ) );
		$this->assertSame( 'cloud_handoff', $assistant->detect_intent( 'Rewrite my title to rank higher' ) );

		$descriptions = $this->ask( $controller, 'Find posts missing meta descriptions' );
		$this->assertSame( 'local', $descriptions['tier'] );
		$this->assertTrue( $descriptions['offline'] );
		$this->assertSame( 'missing_descriptions', $descriptions['intent'] );
		$this->assertStringContainsString( '3 published items', $descriptions['explanation'] );
		$this->assertSame( 'Welcome post', $descriptions['items'][0]['title'] );
		$this->assertStringContainsString( 'post.php?post=7', $descriptions['items'][0]['edit_link'] );
		$this->assertNotSame( '', $descriptions['items'][0]['tip'] );
		$this->assertCount( 3, $descriptions['follow_ups'] );
		$this->assertSame( [], $descriptions['action_cards'] );
		$this->assertFalse( $descriptions['operator_slot']['enabled'] );
		$this->assertNull( $descriptions['cloud_handoff'] );

		$titles = $assistant->respond( 'Find posts missing SEO titles' );
		$this->assertSame( 'missing_titles', $titles['intent'] );
		$this->assertSame( 'Untitled launch', $titles['items'][0]['title'] );

		$scores = $assistant->respond( 'Show posts with a low SEO score' );
		$this->assertSame( 'low_scores', $scores['intent'] );
		$this->assertSame( 40, $scores['items'][0]['seo_score'] );

		$thin = $assistant->respond( 'Show thin posts' );
		$this->assertSame( 'thin_posts', $thin['intent'] );
		$this->assertSame( 40, $thin['items'][0]['word_count'] );

		$missing = $assistant->respond( 'Show recent unresolved 404 logs' );
		$this->assertSame( 'open_404s', $missing['intent'] );
		$this->assertSame( '/old-pricing', $missing['items'][0]['title'] );
		$this->assertSame( 'redirects', $missing['items'][0]['page'] );

		$redirects = $assistant->respond( 'How many redirects are saved?' );
		$this->assertSame( 'redirects', $redirects['intent'] );
		$this->assertStringContainsString( '6 redirect', $redirects['explanation'] );
		$this->assertStringContainsString( '5 of them are active', $redirects['explanation'] );

		$modules = $assistant->respond( 'Is the sitemap healthy?' );
		$this->assertSame( 'module_health', $modules['intent'] );
		$this->assertStringContainsString( 'sitemap tool is on', $modules['explanation'] );

		$post = $assistant->respond( 'Inspect post 42' );
		$this->assertSame( 'inspect_post', $post['intent'] );
		$this->assertSame( 'Pricing', $post['items'][0]['title'] );
		$this->assertStringContainsString( 'SEO title', $post['items'][0]['tip'] );
		$this->assertStringContainsString( '40', $post['items'][0]['tip'] );

		$by_title = $assistant->respond( 'Review the page "Pricing Guide"' );
		$this->assertSame( 'inspect_post', $by_title['intent'] );
		$this->assertSame( 'Pricing Guide', $by_title['items'][0]['title'] );

		$by_context = $assistant->respond( 'Check this page', [ 'post_id' => 42 ] );
		$this->assertSame( 'Pricing', $by_context['items'][0]['title'] );

		$health = $assistant->respond( 'What should I fix first?' );
		$this->assertSame( 'site_health', $health['intent'] );
		$this->assertStringContainsString( '62/100', $health['explanation'] );

		$cloud = $assistant->respond( 'Why did my traffic drop?' );
		$this->assertSame( 'cloud_handoff', $cloud['intent'] );
		$this->assertIsArray( $cloud['cloud_handoff'] );
		$this->assertSame( 'Connect RankMySEO Cloud', $cloud['cloud_handoff']['title'] );
		$this->assertStringContainsString( 'No request was sent', $cloud['explanation'] );
	}

	public function test_local_chat_rejects_empty_prompts_and_honors_the_operator_slot_filter(): void {
		$controller = new ChatController( $this->assistant() );

		$empty = new WP_REST_Request();
		$empty->set_json_params( [ 'message' => '   ' ] );
		$error = $controller->local_reply( $empty );
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'rankmyseo_invalid_message', $error->get_error_code() );

		RankMySEO_Test_State::$current_user_caps = [];
		$denied                                  = $controller->check_edit_posts_permission();
		$this->assertInstanceOf( WP_Error::class, $denied );

		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$this->assertTrue( $controller->check_edit_posts_permission() );

		add_filter(
			'rankmyseo_chat_local_response',
			static function ( array $response, string $message ): array {
				unset( $message );
				$response['operator_slot'] = [
					'enabled' => true,
					'mode'    => 'cloud',
				];
				return $response;
			},
			10,
			2
		);

		$filtered = $this->ask( $controller, 'How is my site SEO?' );
		$this->assertTrue( $filtered['operator_slot']['enabled'] );
		$this->assertSame( 'cloud', $filtered['operator_slot']['mode'] );

		$module = new ChatModule( new Container() );
		$restored = $module->ensure_operator_slot(
			[ 'explanation' => 'Local only.' ],
			'hello'
		);
		$this->assertFalse( $restored['operator_slot']['enabled'] );
		$this->assertSame( [], $restored['action_cards'] );
	}

	public function test_wordpress_site_index_queries_local_tables_and_chat_code_makes_no_http_calls(): void {
		$wpdb                  = new wpdb();
		$wpdb->existing_tables = [
			'wp_rankmyseo_404_logs',
			'wp_rankmyseo_redirects',
		];
		$index                 = new WordPressSiteIndex( $wpdb );

		$index->count_missing_titles();
		$index->list_missing_titles( 5 );
		$index->count_missing_descriptions();
		$index->list_missing_descriptions( 5 );
		$index->count_low_scores( SiteHealth::LOW_SCORE_THRESHOLD );
		$index->list_low_scores( SiteHealth::LOW_SCORE_THRESHOLD, 5 );
		$index->count_thin_posts( SiteHealth::THIN_WORD_COUNT );
		$index->list_thin_posts( SiteHealth::THIN_WORD_COUNT, 5 );
		$index->count_open_404s();
		$index->list_open_404s( 5 );
		$index->redirect_counts();
		$index->get_post( 42 );
		$index->find_posts( 'pricing', 5 );

		$sql = implode( "\n", $wpdb->queries );
		$this->assertStringContainsString( '_rankmyseo_title', $sql );
		$this->assertStringContainsString( '_rankmyseo_description', $sql );
		$this->assertStringContainsString( '_rankmyseo_seo_score', $sql );
		$this->assertStringContainsString( 'wp_rankmyseo_404_logs', $sql );
		$this->assertStringContainsString( 'wp_rankmyseo_redirects', $sql );
		$this->assertStringContainsString( 'wp_posts', $sql );
		$this->assertStringContainsString( 'wp_postmeta', $sql );

		$directory = dirname( __DIR__, 2 ) . '/includes/Modules/Chat';
		$files     = glob( $directory . '/*.php' );
		$this->assertIsArray( $files );
		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file );
			$this->assertDoesNotMatchRegularExpression(
				'/wp_remote_|curl_exec|curl_init|file_get_contents\s*\(\s*[\'"]https?:/',
				$source,
				$file
			);
		}
	}

	private function assistant(): LocalAssistant {
		$settings   = new Settings();
		$onboarding = new OnboardingStore( $settings );
		$wpdb       = new wpdb();
		$wpdb->existing_tables = array_values( Schema::get_table_names( 'wp_' ) );
		$migrator   = new Migrator( $wpdb, static function (): void {} );

		return new LocalAssistant( new FakeSiteIndex(), $settings, $onboarding, $migrator );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function ask( ChatController $controller, string $message ): array {
		$request = new WP_REST_Request();
		$request->set_json_params( [ 'message' => $message ] );
		$response = $controller->local_reply( $request );
		$this->assertNotInstanceOf( WP_Error::class, $response );

		return $response->get_data();
	}
}

/**
 * In-memory site index for intent tests.
 */
final class FakeSiteIndex implements SiteIndex {
	public function count_missing_titles(): int {
		return 2;
	}

	public function list_missing_titles( int $limit ): array {
		unset( $limit );
		return [ $this->post( 8, 'Untitled launch', 'untitled-launch', 'post' ) ];
	}

	public function count_missing_descriptions(): int {
		return 3;
	}

	public function list_missing_descriptions( int $limit ): array {
		unset( $limit );
		return [ $this->post( 7, 'Welcome post', 'welcome', 'post' ) ];
	}

	public function count_low_scores( int $threshold ): int {
		unset( $threshold );
		return 1;
	}

	public function list_low_scores( int $threshold, int $limit ): array {
		unset( $threshold, $limit );
		$row               = $this->post( 9, 'Thin offer', 'thin-offer', 'page' );
		$row['seo_score']  = 40;
		return [ $row ];
	}

	public function count_thin_posts( int $min_words ): int {
		unset( $min_words );
		return 4;
	}

	public function list_thin_posts( int $min_words, int $limit ): array {
		unset( $min_words, $limit );
		$row               = $this->post( 9, 'Thin offer', 'thin-offer', 'page' );
		$row['word_count'] = 40;
		return [ $row ];
	}

	public function count_open_404s(): int {
		return 2;
	}

	public function list_open_404s( int $limit ): array {
		unset( $limit );
		return [
			[
				'id'        => 3,
				'title'     => '/old-pricing',
				'slug'      => '',
				'type'      => '404',
				'edit_link' => '',
				'page'      => 'redirects',
				'hits'      => 4,
			],
		];
	}

	public function redirect_counts(): array {
		return [
			'total'  => 6,
			'active' => 5,
		];
	}

	public function get_post( int $post_id ): ?array {
		if ( 42 !== $post_id ) {
			return null;
		}

		$row                     = $this->post( 42, 'Pricing', 'pricing', 'page' );
		$row['seo_title']        = '';
		$row['seo_description']  = '';
		$row['seo_score']        = 40;
		$row['word_count']       = 80;
		$row['focus_keyword']    = 'pricing';
		return $row;
	}

	public function find_posts( string $lookup, int $limit ): array {
		unset( $limit );
		if ( str_contains( strtolower( $lookup ), 'pricing guide' ) ) {
			$row                    = $this->post( 43, 'Pricing Guide', 'pricing-guide', 'page' );
			$row['seo_title']       = 'Pricing Guide';
			$row['seo_description'] = 'Compare plans.';
			$row['seo_score']       = 90;
			$row['word_count']      = 400;
			return [ $row ];
		}

		return [];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function post( int $id, string $title, string $slug, string $type ): array {
		return [
			'id'        => $id,
			'title'     => $title,
			'slug'      => $slug,
			'type'      => $type,
			'edit_link' => 'https://example.com/wp-admin/post.php?post=' . $id . '&action=edit',
			'page'      => 'meta',
		];
	}
}
