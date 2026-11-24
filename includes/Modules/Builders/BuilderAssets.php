<?php
/**
 * Enqueues a compiled builder bundle only when its file exists.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders;

/**
 * Shared script loader for the Elementor and Divi editor bundles.
 */
final class BuilderAssets {
	/**
	 * Plugin directory with a trailing slash.
	 *
	 * @var string
	 */
	private string $directory;

	/**
	 * Plugin URL with a trailing slash.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Constructor.
	 *
	 * @param string $directory Plugin directory. Empty uses RANKMYSEO_DIR.
	 * @param string $url       Plugin URL. Empty uses RANKMYSEO_URL.
	 */
	public function __construct( string $directory = '', string $url = '' ) {
		if ( '' === $directory && defined( 'RANKMYSEO_DIR' ) ) {
			$directory = RANKMYSEO_DIR;
		}
		if ( '' === $url && defined( 'RANKMYSEO_URL' ) ) {
			$url = RANKMYSEO_URL;
		}

		$this->directory = rtrim( $directory, '/\\' ) . '/';
		$this->url       = rtrim( $url, '/' ) . '/';
	}

	/**
	 * Enqueue one builder bundle and its localized config.
	 *
	 * @param string               $slug        Build filename without extension.
	 * @param string               $handle      Script handle.
	 * @param string[]             $deps        Extra script dependencies.
	 * @param array<string, mixed> $config      Localized editor config.
	 * @param string               $object_name JavaScript global name.
	 * @return bool Whether the script file was enqueued.
	 */
	public function enqueue( string $slug, string $handle, array $deps, array $config, string $object_name ): bool {
		$script = $this->directory . 'build/' . $slug . '.js';
		if ( ! is_readable( $script ) ) {
			return false;
		}

		$version = defined( 'RANKMYSEO_VERSION' ) ? RANKMYSEO_VERSION : '1.0.0';
		$asset   = $this->directory . 'build/' . $slug . '.asset.php';

		if ( is_readable( $asset ) ) {
			$loaded = include $asset;
			if ( is_array( $loaded ) ) {
				if ( isset( $loaded['version'] ) ) {
					$version = (string) $loaded['version'];
				}
				if ( isset( $loaded['dependencies'] ) && is_array( $loaded['dependencies'] ) ) {
					$deps = array_values( array_unique( array_merge( $loaded['dependencies'], $deps ) ) );
				}
			}
		}

		wp_enqueue_script(
			$handle,
			$this->url . 'build/' . $slug . '.js',
			$deps,
			$version,
			true
		);
		wp_localize_script( $handle, $object_name, $config );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, 'rankmyseo', $this->directory . 'languages' );
		}

		$style = $this->directory . 'build/' . $slug . '.css';
		if ( is_readable( $style ) ) {
			wp_enqueue_style( $handle, $this->url . 'build/' . $slug . '.css', [], $version );
		}

		return true;
	}
}
