<?php
/**
 * REST controller for the offline SEO chat inspector.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\Chat\LocalAssistant;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes POST /rankmyseo/v1/chat/local.
 *
 * The connected Operator proxy and inline action approvals are intentionally
 * not implemented here. Responses include an empty action_cards list and a
 * disabled operator_slot so a later PR can fill them through the
 * rankmyseo_chat_local_response filter.
 */
final class ChatController extends AbstractController {
	/**
	 * Offline assistant.
	 *
	 * @var LocalAssistant
	 */
	private LocalAssistant $assistant;

	/**
	 * Constructor.
	 *
	 * @param LocalAssistant $assistant Offline assistant.
	 */
	public function __construct( LocalAssistant $assistant ) {
		$this->assistant = $assistant;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/chat/local',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'local_reply' ],
					'permission_callback' => [ $this, 'check_edit_posts_permission' ],
				],
			]
		);
	}

	/**
	 * Handle POST /rankmyseo/v1/chat/local.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function local_reply( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) || empty( $payload ) ) {
			$payload = $request->get_body_params();
		}
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		$message = isset( $payload['message'] ) ? sanitize_textarea_field( (string) $payload['message'] ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$message = mb_substr( $message, 0, 2000 );
		} else {
			$message = substr( $message, 0, 2000 );
		}
		$message = trim( $message );

		if ( '' === $message ) {
			return $this->error_response(
				'rankmyseo_invalid_message',
				__( 'Type a question to check your site.', 'rankmyseo' ),
				400
			);
		}

		$post_id = isset( $payload['post_id'] ) ? absint( $payload['post_id'] ) : 0;
		$screen  = isset( $payload['screen'] ) ? sanitize_key( (string) $payload['screen'] ) : '';

		$response = $this->assistant->respond(
			$message,
			[
				'post_id' => $post_id,
				'screen'  => $screen,
			]
		);

		/**
		 * Filter a local chat reply before it is returned.
		 *
		 * Later PRs may attach operator metadata. They must not add outbound
		 * requests to the offline path.
		 *
		 * @param array<string, mixed> $response Local reply.
		 * @param string               $message  Sanitized question.
		 */
		$filtered = apply_filters( 'rankmyseo_chat_local_response', $response, $message );

		if ( ! is_array( $filtered ) ) {
			$filtered = $response;
		}

		return $this->success_response( $filtered );
	}
}
