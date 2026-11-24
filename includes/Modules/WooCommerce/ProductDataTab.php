<?php
/**
 * WooCommerce product data tab for RankMySEO fields.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

use WP_Post;

/**
 * Adds the SEO tab on simple and variable products, including per-variation identifiers.
 */
final class ProductDataTab {
	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION = 'rankmyseo_product_seo';

	/**
	 * Nonce field name.
	 */
	public const NONCE_FIELD = 'rankmyseo_product_seo_nonce';

	/**
	 * Optional request override used by tests.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $request;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed>|null $request Optional request body. Null reads the current POST body.
	 */
	public function __construct( ?array $request = null ) {
		$this->request = $request;
	}

	/**
	 * Register WooCommerce admin hooks. Safe to call only when WooCommerce is active.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_panel' ] );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_product' ] );
		add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'render_variation' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation' ], 10, 2 );
	}

	/**
	 * Add the SEO tab beside the other product data tabs.
	 *
	 * @param array<string, mixed> $tabs Existing tabs.
	 * @return array<string, mixed>
	 */
	public function add_tab( array $tabs ): array {
		$tabs['rankmyseo'] = [
			'label'    => __( 'SEO', 'rankmyseo' ),
			'target'   => 'rankmyseo_product_data',
			'class'    => [ 'show_if_simple', 'show_if_variable' ],
			'priority' => 80,
		];

		return $tabs;
	}

	/**
	 * Print the product SEO panel.
	 *
	 * @return void
	 */
	public function render_panel(): void {
		$post_id = $this->current_post_id();
		$brand   = $this->meta_string( $post_id, ProductMeta::BRAND );
		$gtin    = $this->meta_string( $post_id, ProductMeta::GTIN );
		$mpn     = $this->meta_string( $post_id, ProductMeta::MPN );
		$return  = get_post_meta( $post_id, ProductMeta::RETURN_POLICY, true );
		$ship    = get_post_meta( $post_id, ProductMeta::SHIPPING, true );
		$return  = is_array( $return ) ? $return : [];
		$ship    = is_array( $ship ) ? $ship : [];

		echo '<div id="rankmyseo_product_data" class="panel woocommerce_options_panel hidden">';
		echo '<div class="options_group">';
		$this->nonce_field();
		echo '<p class="form-field"><span class="description">' . esc_html__( 'These details stay on this site and help search engines understand the product.', 'rankmyseo' ) . '</span></p>';
		$this->text_row( 'rankmyseo_brand', __( 'Brand', 'rankmyseo' ), $brand, __( 'The maker or brand name shown with this product.', 'rankmyseo' ) );
		$this->text_row( 'rankmyseo_gtin', __( 'Barcode (GTIN, EAN, UPC, or ISBN)', 'rankmyseo' ), $gtin, __( 'Digits only. An ISBN-10 may end in X.', 'rankmyseo' ) );
		$this->text_row( 'rankmyseo_mpn', __( 'Manufacturer number (MPN)', 'rankmyseo' ), $mpn, __( 'The maker’s own part number, when it is different from the barcode.', 'rankmyseo' ) );
		$this->mode_row( 'rankmyseo_return_mode', __( 'Return policy for this product', 'rankmyseo' ), (string) ( $return['mode'] ?? 'inherit' ) );
		$this->text_row( 'rankmyseo_return_country', __( 'Return country', 'rankmyseo' ), (string) ( $return['country'] ?? '' ), __( 'Two letters, such as US. Used when this product has its own return policy.', 'rankmyseo' ) );
		$this->select_row( 'rankmyseo_return_category', __( 'Return window', 'rankmyseo' ), (string) ( $return['category'] ?? 'MerchantReturnFiniteReturnWindow' ), $this->category_options() );
		$this->text_row( 'rankmyseo_return_days', __( 'Return window in days', 'rankmyseo' ), (string) ( $return['days'] ?? '' ), '' );
		$this->select_row( 'rankmyseo_return_method', __( 'How shoppers send an item back', 'rankmyseo' ), (string) ( $return['method'] ?? 'ReturnByMail' ), $this->method_options() );
		$this->select_row( 'rankmyseo_return_fees', __( 'Who pays for the return', 'rankmyseo' ), (string) ( $return['fees'] ?? 'FreeReturn' ), $this->fee_options() );
		$this->text_row( 'rankmyseo_return_url', __( 'Return policy link', 'rankmyseo' ), (string) ( $return['url'] ?? '' ), '' );
		$this->mode_row( 'rankmyseo_shipping_mode', __( 'Shipping for this product', 'rankmyseo' ), (string) ( $ship['mode'] ?? 'inherit' ) );
		$this->text_row( 'rankmyseo_shipping_country', __( 'Shipping country', 'rankmyseo' ), (string) ( $ship['country'] ?? '' ), __( 'Two letters. Used when this product has its own shipping details.', 'rankmyseo' ) );
		$this->text_row( 'rankmyseo_shipping_rate', __( 'Shipping price', 'rankmyseo' ), (string) ( $ship['rate'] ?? '' ), __( 'Use 0 for free shipping.', 'rankmyseo' ) );
		$this->text_row( 'rankmyseo_shipping_currency', __( 'Shipping currency', 'rankmyseo' ), (string) ( $ship['currency'] ?? '' ), __( 'Three letters, such as USD. Leave blank to use the shop currency.', 'rankmyseo' ) );
		$this->text_row( 'rankmyseo_shipping_handling_min', __( 'Handling time, minimum days', 'rankmyseo' ), (string) ( $ship['handling_min'] ?? '' ), '' );
		$this->text_row( 'rankmyseo_shipping_handling_max', __( 'Handling time, maximum days', 'rankmyseo' ), (string) ( $ship['handling_max'] ?? '' ), '' );
		$this->text_row( 'rankmyseo_shipping_transit_min', __( 'Transit time, minimum days', 'rankmyseo' ), (string) ( $ship['transit_min'] ?? '' ), '' );
		$this->text_row( 'rankmyseo_shipping_transit_max', __( 'Transit time, maximum days', 'rankmyseo' ), (string) ( $ship['transit_max'] ?? '' ), '' );
		echo '</div></div>';
	}

