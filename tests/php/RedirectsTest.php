<?php
/**
 * Unit tests for redirects, regex safety, loops, slug changes, 404s, CSV, and REST auth.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Modules\Redirects\CsvCodec;
use RankMySEO\Modules\Redirects\FrontendInterceptor;
use RankMySEO\Modules\Redirects\LoopGuard;
use RankMySEO\Modules\Redirects\Monitor404;
use RankMySEO\Modules\Redirects\PathNormalizer;
use RankMySEO\Modules\Redirects\RedirectCache;
use RankMySEO\Modules\Redirects\RedirectException;
use RankMySEO\Modules\Redirects\RedirectService;
use RankMySEO\Modules\Redirects\RedirectSettings;
use RankMySEO\Modules\Redirects\RedirectsModule;
use RankMySEO\Modules\Redirects\RegexGuard;
use RankMySEO\Modules\Redirects\ServerRulesExporter;
use RankMySEO\Modules\Redirects\SlugWatcher;
use RankMySEO\Modules\Redirects\WpdbNotFoundStore;
use RankMySEO\Modules\Redirects\WpdbRedirectStore;
use RankMySEO\Rest\NotFoundController;
use RankMySEO\Rest\RedirectsController;
use RankMySEO\Tests\Support\MemoryNotFoundStore;
use RankMySEO\Tests\Support\MemoryRedirectStore;
use RankMySEO_Test_State;
use WP_Error;
use WP_REST_Request;
use wpdb;

final class RedirectsTest extends TestCase {
	private MemoryRedirectStore $redirect_store;

	private MemoryNotFoundStore $log_store;

	private RedirectService $redirects;

	private Monitor404 $monitor;

	private RedirectSettings $settings;

	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		$this->settings       = new RedirectSettings();
		$this->redirect_store = new MemoryRedirectStore();
		$this->log_store      = new MemoryNotFoundStore();
		$paths                = new PathNormalizer( $this->settings );
		$regex                = new RegexGuard();
		$this->redirects      = new RedirectService(
			$this->redirect_store,
			$paths,
			$regex,
			new LoopGuard(),
			new CsvCodec(),
			new ServerRulesExporter(),
			new RedirectCache(),
			$this->settings
		);
		$this->monitor        = new Monitor404( $this->log_store, $paths, $regex, $this->settings, $this->redirects );
	}

	public function test_exact_hash_lookup_is_stable_and_cached(): void {
		$paths = new PathNormalizer( $this->settings );
		$this->assertSame( '/pricing', $paths->normalize_path( '/Pricing/' ) );
		$this->assertSame(
			$paths->hash( $paths->normalize_path( '/pricing' ) ),
			$paths->hash( $paths->normalize_path( '/Pricing/' ) )
		);

		$created = $this->redirects->create(
			[
				'source' => '/Pricing/',
				'target' => '/plans',
			]
		);

		$this->assertSame( 64, strlen( (string) $created['redirect']['source_hash'] ) );
		$this->assertSame( $paths->hash( '/pricing' ), $created['redirect']['source_hash'] );

		$first = $this->redirects->match( '/pricing' );
		$this->redirects->match( '/pricing' );

		$this->assertNotNull( $first );
		$this->assertSame( '/plans', $first['target_url'] );
		$this->assertSame( 1, $this->redirect_store->exact_lookups );
	}

	public function test_safe_regex_matches_and_redos_patterns_are_rejected(): void {
		$guard = new RegexGuard();
		$this->assertNull( $guard->rejection_reason( '^/blog/(.+)$' ) );
		$this->assertSame( 'catastrophic', $guard->rejection_reason( '(a+)+' ) );
		$this->assertSame( 'catastrophic', $guard->rejection_reason( '(.*)*' ) );
		$this->assertSame( 'catastrophic', $guard->rejection_reason( '(a|a)+' ) );
		$this->assertSame( 'too_long', $guard->rejection_reason( str_repeat( 'a', 257 ) ) );
		$this->assertFalse( $guard->matches( '(a+)+', str_repeat( 'a', 30 ) ) );

		$this->redirects->create(
			[
				'source'      => '^/blog/(.+)$',
				'target'      => '/news/$1',
				'match_type'  => 'regex',
				'status_code' => 302,
			]
		);

		$tested = $this->redirects->test_url( '/blog/hello' );
		$this->assertTrue( $tested['matched'] );
		$this->assertSame( 'regex', $tested['match_type'] );
		$this->assertSame( 302, $tested['status_code'] );
		$this->assertSame( 'https://example.com/news/hello', $tested['target_url'] );

		try {
			$this->redirects->create(
				[
					'source'     => '(a+)+',
					'target'     => '/safe',
					'match_type' => 'regex',
				]
			);
			$this->fail( 'Unsafe regex should be rejected.' );
		} catch ( RedirectException $exception ) {
			$this->assertSame( 'redirect_regex', $exception->error_code() );
		}
	}

	public function test_loop_and_chain_detection(): void {
		$this->redirects->create(
			[
				'source' => '/b',
				'target' => '/c',
			]
		);

		$chain = $this->redirects->create(
			[
				'source' => '/a',
				'target' => '/b',
			]
		);
		$this->assertSame( LoopGuard::CHAIN, $chain['warning'] );
		$this->assertSame( [ '/a', '/b', '/c' ], $chain['chain'] );

		$flat = $this->redirects->flatten( (int) $chain['redirect']['id'] );
		$this->assertSame( '/c', $flat['redirect']['target_url'] );

		try {
			$this->redirects->create(
				[
					'source' => '/c',
					'target' => '/c',
				]
			);
			$this->fail( 'Self redirect should be rejected.' );
		} catch ( RedirectException $exception ) {
			$this->assertSame( LoopGuard::SELF, $exception->error_code() );
		}

		try {
			$this->redirects->create(
				[
					'source' => '/c',
					'target' => '/b',
				]
			);
			$this->fail( 'Circular redirect should be rejected.' );
		} catch ( RedirectException $exception ) {
			$this->assertSame( LoopGuard::LOOP, $exception->error_code() );
		}
	}

	public function test_terminal_statuses_do_not_require_a_target_and_query_is_preserved(): void {
		$gone = $this->redirects->create(
			[
				'source'      => '/removed',
				'status_code' => 410,
			]
		);
		$this->assertNull( $gone['redirect']['target_url'] );
		$tested = $this->redirects->test_url( '/removed?ref=ad' );
		$this->assertTrue( $tested['matched'] );
		$this->assertSame( 410, $tested['status_code'] );
		$this->assertNull( $tested['target_url'] );

		$legal = $this->redirects->create(
			[
				'source'      => '/blocked',
				'status_code' => 451,
			]
		);
		$this->assertSame( 451, $legal['redirect']['status_code'] );
		$this->assertNull( $legal['redirect']['target_url'] );

		$this->redirects->create(
			[
				'source' => '/old',
				'target' => '/new',
			]
		);
		$with_query = $this->redirects->test_url( '/old?utm=spring' );
		$this->assertSame( 'https://example.com/new?utm=spring', $with_query['target_url'] );

		try {
			$this->redirects->create(
				[
					'source'      => '/needs-target',
					'status_code' => 301,
					'target'      => '',
				]
			);
			$this->fail( '301 without a target should be rejected.' );
		} catch ( RedirectException $exception ) {
			$this->assertSame( 'redirect_target', $exception->error_code() );
		}

		try {
			$this->redirects->create(
				[
					'source' => '/wp-admin/options.php',
					'target' => '/plans',
				]
			);
			$this->fail( 'Protected paths should be rejected.' );
		} catch ( RedirectException $exception ) {
			$this->assertSame( 'redirect_protected', $exception->error_code() );
		}
	}

	public function test_slug_change_creates_a_redirect_and_skips_drafts(): void {
		$this->redirects->create(
			[
				'source' => '/promo',
				'target' => '/about',
			]
		);

		$watcher = new SlugWatcher( $this->redirects, $this->settings, new PathNormalizer( $this->settings ) );
		$created = $watcher->handle_slug_change(
			42,
			[
				'post_name'   => 'about',
				'post_status' => 'publish',
				'post_type'   => 'page',
			],
			[
				'post_name'   => 'about-us',
				'post_status' => 'publish',
				'post_type'   => 'page',
			]
		);

		$this->assertNotNull( $created );
		$this->assertSame( '/about', $created['source_path'] );
		$this->assertSame( '/about-us', $created['target_url'] );
		$this->assertSame( 'auto_slug', $created['source_origin'] );
		$this->assertSame( 301, $created['status_code'] );

		$promo = $this->redirects->match( '/promo' );
		$this->assertSame( '/about-us', $promo['target_url'] );

		$draft = $watcher->handle_slug_change(
			7,
			[
				'post_name'   => 'draft-title',
				'post_status' => 'draft',
				'post_type'   => 'post',
			],
			[
				'post_name'   => 'published-title',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);
		$this->assertNull( $draft );

		$this->settings->update( [ 'auto_slug' => false ] );
		$disabled = $watcher->handle_slug_change(
			9,
			[
				'post_name'   => 'old-name',
				'post_status' => 'publish',
				'post_type'   => 'post',
			],
			[
				'post_name'   => 'new-name',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);
		$this->assertNull( $disabled );
	}

	public function test_404_logs_are_deduplicated_and_probes_are_filtered(): void {
		$ua = 'Mozilla/5.0';
		$this->assertTrue( $this->monitor->record( '/missing-page', '', 'https://example.com/', $ua ) );
		$this->assertTrue( $this->monitor->record( '/missing-page/', 'utm=1', 'https://example.com/ref', $ua ) );

		$open = $this->monitor->search( [ 'status' => 'open' ] );
		$this->assertSame( 1, $open['total'] );
		$this->assertSame( 2, $open['items'][0]['hits'] );
		$this->assertSame( '/missing-page', $open['items'][0]['request_path'] );

		$this->assertFalse( $this->monitor->record( '/.env', '', '', $ua ) );
		$this->assertFalse( $this->monitor->record( '/wp-login.php', '', '', $ua ) );
		$this->assertFalse( $this->monitor->record( '/xmlrpc.php', '', '', $ua ) );
		$this->assertFalse( $this->monitor->record( '/wp-content/uploads/shell.php', '', '', $ua ) );
		$this->assertFalse( $this->monitor->record( '/missing-page', '', '', '' ) );
		$this->assertFalse( $this->monitor->record( '/another', '', '', 'Mozilla/5.0 (compatible; Googlebot/2.1)' ) );
		$this->assertSame( 1, $this->monitor->search( [] )['total'] );

		$this->settings->update( [ 'ignore_patterns' => [ '*/private/*' ] ] );
		$this->assertFalse( $this->monitor->record( '/private/report', '', '', $ua ) );

		$converted = $this->monitor->convert_to_redirect( (int) $open['items'][0]['id'], '/plans' );
		$this->assertSame( '/plans', $converted['redirect']['target_url'] );
		$this->assertSame( 'redirected', $converted['log']['status'] );
		$this->assertNotNull( $this->redirects->match( '/missing-page' ) );
	}

	public function test_404_prune_drops_stale_rows_and_enforces_the_cap(): void {
		$ua = 'Mozilla/5.0';
		$this->monitor->record( '/stale', '', '', $ua );
		$this->log_store->rows[1]['last_seen_at'] = '2000-01-01 00:00:00';
		for ( $i = 0; $i < 101; $i++ ) {
			$this->monitor->record( '/page-' . $i, '', '', $ua );
		}

		$this->settings->update(
			[
				'ttl_days' => 30,
				'max_rows' => 100,
			]
		);

		$removed = $this->monitor->prune();
		$this->assertGreaterThanOrEqual( 2, $removed );
		$this->assertLessThanOrEqual( 100, count( $this->log_store->rows ) );
	}

	public function test_csv_export_escapes_formula_injection(): void {
		$codec = new CsvCodec();
		$this->assertSame( "'=1+1", $codec->escape_cell( '=1+1' ) );
		$this->assertSame( "'+cmd", $codec->escape_cell( '+cmd' ) );
		$this->assertSame( "'-1", $codec->escape_cell( '-1' ) );
		$this->assertSame( "'@SUM(A1)", $codec->escape_cell( '@SUM(A1)' ) );

		$csv = $codec->export(
			[
				[
					'source_path'   => '/old',
					'match_type'    => 'exact',
					'target_url'    => '=HYPERLINK("https://evil.example")',
					'status_code'   => 301,
					'is_active'     => true,
					'source_origin' => 'manual',
					'hits'          => 0,
				],
			]
		);

		$this->assertStringContainsString( "'=HYPERLINK", $csv );
		$parsed = $codec->parse( $csv );
		$this->assertSame( '=HYPERLINK("https://evil.example")', $parsed[0]['target_url'] );
		$this->assertStringNotContainsString( "\t=HYPERLINK", $csv );
	}

	public function test_hit_counter_is_throttled(): void {
		$created = $this->redirects->create(
			[
				'source' => '/counted',
				'target' => '/dest',
			]
		);
		$id      = (int) $created['redirect']['id'];

		$this->assertTrue( $this->redirect_store->increment_hits( $id, 0 ) );
		$this->assertTrue( $this->redirect_store->increment_hits( $id, 0 ) );
		$this->assertFalse( $this->redirect_store->increment_hits( $id, 60 ) );
		$this->assertSame( 2, $this->redirects->find( $id )['hits'] );
	}

	public function test_rest_authorization_nonce_and_routes(): void {
		$redirect_controller = new RedirectsController( $this->redirects, $this->settings );
		$log_controller      = new NotFoundController( $this->monitor );
		$redirect_controller->register_routes();
		$log_controller->register_routes();

		$routes = array_map(
			static fn( array $entry ): string => $entry['namespace'] . $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);

		$this->assertContains( 'rankmyseo/v1/redirects', $routes );
		$this->assertContains( 'rankmyseo/v1/redirects/test-url', $routes );
		$this->assertContains( 'rankmyseo/v1/redirects/import', $routes );
		$this->assertContains( 'rankmyseo/v1/redirects/export', $routes );
		$this->assertContains( 'rankmyseo/v1/404-logs', $routes );
		$this->assertContains( 'rankmyseo/v1/404-logs/(?P<id>\d+)/redirect', $routes );

		foreach ( RankMySEO_Test_State::$registered_routes as $entry ) {
			$handlers = isset( $entry['args']['callback'] ) ? [ $entry['args'] ] : $entry['args'];
			foreach ( $handlers as $handler ) {
				if ( ! is_array( $handler ) || ! isset( $handler['permission_callback'] ) ) {
					continue;
				}
				$this->assertSame( 'check_manage_permission', $handler['permission_callback'][1] );
			}
		}

		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$denied                                  = $redirect_controller->check_manage_permission();
		$this->assertInstanceOf( WP_Error::class, $denied );
		$this->assertSame( 'rankmyseo_forbidden', $denied->get_error_code() );

		RankMySEO_Test_State::$current_user_caps = [ 'rankmyseo_manage' => true ];
		$this->assertTrue( $redirect_controller->check_manage_permission() );

		RankMySEO_Test_State::$nonce_valid = false;
		$request                           = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'bad' );
		$nonce_error = $redirect_controller->list_redirects( $request );
		$this->assertInstanceOf( WP_Error::class, $nonce_error );
		$this->assertSame( 'rankmyseo_invalid_nonce', $nonce_error->get_error_code() );

		RankMySEO_Test_State::$nonce_valid = true;
		$create                            = new WP_REST_Request();
		$create->set_json_params(
			[
				'source' => '/from-rest',
				'target' => '/to-rest',
			]
		);
		$response = $redirect_controller->create_redirect( $create );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( '/from-rest', $response->get_data()['redirect']['source_path'] );
	}

	public function test_prepared_sql_binds_the_hash_and_dedup_update(): void {
		$db    = new RecordingWpdb();
		$store = new WpdbRedirectStore( $db );
		$hash  = str_repeat( 'ab', 32 );
		$store->find_active_exact_by_hash( $hash );

		$this->assertStringContainsString( 'source_hash = %s', $db->prepares[0]['sql'] );
		$this->assertSame( $hash, $db->prepares[0]['args'][1] );
		$this->assertStringNotContainsString( $hash, $db->prepares[0]['sql'] );

		$store->increment_hits( 4, 60 );
		$hit_sql = $db->prepares[1]['sql'];
		$this->assertStringContainsString( 'hits = hits + 1', $hit_sql );
		$this->assertContains( 4, $db->prepares[1]['args'] );

		$logs = new WpdbNotFoundStore( $db );
		$logs->upsert( '/missing', $hash, '', 'https://example.com', 'Mozilla/5.0', '2026-01-01 00:00:00' );
		$upsert = null;
		foreach ( $db->prepares as $prepared ) {
			if ( str_contains( $prepared['sql'], 'ON DUPLICATE KEY UPDATE' ) ) {
				$upsert = $prepared;
			}
		}
		$this->assertIsArray( $upsert );
		$this->assertStringContainsString( 'hits + 1', $upsert['sql'] );
		$this->assertContains( '/missing', $upsert['args'] );
		$this->assertStringNotContainsString( '/missing', $upsert['sql'] );
	}

	public function test_frontend_dispatch_and_module_hooks(): void {
		$created = $this->redirects->create(
			[
				'source' => '/legacy',
				'target' => '/current',
			]
		);

		$paths       = new PathNormalizer( $this->settings );
		$interceptor = new FrontendInterceptor( $this->redirects, $this->monitor, $paths );
		add_filter(
			'rankmyseo_redirect_should_exit',
			static function (): bool {
				return false;
			}
		);

		$interceptor->dispatch( $created['redirect'], '/legacy', '' );
		$this->assertSame( 'https://example.com/current', RankMySEO_Test_State::$redirects[0]['location'] );
		$this->assertSame( 301, RankMySEO_Test_State::$redirects[0]['status'] );

		$GLOBALS['wpdb'] = new wpdb();
		$module          = new RedirectsModule( new Container() );
		$module->register();
		$module->ensure_cron();

		$this->assertArrayHasKey( 'template_redirect', RankMySEO_Test_State::$actions );
		$this->assertArrayHasKey( 'post_updated', RankMySEO_Test_State::$actions );
		$this->assertSame( 'rankmyseo_prune_404_logs', RankMySEO_Test_State::$scheduled[0]['hook'] );
	}
}

/**
 * Records prepared statements without sending them to a database.
 */
final class RecordingWpdb extends wpdb {
	/**
	 * Prepared statements.
	 *
	 * @var list<array{sql: string, args: array<int, mixed>}>
	 */
	public array $prepares = [];

	/**
	 * @param mixed ...$args
	 */
	public function prepare( string $query, ...$args ): string {
		$this->prepares[] = [
			'sql'  => $query,
			'args' => $args,
		];
		return $query;
	}

	/**
	 * @param mixed $query
	 * @param mixed $output
	 * @param mixed $y
	 * @return null
	 */
	public function get_row( $query = null, $output = 'ARRAY_A', $y = 0 ) {
		unset( $query, $output, $y );
		return null;
	}

	/**
	 * @param mixed $query
	 * @param mixed $output
	 * @return array<int, mixed>
	 */
	public function get_results( $query = null, $output = 'ARRAY_A' ) {
		unset( $query, $output );
		return [];
	}

	/**
	 * @param string $query
	 */
	public function query( string $query ): int {
		$this->queries[] = $query;
		return 1;
	}
}
