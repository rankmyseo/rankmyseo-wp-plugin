<?php
/**
 * Elementor widget that prints the RankMySEO breadcrumb trail.
 *
 * Loaded only after Elementor has registered Widget_Base.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders\Elementor;

use RankMySEO\Modules\Builders\BreadcrumbHtml;
use RankMySEO\Modules\Schema\BreadcrumbTrail;
use WP_Post;

/**
 * Lightweight breadcrumbs widget. It has no remote calls and no extra scripts.
 */
final class BreadcrumbsWidget extends \Elementor\Widget_Base {
	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'rankmyseo-breadcrumbs';
	}

	/**
	 * Widget title shown in the Elementor panel.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'RankMySEO Breadcrumbs', 'rankmyseo' );
	}

	/**
	 * Elementor icon class.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-navigation-horizontal';
	}

	/**
	 * Elementor categories.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return [ 'general' ];
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return [ 'breadcrumb', 'seo', 'rankmyseo', 'navigation' ];
	}

	/**
	 * Accessible breadcrumb HTML for the current post.
	 *
	 * @return string
	 */
	public function render_html(): string {
		$post = $this->current_post();
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$permalink = get_permalink( $post );
		$home      = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$trail     = BreadcrumbTrail::for_post(
			$post,
			$home,
			(string) $post->post_title,
			is_string( $permalink ) ? $permalink : ''
		);

		return BreadcrumbHtml::render( $trail['crumbs'] );
	}

	/**
	 * Print the trail. Elementor calls this while rendering the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$html = $this->render_html();
		if ( '' === $html ) {
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BreadcrumbHtml escapes every value.
	}

	/**
	 * Post the widget should describe.
	 *
	 * @return WP_Post|null
	 */
	private function current_post(): ?WP_Post {
		if ( function_exists( 'get_queried_object_id' ) ) {
			$queried = (int) get_queried_object_id();
			if ( $queried > 0 ) {
				$post = get_post( $queried );
				if ( $post instanceof WP_Post ) {
					return $post;
				}
			}
		}

		$post = get_post();
		return $post instanceof WP_Post ? $post : null;
	}
}
