<?php
/**
 * REST routes for the local link index.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\Links\LinkIndexer;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Orphan report, link suggestions, and chunked reindex. All local.
 */
final class LinksController extends AbstractController {
	/**
	 * Link index.
	 *
	 * @var LinkIndexer
	 */
	private LinkIndexer $indexer;

	/**
	 * Constructor.
	 *
	 * @param LinkIndexer $indexer Link index.
	 */
	public function __construct( LinkIndexer $indexer ) {
		$this->indexer = $indexer;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/links/orphans',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_orphans' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/links/suggestions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_suggestions' ],
					'permission_callback' => [ $this, 'check_edit_posts_permission' ],
					'args'                => [
						'post_id'   => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'focus'     => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'title'     => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'slug'      => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						],
						'secondary' => [
							'type' => 'string',
						],
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/links/reindex',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'post_reindex' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);
	}

	/**
	 * Handle GET /rankmyseo/v1/links/orphans.
	 *
	 * @return WP_REST_Response
	 */
	public function get_orphans(): WP_REST_Response {
		return $this->success_response(
			[
				'count' => $this->indexer->orphan_count(),
				'items' => $this->indexer->list_orphans( 20 ),
			]
		);
	}

	/**
	 * Handle GET /rankmyseo/v1/links/suggestions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_suggestions( WP_REST_Request $request ): WP_REST_Response {
		$secondary = $request->get_param( 'secondary' );
		$list      = [];
		if ( is_string( $secondary ) && '' !== $secondary ) {
			$parts = preg_split( '/\s*,\s*/', $secondary );
			if ( is_array( $parts ) ) {
				foreach ( $parts as $part ) {
					$clean = sanitize_text_field( (string) $part );
					if ( '' !== $clean ) {
						$list[] = $clean;
					}
				}
			}
		}
		return $this->success_response(
			[
				'suggestions' => $this->indexer->suggestions(
					[
						'post_id'   => (int) $request->get_param( 'post_id' ),
						'focus'     => (string) $request->get_param( 'focus' ),
						'title'     => (string) $request->get_param( 'title' ),
						'slug'      => (string) $request->get_param( 'slug' ),
						'secondary' => array_slice( $list, 0, 5 ),
					]
				),
			]
		);
	}

	/**
	 * Handle POST /rankmyseo/v1/links/reindex.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function post_reindex( WP_REST_Request $request ): WP_REST_Response {
		$offset = absint( $request->get_param( 'offset' ) );
		$limit  = absint( $request->get_param( 'limit' ) );
		return $this->success_response( $this->indexer->reindex( $offset, $limit > 0 ? $limit : LinkIndexer::BATCH_DEFAULT ) );
	}
}
