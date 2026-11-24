<?php
/**
 * Theme template tags.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

if ( ! function_exists( 'rankmyseo_breadcrumbs' ) ) {
	/**
	 * Print or return the visible breadcrumb trail.
	 *
	 * Uses the same trail as the BreadcrumbList JSON-LD node.
	 *
	 * @param array<string, mixed> $args {
	 *     Optional display arguments.
	 *
	 *     @type string $separator    Text between crumbs. Default '/'.
	 *     @type bool   $show_home    Whether to include Home. Default true.
	 *     @type bool   $show_current Whether to include the current item. Default true.
	 *     @type bool   $echo         Whether to echo the HTML. Default true.
	 * }
	 * @return string
	 */
	function rankmyseo_breadcrumbs( array $args = [] ): string {
		$echo = ! array_key_exists( 'echo', $args ) || ! empty( $args['echo'] );
		$html = ( new RankMySEO\Modules\Schema\BreadcrumbRenderer() )->html(
			( new RankMySEO\Modules\Schema\BreadcrumbTrail() )->for_request(),
			$args
		);

		if ( $echo ) {
			// Escaped in BreadcrumbRenderer.
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $html;
	}
}
