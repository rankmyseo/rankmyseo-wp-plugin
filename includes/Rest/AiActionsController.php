<?php
/**
 * REST API for the AI approval queue, policy, audit log, and connected chat.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\AiActions\ActionExecutor;
use RankMySEO\Modules\AiActions\ActionRepository;
use RankMySEO\Modules\AiActions\ActionSync;
use RankMySEO\Modules\AiActions\AuditLog;
use RankMySEO\Modules\AiActions\AutoApplyRunner;
use RankMySEO\Modules\AiActions\ChatBridge;
use RankMySEO\Modules\AiActions\PolicyStore;
use RankMySEO\Modules\AiActions\WebhookVerifier;
use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\TokenVault;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Admin routes under rankmyseo/v1/ai-actions and rankmyseo/v1/cloud/chat.
 */
final class AiActionsController extends AbstractController {
	/**
	 * Action store.
	 *
	 * @var ActionRepository
	 */
	private ActionRepository $actions;

	/**
	 * Executor.
	 *
	 * @var ActionExecutor
	 */
	private ActionExecutor $executor;

	/**
	 * Sync.
	 *
	 * @var ActionSync
	 */
	private ActionSync $sync;

	/**
	 * Policy.
	 *
	 * @var PolicyStore
	 */
	private PolicyStore $policy;

	/**
	 * Audit log.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Chat bridge.
	 *
	 * @var ChatBridge
	 */
	private ChatBridge $chat;

	/**
	 * Webhook verifier.
	 *
	 * @var WebhookVerifier
	 */
	private WebhookVerifier $webhooks;

	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * Auto-apply runner.
	 *
	 * @var AutoApplyRunner
	 */
	private AutoApplyRunner $runner;

	/**
	 * Cloud client, used when saving policy upstream.
	 *
	 * @var CloudClient
	 */
	private CloudClient $client;

	/**
	 * Constructor.
	 *
	 * @param ActionRepository $actions  Action store.
	 * @param ActionExecutor   $executor Executor.
	 * @param ActionSync       $sync     Sync.
	 * @param PolicyStore      $policy   Policy.
	 * @param AuditLog         $audit    Audit log.
	 * @param ChatBridge       $chat     Chat bridge.
	 * @param WebhookVerifier  $webhooks Webhook verifier.
	 * @param TokenVault       $vault    Credential vault.
	 * @param AutoApplyRunner  $runner   Auto-apply runner.
	 * @param CloudClient      $client   Cloud client.
	 */
	public function __construct(
		ActionRepository $actions,
		ActionExecutor $executor,
		ActionSync $sync,
		PolicyStore $policy,
		AuditLog $audit,
		ChatBridge $chat,
		WebhookVerifier $webhooks,
		TokenVault $vault,
		AutoApplyRunner $runner,
		CloudClient $client
	) {
		$this->actions  = $actions;
		$this->executor = $executor;
		$this->sync     = $sync;
		$this->policy   = $policy;
		$this->audit    = $audit;
		$this->chat     = $chat;
		$this->webhooks = $webhooks;
		$this->vault    = $vault;
		$this->runner   = $runner;
		$this->client   = $client;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$manage  = array( $this, 'check_manage_permission' );
		$approve = array( $this, 'check_approve_ai_permission' );
		$edit    = array( $this, 'check_edit_posts_permission' );

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-actions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_actions' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-actions/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_actions' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-actions/audit-log',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'audit_log' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-actions/policy',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_policy' ),
					'permission_callback' => $approve,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_policy' ),
					'permission_callback' => $approve,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-actions/(?P<id>\d+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve' ),
				'permission_callback' => $approve,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-actions/(?P<id>\d+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reject' ),
				'permission_callback' => $approve,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai-actions/(?P<id>\d+)/rollback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rollback' ),
				'permission_callback' => $approve,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/chat/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat_send' ),
				'permission_callback' => $edit,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/chat/turns/(?P<turn_id>[A-Za-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'chat_turn' ),
				'permission_callback' => $edit,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/chat/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'chat_history' ),
				'permission_callback' => $edit,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cloud/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'webhook' ),
				'permission_callback' => array( $this, 'allow_signed_webhook' ),
			)
		);

