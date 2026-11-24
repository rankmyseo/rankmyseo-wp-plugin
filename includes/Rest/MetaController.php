<?php
/**
 * REST helpers for title templates, previews, and per-object SEO meta.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Core\Settings;
use RankMySEO\Modules\Analyzer\AnalyzerMeta;
use RankMySEO\Modules\Meta\ConflictGuard;
use RankMySEO\Modules\Schema\SchemaMeta;
use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\DocumentBuilder;
use RankMySEO\Modules\Meta\MetaStore;
use RankMySEO\Modules\Meta\TemplateDefaults;
use RankMySEO\Modules\Meta\TitleSettings;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Routes under rankmyseo/v1/meta.
 */
final class MetaController extends AbstractController {
	/**
	 * Core settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Title templates.
	 *
	 * @var TitleSettings
	 */
	private TitleSettings $titles;

	/**
	 * Object meta store.
	 *
	 * @var MetaStore
	 */
	private MetaStore $store;

	/**
	 * Conflict guard.
	 *
	 * @var ConflictGuard
	 */
	private ConflictGuard $conflicts;

	/**
	 * Document builder.
	 *
	 * @var DocumentBuilder
	 */
	private DocumentBuilder $builder;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings  Core settings.
	 * @param TitleSettings   $titles    Title templates.
	 * @param MetaStore       $store     Meta store.
	 * @param ConflictGuard   $conflicts Conflict guard.
	 * @param DocumentBuilder $builder   Preview builder.
	 */
	public function __construct(
		Settings $settings,
		TitleSettings $titles,
		MetaStore $store,
		ConflictGuard $conflicts,
		DocumentBuilder $builder
	) {
		$this->settings  = $settings;
		$this->titles    = $titles;
		$this->store     = $store;
		$this->conflicts = $conflicts;
		$this->builder   = $builder;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/meta/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/meta/defaults',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_defaults' ],
				'permission_callback' => [ $this, 'check_edit_posts_permission' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/meta/preview',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'check_edit_posts_permission' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/meta/posts/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_post_meta' ],
					'permission_callback' => [ $this, 'check_post_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_post_meta' ],
					'permission_callback' => [ $this, 'check_post_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/meta/terms/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_term_meta' ],
					'permission_callback' => [ $this, 'check_term_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_term_meta' ],
					'permission_callback' => [ $this, 'check_term_permission' ],
				],
			]
		);
	}

	/**
	 * GET /rankmyseo/v1/meta/settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return $this->success_response( $this->settings_payload() );
	}

	/**
	 * POST /rankmyseo/v1/meta/settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$this->titles->update( $this->payload( $request ) );
		return $this->success_response(
			array_merge( $this->settings_payload(), [ 'updated' => true ] )
		);
	}

	/**
	 * GET /rankmyseo/v1/meta/defaults.
	 *
	 * @return WP_REST_Response
	 */
	public function get_defaults(): WP_REST_Response {
		return $this->success_response(
			[
				'variables'  => TemplateDefaults::variable_catalog(),
				'blueprint'  => TemplateDefaults::blueprint(),
				'separators' => Settings::ALLOWED_SEPARATORS,
			]
		);
	}

	/**
	 * POST /rankmyseo/v1/meta/preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response {
		$body = $this->payload( $request );
		$vars = isset( $body['variables'] ) && is_array( $body['variables'] ) ? $body['variables'] : [];
		$vars = array_merge(
			[
				'sep'      => $this->titles->separator(),
				'sitename' => $this->titles->site_name(),
				'tagline'  => $this->titles->tagline(),
			],
			$vars
		);

		return $this->success_response(
			$this->builder->preview(
				isset( $body['title'] ) ? (string) $body['title'] : '',
				isset( $body['description'] ) ? (string) $body['description'] : '',
				$vars
			)
		);
	}

	/**
	 * GET /rankmyseo/v1/meta/posts/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_post_meta( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return $this->error_response( 'rankmyseo_not_found', __( 'That page could not be found.', 'rankmyseo' ), 404 );
		}

		return $this->success_response(
			[
				'id'   => $post_id,
				'meta' => $this->with_schema_type( $post_id, $this->store->read_post( $post_id ) ),
			]
		);
	}

	/**
	 * POST /rankmyseo/v1/meta/posts/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_post_meta( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return $this->error_response( 'rankmyseo_not_found', __( 'That page could not be found.', 'rankmyseo' ), 404 );
		}

		$payload = $this->payload( $request );
		$meta    = $this->store->write_post( $post_id, $payload );
		$this->persist_analyzer_meta( $post_id, $payload );
		$this->persist_schema_type( $post_id, $payload );

		return $this->success_response(
			[
				'id'      => $post_id,
				'updated' => true,
				'meta'    => $this->with_schema_type( $post_id, $meta ),
			]
		);
	}

	/**
	 * GET /rankmyseo/v1/meta/terms/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_term_meta( WP_REST_Request $request ) {
		$term_id = (int) $request->get_param( 'id' );
		$term    = get_term( $term_id );

		if ( ! $term instanceof \WP_Term ) {
			return $this->error_response( 'rankmyseo_not_found', __( 'That archive could not be found.', 'rankmyseo' ), 404 );
		}

		return $this->success_response(
			[
				'id'   => $term_id,
				'meta' => $this->store->read_term( $term_id ),
			]
		);
	}

	/**
	 * POST /rankmyseo/v1/meta/terms/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_term_meta( WP_REST_Request $request ) {
		$term_id = (int) $request->get_param( 'id' );
		$term    = get_term( $term_id );

		if ( ! $term instanceof \WP_Term ) {
			return $this->error_response( 'rankmyseo_not_found', __( 'That archive could not be found.', 'rankmyseo' ), 404 );
		}

		$meta = $this->store->write_term( $term_id, $this->payload( $request ) );

		return $this->success_response(
			[
				'id'      => $term_id,
				'updated' => true,
				'meta'    => $meta,
			]
		);
	}

	/**
	 * Permission callback for a single post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|\WP_Error
	 */
	public function check_post_permission( WP_REST_Request $request ) {
		$nonce = $this->check_rest_nonce( $request );
		if ( true !== $nonce ) {
			return $nonce;
		}

		$post_id = (int) $request->get_param( 'id' );
		if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}

		return $this->error_response(
			'rankmyseo_forbidden',
			__( 'You do not have permission to edit SEO fields for this page.', 'rankmyseo' ),
			403
		);
	}

	/**
	 * Permission callback for a single term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|\WP_Error
	 */
	public function check_term_permission( WP_REST_Request $request ) {
		$nonce = $this->check_rest_nonce( $request );
		if ( true !== $nonce ) {
			return $nonce;
		}

		$term_id = (int) $request->get_param( 'id' );
		if ( $term_id > 0 && current_user_can( 'edit_term', $term_id ) ) {
			return true;
		}

		return $this->error_response(
			'rankmyseo_forbidden',
			__( 'You do not have permission to edit SEO fields for this archive.', 'rankmyseo' ),
			403
		);
	}

	/**
	 * Require the REST nonce used by the block editor and the builder panels.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|\WP_Error
	 */
	private function check_rest_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$param = $request->get_param( '_wpnonce' );
			$nonce = is_string( $param ) ? $param : '';
		}

		$verified = '' !== $nonce ? wp_verify_nonce( $nonce, 'wp_rest' ) : false;
		if ( false !== $verified && 0 !== $verified ) {
			return true;
		}

		return $this->error_response(
			'rankmyseo_invalid_nonce',
			__( 'The security check for this SEO save failed. Reload the editor and try again.', 'rankmyseo' ),
			403
		);
	}

	/**
	 * Store the on-page score when a builder panel sends one.
	 *
	 * Missing keys are left untouched so a title save does not wipe the score.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Request payload.
	 * @return void
	 */
	private function persist_analyzer_meta( int $post_id, array $payload ): void {
		if ( array_key_exists( 'seo_score', $payload ) || array_key_exists( MetaKeys::SEO_SCORE, $payload ) ) {
			$raw = array_key_exists( 'seo_score', $payload ) ? $payload['seo_score'] : $payload[ MetaKeys::SEO_SCORE ];
			update_post_meta( $post_id, MetaKeys::SEO_SCORE, AnalyzerMeta::sanitize_score( $raw ) );
		}

		if ( ! array_key_exists( 'seo_summary', $payload ) && ! array_key_exists( AnalyzerMeta::SUMMARY, $payload ) ) {
			return;
		}

		$summary = array_key_exists( 'seo_summary', $payload ) ? $payload['seo_summary'] : $payload[ AnalyzerMeta::SUMMARY ];
		$clean   = AnalyzerMeta::sanitize_summary( $summary );
		if ( '' === $clean ) {
			delete_post_meta( $post_id, AnalyzerMeta::SUMMARY );
			return;
		}

		update_post_meta( $post_id, AnalyzerMeta::SUMMARY, $clean );
	}

	/**
	 * Attach the per-post schema override to a meta bundle.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $meta    Titles meta.
	 * @return array<string, mixed>
	 */
	private function with_schema_type( int $post_id, array $meta ): array {
		$meta['schema_type'] = SchemaMeta::read_type( $post_id );
		return $meta;
	}

	/**
	 * Store a schema type override when the editor sent one.
	 *
	 * Missing keys are left untouched so a title-only save does not clear the override.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Request payload.
	 * @return void
	 */
	private function persist_schema_type( int $post_id, array $payload ): void {
		if ( array_key_exists( 'schema_type', $payload ) ) {
			SchemaMeta::write_type( $post_id, $payload['schema_type'] );
			return;
		}
		if ( array_key_exists( 'schemaType', $payload ) ) {
			SchemaMeta::write_type( $post_id, $payload['schemaType'] );
			return;
		}
		if ( array_key_exists( SchemaMeta::META_TYPE, $payload ) ) {
			SchemaMeta::write_type( $post_id, $payload[ SchemaMeta::META_TYPE ] );
		}
	}

	/**
	 * Settings payload.
	 *
	 * @return array<string, mixed>
	 */
	private function settings_payload(): array {
		return [
			'settings'    => $this->titles->for_editor(),
			'variables'   => TemplateDefaults::variable_catalog(),
			'separators'  => Settings::ALLOWED_SEPARATORS,
			'conflict'    => $this->conflicts->has_conflict(),
			'head_paused' => $this->conflicts->should_pause_head(),
			'site_name'   => (string) $this->settings->get( 'site_name', '' ),
		];
	}

	/**
	 * Payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return array<string, mixed>
	 */
	private function payload( WP_REST_Request $request ): array {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) || [] === $payload ) {
			$payload = $request->get_body_params();
		}

		return is_array( $payload ) ? $payload : [];
	}
}
