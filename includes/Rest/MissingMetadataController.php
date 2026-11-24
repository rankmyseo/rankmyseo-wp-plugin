<?php
/**
 * REST API for the missing-metadata screen.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\AiActions\MissingMetadataQueue;
use RankMySEO\Modules\Cloud\TokenVault;
use RankMySEO\Modules\Meta\MissingMetadataQuery;
use RankMySEO\Modules\Meta\TemplateFill;
use RankMySEO\Modules\Meta\TitleSettings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Routes under rankmyseo/v1/missing-metadata.
 *
 * WordPress checks the REST nonce before these callbacks run. Each write also
 * requires edit_post on that page.
 */
final class MissingMetadataController extends AbstractController {
	/**
	 * Query.
	 *
	 * @var MissingMetadataQuery
	 */
	private MissingMetadataQuery $query;

	/**
	 * Local template fill.
	 *
	 * @var TemplateFill
	 */
	private TemplateFill $fill;

	/**
	 * Approval-queue writer.
	 *
	 * @var MissingMetadataQueue
	 */
	private MissingMetadataQueue $queue;

	/**
	 * Title settings.
	 *
	 * @var TitleSettings
	 */
	private TitleSettings $titles;

	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * Constructor.
	 *
	 * @param MissingMetadataQuery $query  Query.
	 * @param TemplateFill         $fill   Template fill.
	 * @param MissingMetadataQueue $queue  Queue.
	 * @param TitleSettings        $titles Title settings.
	 * @param TokenVault           $vault  Credential vault.
	 */
	public function __construct(
		MissingMetadataQuery $query,
		TemplateFill $fill,
		MissingMetadataQueue $queue,
		TitleSettings $titles,
		TokenVault $vault
	) {
		$this->query  = $query;
		$this->fill   = $fill;
		$this->queue  = $queue;
		$this->titles = $titles;
		$this->vault  = $vault;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$manage = [ $this, 'check_manage_permission' ];

		register_rest_route(
			self::REST_NAMESPACE,
			'/missing-metadata',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_items' ],
				'permission_callback' => $manage,
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/missing-metadata/preview',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => $manage,
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/missing-metadata/apply',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'apply_templates' ],
				'permission_callback' => $manage,
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/missing-metadata/queue',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'queue_suggestions' ],
				'permission_callback' => $manage,
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/missing-metadata/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'check_item_permission' ],
			]
		);
	}

	/**
	 * GET /rankmyseo/v1/missing-metadata.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$result                      = $this->query->search(
			[
				'missing'   => $this->text_param( $request, 'missing' ),
				'post_type' => $this->text_param( $request, 'post_type' ),
				'search'    => $this->text_param( $request, 'search' ),
				'page'      => (int) $request->get_param( 'page' ),
				'per_page'  => (int) $request->get_param( 'per_page' ),
			]
		);
		$result['connected']         = $this->vault->is_connected();
		$result['image_alt_pattern'] = $this->titles->image_alt_pattern();
		return $this->success_response( $result );
	}

	/**
	 * POST /rankmyseo/v1/missing-metadata/preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response {
		$body = $this->body( $request );
		return $this->success_response( $this->fill->preview( $this->ids( $body ), $this->fields( $body ), true ) );
	}

	/**
	 * POST /rankmyseo/v1/missing-metadata/apply.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function apply_templates( WP_REST_Request $request ) {
		$body = $this->body( $request );
		$ids  = $this->ids( $body );
		if ( [] === $ids ) {
			return $this->error_response( 'rankmyseo_missing_ids', __( 'Choose at least one page.', 'rankmyseo' ), 400 );
		}
		return $this->success_response( $this->fill->apply( $ids, $this->fields( $body ), true ) );
	}

	/**
	 * POST /rankmyseo/v1/missing-metadata/queue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function queue_suggestions( WP_REST_Request $request ) {
		$body   = $this->body( $request );
		$result = $this->queue->queue( $this->ids( $body ) );
		if ( empty( $result['ok'] ) ) {
			return $this->error_response(
				'rankmyseo_missing_queue',
				(string) ( $result['message'] ?? __( 'Those suggestions could not be queued.', 'rankmyseo' ) ),
				(int) ( $result['status'] ?? 400 )
			);
		}
		return $this->success_response( $result );
	}

	/**
	 * POST /rankmyseo/v1/missing-metadata/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$result = $this->fill->save_fields( (int) $request->get_param( 'id' ), $this->body( $request ) );
		if ( empty( $result['ok'] ) ) {
			return $this->error_response(
				'rankmyseo_missing_save',
				(string) ( $result['message'] ?? __( 'That page could not be saved.', 'rankmyseo' ) ),
				(int) ( $result['status'] ?? 400 )
			);
		}
		return $this->success_response( $result );
	}

	/**
	 * Permission callback for one row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function check_item_permission( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) && $this->check_manage_permission() === true ) {
			return true;
		}

		return $this->error_response(
			'rankmyseo_forbidden',
			__( 'You cannot edit that page.', 'rankmyseo' ),
			403
		);
	}

	/**
	 * String query parameter, or an empty string.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $key     Parameter name.
	 * @return string
	 */
	private function text_param( WP_REST_Request $request, string $key ): string {
		$value = $request->get_param( $key );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * JSON body, or an empty array.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function body( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		return is_array( $json ) ? $json : [];
	}

	/**
	 * Post IDs from the body.
	 *
	 * @param array<string, mixed> $body Body.
	 * @return list<int>
	 */
	private function ids( array $body ): array {
		$raw = isset( $body['ids'] ) && is_array( $body['ids'] ) ? $body['ids'] : [];
		$ids = [];
		foreach ( $raw as $id ) {
			$number = (int) $id;
			if ( $number > 0 ) {
				$ids[] = $number;
			}
		}
		return $ids;
	}

	/**
	 * Requested template fields.
	 *
	 * @param array<string, mixed> $body Body.
	 * @return list<string>
	 */
	private function fields( array $body ): array {
		$raw    = isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : TemplateFill::FIELDS;
		$fields = [];
		foreach ( $raw as $field ) {
			if ( is_string( $field ) ) {
				$fields[] = $field;
			}
		}
		return $fields;
	}
}