	/**
	 * Print identifier fields on one variation row.
	 *
	 * @param int                  $loop      Variation index.
	 * @param array<string, mixed> $data      Variation data. Unused.
	 * @param object               $variation Variation post.
	 * @return void
	 */
	public function render_variation( int $loop, array $data, object $variation ): void {
		unset( $data );
		$variation_id = $variation instanceof WP_Post ? (int) $variation->ID : 0;
		$gtin         = $this->meta_string( $variation_id, ProductMeta::GTIN );
		$mpn          = $this->meta_string( $variation_id, ProductMeta::MPN );

		echo '<div class="form-row form-row-full rankmyseo-variation-identifiers">';
		echo '<p class="form-row form-row-first">';
		echo '<label>' . esc_html__( 'Barcode (GTIN, EAN, UPC, or ISBN)', 'rankmyseo' ) . '</label>';
		echo '<input type="text" name="rankmyseo_variation_gtin[' . esc_attr( (string) $loop ) . ']" value="' . esc_attr( $gtin ) . '" />';
		echo '</p>';
		echo '<p class="form-row form-row-last">';
		echo '<label>' . esc_html__( 'Manufacturer number (MPN)', 'rankmyseo' ) . '</label>';
		echo '<input type="text" name="rankmyseo_variation_mpn[' . esc_attr( (string) $loop ) . ']" value="' . esc_attr( $mpn ) . '" />';
		echo '</p></div>';
	}

