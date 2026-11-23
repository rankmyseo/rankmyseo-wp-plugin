<?php
/**
 * Optional crawl-hygiene hooks. Every control is off until an admin enables it.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Registers attachment redirects, robots tweaks, and head-link cleanup.
 */
final class CrawlControls {
	/**
	 * Active crawl flags.
	 *
	 * @var array<string, bool>
	 */
	private array $crawl;

	/**
	 * Constructor.
	 *
	 * @param array<string, bool> $crawl Crawl flags from settings.
	 */
	public function __construct( array $crawl ) {
		$this->crawl = $crawl;
	}

	/**
	 * Register only the hooks for flags that are on.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! empty( $this->crawl['redirect_attachments'] ) || ! empty( $this->crawl['strip_replytocom'] ) ) {
			add_action( 'template_redirect', [ $this, 'on_template_redirect' ], 1 );
		}

		if ( ! empty( $this->crawl['noindex_attachments'] ) || ! empty( $this->crawl['noindex_search'] ) || ! empty( $this->crawl['noindex_comment_pages'] ) ) {
			add_filter( 'wp_robots', [ $this, 'filter_wp_robots' ] );
		}

		if ( ! empty( $this->crawl['remove_rsd'] ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( ! empty( $this->crawl['remove_wlw'] ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( ! empty( $this->crawl['remove_shortlink'] ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		}
	}

	/**
	 * Redirect attachment pages or strip replytocom query arguments.
	 *
	 * @return void
	 */
	public function on_template_redirect(): void {
		if ( ! empty( $this->crawl['strip_replytocom'] ) && isset( $_GET['replytocom'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public crawl query arg, not a form submission.
			$target = function_exists( 'remove_query_arg' ) ? remove_query_arg( 'replytocom' ) : home_url( '/' );
			$this->redirect( is_string( $target ) ? $target : home_url( '/' ) );
		}

		if ( empty( $this->crawl['redirect_attachments'] ) || ! function_exists( 'is_attachment' ) || ! is_attachment() ) {
			return;
		}

		$post = function_exists( 'get_post' ) ? get_post() : null;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$target = '';
		if ( ! empty( $post->post_parent ) && function_exists( 'get_permalink' ) ) {
			$parent = get_permalink( (int) $post->post_parent );
			$target = is_string( $parent ) ? $parent : '';
		}

		if ( '' === $target && function_exists( 'wp_get_attachment_url' ) ) {
			$file   = wp_get_attachment_url( (int) $post->ID );
			$target = is_string( $file ) ? $file : '';
		}

		if ( '' !== $target ) {
			$this->redirect( $target );
		}
	}

	/**
	 * Add noindex for the optional archive surfaces.
	 *
	 * @param array<string, bool|string> $robots Existing robots directives.
	 * @return array<string, bool|string>
	 */
	public function filter_wp_robots( array $robots ): array {
		$noindex = false;

		if ( ! empty( $this->crawl['noindex_search'] ) && function_exists( 'is_search' ) && is_search() ) {
			$noindex = true;
		}

		if ( ! empty( $this->crawl['noindex_attachments'] ) && function_exists( 'is_attachment' ) && is_attachment() ) {
			$noindex = true;
		}

		if ( ! empty( $this->crawl['noindex_comment_pages'] ) && function_exists( 'is_singular' ) && is_singular() && function_exists( 'get_query_var' ) ) {
			$cpage = (int) get_query_var( 'cpage' );
			if ( $cpage > 1 ) {
				$noindex = true;
			}
		}

		if ( $noindex ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}

		return $robots;
	}

	/**
	 * Send a 301 and stop the request.
	 *
	 * @param string $target Destination URL.
	 * @return void
	 */
	private function redirect( string $target ): void {
		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $target, 301 );
		} else {
			header( 'Location: ' . $target, true, 301 );
		}
		exit;
	}
}
