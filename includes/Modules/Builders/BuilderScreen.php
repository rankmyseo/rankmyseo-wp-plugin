<?php
/**
 * Read-only screen flags for the Elementor and Divi editors.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders;

use WP_Post;

/**
 * Identifies the post open in a builder and whether Divi's visual builder is on.
 */
final class BuilderScreen {
	/**
	 * Post being edited, when the request exposes one.
	 *
	 * @return int
	 */
	public function edited_post_id(): int {
		// Read-only editor routing. SEO saves use the REST nonce.
		$post_param = isset( $_GET['post'] ) && ! is_array( $_GET['post'] ) ? wp_unslash( (string) $_GET['post'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_string( $post_param ) && '' !== $post_param ) {
			return absint( $post_param );
		}

		if ( function_exists( 'get_the_ID' ) ) {
			$the_id = (int) get_the_ID();
			if ( $the_id > 0 ) {
				return $the_id;
			}
		}

		$post = function_exists( 'get_post' ) ? get_post() : null;
		if ( $post instanceof WP_Post ) {
			return (int) $post->ID;
		}

		return 0;
	}

	/**
	 * Whether this request is a Divi Visual Builder screen.
	 *
	 * Unknown Divi 4/5 flags return false so the bundle stays unloaded.
	 *
	 * @return bool
	 */
	public function is_divi_builder_request(): bool {
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return true;
		}

		if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
			return true;
		}

		if ( function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled() && $this->has_builder_flag() ) {
			return true;
		}

		return $this->has_builder_flag();
	}

	/**
	 * Divi opens the visual builder with et_fb=1 across recent major versions.
	 *
	 * @return bool
	 */
	private function has_builder_flag(): bool {
		// Read-only builder screen flag. SEO saves use the REST nonce.
		$raw = isset( $_GET['et_fb'] ) && ! is_array( $_GET['et_fb'] ) ? wp_unslash( (string) $_GET['et_fb'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_string( $raw ) || '' === $raw ) {
			return false;
		}

		$flag = sanitize_text_field( $raw );

		return '1' === $flag || 'true' === $flag;
	}
}
