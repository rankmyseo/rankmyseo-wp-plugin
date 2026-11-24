<?php
/**
 * Tests for missing metadata, local templates, the approval queue, and editor insights.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\AiActions\ActionExecutor;
use RankMySEO\Modules\AiActions\ActionRepository;
use RankMySEO\Modules\AiActions\AuditLog;
use RankMySEO\Modules\AiActions\MissingMetadataQueue;
use RankMySEO\Modules\AiActions\PayloadSanitizer;
use RankMySEO\Modules\AiActions\PolicyStore;
use RankMySEO\Modules\AiActions\RedirectWriter;
use RankMySEO\Modules\AiActions\SnapshotRepository;
use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\EditorInsightReader;
use RankMySEO\Modules\Cloud\InsightsCache;
use RankMySEO\Modules\Cloud\TokenVault;
use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\MetaStore;
use RankMySEO\Modules\Meta\MissingMetadataQuery;
use RankMySEO\Modules\Meta\QuickBulkEdit;
use RankMySEO\Modules\Meta\TemplateEngine;
use RankMySEO\Modules\Meta\TemplateFill;
use RankMySEO\Modules\Meta\TitleSettings;
use RankMySEO\Rest\EditorInsightsController;
use RankMySEO\Rest\MissingMetadataController;
use RankMySEO_Test_State;
use WP_Error;
use WP_Post;
use WP_REST_Request;

final class MissingMetadataTest extends TestCase {
	private MetaStore $store;

	private MissingMetadataQuery $query;

	private TitleSettings $titles;

	private TemplateFill $fill;

	private TokenVault $vault;

	private ActionRepository $actions;

	private ActionExecutor $executor;

	private MissingMetadataController $controller;

	private EditorInsightsController $insights;

	protected function setUp(): void {
		RankMySEO_Test_State::reset();
		$wpdb                        = $GLOBALS['wpdb'];
		$wpdb->table_rows            = array();
		$wpdb->insert_id             = 0;
		RankMySEO_Test_State::$bloginfo['name'] = 'Example';
		RankMySEO_Test_State::$current_user_caps = array(
			'edit_posts'            => true,
			'rankmyseo_manage'      => true,
			'rankmyseo_approve_ai'  => true,
		);

		$this->store    = new MetaStore();
		$this->query    = new MissingMetadataQuery( $this->store );
		$this->titles   = new TitleSettings( new Settings() );
		$this->fill     = new TemplateFill( $this->titles, new TemplateEngine(), $this->query );
		$this->vault    = new TokenVault();
		$client         = new CloudClient( $this->vault );
		$this->actions  = new ActionRepository( $wpdb );
		$snapshots      = new SnapshotRepository( $wpdb );
		$this->executor = new ActionExecutor(
			$this->actions,
			$snapshots,
			new AuditLog( $wpdb ),
			new RedirectWriter( $wpdb ),
			new PolicyStore(),
			$client,
			$this->vault
		);
		$queue          = new MissingMetadataQueue( $this->vault, $client, $this->actions, $this->executor, $this->query );
		$this->controller = new MissingMetadataController( $this->query, $this->fill, $queue, $this->titles, $this->vault );
		$this->insights   = new EditorInsightsController( new EditorInsightReader( new InsightsCache( $this->vault, $client ), $this->actions ) );
	}

	public function test_filters_published_posts_pages_products_and_images(): void {
		$door = $this->seed( 'post', 'publish', 'Front door', 'door' );
		$photo = $this->seed( 'attachment', 'inherit', 'Photo', 'photo' );
		update_post_meta( $door, '_thumbnail_id', $photo );
		update_post_meta( $photo, '_wp_attached_file', 'photos/front-door.jpg' );
		$about = $this->seed( 'page', 'publish', 'About', 'about' );
		update_post_meta( $about, MetaKeys::TITLE, 'About Example' );
		$lamp = $this->seed( 'product', 'publish', 'Lamp', 'lamp' );
		$this->seed( 'post', 'draft', 'Draft door', 'draft-door' );

		$titles = $this->ids( $this->list( 'title', '', '' ) );
		$this->assertContains( $door, $titles );
		$this->assertContains( $photo, $titles );
		$this->assertContains( $lamp, $titles );
		$this->assertNotContains( $about, $titles );

		$alts = $this->ids( $this->list( 'image_alt', '', '' ) );
		$this->assertSame( array( $door, $photo ), $alts );

		$pages = $this->ids( $this->list( '', 'page', '' ) );
		$this->assertSame( array( $about ), $pages );

		$search = $this->ids( $this->list( '', '', 'door' ) );
		$this->assertSame( array( $door ), $search );
		$this->assertNotContains( 'Draft door', array_column( $this->list( '', '', '' ), 'title' ) );
	}

	public function test_local_template_preview_and_apply_do_not_call_the_network(): void {
		$door  = $this->seed( 'post', 'publish', 'Front door', 'door' );
		$photo = $this->seed( 'attachment', 'inherit', 'Photo', 'photo' );
		update_post_meta( $door, '_thumbnail_id', $photo );
		update_post_meta( $photo, '_wp_attached_file', 'photos/front-door.jpg' );
		$this->titles->update( array( 'image_alt' => '%filename%' ) );

		$request = new WP_REST_Request();
		$request->set_json_params(
			array(
				'ids'    => array( $door ),
				'fields' => array( 'title', 'description', 'image_alt' ),
			)
		);
		$preview = $this->controller->preview( $request )->get_data();
		$changes = array();
		foreach ( $preview['items'][0]['changes'] as $change ) {
			$changes[ $change['field'] ] = $change['after'];
		}
		$this->assertSame( 'Front door | Example', $changes['title'] );
		$this->assertSame( 'Excerpt for Front door', $changes['description'] );
		$this->assertSame( 'front door', $changes['image_alt'] );
		$this->assertSame( '', get_post_meta( $door, MetaKeys::TITLE, true ) );
		$this->assertSame( array(), RankMySEO_Test_State::$http_log );

		$applied = $this->controller->apply_templates( $request )->get_data();
		$this->assertSame( 1, $applied['applied'] );
		$this->assertSame( 'Front door | Example', get_post_meta( $door, MetaKeys::TITLE, true ) );
		$this->assertSame( 'front door', get_post_meta( $photo, '_wp_attachment_image_alt', true ) );
		$this->assertSame( array(), $this->ids( $this->list( 'title', '', 'door' ) ) );
		$this->assertSame( array(), RankMySEO_Test_State::$http_log );
	}

	public function test_queue_stays_offline_until_connected_and_image_alt_rolls_back(): void {
		$photo = $this->seed( 'attachment', 'inherit', 'Photo', 'photo' );
		update_post_meta( $photo, '_wp_attachment_image_alt', 'Old alt' );

		$request = new WP_REST_Request();
		$request->set_json_params( array( 'ids' => array( $photo ) ) );
		$blocked = $this->controller->queue_suggestions( $request );
		$this->assertInstanceOf( WP_Error::class, $blocked );
		$this->assertSame( 409, $blocked->get_error_data()['status'] );
		$this->assertSame( array(), RankMySEO_Test_State::$http_log );
		$this->assertSame( 'Old alt', get_post_meta( $photo, '_wp_attachment_image_alt', true ) );

		$this->connect_vault();
		$photo_id = $photo;
		add_filter(
			'pre_http_request',
			static function ( $pre, array $args, string $url ) use ( $photo_id ) {
				unset( $pre );
				if ( ! str_contains( $url, '/wp/v1/actions/generate' ) ) {
					return rankmyseo_test_http_response( 200, array( 'ok' => true ) );
				}
				$body = json_decode( (string) $args['body'], true );
				if ( ! is_array( $body ) || 'missing_metadata' !== ( $body['source'] ?? '' ) ) {
					return rankmyseo_test_http_response( 400, array( 'error' => 'invalid' ) );
				}
				return rankmyseo_test_http_response(
					200,
					array(
						'suggestions' => array(
							array(
								'wpPostId'     => $photo_id,
								'imageAlt'     => 'Front door of the shop',
								'attachmentId' => $photo_id,
								'rationale'    => 'Describe the photo.',
							),
						),
					)
				);
			},
			10,
			3
		);

		$queued = $this->controller->queue_suggestions( $request );
		$this->assertNotInstanceOf( WP_Error::class, $queued );
		$data = $queued->get_data();
		$this->assertSame( 1, $data['queued'] );
		$this->assertSame( 'image_alt', $data['actions'][0]['action_type'] );
		$this->assertSame( 'low', $data['actions'][0]['risk_tier'] );
		$this->assertSame( 'pending', $data['actions'][0]['status'] );
		$this->assertSame( 'Old alt', $data['actions'][0]['before_text'] );
		$this->assertSame( 'Front door of the shop', $data['actions'][0]['after_text'] );
		$this->assertSame( 'Old alt', get_post_meta( $photo, '_wp_attachment_image_alt', true ) );
		$this->assertSame( '', get_post_meta( $photo, MetaKeys::TITLE, true ) );

		$applied = $this->executor->apply( (int) $data['actions'][0]['id'], 'human' );
		$this->assertTrue( $applied['ok'] );
		$this->assertSame( 'Front door of the shop', get_post_meta( $photo, PayloadSanitizer::META_IMAGE_ALT, true ) );

		$rolled = $this->executor->rollback( (int) $data['actions'][0]['id'] );
		$this->assertTrue( $rolled['ok'] );
		$this->assertSame( 'rolled_back', $rolled['action']['status'] );
		$this->assertSame( 'Old alt', get_post_meta( $photo, PayloadSanitizer::META_IMAGE_ALT, true ) );
	}

	public function test_bulk_edit_applies_templates_or_clears_custom_meta(): void {
		$post = $this->seed( 'post', 'publish', 'Hello', 'hello' );
		update_post_meta( $post, MetaKeys::TITLE, 'Custom title' );
		update_post_meta( $post, MetaKeys::DESCRIPTION, 'Custom description' );
		update_post_meta( $post, MetaKeys::CANONICAL, 'https://example.com/hello/' );
		update_post_meta( $post, MetaKeys::FOCUS_KEYWORD, 'hello' );
		update_post_meta( $post, MetaKeys::ROBOTS, array( 'noindex' => true ) );
		update_post_meta( $post, '_wp_attachment_image_alt', 'Keep alt' );

		$editor = new QuickBulkEdit( $this->store, $this->fill );
		ob_start();
		$editor->render_bulk_edit( 'rankmyseo', 'post' );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Apply title template', $html );
		$this->assertStringContainsString( 'Clear custom title, description, and keyword', $html );

		RankMySEO_Test_State::$nonces['bulk-posts'] = 'bulk-ok';
		$_REQUEST = array(
			'rankmyseo_bulk_edit'  => '1',
			'rankmyseo_bulk_title' => 'template',
			'_wpnonce'             => 'wrong',
		);
		$editor->save_post( $post );
		$this->assertSame( 'Custom title', get_post_meta( $post, MetaKeys::TITLE, true ) );

		$_REQUEST['rankmyseo_bulk_noindex'] = '-1';
		$_REQUEST['_wpnonce']               = 'bulk-ok';
		$editor->save_post( $post );
		$this->assertSame( 'Hello | Example', get_post_meta( $post, MetaKeys::TITLE, true ) );
		$this->assertSame( 'Custom description', get_post_meta( $post, MetaKeys::DESCRIPTION, true ) );

		$_REQUEST['rankmyseo_bulk_title']     = '-1';
		$_REQUEST['rankmyseo_bulk_clear_meta'] = '1';
		$editor->save_post( $post );
		$this->assertSame( '', get_post_meta( $post, MetaKeys::TITLE, true ) );
		$this->assertSame( '', get_post_meta( $post, MetaKeys::DESCRIPTION, true ) );
		$this->assertSame( '', get_post_meta( $post, MetaKeys::CANONICAL, true ) );
		$this->assertSame( '', get_post_meta( $post, MetaKeys::FOCUS_KEYWORD, true ) );
		$this->assertSame( array( 'noindex' => true ), get_post_meta( $post, MetaKeys::ROBOTS, true ) );
		$this->assertSame( 'Keep alt', get_post_meta( $post, '_wp_attachment_image_alt', true ) );
	}

	public function test_editor_insights_match_the_cache_without_a_request(): void {
		$post = $this->seed( 'post', 'publish', 'Front door', 'door' );
		$this->connect_vault();
		set_transient(
			InsightsCache::TRANSIENT,
			array(
				'connected' => true,
				'keywords'  => array(
					'tracked' => 2,
					'rows'    => array(
						array(
							'keyword'          => 'unknown phrase',
							'position'         => null,
							'outside_top_20'   => false,
							'positions_gained' => null,
							'url'              => 'https://example.com/door/',
							'wp_post_id'       => null,
						),
						array(
							'keyword'          => 'front door',
							'position'         => 4,
							'outside_top_20'   => false,
							'positions_gained' => 3,
							'url'              => 'https://example.com/door',
							'wp_post_id'       => $post,
							'traffic'          => 99,
						),
					),
				),
				'issues'       => array(
					array( 'id' => 'title' ),
				),
				'page_actions' => array(
					array(
						'url'        => 'https://other.example/door',
						'action'     => 'tune',
						'summary'    => 'Small edits can help this page.',
						'wp_post_id' => $post,
					),
				),
			),
			900
		);

		$request = new WP_REST_Request();
		$request->set_param( 'post_id', $post );
		$response = $this->insights->get_insights( $request )->get_data();
		$this->assertSame( array(), RankMySEO_Test_State::$http_log );
		$this->assertTrue( $response['connected'] );
		$this->assertTrue( $response['matched'] );
		$this->assertSame( 'front door', $response['keyword']['keyword'] );
		$this->assertSame( 4, $response['keyword']['position'] );
		$this->assertFalse( $response['keyword']['outside_top_20'] );
		$this->assertSame( 3, $response['keyword']['positions_gained'] );
		$this->assertArrayNotHasKey( 'traffic', $response['keyword'] );
		$this->assertSame( 'tune', $response['page_action']['action'] );
		$this->assertSame( 1, $response['issue_count'] );
		$this->assertStringNotContainsString( 'rms_wp_', (string) wp_json_encode( $response ) );

		$other = new WP_REST_Request();
		$other->set_param( 'post_id', 0 );
		$other->set_param( 'url', 'https://example.com/unrelated' );
		$miss = $this->insights->get_insights( $other )->get_data();
		$this->assertFalse( $miss['matched'] );

		RankMySEO_Test_State::reset();
		$vault  = new TokenVault();
		$reader = new EditorInsightReader( new InsightsCache( $vault, new CloudClient( $vault ) ), new ActionRepository( $GLOBALS['wpdb'] ) );
		$empty  = $reader->for_post( $post );
		$this->assertFalse( $empty['connected'] );
		$this->assertSame( array(), RankMySEO_Test_State::$http_log );
	}

	public function test_insights_cache_keeps_rank_flags_and_drops_traffic(): void {
		$this->connect_vault();
		add_filter(
			'pre_http_request',
			static function () {
				return rankmyseo_test_http_response(
					200,
					array(
						'keywords'    => array(
							'rows' => array(
								array(
									'keyword'         => 'front door',
									'position'        => null,
									'outsideTop20'    => true,
									'positionsGained' => 0,
									'url'             => 'https://example.com/door',
									'traffic'         => 1200,
									'searchVolume'    => 4000,
								),
								array(
									'keyword'      => 'false flag',
									'position'     => null,
									'outsideTop20' => 'false',
									'url'          => 'https://example.com/other',
								),
							),
						),
						'pageActions' => array(
							array(
								'url'     => 'https://example.com/door',
								'action'  => 'tune',
								'summary' => 'Small edits can help this page.',
								'traffic' => 50,
							),
						),
					)
				);
			}
		);

		$cache   = new InsightsCache( $this->vault, new CloudClient( $this->vault ) );
		$payload = $cache->get( true );
		$row     = $payload['keywords']['rows'][0];
		$this->assertNull( $row['position'] );
		$this->assertTrue( $row['outside_top_20'] );
		$this->assertSame( 0, $row['positions_gained'] );
		$this->assertArrayNotHasKey( 'traffic', $row );
		$this->assertArrayNotHasKey( 'searchVolume', $row );
		$this->assertFalse( $payload['keywords']['rows'][1]['outside_top_20'] );
		$this->assertSame( 'tune', $payload['page_actions'][0]['action'] );
		$this->assertArrayNotHasKey( 'traffic', $payload['page_actions'][0] );
		$this->assertSame( $payload['page_updates'], $payload['page_actions'] );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function list( string $missing, string $type, string $search ): array {
		$request = new WP_REST_Request();
		$request->set_param( 'missing', $missing );
		$request->set_param( 'post_type', $type );
		$request->set_param( 'search', $search );
		$request->set_param( 'page', 1 );
		$items = $this->controller->list_items( $request )->get_data()['items'];
		return is_array( $items ) ? $items : array();
	}

	/**
	 * @param list<array<string, mixed>> $items Rows.
	 * @return list<int>
	 */
	private function ids( array $items ): array {
		$ids = array();
		foreach ( $items as $item ) {
			$ids[] = (int) $item['id'];
		}
		sort( $ids );
		return $ids;
	}

	private function seed( string $type, string $status, string $title, string $slug ): int {
		$post               = new WP_Post();
		$post->ID           = RankMySEO_Test_State::$next_post_id;
		RankMySEO_Test_State::$next_post_id++;
		$post->post_type    = $type;
		$post->post_status  = $status;
		$post->post_title   = $title;
		$post->post_name    = $slug;
		$post->post_excerpt = 'Excerpt for ' . $title;
		RankMySEO_Test_State::$posts[ $post->ID ] = $post;
		return (int) $post->ID;
	}

	private function connect_vault(): void {
		$this->vault->save_secrets(
			array(
				'token'          => 'rms_wp_abcdefSECRETSECRET',
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