	/**
	 * Save product SEO fields after a valid nonce and edit capability check.
	 *
	 * @param object $product Product being saved.
	 * @return void
	 */
	public function save_product( object $product ): void {
		$post_id = 0;
		if ( method_exists( $product, 'get_id' ) ) {
			$id = $product->get_id();
			if ( is_numeric( $id ) ) {
				$post_id = (int) $id;
			}
		}
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) || ! $this->authorized( false ) ) {
			return;
		}

		foreach ( ProductMeta::fields_from_request( $this->body() ) as $key => $value ) {
			if ( method_exists( $product, 'update_meta_data' ) ) {
				$product->update_meta_data( $key, $value );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		$gtin = ProductMeta::sanitize_identifier( $this->body()['rankmyseo_gtin'] ?? '' );
		if ( '' !== $gtin && method_exists( $product, 'set_global_unique_id' ) ) {
			$product->set_global_unique_id( $gtin );
		}
	}

	/**
	 * Save one variation's barcode and manufacturer number.
	 *
	 * @param int $variation_id Variation post ID.
	 * @param int $index        Variation index in the submitted form.
	 * @return void
	 */
	public function save_variation( int $variation_id, int $index ): void {
		if ( $variation_id <= 0 || ! current_user_can( 'edit_post', $variation_id ) || ! $this->authorized( true ) ) {
			return;
		}

		$body  = $this->body();
		$gtins = isset( $body['rankmyseo_variation_gtin'] ) && is_array( $body['rankmyseo_variation_gtin'] ) ? $body['rankmyseo_variation_gtin'] : [];
		$mpns  = isset( $body['rankmyseo_variation_mpn'] ) && is_array( $body['rankmyseo_variation_mpn'] ) ? $body['rankmyseo_variation_mpn'] : [];

		update_post_meta( $variation_id, ProductMeta::GTIN, ProductMeta::sanitize_identifier( $gtins[ $index ] ?? '' ) );
		update_post_meta( $variation_id, ProductMeta::MPN, ProductMeta::sanitize_mpn( $mpns[ $index ] ?? '' ) );
	}

	/**
	 * Whether this save request carries a RankMySEO nonce or WooCommerce's own product nonce.
	 *
	 * @param bool $variation True when saving a variation row.
	 * @return bool
	 */
	private function authorized( bool $variation ): bool {
		$body  = $this->body();
		$nonce = isset( $body[ self::NONCE_FIELD ] ) ? sanitize_text_field( (string) $body[ self::NONCE_FIELD ] ) : '';
		if ( '' !== $nonce && wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return true;
		}

		if ( $variation ) {
			$security = isset( $body['security'] ) ? sanitize_text_field( (string) $body['security'] ) : '';
			if ( '' !== $security && wp_verify_nonce( $security, 'save-variations' ) ) {
				return true;
			}
		}

		$wc_nonce = isset( $body['woocommerce_meta_nonce'] ) ? sanitize_text_field( (string) $body['woocommerce_meta_nonce'] ) : '';

		return '' !== $wc_nonce && false !== wp_verify_nonce( $wc_nonce, 'woocommerce_save_data' );
	}

	/**
	 * Request body with slashes removed.
	 *
	 * @return array<string, mixed>
	 */
	private function body(): array {
		if ( null !== $this->request ) {
			return $this->request;
		}

		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorized() before any meta is written.
		return is_array( $post ) ? $post : [];
	}

	/**
	 * Current product ID on the edit screen.
	 *
	 * @return int
	 */
	private function current_post_id(): int {
		$post = get_post();
		return $post instanceof WP_Post ? (int) $post->ID : 0;
	}

	/**
	 * Read one meta string.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	private function meta_string( int $post_id, string $key ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$value = get_post_meta( $post_id, $key, true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Print the RankMySEO nonce.
	 *
	 * @return void
	 */
	private function nonce_field(): void {
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
			return;
		}

		echo '<input type="hidden" name="' . esc_attr( self::NONCE_FIELD ) . '" value="' . esc_attr( wp_create_nonce( self::NONCE_ACTION ) ) . '" />';
	}

	/**
	 * One text field row.
	 *
	 * @param string $name        Field name.
	 * @param string $label       Field label.
	 * @param string $value       Current value.
	 * @param string $description Optional help.
	 * @return void
	 */
	private function text_row( string $name, string $label, string $value, string $description ): void {
		echo '<p class="form-field ' . esc_attr( $name ) . '_field">';
		echo '<label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="text" class="short" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		if ( '' !== $description ) {
			echo '<span class="description">' . esc_html( $description ) . '</span>';
		}
		echo '</p>';
	}

	/**
	 * Inherit / custom / none selector.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Current mode.
	 * @return void
	 */
	private function mode_row( string $name, string $label, string $value ): void {
		$this->select_row(
			$name,
			$label,
			$value,
			[
				'inherit' => __( 'Use the store default', 'rankmyseo' ),
				'custom'  => __( 'Use a policy just for this product', 'rankmyseo' ),
				'none'    => __( 'Do not show a policy on this product', 'rankmyseo' ),
			]
		);
	}

	/**
	 * One select row.
	 *
	 * @param string                $name    Field name.
	 * @param string                $label   Field label.
	 * @param string                $value   Current value.
	 * @param array<string, string> $options Value => label.
	 * @return void
	 */
	private function select_row( string $name, string $label, string $value, array $options ): void {
		echo '<p class="form-field ' . esc_attr( $name ) . '_field">';
		echo '<label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		echo '<select class="select short" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		foreach ( $options as $option => $option_label ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></p>';
	}

	/**
	 * Return window labels.
	 *
	 * @return array<string, string>
	 */
	private function category_options(): array {
		return [
			'MerchantReturnFiniteReturnWindow' => __( 'A set number of days', 'rankmyseo' ),
			'MerchantReturnUnlimitedWindow'    => __( 'No time limit', 'rankmyseo' ),
			'MerchantReturnNotPermitted'       => __( 'Returns are not accepted', 'rankmyseo' ),
		];
	}

	/**
	 * Return method labels.
	 *
	 * @return array<string, string>
	 */
	private function method_options(): array {
		return [
			'ReturnByMail'  => __( 'By mail', 'rankmyseo' ),
			'ReturnInStore' => __( 'In store', 'rankmyseo' ),
			'ReturnAtKiosk' => __( 'At a drop-off point', 'rankmyseo' ),
		];
	}

	/**
	 * Return fee labels.
	 *
	 * @return array<string, string>
	 */
	private function fee_options(): array {
		return [
			'FreeReturn'                       => __( 'Free returns', 'rankmyseo' ),
			'ReturnFeesCustomerResponsibility' => __( 'The customer pays', 'rankmyseo' ),
			'ReturnShippingFees'               => __( 'The customer pays return shipping', 'rankmyseo' ),
			'OriginalShippingFees'             => __( 'Original shipping is not refunded', 'rankmyseo' ),
		];
	}
}
