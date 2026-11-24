<?php
/**
 * Prints a small front-end stylesheet only when a block or shortcode needs it.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Registers and prints one plugin stylesheet without a front-end script.
 */
final class FrontendStyle {
	/**
	 * Handles already queued for a late footer print.
	 *
	 * @var array<string, bool>
	 */
	private static array $footer = [];

	/**
	 * Enqueue a stylesheet from the plugin directory.
	 *
	 * @param string $handle   Style handle.
	 * @param string $relative Path relative to the plugin root.
	 * @return void
	 */
	public static function enqueue( string $handle, string $relative ): void {
		if ( ! defined( 'RANKMYSEO_DIR' ) || ! defined( 'RANKMYSEO_URL' ) ) {
			return;
		}

		$path = RANKMYSEO_DIR . ltrim( $relative, '/' );
		if ( ! is_readable( $path ) ) {
			return;
		}

		$version = defined( 'RANKMYSEO_VERSION' ) ? RANKMYSEO_VERSION : '1.0.0';
		if ( function_exists( 'wp_register_style' ) ) {
			wp_register_style( $handle, RANKMYSEO_URL . ltrim( $relative, '/' ), [], $version );
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( $handle );
		}

		$after_head = function_exists( 'did_action' ) && did_action( 'wp_head' ) > 0;
		if ( $after_head && ! isset( self::$footer[ $handle ] ) ) {
			self::$footer[ $handle ] = true;
			add_action(
				'wp_footer',
				static function () use ( $handle ): void {
					if ( function_exists( 'wp_print_styles' ) ) {
						wp_print_styles( $handle );
					}
				},
				5
			);
		}
	}
}
