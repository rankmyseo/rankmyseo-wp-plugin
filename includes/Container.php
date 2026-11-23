<?php
/**
 * Minimal zero-dependency dependency injection container.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO;

use InvalidArgumentException;

/**
 * Lightweight PSR-11-style service container.
 */
final class Container {
	/**
	 * Registered service factory definitions.
	 *
	 * @var array<string, array{factory: callable(Container): mixed, shared: bool}>
	 */
	private array $definitions = [];

	/**
	 * Resolved singleton instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = [];

	/**
	 * Register a non-shared factory binding.
	 *
	 * @param string                    $id      Service identifier.
	 * @param callable(Container):mixed $factory Factory callable.
	 * @return void
	 */
	public function bind( string $id, callable $factory ): void {
		$this->definitions[ $id ] = [
			'factory' => $factory,
			'shared'  => false,
		];
		unset( $this->instances[ $id ] );
	}

	/**
	 * Register a shared singleton factory binding.
	 *
	 * @param string                    $id      Service identifier.
	 * @param callable(Container):mixed $factory Factory callable.
	 * @return void
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->definitions[ $id ] = [
			'factory' => $factory,
			'shared'  => true,
		];
		unset( $this->instances[ $id ] );
	}

	/**
	 * Store an already-instantiated service value.
	 *
	 * @param string $id       Service identifier.
	 * @param mixed  $instance Resolved service value.
	 * @return void
	 */
	public function set( string $id, $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Check whether a service identifier is registered.
	 *
	 * @param string $id Service identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return array_key_exists( $id, $this->instances ) || array_key_exists( $id, $this->definitions );
	}

	/**
	 * Resolve and return a service by identifier.
	 *
	 * @param string $id Service identifier.
	 * @return mixed
	 * @throws InvalidArgumentException When the service is not registered.
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! array_key_exists( $id, $this->definitions ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Service "%s" is not registered in the RankMySEO container.', esc_html( $id ) )
			);
		}

		$definition = $this->definitions[ $id ];
		$resolved   = ( $definition['factory'] )( $this );

		if ( $definition['shared'] ) {
			$this->instances[ $id ] = $resolved;
		}

		return $resolved;
	}

	/**
	 * Return all registered service identifiers.
	 *
	 * @return list<string>
	 */
	public function keys(): array {
		$keys = array_unique(
			array_merge(
				array_keys( $this->definitions ),
				array_keys( $this->instances )
			)
		);

		return array_values( $keys );
	}
}
