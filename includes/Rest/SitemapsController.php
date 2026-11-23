<?php
/**
 * REST API for sitemaps, IndexNow, and robots.txt.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Core\Settings;
use RankMySEO\Modules\Sitemaps\IndexNow;
use RankMySEO\Modules\Sitemaps\RobotsTxt;
use RankMySEO\Modules\Sitemaps\SitemapLinks;
use RankMySEO\Modules\Sitemaps\SitemapSettings;
use RankMySEO\Modules\Sitemaps\WordPressSitemapSource;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Routes under /rankmyseo/v1/sitemaps/*.
 */
final class SitemapsController extends AbstractController {
	/**
	 * Core plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Sitemap settings.
	 *
	 * @var SitemapSettings
	 */
	private SitemapSettings $sitemap_settings;

	/**
	 * IndexNow service.
	 *
	 * @var IndexNow
	 */
	private IndexNow $indexnow;

	/**
	 * Robots.txt service.
	 *
	 * @var RobotsTxt
	 */
	private RobotsTxt $robots;

	/**
	 * Content source used for the admin matrix.
	 *
	 * @var WordPressSitemapSource
	 */
	private WordPressSitemapSource $source;

	/**
	 * Constructor.
	 *
	 * @param Settings               $settings         Core settings.
	 * @param SitemapSettings        $sitemap_settings Sitemap settings.
	 * @param IndexNow               $indexnow         IndexNow service.
	 * @param RobotsTxt              $robots           Robots.txt service.
	 * @param WordPressSitemapSource $source           Sitemap content source.
	 */
	public function __construct(
		Settings $settings,
		SitemapSettings $sitemap_settings,
		IndexNow $indexnow,
		RobotsTxt $robots,
		WordPressSitemapSource $source
	) {
		$this->settings         = $settings;
		$this->sitemap_settings = $sitemap_settings;
		$this->indexnow         = $indexnow;
		$this->robots           = $robots;
		$this->source           = $source;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$read = [
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => [ $this, 'check_manage_permission' ],
		];
		$post = [
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => [ $this, 'check_manage_permission' ],
		];

		register_rest_route(
			self::REST_NAMESPACE,
			'/sitemaps/status',
			array_merge( $read, [ 'callback' => [ $this, 'get_status' ] ] )
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/sitemaps/settings',
			[
				array_merge( $read, [ 'callback' => [ $this, 'get_settings' ] ] ),
				array_merge( $post, [ 'callback' => [ $this, 'update_settings' ] ] ),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/sitemaps/flush',
			array_merge( $post, [ 'callback' => [ $this, 'flush_cache' ] ] )
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/sitemaps/indexnow/rotate',
			array_merge( $post, [ 'callback' => [ $this, 'rotate_key' ] ] )
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/sitemaps/indexnow/ping',
			array_merge( $post, [ 'callback' => [ $this, 'ping' ] ] )
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/sitemaps/robots',
			[
				array_merge( $read, [ 'callback' => [ $this, 'get_robots' ] ] ),
				array_merge( $post, [ 'callback' => [ $this, 'update_robots' ] ] ),
			]
		);
	}

	/**
	 * GET /rankmyseo/v1/sitemaps/status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status(): WP_REST_Response {
		return $this->success_response( $this->status_payload() );
	}

	/**
	 * GET /rankmyseo/v1/sitemaps/settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return $this->success_response(
			[
				'settings' => $this->settings_payload(),
			]
		);
	}

	/**
	 * POST /rankmyseo/v1/sitemaps/settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = $this->params( $request );
		$this->persist( $params );

		return $this->success_response(
			[
				'updated'  => true,
				'settings' => $this->settings_payload(),
				'status'   => $this->status_payload(),
			]
		);
	}

	/**
	 * POST /rankmyseo/v1/sitemaps/flush.
	 *
	 * @return WP_REST_Response
	 */
	public function flush_cache(): WP_REST_Response {
		$generation = $this->sitemap_settings->flush_cache();

		return $this->success_response(
			[
				'flushed'    => true,
				'generation' => $generation,
				'status'     => $this->status_payload(),
			]
		);
	}

	/**
	 * POST /rankmyseo/v1/sitemaps/indexnow/rotate.
	 *
	 * @return WP_REST_Response
	 */
	public function rotate_key(): WP_REST_Response {
		$key = $this->sitemap_settings->rotate_key();
		update_option( 'rankmyseo_flush_rewrite_rules', 1, false );

		return $this->success_response(
			[
				'key'     => $key,
				'key_url' => home_url( '/' . $key . '.txt' ),
				'status'  => $this->status_payload(),
			]
		);
	}

	/**
	 * POST /rankmyseo/v1/sitemaps/indexnow/ping.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function ping( WP_REST_Request $request ) {
		$params = $this->params( $request );
		$url    = isset( $params['url'] ) ? (string) $params['url'] : '';
		$result = $this->indexnow->ping_url( $url );

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			return $this->error_response( $result->get_error_code(), $result->get_error_message(), $status );
		}

		return $this->success_response(
			[
				'result' => $result,
				'log'    => $this->sitemap_settings->get_log(),
			]
		);
	}

	/**
	 * GET /rankmyseo/v1/sitemaps/robots.
	 *
	 * @return WP_REST_Response
	 */
	public function get_robots(): WP_REST_Response {
		return $this->success_response( $this->robots_payload() );
	}

	/**
	 * POST /rankmyseo/v1/sitemaps/robots.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_robots( WP_REST_Request $request ): WP_REST_Response {
		$this->persist( $this->params( $request ) );

		return $this->success_response(
			[
				'updated' => true,
				'robots'  => $this->robots_payload(),
				'status'  => $this->status_payload(),
			]
		);
	}

	/**
	 * Save settings and react to an IndexNow toggle.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @return void
	 */
	private function persist( array $params ): void {
		$was_enabled = $this->sitemap_settings->is_indexnow_enabled();
		$this->sitemap_settings->update( $params );
		$now_enabled = $this->sitemap_settings->is_indexnow_enabled();
		$this->indexnow->on_toggled( $was_enabled, $now_enabled );
		if ( $was_enabled !== $now_enabled ) {
			update_option( 'rankmyseo_flush_rewrite_rules', 1, false );
		}
		$this->sitemap_settings->flush_cache();
	}

	/**
	 * Read JSON or form params from the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function params( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( is_array( $json ) && [] !== $json ) {
			return $json;
		}

		$body = $request->get_body_params();
		return is_array( $body ) ? $body : [];
	}

	/**
	 * Admin status payload shared by the sitemap screen.
	 *
	 * @return array<string, mixed>
	 */
	private function status_payload(): array {
		$key = $this->sitemap_settings->ensure_key();

		return [
			'module_enabled'     => $this->settings->is_module_enabled( 'sitemaps' ),
			'blog_public'        => '0' !== (string) get_option( 'blog_public', '1' ),
			'sitemap_url'        => SitemapLinks::index_url(),
			'xsl_url'            => SitemapLinks::xsl_url(),
			'max_urls'           => $this->sitemap_settings->max_urls(),
			'include_images'     => $this->sitemap_settings->include_images(),
			'include_authors'    => $this->sitemap_settings->include_authors(),
			'allow_crawl'        => $this->sitemap_settings->allow_crawl(),
			'hide_utility_pages' => $this->sitemap_settings->hides_utility_pages(),
			'post_types'         => $this->post_type_rows(),
			'taxonomies'         => $this->taxonomy_rows(),
			'excluded_post_ids'  => $this->sitemap_settings->excluded_post_ids(),
			'excluded_term_ids'  => $this->sitemap_settings->excluded_term_ids(),
			'crawl'              => $this->sitemap_settings->crawl(),
			'indexnow'           => [
				'enabled'  => $this->sitemap_settings->is_indexnow_enabled(),
				'key'      => $key,
				'key_url'  => home_url( '/' . $key . '.txt' ),
				'endpoint' => SitemapSettings::INDEXNOW_ENDPOINT,
				'log'      => $this->sitemap_settings->get_log(),
			],
			'robots'             => $this->robots_payload(),
		];
	}

	/**
	 * Stored settings plus the custom robots body.
	 *
	 * @return array<string, mixed>
	 */
	private function settings_payload(): array {
		$settings                       = $this->sitemap_settings->all();
		$settings['robots_custom']      = $this->sitemap_settings->robots_custom();
		$settings['indexnow_key']       = $this->sitemap_settings->ensure_key();
		$settings['hide_utility_pages'] = $this->sitemap_settings->hides_utility_pages();
		return $settings;
	}

	/**
	 * Robots preview, physical-file check, and warnings.
	 *
	 * @return array<string, mixed>
	 */
	private function robots_payload(): array {
		$warnings = $this->robots->warnings();
		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			array_unshift(
				$warnings,
				[
					'code'    => 'blog_public',
					'message' => __( 'WordPress is set to discourage search engines. Crawlers are asked not to index this site until you allow them under Settings → Reading.', 'rankmyseo' ),
				]
			);
		}

		return [
			'mode'          => $this->sitemap_settings->robots_mode(),
			'preset'        => $this->sitemap_settings->robots_preset(),
			'custom'        => $this->sitemap_settings->robots_custom(),
			'preview'       => $this->robots->render(),
			'physical_file' => $this->robots->has_physical_file(),
			'physical_path' => $this->robots->physical_path(),
			'warnings'      => $warnings,
		];
	}

