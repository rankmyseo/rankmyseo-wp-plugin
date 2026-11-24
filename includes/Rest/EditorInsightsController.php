<?php
/**
 * REST API for cached insights inside the block editor.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Modules\Cloud\EditorInsightReader;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Route rankmyseo/v1/editor/insights.
 *
 * The response is read from the local cache. It never includes the site token.
 */
final class EditorInsightsController extends AbstractController {
	/**
	 * Matcher.
	 *
	 * @var EditorInsightReader
	 */
	private EditorInsightReader $reader;

	/**
	 * Constructor.
	 *
	 * @param EditorInsightReader $reader Matcher.
	 */
	public function __construct( EditorInsightReader $reader ) {
		$this->reader = $reader;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/editor/insights',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_insights' ],
				'permission_callback' => [ $this, 'check_editor_permission' ],
			]
		);
	}

	/**
	 * GET /rankmyseo/v1/editor/insights.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_insights( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$url     = $request->get_param( 'url' );
		$url     = is_string( $url ) ? $url : '';
		return $this->success_response( $this->reader->for_post( $post_id, $url ) );
	}

	/**
	 * Editors can read insights for a page they can edit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function check_editor_permission( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}
		if ( $post_id <= 0 && ( current_user_can( 'edit_posts' ) || $this->check_manage_permission() === true ) ) {
			return true;
		}

		return $this->error_response(
			'rankmyseo_forbidden',
			__( 'You cannot view search details for that page.', 'rankmyseo' ),
			403
		);
	}
}
