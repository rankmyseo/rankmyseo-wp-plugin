<?php
/**
 * Base abstract class for RankMySEO feature modules.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules;

use RankMySEO\Container;
use RankMySEO\Core\Settings;

/**
 * Shared boilerplate for modules wired to the DI container.
 */
abstract class AbstractModule implements ModuleInterface {
	/**
	 * Service container.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Plugin DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled_by_default(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_ready(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function requires_cloud(): bool {
		return false;
	}

	/**
	 * Access the plugin settings repository.
	 *
	 * @return Settings
	 */
	protected function settings(): Settings {
		$settings = $this->container->get( Settings::class );
		assert( $settings instanceof Settings );
		return $settings;
	}
}
