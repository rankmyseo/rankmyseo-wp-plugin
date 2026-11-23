<?php
/**
 * Connected Operator chat that does not hold a PHP worker open.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\CloudResult;
use RankMySEO\Modules\Cloud\TokenVault;

/**
 * Proxies /wp/v1/chat/message, /wp/v1/chat/progress, and /wp/v1/chat/thread.
 *
 * When the site is disconnected this class makes no HTTP calls.
 */
final class ChatBridge {
	/**
	 * Cloud client.
	 *
	 * @var CloudClient
	 */
	private CloudClient $client;

	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * Action sync.
	 *
	 * @var ActionSync
	 */
	private ActionSync $sync;

	/**
	 * Action store.
	 *
	 * @var ActionRepository
	 */
	private ActionRepository $actions;

	/**
	 * Constructor.
	 *
	 * @param CloudClient      $client  Cloud client.
	 * @param TokenVault       $vault   Credential vault.
	 * @param ActionSync       $sync    Action sync.
	 * @param ActionRepository $actions Action store.
	 */
	public function __construct( CloudClient $client, TokenVault $vault, ActionSync $sync, ActionRepository $actions ) {
		$this->client  = $client;
		$this->vault   = $vault;
		$this->sync    = $sync;
		$this->actions = $actions;
	}

	/**
	 * SHA-256 of the site URL and the current WordPress user.
	 *
	 * @return string
	 */
	public function user_hash(): string {
		return hash( 'sha256', untrailingslashit( home_url() ) . ':' . (string) get_current_user_id() );
	}

	/**
	 * Start a turn. Returns as soon as RankMySEO accepts it.
	 *
	 * @param string               $content Message text.
	 * @param array<string, mixed> $page    Optional page context.
	 * @return array<string, mixed>
	 */
	public function send( string $content, array $page = array() ): array {
		$content = trim( $content );
		if ( '' === $content ) {
			return $this->local_error( __( 'Send a question.', 'rankmyseo' ), 400 );
		}
		if ( ! $this->vault->is_connected() ) {
			return $this->local_error( __( 'Connect RankMySEO to ask about this site.', 'rankmyseo' ), 409 );
		}

		$body         = array( 'content' => substr( $content, 0, 4000 ) );
		$page_context = $this->page_context( $page );
		if ( array() !== $page_context ) {
			$body['pageContext'] = $page_context;
		}

		$result = $this->client->send_chat( $this->user_hash(), $body );
		if ( ! $result->attempted ) {
			return $this->local_error( $result->message, 409 );
		}
		return $this->from_result( $result );
	}