		add_action( 'rankmyseo_run_auto_apply', array( $this, 'run_auto_apply' ) );
		if ( ! wp_next_scheduled( 'rankmyseo_run_auto_apply' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'rankmyseo_run_auto_apply' );
		}
	}

	/**
	 * Cron callback. The count is intentionally unused.
	 *
	 * @return void
	 */
	public function run_auto_apply(): void {
		$this->runner->run();
	}

	/**
	 * Signed webhooks do not use a WordPress cookie.
	 *
	 * @return bool
	 */
	public function allow_signed_webhook(): bool {
		return true;
	}

	/**
	 * List local actions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_actions( WP_REST_Request $request ): WP_REST_Response {
		$status = $request->get_param( 'status' );
		$type   = $request->get_param( 'type' );
		$status = is_string( $status ) ? sanitize_key( $status ) : '';
		$type   = is_string( $type ) ? sanitize_key( $type ) : '';
		$items  = array();
		foreach ( $this->actions->list_actions( $status, $type ) as $action ) {
			$items[] = $this->actions->present( $action );
		}
		return $this->success_response( array( 'actions' => $items ) );
	}

	/**
	 * Pull pending actions from RankMySEO.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync_actions( WP_REST_Request $request ) {
		$generate = $request->get_param( 'generate' );
		$want     = true === $generate || 1 === $generate || '1' === $generate || 'true' === $generate;
		$body     = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$result = $this->sync->pull( $want, $body );
		if ( empty( $result['ok'] ) ) {
			return $this->error_response( 'rankmyseo_ai_sync', (string) $result['message'], (int) $result['status'] );
		}
		return $this->success_response(
			array(
				'imported' => (int) $result['imported'],
				'actions'  => $result['actions'],
				'message'  => (string) $result['message'],
			)
		);
	}

	/**
	 * Approve and apply one action.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$after  = $request->get_param( 'after' );
		$edited = is_array( $after ) ? $after : null;
		$force  = $request->get_param( 'force' );
		$result = $this->executor->apply( $id, 'human', $edited, true === $force || 1 === $force || '1' === $force );
		return $this->from_executor( $result );
	}

	/**
	 * Reject one action.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject( WP_REST_Request $request ) {
		$note   = $request->get_param( 'note' );
		$result = $this->executor->reject( (int) $request->get_param( 'id' ), is_string( $note ) ? $note : '' );
		return $this->from_executor( $result );
	}

	/**
	 * Roll back one applied action.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rollback( WP_REST_Request $request ) {
		return $this->from_executor( $this->executor->rollback( (int) $request->get_param( 'id' ) ) );
	}

	/**
	 * Recent audit rows.
	 *
	 * @return WP_REST_Response
	 */
	public function audit_log(): WP_REST_Response {
		return $this->success_response( array( 'entries' => $this->audit->recent( 100 ) ) );
	}

	/**
	 * Read the auto-apply policy. Refreshes from RankMySEO when connected.
	 *
	 * @return WP_REST_Response
	 */
	public function get_policy(): WP_REST_Response {
		if ( $this->vault->is_connected() ) {
			$remote = $this->client->get_policy();
			if ( $remote->ok && isset( $remote->data['policy'] ) && is_array( $remote->data['policy'] ) ) {
				$parsed = $this->policy->parse( $remote->data['policy'] );
				if ( ! empty( $parsed['ok'] ) && isset( $parsed['policy'] ) && is_array( $parsed['policy'] ) ) {
					$this->policy->save( $parsed['policy'] );
				}
			}
		}
		return $this->success_response( array( 'policy' => $this->policy->get() ) );
	}

	/**
	 * Save the auto-apply policy and mirror it to RankMySEO when connected.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_policy( WP_REST_Request $request ) {
		$parsed = $this->policy->parse( $request->get_json_params() );
		if ( empty( $parsed['ok'] ) || ! isset( $parsed['policy'] ) || ! is_array( $parsed['policy'] ) ) {
			return $this->error_response( 'rankmyseo_ai_policy', (string) ( $parsed['message'] ?? '' ), 400 );
		}
		$this->policy->save( $parsed['policy'] );
		$this->audit->record( AuditLog::POLICY, 'user', __( 'Auto-apply settings saved.', 'rankmyseo' ), 'site', 0 );
		if ( $this->vault->is_connected() ) {
			$this->client->put_policy( $parsed['policy'] );
		}
		return $this->success_response( array( 'policy' => $this->policy->get() ) );
	}

	/**
	 * Send a connected chat message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat_send( WP_REST_Request $request ) {
		$content = $request->get_param( 'content' );
		$page    = $request->get_param( 'pageContext' );
		$result  = $this->chat->send( is_string( $content ) ? $content : '', is_array( $page ) ? $page : array() );
		return $this->from_chat( $result );
	}

	/**
	 * Poll one chat turn.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat_turn( WP_REST_Request $request ) {
		$turn = $request->get_param( 'turn_id' );
		return $this->from_chat( $this->chat->turn( is_string( $turn ) ? $turn : '' ) );
	}

	/**
	 * Read chat history.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat_history() {
		return $this->from_chat( $this->chat->history() );
	}

	/**
	 * Accept a signed action delivery.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function webhook( WP_REST_Request $request ) {
		$body   = $request->get_body();
		$header = $request->get_header( 'x-webhook-signature' );
		if ( ! is_string( $header ) || '' === $header ) {
			$header = (string) $request->get_header( 'X-Webhook-Signature' );
		}
		$secret = $this->vault->get_secret( 'webhook_secret' );
		if ( ! $this->webhooks->verify( $secret, is_string( $header ) ? $header : '', $body ) ) {
			return $this->error_response( 'rankmyseo_webhook', __( 'That delivery could not be verified.', 'rankmyseo' ), 401 );
		}
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return $this->error_response( 'rankmyseo_webhook', __( 'That delivery could not be read.', 'rankmyseo' ), 400 );
		}
		$rows = array();
		if ( isset( $decoded['actions'] ) && is_array( $decoded['actions'] ) ) {
			$rows = $decoded['actions'];
		} elseif ( isset( $decoded['action'] ) && is_array( $decoded['action'] ) ) {
			$rows = array( $decoded['action'] );
		}
		$imported = 0;
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && $this->sync->ingest( $row ) > 0 ) {
				++$imported;
			}
		}
		$this->audit->record( AuditLog::WEBHOOK, 'cloud_sync', __( 'Suggestions received.', 'rankmyseo' ), 'site', 0, array( 'imported' => $imported ) );
		return $this->success_response( array( 'imported' => $imported ) );
	}

	/**
	 * Map an executor result.
	 *
	 * @param array<string, mixed> $result Executor result.
	 * @return WP_REST_Response|WP_Error
	 */
	private function from_executor( array $result ) {
		if ( ! empty( $result['ok'] ) ) {
			return $this->success_response(
				array(
					'message' => (string) ( $result['message'] ?? '' ),
					'action'  => $result['action'] ?? array(),
				)
			);
		}
		return $this->error_response(
			'rankmyseo_ai_action',
			(string) ( $result['message'] ?? '' ),
			(int) ( $result['status'] ?? 400 )
		);
	}

	/**
	 * Map a chat result. 202 stays 202.
	 *
	 * @param array<string, mixed> $result Chat result.
	 * @return WP_REST_Response|WP_Error
	 */
	private function from_chat( array $result ) {
		if ( empty( $result['ok'] ) ) {
			return $this->error_response( 'rankmyseo_ai_chat', (string) ( $result['message'] ?? '' ), (int) ( $result['status'] ?? 400 ) );
		}
		$status = (int) ( $result['status'] ?? 200 );
		unset( $result['ok'] );
		return $this->success_response( $result, $status > 0 ? $status : 200 );
	}
}
