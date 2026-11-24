<?php
/**
 * Divi Visual Builder SEO panel.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders;

/**
 * Enqueues the Divi drawer only inside the visual builder.
 */
final class DiviIntegration {
	/**
	 * Hooks that Divi 4 and Divi 5 have used for builder assets.
	 *
	 * Missing hooks are harmless. The enqueue callback still checks the request.
	 */
	private const ENQUEUE_HOOKS = [
		'et_fb_enqueue_assets',
		'divi_visual_builder_assets_before_enqueue_scripts',
		'wp_enqueue_scripts',
		'admin_enqueue_scripts',
	];

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
	 * Whether this request already printed the bundle.
	 *
	 * @var bool
	 */
	private bool $enqueued = false;

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
	 * Register Divi hooks only when Divi is active.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->detector->divi_active() ) {
			return;
		}

		foreach ( self::ENQUEUE_HOOKS as $hook ) {
			add_action( $hook, [ $this, 'enqueue' ] );
		}
	}

	/**
	 * Enqueue the drawer when this request is the visual builder.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( $this->enqueued || ! $this->screen->is_divi_builder_request() ) {
			return;
		}

		$post_id = $this->screen->edited_post_id();
		if ( ! $this->user_can_edit( $post_id ) ) {
			return;
		}

		$loaded = $this->assets->enqueue(
			'divi',
			'rankmyseo-divi',
			[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
			$this->config->for_editor( $post_id ),
			'rankmyseoDivi'
		);

		if ( $loaded ) {
			$this->enqueued = true;
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
