<?php
/**
 * REST lookup for posts that share a focus keyword.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

use RankMySEO\Core\Capabilities;
use RankMySEO\Rest\AbstractController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /rankmyseo/v1/analyzer/cannibalization
 */
final class CannibalizationController extends AbstractController {
	/**
	 * Query.
	 *
	 * @var CannibalizationQuery
	 */
	private CannibalizationQuery $query;

	/**
	 * Constructor.
	 *
	 * @param CannibalizationQuery|null $query Optional query.
	 */
	public function __construct( ?CannibalizationQuery $query = null ) {
		$this->query = $query ?? new CannibalizationQuery();
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/analyzer/cannibalization',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_matches' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'keyword'         => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'exclude_post_id' => [
						'type'              => 'integer',
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Editors may check keyword collisions.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( current_user_can( 'edit_posts' ) || Capabilities::current_user_can_manage() ) {
			return true;
		}

		return new WP_Error(
			'rankmyseo_forbidden',
			__( 'You do not have permission to check focus keywords.', 'rankmyseo' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Return published posts that already use this focus keyword.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_matches( WP_REST_Request $request ): WP_REST_Response {
		$keyword = sanitize_text_field( (string) $request->get_param( 'keyword' ) );
		$exclude = absint( $request->get_param( 'exclude_post_id' ) );
		$matches = $this->query->find( $keyword, $exclude );

		return $this->success_response(
			[
				'keyword' => $keyword,
				'count'   => count( $matches ),
				'matches' => $matches,
			]
		);
	}
}