	/**
	 * Read a turn that was started with send().
	 *
	 * @param string $turn_id Turn id.
	 * @return array<string, mixed>
	 */
	public function turn( string $turn_id ): array {
		if ( ! $this->vault->is_connected() ) {
			return $this->local_error( __( 'Connect RankMySEO to ask about this site.', 'rankmyseo' ), 409 );
		}
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/i', $turn_id ) ) {
			return $this->local_error( __( 'That reply is not available.', 'rankmyseo' ), 400 );
		}
		$result = $this->client->chat_progress( $this->user_hash(), $turn_id );
		if ( ! $result->attempted ) {
			return $this->local_error( $result->message, 409 );
		}
		return $this->from_result( $result );
	}

	/**
	 * Read saved messages for this WordPress user.
	 *
	 * @return array<string, mixed>
	 */
	public function history(): array {
		if ( ! $this->vault->is_connected() ) {
			return $this->local_error( __( 'Connect RankMySEO to see earlier questions.', 'rankmyseo' ), 409 );
		}
		$result = $this->client->chat_thread( $this->user_hash() );
		if ( ! $result->attempted ) {
			return $this->local_error( $result->message, 409 );
		}
		if ( ! $result->ok ) {
			return $this->from_result( $result );
		}
		$messages = isset( $result->data['messages'] ) && is_array( $result->data['messages'] ) ? $result->data['messages'] : array();
		$actions  = array();
		foreach ( $messages as $message ) {
			if ( is_array( $message ) ) {
				$actions = array_merge( $actions, $this->absorb_message( $message ) );
			}
		}
		return array(
			'ok'        => true,
			'status'    => 200,
			'connected' => true,
			'thread_id' => isset( $result->data['threadId'] ) && is_string( $result->data['threadId'] ) ? $result->data['threadId'] : '',
			'messages'  => $this->present_messages( $messages ),
			'actions'   => $actions,
			'quota'     => isset( $result->data['quota'] ) && is_array( $result->data['quota'] ) ? $result->data['quota'] : null,
			'message'   => '',
		);
	}

	/**
	 * Map a cloud result into the local chat payload and save any action cards.
	 *
	 * @param CloudResult $result Cloud result.
	 * @return array<string, mixed>
	 */
	private function from_result( CloudResult $result ): array {
		if ( ! $result->ok ) {
			return array(
				'ok'        => false,
				'status'    => $result->status > 0 ? $result->status : 502,
				'connected' => true,
				'message'   => '' !== $result->message ? $result->message : __( 'RankMySEO could not answer just now.', 'rankmyseo' ),
				'actions'   => array(),
			);
		}

		$data    = $result->data;
		$actions = array();
		if ( isset( $data['assistantMessage'] ) && is_array( $data['assistantMessage'] ) ) {
			$actions = $this->absorb_message( $data['assistantMessage'] );
		}
		$state = isset( $data['status'] ) && is_string( $data['status'] ) ? $data['status'] : 'done';
		$reply = '';
		if ( isset( $data['reply'] ) && is_string( $data['reply'] ) ) {
			$reply = $data['reply'];
		} elseif ( isset( $data['assistantMessage']['content'] ) && is_string( $data['assistantMessage']['content'] ) ) {
			$reply = $data['assistantMessage']['content'];
		}

		return array(
			'ok'        => true,
			'status'    => $result->status > 0 ? $result->status : 200,
			'connected' => true,
			'state'     => $state,
			'turn_id'   => isset( $data['turnId'] ) && is_string( $data['turnId'] ) ? $data['turnId'] : '',
			'thread_id' => isset( $data['threadId'] ) && is_string( $data['threadId'] ) ? $data['threadId'] : '',
			'reply'     => $reply,
			'phase'     => isset( $data['phase'] ) && is_string( $data['phase'] ) ? $data['phase'] : '',
			'steps'     => isset( $data['steps'] ) && is_array( $data['steps'] ) ? $data['steps'] : array(),
			'actions'   => $actions,
			'quota'     => isset( $data['quota'] ) && is_array( $data['quota'] ) ? $data['quota'] : null,
			'message'   => isset( $data['message'] ) && is_string( $data['message'] ) ? sanitize_text_field( $data['message'] ) : '',
		);
	}

	/**
	 * Save wp_action cards from one message.
	 *
	 * @param array<string, mixed> $message Assistant or stored message.
	 * @return list<array<string, mixed>>
	 */
	private function absorb_message( array $message ): array {
		$attachments = isset( $message['attachments'] ) && is_array( $message['attachments'] ) ? $message['attachments'] : array();
		$saved       = array();
		foreach ( $attachments as $attachment ) {
			if ( ! is_array( $attachment ) || ( $attachment['type'] ?? '' ) !== 'wp_action' ) {
				continue;
			}
			$action = isset( $attachment['action'] ) && is_array( $attachment['action'] ) ? $attachment['action'] : $attachment;
			if ( ! isset( $action['actionType'] ) && isset( $attachment['actionType'] ) ) {
				$action = $attachment;
			}
			$id = $this->sync->ingest( $action );
			if ( $id <= 0 ) {
				continue;
			}
			$row = $this->actions->find( $id );
			if ( null !== $row ) {
				$saved[] = $this->actions->present( $row );
			}
		}
		return $saved;
	}

	/**
	 * Messages safe for the admin UI.
	 *
	 * @param array<mixed> $messages Raw messages.
	 * @return list<array<string, mixed>>
	 */
	private function present_messages( array $messages ): array {
		$clean = array();
		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}
			$role = isset( $message['role'] ) && is_string( $message['role'] ) ? $message['role'] : 'assistant';
			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
				$role = 'assistant';
			}
			$clean[] = array(
				'id'      => isset( $message['id'] ) && is_string( $message['id'] ) ? $message['id'] : '',
				'role'    => $role,
				'content' => isset( $message['content'] ) && is_string( $message['content'] ) ? $message['content'] : '',
			);
		}
		return $clean;
	}

	/**
	 * Page fields accepted by POST /wp/v1/chat/message.
	 *
	 * @param array<string, mixed> $page Raw page context.
	 * @return array<string, mixed>
	 */
	private function page_context( array $page ): array {
		$clean = array();
		if ( isset( $page['wpPostId'] ) && is_numeric( $page['wpPostId'] ) && (int) $page['wpPostId'] > 0 ) {
			$clean['wpPostId'] = (int) $page['wpPostId'];
		}
		foreach ( array( 'url', 'title', 'metaDescription', 'focusKeyword' ) as $key ) {
			if ( isset( $page[ $key ] ) && is_string( $page[ $key ] ) && '' !== $page[ $key ] ) {
				$clean[ $key ] = $page[ $key ];
			}
		}
		if ( isset( $page['seoScore'] ) && is_numeric( $page['seoScore'] ) ) {
			$clean['seoScore'] = max( 0, min( 100, (float) $page['seoScore'] ) );
		}
		return $clean;
	}

	/**
	 * Result that did not call RankMySEO.
	 *
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return array<string, mixed>
	 */
	private function local_error( string $message, int $status ): array {
		return array(
			'ok'        => false,
			'status'    => $status,
			'connected' => false,
			'message'   => $message,
			'actions'   => array(),
		);
	}
}
