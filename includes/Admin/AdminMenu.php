<?php
/**
 * WordPress Admin menu registration and React SPA asset enqueueing.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Admin;

use RankMySEO\Core\Capabilities;
use RankMySEO\Core\Settings;

/**
 * Registers RankMySEO admin pages and enqueues compiled assets only on RankMySEO screens.
 */
final class AdminMenu {
	/**
	 * Top-level menu slug.
	 */
	public const MENU_SLUG = 'rankmyseo';

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Registered WP admin screen hook suffixes.
	 *
	 * @var list<string>
	 */
	private array $screen_hooks = [];

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Attach WordPress admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Return all submenu definitions for RankMySEO.
	 *
	 * @return list<array{slug: string, page_id: string, title: string, menu_title: string, capability: string}>
	 */
	public function get_submenus(): array {
		return [
			[
				'slug'       => self::MENU_SLUG,
				'page_id'    => 'dashboard',
				'title'      => __( 'RankMySEO Dashboard', 'rankmyseo' ),
				'menu_title' => __( 'Dashboard', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
			[
				'slug'       => 'rankmyseo-meta',
				'page_id'    => 'meta',
				'title'      => __( 'Titles & Meta — RankMySEO', 'rankmyseo' ),
				'menu_title' => __( 'Titles & Meta', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
			[
				'slug'       => 'rankmyseo-missing',
				'page_id'    => 'missing',
				'title'      => __( 'Missing details — RankMySEO', 'rankmyseo' ),
				'menu_title' => __( 'Missing details', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
			[
				'slug'       => 'rankmyseo-schema',
				'page_id'    => 'schema',
				'title'      => __( 'Schema — RankMySEO', 'rankmyseo' ),
				'menu_title' => __( 'Schema', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
			[
				'slug'       => 'rankmyseo-sitemaps',
				'page_id'    => 'sitemaps',
				'title'      => __( 'Sitemaps — RankMySEO', 'rankmyseo' ),
				'menu_title' => __( 'Sitemaps', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
			[
				'slug'       => 'rankmyseo-redirects',
				'page_id'    => 'redirects',
				'title'      => __( 'Redirects & 404s — RankMySEO', 'rankmyseo' ),
				'menu_title' => __( 'Redirects & 404s', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
			[
				'slug'       => 'rankmyseo-import',
				'page_id'    => 'import',
				'title'      => __( 'Import — RankMySEO', 'rankmyseo' ),
				'menu_title' => __( 'Import', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
			[
				'slug'       => 'rankmyseo-cloud',
				'page_id'    => 'cloud',
				'title'      => __( 'RankMySEO Cloud', 'rankmyseo' ),
				'menu_title' => __( 'RankMySEO Cloud', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
			[
				'slug'       => 'rankmyseo-ai-queue',
				'page_id'    => 'ai-queue',
				'title'      => __( 'AI Queue — RankMySEO', 'rankmyseo' ),
				'menu_title' => __( 'AI Queue', 'rankmyseo' ),
				'capability' => Capabilities::CAP_APPROVE_AI,
			],
			[
				'slug'       => 'rankmyseo-settings',
				'page_id'    => 'settings',
				'title'      => __( 'Settings — RankMySEO', 'rankmyseo' ),
				'menu_title' => __( 'Settings', 'rankmyseo' ),
				'capability' => Capabilities::CAP_MANAGE,
			],
		];
	}

	/**
	 * Register top-level RankMySEO menu and all submenus.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		$this->screen_hooks = [];

		$top_hook = add_menu_page(
			__( 'RankMySEO', 'rankmyseo' ),
			__( 'RankMySEO', 'rankmyseo' ),
			Capabilities::CAP_MANAGE,
			self::MENU_SLUG,
			[ $this, 'render_page' ],
			$this->get_menu_icon_data_uri(),
			65
		);

		if ( is_string( $top_hook ) && '' !== $top_hook ) {
			$this->screen_hooks[] = $top_hook;
		}

		foreach ( $this->get_submenus() as $submenu ) {
			$hook = add_submenu_page(
				self::MENU_SLUG,
				$submenu['title'],
				$submenu['menu_title'],
				$submenu['capability'],
				$submenu['slug'],
				[ $this, 'render_page' ]
			);

			if ( is_string( $hook ) && '' !== $hook ) {
				$this->screen_hooks[] = $hook;
			}
		}
	}

	/**
	 * Enqueue compiled React admin SPA assets strictly on RankMySEO admin screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->screen_hooks, true ) ) {
			return;
		}

		$asset_file = RANKMYSEO_DIR . 'build/admin.asset.php';
		$asset      = [
			'dependencies' => [ 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n' ],
			'version'      => RANKMYSEO_VERSION,
		];

		if ( file_exists( $asset_file ) ) {
			$loaded = include $asset_file;
			if ( is_array( $loaded ) ) {
				$asset = array_merge( $asset, $loaded );
			}
		}

		$script_path = RANKMYSEO_DIR . 'build/admin.js';
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'rankmyseo-admin',
				RANKMYSEO_URL . 'build/admin.js',
				$asset['dependencies'],
				(string) $asset['version'],
				true
			);

			wp_set_script_translations( 'rankmyseo-admin', 'rankmyseo', RANKMYSEO_DIR . 'languages' );

			wp_localize_script(
				'rankmyseo-admin',
				'rankmyseoAdmin',
				$this->get_bootstrap_payload()
			);
		}

		wp_enqueue_style( 'wp-components' );

		$style_path = RANKMYSEO_DIR . 'build/admin.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'rankmyseo-admin',
				RANKMYSEO_URL . 'build/admin.css',
				[ 'wp-components' ],
				(string) $asset['version']
			);
		}
	}

	/**
	 * Render the React mount container on RankMySEO admin pages.
	 *
	 * @return void
	 */
	public function render_page(): void {
		echo '<div class="wrap rankmyseo-wrap">';
		echo '<div id="rankmyseo-admin-root" class="rms-root">';
		echo '<noscript>';
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'RankMySEO requires JavaScript to be enabled in your browser to use the admin interface.', 'rankmyseo' );
		echo '</p></div>';
		echo '</noscript>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Build the initial JS bootstrap object localized for the Admin SPA.
	 *
	 * @return array<string, mixed>
	 */
	public function get_bootstrap_payload(): array {
		$user_id      = get_current_user_id();
		$current_slug = $this->resolve_current_page_slug();
		$page_id      = $this->slug_to_page_id( $current_slug );

		return [
			'version'         => RANKMYSEO_VERSION,
			'restUrl'         => esc_url_raw( rest_url( 'rankmyseo/v1' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'adminUrl'        => esc_url_raw( admin_url( 'admin.php' ) ),
			'siteName'        => (string) get_bloginfo( 'name' ),
			'siteUrl'         => esc_url_raw( home_url( '/' ) ),
			'blogPublic'      => '0' !== (string) get_option( 'blog_public', '1' ),
			'initialPage'     => $page_id,
			'uiMode'          => $this->settings->get_user_ui_mode( $user_id ),
			'defaultUiMode'   => (string) $this->settings->get( 'default_ui_mode', 'simple' ),
			'wizardCompleted' => (bool) $this->settings->get( 'wizard_completed', false ),
			'wizardDismissed' => $this->settings->is_wizard_dismissed_for_user( $user_id ),
			'canManage'       => Capabilities::current_user_can_manage(),
			'canApproveAi'    => Capabilities::current_user_can_approve_ai(),
		];
	}

	/**
	 * Return registered screen hooks (useful for tests).
	 *
	 * @return list<string>
	 */
	public function get_screen_hooks(): array {
		return $this->screen_hooks;
	}

	/**
	 * Resolve the current admin page slug safely from query parameters.
	 *
	 * @return string
	 */
	private function resolve_current_page_slug(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing of WP admin page slug.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : self::MENU_SLUG;

		return '' !== $page ? $page : self::MENU_SLUG;
	}

	/**
	 * Map a WP submenu slug to the internal SPA page identifier.
	 *
	 * @param string $slug Submenu slug.
	 * @return string
	 */
	private function slug_to_page_id( string $slug ): string {
		foreach ( $this->get_submenus() as $submenu ) {
			if ( $submenu['slug'] === $slug ) {
				return $submenu['page_id'];
			}
		}

		return 'dashboard';
	}

	/**
	 * Return a crisp 20x20 monochrome climbing-bars SVG icon for the WP Admin menu.
	 *
	 * @return string
	 */
	private function get_menu_icon_data_uri(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">'
			. '<rect x="2" y="11" width="4" height="7" rx="1" fill="black"/>'
			. '<rect x="8" y="7" width="4" height="11" rx="1" fill="black"/>'
			. '<rect x="14" y="2" width="4" height="16" rx="1" fill="black"/>'
			. '</svg>';

		return 'data:image/svg+xml,' . rawurlencode( $svg );
	}
}
