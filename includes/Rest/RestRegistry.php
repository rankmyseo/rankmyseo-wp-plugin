<?php
/**
 * REST API controller registry.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

/**
 * Hooks registered REST controllers onto rest_api_init.
 */
final class RestRegistry {
	/**
	 * Registered REST controllers.
	 *
	 * @var list<AbstractController>
	 */
	private array $controllers;

	/**
	 * Constructor.
	 *
	 * @param array<int, AbstractController> $controllers Initial list of controllers.
	 */
	public function __construct( array $controllers = [] ) {
		$this->controllers = array_values( $controllers );
	}

	/**
	 * Add a controller to the registry before rest_api_init fires.
	 *
	 * @param AbstractController $controller Controller instance.
	 * @return void
	 */
	public function add( AbstractController $controller ): void {
		$this->controllers[] = $controller;
	}

	/**
	 * Hook route registration onto WordPress rest_api_init.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Invoke register_routes() on every registered controller.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		/**
		 * Filter the list of REST controllers registered by RankMySEO.
		 *
		 * @param array<int, AbstractController> $controllers Registered controllers.
		 */
		$controllers = apply_filters( 'rankmyseo_rest_controllers', $this->controllers );

		foreach ( $controllers as $controller ) {
			if ( $controller instanceof AbstractController ) {
				$controller->register_routes();
			}
		}
	}

	/**
	 * Return registered controller instances.
	 *
	 * @return list<AbstractController>
	 */
	public function all(): array {
		return $this->controllers;
	}
}
