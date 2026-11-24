<?php
/**
 * Wires Elementor and Divi to the shared SEO meta bridge.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders;

/**
 * Registers builder integrations. Inactive builders add no hooks.
 */
final class PageBuilderBridge {
	/**
	 * Elementor integration.
	 *
	 * @var ElementorIntegration
	 */
	private ElementorIntegration $elementor;

	/**
	 * Divi integration.
	 *
	 * @var DiviIntegration
	 */
	private DiviIntegration $divi;

	/**
	 * Constructor.
	 *
	 * @param BuilderDetector    $detector Active-builder checks.
	 * @param BuilderAssets      $assets   Script loader.
	 * @param BuilderConfig      $config   Localized config.
	 * @param BuilderScreen|null $screen   Current editor screen.
	 */
	public function __construct( BuilderDetector $detector, BuilderAssets $assets, BuilderConfig $config, ?BuilderScreen $screen = null ) {
		$screen          = $screen ?? new BuilderScreen();
		$this->elementor = new ElementorIntegration( $detector, $assets, $config, $screen );
		$this->divi      = new DiviIntegration( $detector, $assets, $config, $screen );
	}

	/**
	 * Register whichever builders are actually loaded.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->elementor->register();
		$this->divi->register();
	}

	/**
	 * Elementor integration, for tests and the widget registrar.
	 *
	 * @return ElementorIntegration
	 */
	public function elementor(): ElementorIntegration {
		return $this->elementor;
	}

	/**
	 * Divi integration.
	 *
	 * @return DiviIntegration
	 */
	public function divi(): DiviIntegration {
		return $this->divi;
	}
}
