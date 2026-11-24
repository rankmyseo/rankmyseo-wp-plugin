<?php
/**
 * REST endpoints for schema settings, preview, and JSON validation.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

use RankMySEO\Core\Settings;
use RankMySEO\Rest\AbstractController;
use WP_Error;
use WP_Post;
use WP_Post_Type;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes schema settings and a live @graph preview under rankmyseo/v1/schema.
 */
final class SchemaController extends AbstractController {
	/**
	 * Schema settings.
	 *
	 * @var SchemaSettings
	 */
	private SchemaSettings $schema_settings;

	/**
	 * Core settings.
	 *
	 * @var Settings
	 */
	private Settings $core_settings;

	/**
	 * Context factory.
	 *
	 * @var ContextFactory
	 */
	private ContextFactory $contexts;

	/**
	 * Graph builder.
	 *
	 * @var GraphBuilder
	 */
	private GraphBuilder $graph;

	/**
	 * Conflict scanner.
	 *
	 * @var ConflictScanner
	 */
	private ConflictScanner $conflicts;

	/**
	 * Constructor.
	 *
	 * @param SchemaSettings  $schema_settings Schema settings.
	 * @param Settings        $core_settings   Core settings.
	 * @param ContextFactory  $contexts        Context factory.
	 * @param GraphBuilder    $graph           Graph builder.
	 * @param ConflictScanner $conflicts       Conflict scanner.
	 */
	public function __construct(
		SchemaSettings $schema_settings,
		Settings $core_settings,
		ContextFactory $contexts,
		GraphBuilder $graph,
		ConflictScanner $conflicts
	) {
		$this->schema_settings = $schema_settings;
		$this->core_settings   = $core_settings;
		$this->contexts        = $contexts;
		$this->graph           = $graph;
		$this->conflicts       = $conflicts;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/schema/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/schema/preview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_preview' ],
				'permission_callback' => [ $this, 'check_manage_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'default'           => 0,
						'required'          => false,
						'sanitize_callback' => static function ( $value ): int {
							return max( 0, (int) $value );
						},
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/schema/validate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'validate_json' ],
				'permission_callback' => [ $this, 'check_manage_permission' ],
			]
		);
	}

	/**
	 * GET /rankmyseo/v1/schema/settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return $this->success_response( $this->settings_payload( $this->presented_settings() ) );
	}

	/**
	 * POST /rankmyseo/v1/schema/settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || [] === $params ) {
			$params = $request->get_body_params();
		}
		if ( ! is_array( $params ) ) {
			$params = [];
		}

		$this->schema_settings->update( $params );

		return $this->success_response( $this->settings_payload( $this->presented_settings(), true ) );
	}

	/**
	 * GET /rankmyseo/v1/schema/preview.
	 *
	 * `post_id` 0 (or omitted) previews the homepage graph.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_preview( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		if ( $post_id < 0 ) {
			$post_id = 0;
		}

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				return $this->error_response(
					'rankmyseo_schema_not_found',
					__( 'That page could not be found, so there is no JSON-LD preview.', 'rankmyseo' ),
					404
				);
			}
			$context = $this->contexts->for_post( $post_id );
		} else {
			$context = $this->contexts->for_homepage();
		}

		return $this->success_response(
			[
				'postId'    => $post_id,
				'url'       => $context->permalink,
				'enabled'   => $this->core_settings->is_module_enabled( 'schema' ),
				'graph'     => $this->graph->build( $context ),
				'conflicts' => $this->conflicts->scan( $this->schema_settings ),
			]
		);
	}

	/**
	 * POST /rankmyseo/v1/schema/validate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function validate_json( WP_REST_Request $request ): WP_REST_Response {
		$json   = $request->get_param( 'json' );
		$result = SchemaMeta::validate_custom_json( $json );

		if ( $result instanceof WP_Error ) {
			return $this->success_response(
				[
					'valid'   => false,
					'message' => $result->get_error_message(),
				]
			);
		}

		if ( '' === $result ) {
			return $this->success_response(
				[
					'valid'   => false,
					'message' => __( 'Enter a JSON object or array to check.', 'rankmyseo' ),
				]
			);
		}

		$decoded = json_decode( $result, true );

		return $this->success_response(
			[
				'valid'      => true,
				'message'    => __( 'This JSON-LD is valid and safe to store on a page.', 'rankmyseo' ),
				'normalized' => is_array( $decoded ) ? $decoded : [],
			]
		);
	}

	/**
	 * Settings as shown in the admin, with core identity fallbacks.
	 *
	 * @return array<string, mixed>
	 */
	private function presented_settings(): array {
		$name = sanitize_text_field( (string) $this->core_settings->get( 'site_name', '' ) );
		if ( '' === $name ) {
			$name = sanitize_text_field( (string) get_bloginfo( 'name' ) );
		}
		$type = sanitize_key( (string) $this->core_settings->get( 'site_type', 'organization' ) );

		return $this->schema_settings->for_admin( $name, $type );
	}

	/**
	 * Envelope returned by the settings routes.
	 *
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @param bool                 $updated  Whether this response follows a save.
	 * @return array<string, mixed>
	 */
	private function settings_payload( array $settings, bool $updated = false ): array {
		return [
			'settings'           => $settings,
			'postTypes'          => $this->public_post_types(),
			'schemaTypes'        => $this->choices( $this->schema_type_labels() ),
			'localBusinessTypes' => $this->choices( $this->local_business_labels() ),
			'woocommerceActive'  => class_exists( 'WooCommerce' ),
			'moduleEnabled'      => $this->core_settings->is_module_enabled( 'schema' ),
			'updated'            => $updated,
			'filters'            => [
				'rankmyseo_schema_graph',
				'rankmyseo_schema_piece_organization',
				'rankmyseo_schema_piece_person',
				'rankmyseo_schema_piece_website',
				'rankmyseo_schema_piece_webpage',
				'rankmyseo_schema_piece_breadcrumb',
				'rankmyseo_schema_piece_article',
				'rankmyseo_schema_piece_product',
				'rankmyseo_schema_piece_faq',
				'rankmyseo_schema_piece_localbusiness',
			],
		];
	}

	/**
	 * Public post types for the mapping table.
	 *
	 * @return list<array{name: string, label: string}>
	 */
	private function public_post_types(): array {
		if ( ! function_exists( 'get_post_types' ) ) {
			return [
				[
					'name'  => 'post',
					'label' => __( 'Post', 'rankmyseo' ),
				],
				[
					'name'  => 'page',
					'label' => __( 'Page', 'rankmyseo' ),
				],
			];
		}

		$objects = get_post_types( [ 'public' => true ], 'objects' );
		$rows    = [];
		if ( is_array( $objects ) ) {
			foreach ( $objects as $object ) {
				if ( ! $object instanceof WP_Post_Type || 'attachment' === $object->name ) {
					continue;
				}
				$label  = $object->labels->singular_name ?? $object->label;
				$rows[] = [
					'name'  => (string) $object->name,
					'label' => is_string( $label ) && '' !== $label ? $label : (string) $object->name,
				];
			}
		}

		return [] === $rows
			? [
				[
					'name'  => 'post',
					'label' => __( 'Post', 'rankmyseo' ),
				],
				[
					'name'  => 'page',
					'label' => __( 'Page', 'rankmyseo' ),
				],
			]
			: $rows;
	}

	/**
	 * Convert a label map into value/label rows.
	 *
	 * @param array<string, string> $labels Labels keyed by stored value.
	 * @return list<array{value: string, label: string}>
	 */
	private function choices( array $labels ): array {
		$rows = [];
		foreach ( $labels as $value => $label ) {
			$rows[] = [
				'value' => $value,
				'label' => $label,
			];
		}

		return $rows;
	}

	/**
	 * Labels for content schema types.
	 *
	 * @return array<string, string>
	 */
	private function schema_type_labels(): array {
		return [
			'BlogPosting'       => __( 'Blog post', 'rankmyseo' ),
			'Article'           => __( 'Article', 'rankmyseo' ),
			'NewsArticle'       => __( 'News article', 'rankmyseo' ),
			'WebPage'           => __( 'Web page', 'rankmyseo' ),
			'AboutPage'         => __( 'About page', 'rankmyseo' ),
			'ContactPage'       => __( 'Contact page', 'rankmyseo' ),
			'CollectionPage'    => __( 'Collection page', 'rankmyseo' ),
			'ProfilePage'       => __( 'Profile page', 'rankmyseo' ),
			'SearchResultsPage' => __( 'Search results page', 'rankmyseo' ),
			'Product'           => __( 'Product', 'rankmyseo' ),
			'LocalBusiness'     => __( 'Local business', 'rankmyseo' ),
			'FAQPage'           => __( 'FAQ page', 'rankmyseo' ),
			'HowTo'             => __( 'How-to', 'rankmyseo' ),
			'none'              => __( 'None', 'rankmyseo' ),
		];
	}

	/**
	 * Labels for LocalBusiness subtypes.
	 *
	 * @return array<string, string>
	 */
	private function local_business_labels(): array {
		return [
			'LocalBusiness'               => __( 'Local business', 'rankmyseo' ),
			'Dentist'                     => __( 'Dentist', 'rankmyseo' ),
			'Restaurant'                  => __( 'Restaurant', 'rankmyseo' ),
			'Store'                       => __( 'Store', 'rankmyseo' ),
			'MedicalBusiness'             => __( 'Medical business', 'rankmyseo' ),
			'LegalService'                => __( 'Legal service', 'rankmyseo' ),
			'ProfessionalService'         => __( 'Professional service', 'rankmyseo' ),
			'HealthAndBeautyBusiness'     => __( 'Health and beauty', 'rankmyseo' ),
			'HomeAndConstructionBusiness' => __( 'Home and construction', 'rankmyseo' ),
			'AutomotiveBusiness'          => __( 'Automotive business', 'rankmyseo' ),
			'FoodEstablishment'           => __( 'Food establishment', 'rankmyseo' ),
			'LodgingBusiness'             => __( 'Lodging', 'rankmyseo' ),
		];
	}
}
