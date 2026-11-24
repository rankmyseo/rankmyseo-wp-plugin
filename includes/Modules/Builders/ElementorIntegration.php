<?php
/**
 * Elementor editor panel and breadcrumbs widget registration.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders;

use RankMySEO\Modules\Builders\Elementor\BreadcrumbsWidget;

/**
 * Hooks that exist only after Elementor has loaded.
 */
final class ElementorIntegration {
	/**
	 * Builder detector.
	 *
	 * @var BuilderDetector
	 */
	private BuilderDetector $detector;

	/**
	 * Asset loader.
	 *
	 * @var BuilderAssets
	 */
	private BuilderAssets $assets;

	/**
	 * Editor config.
	 *
	 * @var BuilderConfig
	 */
	private BuilderConfig $config;

	/**
	 * Screen reader.
	 *
	 * @var BuilderScreen
	 */
	private BuilderScreen $screen;

	/**
	 * Constructor.
	 *
	 * @param BuilderDetector $detector Active-builder checks.
	 * @param BuilderAssets   $assets   Script loader.
	 * @param BuilderConfig   $config   Localized config.
	 * @param BuilderScreen   $screen   Current editor screen.
	 */
	public function __construct( BuilderDetector $detector, BuilderAssets $assets, BuilderConfig $config, BuilderScreen $screen ) {
		$this->detector = $detector;
		$this->assets   = $assets;
		$this->config   = $config;
		$this->screen   = $screen;
	}

	/**
	 * Register Elementor hooks when Elementor itself is loaded.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->detector->elementor_loaded() ) {
			return;
		}

		add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets' ] );
	}

	/**
	 * Enqueue the Elementor editor panel.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$post_id = $this->screen->edited_post_id();
		if ( ! $this->user_can_edit( $post_id ) ) {
			return;
		}

		$this->assets->enqueue(
			'elementor',
			'rankmyseo-elementor',
			[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
			$this->config->for_editor( $post_id ),
			'rankmyseoElementor'
		);
	}

	/**
	 * Register the breadcrumbs widget on whichever Elementor hook fired.
	 *
	 * @param mixed $widgets_manager Elementor widgets manager, when provided.
	 * @return void
	 */
	public function register_widgets( $widgets_manager = null ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		$widget = new BreadcrumbsWidget();

		if ( is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( $widget );
			return;
		}

		if ( is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( $widget );
		}
	}

	/**
	 * Whether the current user may edit the open post.
	 *
	 * @param int $post_id Post ID. Zero falls back to edit_posts.
	 * @return bool
	 */
	private function user_can_edit( int $post_id ): bool {
		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( 'edit_posts' );
	}
}
