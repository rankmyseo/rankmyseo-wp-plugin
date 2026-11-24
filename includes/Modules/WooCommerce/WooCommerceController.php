<?php
/**
 * REST API for WooCommerce store SEO settings.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

use RankMySEO\Core\Settings;
use RankMySEO\Rest\AbstractController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Reads and saves shop SEO choices under rankmyseo/v1/woocommerce/settings.
 */
final class WooCommerceController extends AbstractController {
	/**
	 * Store settings.
	 *
	 * @var WooSettings
	 */
	private WooSettings $store;

	/**
	 * Core settings, used for the module toggle.
	 *
	 * @var Settings
	 */
	private Settings $core;

	/**
	 * Constructor.
	 *
	 * @param WooSettings $store Store settings.
	 * @param Settings    $core  Core settings.
	 */
	public function __construct( WooSettings $store, Settings $core ) {
		$this->store = $store;
		$this->core  = $core;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/woocommerce/settings',
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
	}

	/**
	 * GET /rankmyseo/v1/woocommerce/settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		return $this->success_response( $this->payload( $this->store->all(), false ) );
	}

	/**
	 * POST /rankmyseo/v1/woocommerce/settings.
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

		$before = $this->store->all();
		$saved  = $this->store->update( $params );
		$this->maybe_flush( $before, $saved );

		return $this->success_response( $this->payload( $saved, true ) );
	}

	/**
	 * Response shared by read and write.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 * @param bool                 $updated  Whether this response follows a save.
	 * @return array<string, mixed>
	 */
	private function payload( array $settings, bool $updated ): array {
		return [
			'settings'          => $settings,
			'woocommerceActive' => WooCommerceModule::is_woocommerce_active(),
			'moduleEnabled'     => $this->core->is_module_enabled( 'woocommerce' ),
			'updated'           => $updated,
		];
	}

	/**
	 * Refresh rewrite rules when a permalink base toggle changes.
	 *
	 * @param array<string, mixed> $before Settings before the save.
	 * @param array<string, mixed> $after  Settings after the save.
	 * @return void
	 */
	private function maybe_flush( array $before, array $after ): void {
		$product  = (bool) $before['remove_product_base'] !== (bool) $after['remove_product_base'];
		$category = (bool) $before['remove_category_base'] !== (bool) $after['remove_category_base'];
		if ( ( $product || $category ) && function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}
}
