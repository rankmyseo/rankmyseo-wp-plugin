<?php
/**
 * Enqueues the block editor, classic editor, and quick edit bundles.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

use RankMySEO\Modules\WooCommerce\ProductMeta;

use RankMySEO\Core\Settings;

/**
 * Loads compiled editor assets only on the screens that need them.
 */
final class EditorAssets {
	/**
	 * Core settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Title settings.
	 *
	 * @var TitleSettings
	 */
	private TitleSettings $titles;

	/**
	 * Meta store.
	 *
	 * @var MetaStore
	 */
	private MetaStore $store;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Core settings.
	 * @param TitleSettings $titles   Title settings.
	 * @param MetaStore     $store    Meta store.
	 */
	public function __construct( Settings $settings, TitleSettings $titles, MetaStore $store ) {
		$this->settings = $settings;
		$this->titles   = $titles;
		$this->store    = $store;
	}

	/**
	 * Register enqueue hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
	}

	/**
	 * Enqueue the Gutenberg sidebar bundle.
	 *
	 * @return void
	 */
	public function enqueue_block_editor(): void {
		$this->enqueue_bundle(
			'editor',
			'rankmyseo-editor',
			[ 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-data', 'wp-core-data', 'wp-components', 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-blocks', 'wp-block-editor' ]
		);
		$this->enqueue_public_block_styles();
		wp_localize_script( 'rankmyseo-editor', 'rankmyseoEditor', $this->editor_config() );
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Enqueue classic and quick-edit bundles on the matching admin screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		if ( in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) && ! $this->is_block_editor_screen() ) {
			$this->enqueue_bundle(
				'classic',
				'rankmyseo-classic',
				[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ]
			);
			wp_localize_script( 'rankmyseo-classic', 'rankmyseoClassic', $this->classic_config() );
			if ( function_exists( 'wp_enqueue_media' ) ) {
				wp_enqueue_media();
			}
		}

		if ( 'edit.php' === $hook_suffix ) {
			$this->enqueue_bundle( 'quick-edit', 'rankmyseo-quick-edit', [ 'inline-edit-post' ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 'rankmyseo-meta' === $page && function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Styles used by the breadcrumb and FAQ previews inside the editor.
	 *
	 * @return void
	 */
	private function enqueue_public_block_styles(): void {
		if ( ! defined( 'RANKMYSEO_URL' ) || ! defined( 'RANKMYSEO_VERSION' ) ) {
			return;
		}

		wp_enqueue_style( 'rankmyseo-breadcrumbs', RANKMYSEO_URL . 'assets/css/breadcrumbs.css', [], RANKMYSEO_VERSION );
		wp_enqueue_style( 'rankmyseo-faq', RANKMYSEO_URL . 'assets/css/faq.css', [], RANKMYSEO_VERSION );
	}

	/**
	 * Editor config.
	 *
	 * @return array<string, mixed>
	 */
	public function editor_config(): array {
		$config = [
			'uiMode'     => $this->settings->get_user_ui_mode( get_current_user_id() ),
			'siteName'   => $this->titles->site_name(),
			'siteUrl'    => (string) home_url( '/' ),
			'separator'  => $this->titles->separator(),
			'tagline'    => $this->titles->tagline(),
			'variables'  => TemplateDefaults::variable_catalog(),
			'restRoot'   => (string) rest_url( 'rankmyseo/v1' ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'askAiEvent' => 'rankmyseo:ask-ai',
			'locale'     => function_exists( 'get_locale' ) ? (string) get_locale() : 'en',
		];

		$post = get_post();
		if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
			$config['product'] = ProductMeta::signals_for_post( (int) $post->ID );
		}

		return $config;
	}

	/**
	 * Classic config.
	 *
	 * @return array<string, mixed>
	 */
	private function classic_config(): array {
		$config = $this->editor_config();
		$post   = get_post();

		if ( $post instanceof \WP_Post ) {
			$meta                = $this->store->read_post( (int) $post->ID );
			$config['postId']    = (int) $post->ID;
			$config['postType']  = (string) $post->post_type;
			$config['postTitle'] = (string) $post->post_title;
			$config['permalink'] = (string) get_permalink( $post );
			$config['fields']    = $meta;
		}

		return $config;
	}

	/**
	 * Enqueue bundle.
	 *
	 * @param string   $handle        Script handle suffix and build filename.
	 * @param string   $script_handle Full script handle.
	 * @param string[] $extra_deps    Dependencies merged with the asset file.
	 * @return void
	 */
	private function enqueue_bundle( string $handle, string $script_handle, array $extra_deps ): void {
		$asset_path = RANKMYSEO_DIR . 'build/' . $handle . '.asset.php';
		$script     = RANKMYSEO_DIR . 'build/' . $handle . '.js';

		if ( ! file_exists( $script ) ) {
			return;
		}

		$asset = [
			'dependencies' => $extra_deps,
			'version'      => RANKMYSEO_VERSION,
		];

		if ( file_exists( $asset_path ) ) {
			$loaded = include $asset_path;
			if ( is_array( $loaded ) ) {
				$asset = array_merge( $asset, $loaded );
			}
		}

		$dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? array_values( array_unique( array_merge( $asset['dependencies'], $extra_deps ) ) )
			: $extra_deps;

		wp_enqueue_script(
			$script_handle,
			RANKMYSEO_URL . 'build/' . $handle . '.js',
			$dependencies,
			(string) $asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $script_handle, 'rankmyseo', RANKMYSEO_DIR . 'languages' );
		}

		$style = RANKMYSEO_DIR . 'build/' . $handle . '.css';
		if ( file_exists( $style ) ) {
			wp_enqueue_style(
				$script_handle,
				RANKMYSEO_URL . 'build/' . $handle . '.css',
				[ 'wp-components' ],
				(string) $asset['version']
			);
		}
	}

	/**
	 * Is block editor screen.
	 *
	 * @return bool
	 */
	private function is_block_editor_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		return is_object( $screen ) && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
	}
}