	/**
	 * Post type rows for the admin matrix, always including posts, pages, and products.
	 *
	 * @return list<array{name: string, label: string, included: bool, available: bool}>
	 */
	private function post_type_rows(): array {
		$labels = $this->source->public_post_type_labels();
		if ( ! isset( $labels['post'] ) ) {
			$labels = [ 'post' => __( 'Posts', 'rankmyseo' ) ] + $labels;
		}
		if ( ! isset( $labels['page'] ) ) {
			$labels['page'] = __( 'Pages', 'rankmyseo' );
		}

		$rows = [];
		foreach ( $labels as $name => $label ) {
			$rows[] = [
				'name'      => $name,
				'label'     => $label,
				'included'  => $this->sitemap_settings->is_post_type_included( $name ),
				'available' => $this->source->has_post_type( $name ),
			];
		}

		if ( ! isset( $labels['product'] ) ) {
			$rows[] = [
				'name'      => 'product',
				'label'     => __( 'Products', 'rankmyseo' ),
				'included'  => $this->sitemap_settings->is_post_type_included( 'product' ),
				'available' => false,
			];
		}

		return $rows;
	}

	/**
	 * Taxonomy rows for the admin matrix.
	 *
	 * @return list<array{name: string, label: string, included: bool, available: bool}>
	 */
	private function taxonomy_rows(): array {
		$rows = [];
		foreach ( $this->source->public_taxonomy_labels() as $name => $label ) {
			$rows[] = [
				'name'      => $name,
				'label'     => $label,
				'included'  => $this->sitemap_settings->is_taxonomy_included( $name ),
				'available' => true,
			];
		}

		return $rows;
	}
}
