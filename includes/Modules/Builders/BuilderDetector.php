<?php
/**
 * Detects Elementor and Divi without loading either builder.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders;

/**
 * Cheap, local checks for page-builder presence and activation.
 */
final class BuilderDetector {
	/**
	 * Test override for Elementor being loaded. Null uses runtime markers.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $elementor_loaded_probe;

	/**
	 * Test override for Divi being active. Null uses runtime markers.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $divi_active_probe;

	/**
	 * Constructor.
	 *
	 * @param \Closure|null $elementor_loaded_probe Optional Elementor probe.
	 * @param \Closure|null $divi_active_probe      Optional Divi probe.
	 */
	public function __construct( ?\Closure $elementor_loaded_probe = null, ?\Closure $divi_active_probe = null ) {
		$this->elementor_loaded_probe = $elementor_loaded_probe;
		$this->divi_active_probe      = $divi_active_probe;
	}

	/**
	 * Whether Elementor has booted in this request.
	 *
	 * @return bool
	 */
	public function elementor_loaded(): bool {
		if ( $this->elementor_loaded_probe instanceof \Closure ) {
			return (bool) ( $this->elementor_loaded_probe )();
		}

		$did = function_exists( 'did_action' ) && did_action( 'elementor/loaded' );

		return $did || class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Whether an Elementor install is present, even if it is not active.
	 *
	 * @return bool
	 */
	public function elementor_detected(): bool {
		if ( $this->elementor_loaded() ) {
			return true;
		}

		return $this->readable_plugin( 'elementor/elementor.php' );
	}

	/**
	 * Whether the Divi theme or its builder is active.
	 *
	 * @return bool
	 */
	public function divi_active(): bool {
		if ( $this->divi_active_probe instanceof \Closure ) {
			return (bool) ( $this->divi_active_probe )();
		}

		if ( defined( 'ET_BUILDER_VERSION' ) && '' !== (string) constant( 'ET_BUILDER_VERSION' ) ) {
			return true;
		}

		return function_exists( 'et_setup_theme' );
	}

	/**
	 * Whether Divi is installed or active.
	 *
	 * @return bool
	 */
	public function divi_detected(): bool {
		if ( $this->divi_active() ) {
			return true;
		}

		if ( function_exists( 'get_template' ) && 'Divi' === (string) get_template() ) {
			return true;
		}

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			return is_readable( WP_CONTENT_DIR . '/themes/Divi/functions.php' );
		}

		return false;
	}

	/**
	 * Status payload for Settings and System Health.
	 *
	 * @return array<string, array{id: string, label: string, detected: bool, active: bool, version: string}>
	 */
	public function status(): array {
		return [
			'elementor' => [
				'id'       => 'elementor',
				'label'    => 'Elementor',
				'detected' => $this->elementor_detected(),
				'active'   => $this->elementor_loaded(),
				'version'  => defined( 'ELEMENTOR_VERSION' ) ? (string) constant( 'ELEMENTOR_VERSION' ) : '',
			],
			'divi'      => [
				'id'       => 'divi',
				'label'    => 'Divi',
				'detected' => $this->divi_detected(),
				'active'   => $this->divi_active(),
				'version'  => defined( 'ET_BUILDER_VERSION' ) ? (string) constant( 'ET_BUILDER_VERSION' ) : '',
			],
		];
	}

	/**
	 * Whether a plugin file is present. This does not include the file.
	 *
	 * @param string $relative Path under the plugins directory.
	 * @return bool
	 */
	private function readable_plugin( string $relative ): bool {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			return false;
		}

		return is_readable( WP_PLUGIN_DIR . '/' . ltrim( $relative, '/' ) );
	}
}
