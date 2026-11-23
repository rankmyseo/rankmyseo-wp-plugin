<?php
/**
 * Tests for RankMySEO Cloud credentials, HTTP, sync, and admin REST.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\CloudModule;
use RankMySEO\Modules\Cloud\InsightsCache;
use RankMySEO\Modules\Cloud\PairingService;
use RankMySEO\Modules\Cloud\SyncQueue;
use RankMySEO\Modules\Cloud\SyncWorker;
use RankMySEO\Modules\Cloud\TokenVault;
use RankMySEO\Modules\ModuleManager;
use RankMySEO\Rest\CloudController;
use RankMySEO_Test_State;
use WP_REST_Request;

final class CloudConnectionTest extends TestCase {
	private TokenVault $vault;

	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		$this->vault = new TokenVault();
	}

	public function test_vault_round_trips_with_sodium_and_openssl_without_storing_plaintext(): void {
		$token  = 'rms_wp_abcdefSECRETSECRET';
		$secret = 'whsec_testsecretvalue';

		foreach ( array( null, TokenVault::CIPHER_OPENSSL ) as $cipher ) {
			RankMySEO_Test_State::reset();
			$vault = new TokenVault( $cipher );
			$vault->save_secrets(
				array(
					'token'          => $token,
					'webhook_secret' => $secret,
					'poll_token'     => 'poll_secret_value',
				)
			);
			$vault->update_public(
				array(
					'connected'    => true,
					'token_prefix' => $vault->prefix_from_token( $token ),
					'last_error'   => 'token was ' . $token,
				)
			);

			$stored = get_option( TokenVault::CONNECTION_OPTION );
			$blob   = wp_json_encode( $stored );
			$this->assertIsString( $blob );
			$this->assertStringNotContainsString( $token, $blob );
			$this->assertStringNotContainsString( $secret, $blob );
			$this->assertStringNotContainsString( 'poll_secret_value', $blob );

			$fresh = new TokenVault( $cipher );
			$this->assertSame( $token, $fresh->get_secret( 'token' ) );
			$this->assertSame( $secret, $fresh->get_secret( 'webhook_secret' ) );
			$this->assertTrue( $fresh->is_connected() );

			$public = wp_json_encode( $fresh->public_state() );
			$this->assertIsString( $public );
			$this->assertStringNotContainsString( $token, $public );
			$this->assertStringNotContainsString( $secret, $public );
			$this->assertStringNotContainsString( 'poll_secret_value', $public );
			$this->assertSame( 'rms_wp_abcdef', $fresh->public_state()['token_prefix'] );
			$this->assertSame( '', $fresh->public_state()['last_error'] );
		}
	}

	public function test_vault_cannot_decrypt_after_salts_change_and_wipe_removes_credentials(): void {
		$this->vault->save_secrets( array( 'token' => 'rms_wp_abcdefSECRETSECRET' ) );
		RankMySEO_Test_State::$salts['auth'] = 'different-auth-salt';

		$fresh = new TokenVault();
		$this->assertSame( '', $fresh->get_secret( 'token' ) );
		$this->assertFalse( $fresh->is_connected() );

		$this->vault->wipe();
		$this->assertFalse( get_option( TokenVault::CONNECTION_OPTION ) );
		$this->assertFalse( get_option( TokenVault::SETTINGS_OPTION ) );
		$this->assertArrayNotHasKey( 'rankmyseo_cloud_insights', RankMySEO_Test_State::$transients );
	}

	public function test_disconnected_client_and_worker_make_no_http_requests(): void {
		$client = new CloudClient( $this->vault );
		$queue  = new SyncQueue( $GLOBALS['wpdb'], $this->vault );
		$worker = new SyncWorker( $this->vault, $client, $queue );

		$this->assertFalse( $client->get_connection()->attempted );
		$this->assertFalse( $client->sync_inventory( array() )->attempted );
		$this->assertFalse( $client->sync_events( array() )->attempted );
		$this->assertFalse( $client->get_insights()->attempted );
		$this->assertFalse( $client->get_policy()->attempted );
		$this->assertFalse( $client->put_policy( array( 'enabled' => false ) )->attempted );
		$this->assertFalse( $client->disconnect()->attempted );
		$this->assertFalse( $client->rotate_token()->attempted );
		$this->assertFalse( $queue->enqueue_event( array( 'type' => 'post_updated', 'wpPostId' => 4, 'url' => 'https://example.com/a/' ) ) );
		$worker->process();

		$this->assertSame( array(), RankMySEO_Test_State::$http_log );
		$this->assertSame( array(), $GLOBALS['wpdb']->sync_rows );
	}

	public function test_pairing_start_poll_disconnect_and_rotate_match_the_cloud_contract(): void {
		$services   = $this->services();
		$controller = $services['controller'];

		add_filter(
			'pre_http_request',
			static function ( $pre, array $args, string $url ) {
				unset( $pre );
				if ( str_contains( $url, '/wp/v1/pair/start' ) ) {
					return rankmyseo_test_http_response(
						201,
						array(
							'code'      => 'ABCD-EFGH',
							'pairUrl'   => 'https://app.rankmyseo.com/pair-wp/ABCD-EFGH',
							'pollToken' => 'poll_secret_value',
							'expiresAt' => '2026-11-23T13:00:00Z',
						)
					);
				}
				if ( str_contains( $url, '/wp/v1/pair/poll' ) ) {
					$body = json_decode( (string) $args['body'], true );
					if ( ! is_array( $body ) || 'poll_secret_value' !== ( $body['pollToken'] ?? '' ) ) {
						return rankmyseo_test_http_response( 400, array( 'error' => 'invalid', 'message' => 'bad poll' ) );
					}
					return rankmyseo_test_http_response(
						200,
						array(
							'status'        => 'approved',
							'token'         => 'rms_wp_abcdefSECRETSECRET',
							'connectionId'  => 'conn_1',
							'projectId'     => 'proj_1',
							'siteUrl'       => 'https://example.com/',
							'webhookSecret' => 'whsec_testsecretvalue',
						)
					);
				}
				if ( str_contains( $url, '/wp/v1/connection' ) ) {
					return rankmyseo_test_http_response(
						200,
						array(
							'connection' => array(
								'id'        => 'conn_1',
								'projectId' => 'proj_1',
								'siteUrl'   => 'https://example.com/',
								'siteHost'  => 'example.com',
								'siteName'  => 'Test Site',
								'domain'    => 'example.com',
								'prefix'    => 'rms_wp_abcdef',
								'plan'      => array(
									'id'    => 'starter',
									'label' => 'Starter',
								),
							),
						)
					);
				}
				if ( str_contains( $url, '/wp/v1/disconnect' ) ) {
					return new \WP_Error( 'http_request_failed', 'timed out' );
				}
				if ( str_contains( $url, '/wp/v1/rotate-token' ) ) {
					return rankmyseo_test_http_response(
						200,
						array(
							'token'  => 'rms_wp_zzzzzzNEWTOKENVALUE',
							'prefix' => 'rms_wp_zzzzzz',
						)
					);
				}
				return new \WP_Error( 'http_request_failed', 'unexpected ' . $url );
			},
			10,
			3
		);

		$started = $controller->start_pair();
		$this->assertSame( 200, $started->get_status() );
		$start_json = (string) wp_json_encode( $started->get_data() );
		$this->assertStringContainsString( 'ABCD-EFGH', $start_json );
		$this->assertStringNotContainsString( 'poll_secret_value', $start_json );
		$this->assertSame( array(), RankMySEO_Test_State::$http_log[0]['args']['cookies'] );
		$this->assertArrayNotHasKey( 'Origin', RankMySEO_Test_State::$http_log[0]['args']['headers'] );
		$this->assertArrayNotHasKey( 'Authorization', RankMySEO_Test_State::$http_log[0]['args']['headers'] );

		$polled = $controller->poll_pair();
		$this->assertSame( 200, $polled->get_status() );
		$poll_json = (string) wp_json_encode( $polled->get_data() );
		$this->assertStringContainsString( 'approved', $poll_json );
		$this->assertStringNotContainsString( 'rms_wp_abcdefSECRETSECRET', $poll_json );
		$this->assertStringNotContainsString( 'whsec_testsecretvalue', $poll_json );
		$this->assertSame( 'Starter', $polled->get_data()['state']['plan_label'] );
		$this->assertTrue( $services['vault']->is_connected() );
		$this->assertNotFalse( wp_next_scheduled( SyncWorker::HOOK ) );

		$calls_before_rotate = count( RankMySEO_Test_State::$http_log );
		$rotated             = $controller->rotate_token();
		$this->assertSame( 200, $rotated->get_status() );
		$this->assertSame( 'rms_wp_zzzzzzNEWTOKENVALUE', $services['vault']->get_secret( 'token' ) );
		$this->assertSame( 'whsec_testsecretvalue', $services['vault']->get_secret( 'webhook_secret' ) );
		$this->assertStringNotContainsString( 'rms_wp_zzzzzzNEWTOKENVALUE', (string) wp_json_encode( $rotated->get_data() ) );
		$rotate_call = RankMySEO_Test_State::$http_log[ $calls_before_rotate ];
		$this->assertStringContainsString( 'Bearer rms_wp_abcdefSECRETSECRET', $rotate_call['args']['headers']['Authorization'] );

		$controller->disconnect();
		$this->assertFalse( $services['vault']->has_token() );
		$this->assertFalse( $services['vault']->is_connected() );
		$this->assertFalse( wp_next_scheduled( SyncWorker::HOOK ) );
		$this->assertStringContainsString( '/wp/v1/disconnect', RankMySEO_Test_State::$http_log[ count( RankMySEO_Test_State::$http_log ) - 1 ]['url'] );
	}

	public function test_authenticated_errors_backoff_and_do_not_break_when_rankmyseo_is_down(): void {
		$this->connect_vault();
		$client = new CloudClient( $this->vault );

		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response(
					429,
					array(
						'error'   => 'rate_limited',
						'message' => 'Slow down',
					),
					array( 'Retry-After' => '120' )
				);
			},
			10,
			3
		);
		$limited = $client->get_insights();
		$this->assertSame( 120, $limited->retry_after );
		$this->assertFalse( $this->vault->needs_reconnect() );

		RankMySEO_Test_State::$filters = array();
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response( 403, array( 'error' => 'plan_limit', 'message' => 'Keyword limit reached' ) );
			},
			10,
			3
		);
		$client->sync_events( array( array( 'type' => 'post_updated', 'wpPostId' => 1, 'url' => 'https://example.com/a/' ) ) );
		$this->assertFalse( $this->vault->needs_reconnect() );

		RankMySEO_Test_State::$filters = array();
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response( 500, array( 'error' => 'server', 'message' => 'unavailable' ) );
			},
			10,
			3
		);
		$down = $client->get_connection();
		$this->assertFalse( $down->ok );
		$this->assertTrue( $this->vault->is_connected() );

		RankMySEO_Test_State::$filters = array();
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response( 401, array( 'error' => 'unauthorized', 'message' => 'Token revoked' ) );
			},
			10,
			3
		);
		$client->get_connection();
		$this->assertTrue( $this->vault->needs_reconnect() );
		$before = count( RankMySEO_Test_State::$http_log );
		$this->assertFalse( $client->sync_inventory( array( array( 'wpPostId' => 1 ) ) )->attempted );
		$this->assertSame( $before, count( RankMySEO_Test_State::$http_log ) );
	}

	public function test_base_url_filter_is_used_and_unsafe_values_fall_back(): void {
		$client = new CloudClient( $this->vault );
		add_filter(
			'rankmyseo_cloud_api_base_url',
			static function (): string {
				return 'http://127.0.0.1:8787/';
			}
		);
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response( 201, array( 'code' => 'ABCD-EFGH', 'pairUrl' => 'http://127.0.0.1:3000/pair-wp/ABCD-EFGH', 'pollToken' => 'poll_secret_value', 'expiresAt' => '2026-11-23T13:00:00Z' ) );
			},
			10,
			3
		);
		$client->start_pair( 'https://example.com/', 'Test', '6.4.3', '0.1.0' );
		$this->assertSame( 'http://127.0.0.1:8787/wp/v1/pair/start', RankMySEO_Test_State::$http_log[0]['url'] );

		RankMySEO_Test_State::$filters = array();
		RankMySEO_Test_State::$http_log = array();
		add_filter(
			'rankmyseo_cloud_api_base_url',
			static function (): string {
				return 'javascript:alert(1)';
			}
		);
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response( 201, array( 'code' => 'ABCD-EFGH', 'pairUrl' => 'https://app.rankmyseo.com/pair-wp/ABCD-EFGH', 'pollToken' => 'poll', 'expiresAt' => '2026-11-23T13:00:00Z' ) );
			},
			10,
			3
		);
		$client->start_pair( 'https://example.com/', 'Test', '6.4.3', '0.1.0' );
		$this->assertSame( 'https://api.rankmyseo.com/wp/v1/pair/start', RankMySEO_Test_State::$http_log[0]['url'] );
	}

	public function test_sync_queue_dedupes_retries_and_stops_after_reconnect(): void {
		$this->connect_vault();
		$time  = 1700000000;
		$queue = new SyncQueue(
			$GLOBALS['wpdb'],
			$this->vault,
			static function () use ( &$time ): int {
				return $time;
			}
		);
		$post  = array(
			'wpPostId' => 7,
			'url'      => 'https://example.com/hello/',
			'postType' => 'post',
			'status'   => 'publish',
			'title'    => 'Hello',
		);
		$this->assertTrue( $queue->enqueue_inventory( $post ) );
		$post['title'] = 'Hello again';
		$this->assertTrue( $queue->enqueue_inventory( $post ) );
		$this->assertSame( 1, $queue->count_status( 'pending' ) );
		$this->assertStringContainsString( 'Hello again', (string) $queue->find( 1 )['payload_json'] );

		$client = new CloudClient( $this->vault );
		$worker = new SyncWorker( $this->vault, $client, $queue );
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response( 503, array( 'error' => 'unavailable', 'message' => 'try later' ) );
			},
			10,
			3
		);
		$worker->process();
		$row = $queue->find( 1 );
		$this->assertSame( 'pending', $row['status'] );
		$this->assertSame( 1, (int) $row['attempts'] );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', $time + 60 ), $row['scheduled_at'] );

		$time += 60;
		$worker->process();
		$row = $queue->find( 1 );
		$this->assertSame( 2, (int) $row['attempts'] );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', $time + 120 ), $row['scheduled_at'] );

		RankMySEO_Test_State::$filters = array();
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response( 401, array( 'error' => 'unauthorized', 'message' => 'revoked' ) );
			},
			10,
			3
		);
		$time += 120;
		$attempts = (int) $queue->find( 1 )['attempts'];
		$worker->process();
		$this->assertSame( $attempts, (int) $queue->find( 1 )['attempts'] );
		$this->assertSame( 'pending', $queue->find( 1 )['status'] );
		$this->assertTrue( $this->vault->needs_reconnect() );
		$this->assertSame( 60, $queue->retry_delay( 1, 0 ) );
		$this->assertSame( 500, $queue->retry_delay( 2, 500 ) );
	}

	public function test_failed_rows_stop_after_eight_attempts_and_manual_sync_sends_inventory(): void {
		$this->connect_vault();
		$queue = new SyncQueue( $GLOBALS['wpdb'], $this->vault );
		$queue->enqueue_event(
			array(
				'type'     => 'post_published',
				'wpPostId' => 3,
				'url'      => 'https://example.com/hello/',
				'status'   => 'publish',
				'postType' => 'post',
			)
		);
		for ( $i = 0; $i < 8; $i++ ) {
			$queue->schedule_retry( 1, 0 );
		}
		$this->assertSame( 'failed', $queue->find( 1 )['status'] );

		$post              = new \stdClass();
		$post->ID          = 11;
		$post->post_type   = 'post';
		$post->post_status = 'publish';
		$post->post_name   = 'guide';
		$post->post_title  = 'Guide';
		$post->post_modified_gmt = '2026-11-23 12:00:00';
		RankMySEO_Test_State::$posts[] = $post;
		RankMySEO_Test_State::$post_meta[11] = array(
			'_rankmyseo_focus_keyword' => 'rank tracker',
		);

		add_filter(
			'pre_http_request',
			static function ( $pre, array $args, string $url ) {
				unset( $pre );
				if ( ! str_contains( $url, '/wp/v1/sync/inventory' ) ) {
					return rankmyseo_test_http_response( 200, array( 'accepted' => 0 ) );
				}
				$body = json_decode( (string) $args['body'], true );
				if ( ! is_array( $body ) || 'rank tracker' !== ( $body['posts'][0]['focusKeyword'] ?? '' ) ) {
					return rankmyseo_test_http_response( 400, array( 'error' => 'invalid', 'message' => 'missing keyword' ) );
				}
				return rankmyseo_test_http_response( 200, array( 'upserted' => 1, 'rejected' => 0 ) );
			},
			10,
			3
		);

		$worker = new SyncWorker( $this->vault, new CloudClient( $this->vault ), $queue );
		$result = $worker->sync_now( \RankMySEO\Modules\Cloud\PostPayloadBuilder::collect( 20 ) );
		$this->assertTrue( $result['ok'] );
		$this->assertGreaterThan( 0, $result['processed'] );
		$this->assertNotSame( '', $this->vault->public_state()['last_sync_at'] );
	}

	public function test_insights_are_cached_until_refresh_and_keep_stale_data_on_failure(): void {
		$this->connect_vault();
		$client = new CloudClient( $this->vault );
		$cache  = new InsightsCache( $this->vault, $client );
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response(
					200,
					array(
						'project'  => array(
							'id'     => 'proj_1',
							'name'   => 'Example',
							'domain' => 'example.com',
						),
						'keywords' => array(
							'tracked' => 1,
							'rows'    => array(
								array(
									'keyword'  => 'rank tracker',
									'position' => null,
									'url'      => 'https://example.com/guide/',
								),
							),
						),
						'issues'      => array(
							array(
								'id'       => 'title',
								'category' => 'titles',
								'count'    => 2,
								'impact'   => 'medium',
							),
						),
						'pageUpdates' => array(
							array(
								'url'     => 'https://example.com/guide/',
								'action'  => 'refresh',
								'summary' => 'Refresh the guide',
							),
						),
					)
				);
			},
			10,
			3
		);

		$first = $cache->get( false );
		$cache->get( false );
		$this->assertCount( 1, RankMySEO_Test_State::$http_log );
		$this->assertFalse( $first['cached'] );
		$this->assertNull( $first['keywords']['rows'][0]['position'] );
		$this->assertSame( 'Refresh the guide', $first['page_updates'][0]['summary'] );
		$this->assertArrayNotHasKey( 'pageUpdates', $first );

		$cache->get( true );
		$this->assertCount( 2, RankMySEO_Test_State::$http_log );

		RankMySEO_Test_State::$filters = array();
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response( 503, array( 'error' => 'unavailable', 'message' => 'try later' ) );
			},
			10,
			3
		);
		$stale = $cache->get( true );
		$this->assertTrue( $stale['stale'] );
		$this->assertSame( 'rank tracker', $stale['keywords']['rows'][0]['keyword'] );
	}

	public function test_cloud_rest_status_is_local_and_hooks_queue_only_while_connected(): void {
		$services   = $this->services();
		$controller = $services['controller'];
		$controller->register_routes();
		$routes = array_column( RankMySEO_Test_State::$registered_routes, 'route' );
		$this->assertContains( '/cloud/status', $routes );
		$this->assertContains( '/cloud/pair/start', $routes );
		$this->assertContains( '/cloud/insights', $routes );

		$status = $controller->get_status();
		$this->assertFalse( $status->get_data()['connected'] );
		$this->assertSame( array(), RankMySEO_Test_State::$http_log );

		RankMySEO_Test_State::$current_user_caps['rankmyseo_manage'] = false;
		RankMySEO_Test_State::$current_user_caps['manage_options'] = false;
		$this->assertInstanceOf( \WP_Error::class, $controller->check_manage_permission() );

		$container = new Container();
		$container->set( TokenVault::class, $this->vault );
		$container->set( SyncQueue::class, $services['queue'] );
		$container->set( SyncWorker::class, $services['worker'] );
		$module  = new CloudModule( $container );
		$manager = new ModuleManager( new Settings() );
		$manager->add( $module );
		$this->assertFalse( $manager->is_booted( 'cloud' ) );
		$manager->boot();
		$this->assertFalse( $manager->is_booted( 'cloud' ) );

		( new Settings() )->set_module_enabled( 'cloud', true );
		$manager->boot_one( 'cloud' );
		$this->assertTrue( $manager->is_booted( 'cloud' ) );

		$post              = new \stdClass();
		$post->ID          = 4;
		$post->post_type   = 'post';
		$post->post_status = 'publish';
		$post->post_name   = 'hello';
		$post->post_title  = 'Hello';
		$post->post_modified_gmt = '2026-11-23 12:00:00';
		do_action( 'transition_post_status', 'publish', 'draft', $post );
		$this->assertSame( array(), $GLOBALS['wpdb']->sync_rows );

		$this->connect_vault();
		do_action( 'transition_post_status', 'publish', 'draft', $post );
		$this->assertSame( 'post_published', $this->queued_event( 'post_published' )['type'] );

		$renamed            = clone $post;
		$renamed->post_name = 'hello-world';
		do_action( 'post_updated', 4, $renamed, $post );
		$slug = $this->queued_event( 'slug_changed' );
		$this->assertSame( 'slug_changed', $slug['type'] );
		$this->assertSame( 'https://example.com/hello/', $slug['previousUrl'] );

		RankMySEO_Test_State::$posts[] = $post;
		do_action( 'before_delete_post', 4, $post );
		$this->assertSame( 'post_deleted', $this->queued_event( 'post_deleted' )['type'] );
	}

	public function test_insights_endpoint_refreshes_without_exposing_credentials(): void {
		$this->connect_vault();
		$services = $this->services();
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response(
					200,
					array(
						'keywords'    => array( 'tracked' => 0, 'rows' => array() ),
						'issues'      => array(),
						'pageUpdates' => array(),
					)
				);
			},
			10,
			3
		);
		$request = new WP_REST_Request();
		$request->set_param( 'refresh', '1' );
		$response = $services['controller']->get_insights( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertStringNotContainsString( 'rms_wp_', (string) wp_json_encode( $response->get_data() ) );
		$this->assertNotEmpty( RankMySEO_Test_State::$http_log );
	}

	/**
	 * Build the cloud services used by REST tests.
	 *
	 * @return array{vault: TokenVault, queue: SyncQueue, worker: SyncWorker, controller: CloudController}
	 */
	private function services(): array {
		$client     = new CloudClient( $this->vault );
		$queue      = new SyncQueue( $GLOBALS['wpdb'], $this->vault );
		$worker     = new SyncWorker( $this->vault, $client, $queue );
		$insights   = new InsightsCache( $this->vault, $client );
		$pairing    = new PairingService( $this->vault, $client, $worker, new Settings(), null );
		$controller = new CloudController( $this->vault, $pairing, $worker, $insights );
		return array(
			'vault'      => $this->vault,
			'queue'      => $queue,
			'worker'     => $worker,
			'controller' => $controller,
		);
	}

	/**
	 * Find a queued event payload by type.
	 *
	 * @param string $type Event type.
	 * @return array<string, mixed>
	 */
	private function queued_event( string $type ): array {
		foreach ( $GLOBALS['wpdb']->sync_rows as $row ) {
			$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
			if ( is_array( $payload ) && $type === ( $payload['type'] ?? '' ) ) {
				return $payload;
			}
		}
		$this->fail( 'Missing queued event ' . $type );
	}

	/**
	 * Store a connected token in the vault.
	 *
	 * @return void
	 */
	private function connect_vault(): void {
		$token = 'rms_wp_abcdefSECRETSECRET';
		$this->vault->save_secrets(
			array(
				'token'          => $token,
				'webhook_secret' => 'whsec_testsecretvalue',
			)
		);
		$this->vault->update_public(
			array(
				'connected'          => true,
				'reconnect_required' => false,
				'health'             => 'connected',
				'token_prefix'       => 'rms_wp_abcdef',
				'site_url'           => 'https://example.com/',
			)
		);
	}
}
