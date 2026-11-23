<?php
/**
 * Tests for the AI approval queue, snapshots, policy, webhooks, and chat.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Database\Schema;
use RankMySEO\Modules\AiActions\ActionCatalog;
use RankMySEO\Modules\AiActions\ActionExecutor;
use RankMySEO\Modules\AiActions\ActionRepository;
use RankMySEO\Modules\AiActions\ActionSync;
use RankMySEO\Modules\AiActions\AuditLog;
use RankMySEO\Modules\AiActions\AutoApplyRunner;
use RankMySEO\Modules\AiActions\ChatBridge;
use RankMySEO\Modules\AiActions\PayloadSanitizer;
use RankMySEO\Modules\AiActions\PolicyStore;
use RankMySEO\Modules\AiActions\RedirectWriter;
use RankMySEO\Modules\AiActions\SnapshotRepository;
use RankMySEO\Modules\AiActions\WebhookVerifier;
use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\TokenVault;
use RankMySEO\Rest\AiActionsController;
use RankMySEO_Test_State;
use WP_Error;
use WP_REST_Request;

final class AiActionsTest extends TestCase {
	private TokenVault $vault;

	private CloudClient $client;

	private ActionRepository $actions;

	private ActionExecutor $executor;

	private PolicyStore $policy;

	private AuditLog $audit;

	private ChatBridge $chat;

	private AiActionsController $controller;

	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		$wpdb                 = $GLOBALS['wpdb'];
		$wpdb->table_rows     = array();
		$wpdb->insert_id      = 0;
		$this->vault          = new TokenVault();
		$this->client         = new CloudClient( $this->vault );
		$this->actions        = new ActionRepository( $wpdb );
		$this->policy         = new PolicyStore();
		$this->audit          = new AuditLog( $wpdb );
		$snapshots            = new SnapshotRepository( $wpdb );
		$redirects            = new RedirectWriter( $wpdb );
		$this->executor       = new ActionExecutor( $this->actions, $snapshots, $this->audit, $redirects, $this->policy, $this->client, $this->vault );
		$sync                 = new ActionSync( $this->client, $this->actions, $this->vault );
		$this->chat           = new ChatBridge( $this->client, $this->vault, $sync, $this->actions );
		$this->controller     = new AiActionsController(
			$this->actions,
			$this->executor,
			$sync,
			$this->policy,
			$this->audit,
			$this->chat,
			new WebhookVerifier(),
			$this->vault,
			new AutoApplyRunner( $this->actions, $this->executor ),
			$this->client
		);
	}

	public function test_meta_schema_redirect_draft_and_content_patch_apply_and_roll_back(): void {
		$post_id = $this->seed_post( 'Old title', 'Old body' );
		update_post_meta( $post_id, PayloadSanitizer::META_TITLE, 'Old title' );

		$meta_id = $this->queue(
			array(
				'id'         => '11111111-1111-4111-8111-111111111111',
				'actionType' => 'meta_update',
				'wpPostId'   => $post_id,
				'targetUrl'  => 'https://example.com/about/',
				'rationale'  => 'Clearer search result.',
				'after'      => array(
					'seoTitle'        => 'New title',
					'metaDescription' => 'New description',
					'focusKeyword'    => 'widget',
					'canonical'       => 'https://example.com/about/',
					'robots'          => array( 'nofollow' => true ),
				),
			)
		);
		$applied = $this->executor->apply( $meta_id, 'human' );
		$this->assertTrue( $applied['ok'] );
		$this->assertSame( 'New title', get_post_meta( $post_id, PayloadSanitizer::META_TITLE, true ) );
		$this->assertSame( 'New description', get_post_meta( $post_id, PayloadSanitizer::META_DESCRIPTION, true ) );
		$this->assertSame( 'widget', get_post_meta( $post_id, PayloadSanitizer::META_FOCUS, true ) );
		$this->assertSame( 'https://example.com/about/', get_post_meta( $post_id, PayloadSanitizer::META_CANONICAL, true ) );
		$robots = get_post_meta( $post_id, PayloadSanitizer::META_ROBOTS, true );
		$this->assertIsArray( $robots );
		$this->assertTrue( $robots['nofollow'] );
		$this->assertFalse( $robots['noindex'] );

		$rolled = $this->executor->rollback( $meta_id );
		$this->assertTrue( $rolled['ok'] );
		$this->assertSame( 'Old title', get_post_meta( $post_id, PayloadSanitizer::META_TITLE, true ) );
		$this->assertSame( '', get_post_meta( $post_id, PayloadSanitizer::META_DESCRIPTION, true ) );
		$this->assertSame( 'rolled_back', $this->actions->find( $meta_id )['status'] );

		$schema_id = $this->queue(
			array(
				'id'         => '22222222-2222-4222-8222-222222222222',
				'actionType' => 'schema_update',
				'wpPostId'   => $post_id,
				'after'      => array(
					'schemaType' => 'FAQPage',
					'faq'        => array(
						array(
							'question' => 'What is it?',
							'answer'   => 'A widget.',
						),
					),
				),
			)
		);
		$this->assertTrue( $this->executor->apply( $schema_id, 'human' )['ok'] );
		$this->assertSame( 'faqpage', get_post_meta( $post_id, PayloadSanitizer::META_SCHEMA_TYPE, true ) );
		$faq = get_post_meta( $post_id, PayloadSanitizer::META_SCHEMA_DATA, true );
		$this->assertSame( 'What is it?', $faq[0]['question'] );
		$this->assertTrue( $this->executor->rollback( $schema_id )['ok'] );
		$this->assertSame( '', get_post_meta( $post_id, PayloadSanitizer::META_SCHEMA_TYPE, true ) );

		$redirect_id = $this->queue(
			array(
				'id'         => '33333333-3333-4333-8333-333333333333',
				'actionType' => 'redirect_create',
				'after'      => array(
					'source_path' => '/old-page',
					'target_url'  => '/new-page',
					'status_code' => 301,
				),
			)
		);
		$this->assertTrue( $this->executor->apply( $redirect_id, 'human' )['ok'] );
		$redirects = $GLOBALS['wpdb']->table_rows[ Schema::get_table_name( 'redirects', 'wp_' ) ];
		$this->assertCount( 1, $redirects );
		$this->assertSame( hash( 'sha256', '/old-page' ), $redirects[0]['source_hash'] );
		$this->assertSame( 'ai', $redirects[0]['source_origin'] );
		$this->assertSame( 'exact', $redirects[0]['match_type'] );
		$this->assertTrue( $this->executor->rollback( $redirect_id )['ok'] );
		$this->assertSame( array(), $GLOBALS['wpdb']->table_rows[ Schema::get_table_name( 'redirects', 'wp_' ) ] );

		$loop = $this->queue(
			array(
				'id'         => '33333333-3333-4333-8333-333333333334',
				'actionType' => 'add_redirect',
				'after'      => array(
					'source_path' => '/new-page',
					'target_url'  => '/old-page',
				),
			)
		);
		$this->executor->apply( $loop, 'human' );
		$back = $this->queue(
			array(
				'id'         => '33333333-3333-4333-8333-333333333335',
				'actionType' => 'add_redirect',
				'after'      => array(
					'source_path' => '/old-page',
					'target_url'  => '/new-page',
				),
			)
		);
		$blocked = $this->executor->apply( $back, 'human' );
		$this->assertFalse( $blocked['ok'] );

		$draft_id = $this->queue(
			array(
				'id'         => '44444444-4444-4444-8444-444444444444',
				'actionType' => 'draft_article',
				'after'      => array(
					'title'  => 'Draft headline',
					'body'   => '<p>Draft body</p><script>alert(1)</script>',
					'status' => 'publish',
				),
			)
		);
		$drafted = $this->executor->apply( $draft_id, 'human' );
		$this->assertTrue( $drafted['ok'] );
		$created = $this->latest_post();
		$this->assertSame( 'draft', $created->post_status );
		$this->assertStringNotContainsString( 'script', (string) $created->post_content );
		$this->assertTrue( $this->executor->rollback( $draft_id )['ok'] );
		$this->assertNull( get_post( (int) $created->ID ) );

		$patch_id = $this->queue(
			array(
				'id'         => '55555555-5555-4555-8555-555555555555',
				'actionType' => 'content_patch',
				'wpPostId'   => $post_id,
				'riskTier'   => 'low',
				'after'      => array( 'suggested' => '<p>New body</p>' ),
			)
		);
		$this->assertSame( 'high', $this->actions->find( $patch_id )['risk_tier'] );
		$this->assertTrue( $this->executor->apply( $patch_id, 'human' )['ok'] );
		$updated = get_post( $post_id );
		$this->assertSame( '<p>New body</p>', (string) $updated->post_content );
		$this->assertTrue( $this->executor->rollback( $patch_id )['ok'] );
		$restored = get_post( $post_id );
		$this->assertSame( 'Old body', (string) $restored->post_content );

		$events = array_column( $this->audit->recent(), 'event_type' );
		$this->assertContains( AuditLog::APPLIED, $events );
		$this->assertContains( AuditLog::ROLLED_BACK, $events );
	}

	public function test_auto_apply_blocks_content_and_keeps_articles_as_drafts(): void {
		$post_id = $this->seed_post( 'Keep', 'Body' );
		$this->policy->save(
			array(
				'enabled'      => true,
				'actionTypes'  => array( 'update_title', 'create_draft', 'content_patch' ),
				'postTypes'    => array( 'post' ),
				'dailyCap'     => 10,
				'excludedUrls' => array( '/private' ),
				'articles'     => 'draft',
			)
		);
		$stored = $this->policy->get();
		$this->assertNotContains( 'content_patch', $stored['actionTypes'] );
		$this->assertSame( 'draft', $stored['articles'] );

		$patch = $this->queue(
			array(
				'actionType' => 'content_patch',
				'wpPostId'   => $post_id,
				'after'      => array( 'content' => '<p>Nope</p>' ),
			)
		);
		$blocked = $this->executor->apply( $patch, 'auto' );
		$this->assertFalse( $blocked['ok'] );
		$this->assertSame( 409, $blocked['status'] );
		$this->assertSame( 'Body', (string) get_post( $post_id )->post_content );

		$rejected = $this->policy->parse(
			array(
				'enabled'     => true,
				'actionTypes' => array( 'content_patch' ),
				'articles'    => 'publish',
			)
		);
		$this->assertFalse( $rejected['ok'] );

		$draft = $this->queue(
			array(
				'actionType' => 'create_draft',
				'after'      => array(
					'title'  => 'Auto draft',
					'body'   => 'Still a draft',
					'status' => 'publish',
				),
			)
		);
		$this->assertTrue( $this->executor->apply( $draft, 'auto' )['ok'] );
		$this->assertSame( 'draft', $this->latest_post()->post_status );

		$title = $this->queue(
			array(
				'actionType' => 'update_title',
				'wpPostId'   => $post_id,
				'targetUrl'  => 'https://example.com/about/',
				'after'      => array( 'suggested' => 'Auto title' ),
			)
		);
		$this->assertTrue( $this->executor->apply( $title, 'auto' )['ok'] );
		$this->assertSame( 'Auto title', get_post_meta( $post_id, PayloadSanitizer::META_TITLE, true ) );

		$home = $this->queue(
			array(
				'actionType' => 'update_title',
				'wpPostId'   => $post_id,
				'targetUrl'  => 'https://example.com/',
				'after'      => array( 'suggested' => 'Home title' ),
			)
		);
		$this->policy->save(
			array(
				'enabled'      => true,
				'actionTypes'  => array( 'update_title' ),
				'postTypes'    => array( 'post' ),
				'dailyCap'     => 0,
				'excludedUrls' => array( '/' ),
			)
		);
		$this->assertFalse( $this->executor->apply( $home, 'auto' )['ok'] );

		$events = array_column( $this->audit->recent(), 'event_type' );
		$this->assertContains( AuditLog::AUTO, $events );
	}

	public function test_capabilities_gate_queue_routes(): void {
		RankMySEO_Test_State::$current_user_caps = array();
		$this->assertInstanceOf( WP_Error::class, $this->controller->check_manage_permission() );
		$this->assertInstanceOf( WP_Error::class, $this->controller->check_approve_ai_permission() );
		$this->assertInstanceOf( WP_Error::class, $this->controller->check_edit_posts_permission() );

		RankMySEO_Test_State::$current_user_caps = array(
			'edit_posts'           => true,
			'rankmyseo_approve_ai' => true,
		);
		$this->assertInstanceOf( WP_Error::class, $this->controller->check_manage_permission() );
		$this->assertTrue( $this->controller->check_approve_ai_permission() );

		$post_id = $this->seed_post( 'Cap', 'Body' );
		$id      = $this->queue(
			array(
				'actionType' => 'update_title',
				'wpPostId'   => $post_id,
				'after'      => array( 'suggested' => 'Editor title' ),
			)
		);
		$request = new WP_REST_Request();
		$request->set_param( 'id', $id );
		$approved = $this->controller->approve( $request );
		$this->assertSame( 200, $approved->get_status() );

		RankMySEO_Test_State::$current_user_caps = array(
			'rankmyseo_manage' => true,
		);
		$this->assertTrue( $this->controller->check_manage_permission() );
		$list = $this->controller->list_actions( new WP_REST_Request() );
		$this->assertArrayHasKey( 'actions', $list->get_data() );
	}

	public function test_webhook_signature_and_audit_log(): void {
		$secret = 'whsec_testsecretvalue';
		$this->vault->save_secrets(
			array(
				'token'          => 'rms_wp_abcdefSECRETSECRET',
				'webhook_secret' => $secret,
			)
		);
		$this->vault->update_public( array( 'connected' => true ) );

		$body = (string) wp_json_encode(
			array(
				'action' => array(
					'id'         => '66666666-6666-4666-8666-666666666666',
					'actionType' => 'update_meta_description',
					'wpPostId'   => 12,
					'after'      => array( 'suggested' => 'From the webhook' ),
					'rationale'  => 'Imported.',
				),
			)
		);
		$now = time();
		$good = $this->controller_webhook( $secret, $body, $now );
		$this->assertSame( 200, $good->get_status() );
		$this->assertSame( 1, $good->get_data()['imported'] );
		$this->assertNotNull( $this->actions->find_by_cloud_id( '66666666-6666-4666-8666-666666666666' ) );

		$bad = $this->controller_webhook( 'wrong-secret', $body, $now );
		$this->assertInstanceOf( WP_Error::class, $bad );
		$this->assertSame( 401, $bad->get_error_data()['status'] );

		$stale = $this->controller_webhook( $secret, $body, $now - 1000 );
		$this->assertInstanceOf( WP_Error::class, $stale );

		$events = array_column( $this->audit->recent(), 'event_type' );
		$this->assertContains( AuditLog::WEBHOOK, $events );
		$this->assertNotSame( ActionCatalog::UPDATE_TITLE, 'update_meta_description' );
	}

	public function test_chat_bridge_is_async_and_makes_no_requests_when_disconnected(): void {
		$sent = $this->chat->send( 'How is this site doing?' );
		$this->assertFalse( $sent['ok'] );
		$this->assertSame( array(), RankMySEO_Test_State::$http_log );

		$this->connect();
		add_filter(
			'pre_http_request',
			static function ( $pre, array $args, string $url ) {
				unset( $pre );
				if ( str_contains( $url, '/wp/v1/chat/message' ) ) {
					$body = json_decode( (string) $args['body'], true );
					if ( ! is_array( $body ) || true !== ( $body['async'] ?? false ) ) {
						return rankmyseo_test_http_response( 400, array( 'error' => 'invalid_request', 'message' => 'async required' ) );
					}
					$hash = (string) ( $args['headers']['X-WP-User-Hash'] ?? '' );
					if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
						return rankmyseo_test_http_response( 400, array( 'error' => 'invalid_request', 'message' => 'hash required' ) );
					}
					return rankmyseo_test_http_response(
						202,
						array(
							'status'   => 'running',
							'turnId'   => '77777777-7777-4777-8777-777777777777',
							'threadId' => '88888888-8888-4888-8888-888888888888',
						)
					);
				}
				if ( str_contains( $url, '/wp/v1/chat/progress' ) ) {
					return rankmyseo_test_http_response(
						200,
						array(
							'status'           => 'done',
							'phase'            => 'done',
							'threadId'         => '88888888-8888-4888-8888-888888888888',
							'turnId'           => '77777777-7777-4777-8777-777777777777',
							'assistantMessage' => array(
								'content'     => 'Update the search title.',
								'attachments' => array(
									array(
										'type'   => 'wp_action',
										'action' => array(
											'id'         => '99999999-9999-4999-8999-999999999999',
											'actionType' => 'update_title',
											'wpPostId'   => 4,
											'riskTier'   => 'low',
											'after'      => array( 'suggested' => 'Better title' ),
											'rationale'  => 'Clearer in search results.',
											'status'     => 'pending',
										),
									),
								),
							),
						)
					);
				}
				if ( str_contains( $url, '/wp/v1/chat/thread' ) ) {
					return rankmyseo_test_http_response(
						200,
						array(
							'threadId' => '88888888-8888-4888-8888-888888888888',
							'messages' => array(
								array(
									'id'      => 'm1',
									'role'    => 'assistant',
									'content' => 'Earlier note',
								),
							),
						)
					);
				}
				return rankmyseo_test_http_response( 200, array() );
			},
			10,
			3
		);

		$started = $this->chat->send( 'Suggest a title' );
		$this->assertTrue( $started['ok'] );
		$this->assertSame( 202, $started['status'] );
		$this->assertSame( 'running', $started['state'] );

		$turn = $this->chat->turn( '77777777-7777-4777-8777-777777777777' );
		$this->assertSame( 'done', $turn['state'] );
		$this->assertSame( 'Update the search title.', $turn['reply'] );
		$this->assertCount( 1, $turn['actions'] );
		$this->assertSame( 'update_title', $turn['actions'][0]['action_type'] );
		$this->assertNotNull( $this->actions->find_by_cloud_id( '99999999-9999-4999-8999-999999999999' ) );

		$history = $this->chat->history();
		$this->assertSame( 'Earlier note', $history['messages'][0]['content'] );

		$urls = array_column( RankMySEO_Test_State::$http_log, 'url' );
		$this->assertNotEmpty( $urls );
		foreach ( $urls as $url ) {
			$this->assertStringContainsString( '/wp/v1/chat/', $url );
		}
	}

	public function test_sync_imports_pending_actions_and_acks_apply(): void {
		$this->connect();
		add_filter(
			'pre_http_request',
			static function ( $pre, array $args, string $url ) {
				unset( $pre );
				if ( str_contains( $url, '/ack' ) ) {
					$body = json_decode( (string) $args['body'], true );
					if ( ! is_array( $body ) || 'applied' !== ( $body['status'] ?? '' ) ) {
						return rankmyseo_test_http_response( 400, array( 'message' => 'bad ack' ) );
					}
					return rankmyseo_test_http_response( 200, array( 'ok' => true ) );
				}
				if ( str_contains( $url, '/wp/v1/actions' ) ) {
					return rankmyseo_test_http_response(
						200,
						array(
							'actions' => array(
								array(
									'id'         => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
									'actionType' => 'update_title',
									'wpPostId'   => 21,
									'after'      => array( 'suggested' => 'Synced title' ),
									'rationale'  => 'From the queue.',
									'status'     => 'pending',
								),
							),
						)
					);
				}
				return rankmyseo_test_http_response( 200, array() );
			},
			10,
			3
		);

		$post_id = $this->seed_post( 'Before', 'Body' );
		$pulled  = ( new ActionSync( $this->client, $this->actions, $this->vault ) )->pull( false );
		$this->assertTrue( $pulled['ok'] );
		$this->assertSame( 1, $pulled['imported'] );

		$action = $this->actions->find_by_cloud_id( 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );
		$this->assertNotNull( $action );
		$this->actions->update_columns( (int) $action['id'], array( 'wp_post_id' => $post_id ) );
		$this->assertTrue( $this->executor->apply( (int) $action['id'], 'human' )['ok'] );
		$this->assertSame( 'Synced title', get_post_meta( $post_id, PayloadSanitizer::META_TITLE, true ) );

		$acked = false;
		foreach ( RankMySEO_Test_State::$http_log as $call ) {
			if ( str_contains( $call['url'], '/ack' ) ) {
				$acked = true;
			}
		}
		$this->assertTrue( $acked );
	}

	/**
	 * @param array<string, mixed> $cloud Cloud action.
	 */
	private function queue( array $cloud ): int {
		$id = $this->actions->upsert_cloud( $cloud );
		$this->assertGreaterThan( 0, $id );
		return $id;
	}

	private function seed_post( string $title, string $content ): int {
		return wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
	}

	private function latest_post(): object {
		$posts = RankMySEO_Test_State::$posts;
		$post  = $posts[ count( $posts ) - 1 ];
		$this->assertIsObject( $post );
		return $post;
	}

	private function connect(): void {
		$this->vault->save_secrets( array( 'token' => 'rms_wp_abcdefSECRETSECRET' ) );
		$this->vault->update_public( array( 'connected' => true ) );
	}

	/**
	 * @return \WP_REST_Response|WP_Error
	 */
	private function controller_webhook( string $secret, string $body, int $timestamp ) {
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		$request   = new WP_REST_Request();
		$request->set_body( $body );
		$request->set_header( 'X-Webhook-Signature', 't=' . $timestamp . ',v1=' . $signature );
		return $this->controller->webhook( $request );
	}
}
