<?php
/**
 * Module registry and lifecycle manager.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules;

use RankMySEO\Core\Settings;

/**
 * Registers modules, tracks booted state, and exposes metadata for REST/admin UI.
 */
final class ModuleManager {
	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Registered modules keyed by module ID.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $modules = [];

	/**
	 * IDs of modules whose register() hook has been executed.
	 *
	 * @var array<string, bool>
	 */
	private array $booted = [];

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register a module instance.
	 *
	 * @param ModuleInterface $module Module instance.
	 * @return void
	 */
	public function add( ModuleInterface $module ): void {
		$this->modules[ $module->get_id() ] = $module;
	}

	/**
	 * Retrieve a registered module by ID.
	 *
	 * @param string $id Module identifier.
	 * @return ModuleInterface|null
	 */
	public function get( string $id ): ?ModuleInterface {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Return all registered modules keyed by ID.
	 *
	 * @return array<string, ModuleInterface>
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Check whether a registered module is enabled in settings.
	 *
	 * @param string $id Module identifier.
	 * @return bool
	 */
	public function is_enabled( string $id ): bool {
		if ( ! isset( $this->modules[ $id ] ) ) {
			return false;
		}

		return $this->settings->is_module_enabled( $id );
	}

	/**
	 * Check whether a module has been booted on this request.
	 *
	 * @param string $id Module identifier.
	 * @return bool
	 */
	public function is_booted( string $id ): bool {
		return ! empty( $this->booted[ $id ] );
	}

	/**
	 * Boot all registered and enabled modules exactly once.
	 *
	 * @return void
	 */
	public function boot(): void {
		foreach ( array_keys( $this->modules ) as $id ) {
			$this->boot_one( $id );
		}
	}

	/**
	 * Boot one enabled module if it has not already registered.
	 *
	 * @param string $id Module identifier.
	 * @return void
	 */
	public function boot_one( string $id ): void {
		if ( ! isset( $this->modules[ $id ] ) || ! empty( $this->booted[ $id ] ) ) {
			return;
		}

		if ( ! $this->is_enabled( $id ) ) {
			return;
		}

		$this->modules[ $id ]->register();
		$this->booted[ $id ] = true;
	}

	/**
	 * Return serializable module catalog for REST responses and Admin UI.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function get_catalog(): array {
		$catalog = [];

		foreach ( $this->modules as $id => $module ) {
			$catalog[] = [
				'id'                 => $id,
				'name'               => $module->get_name(),
				'description'        => $module->get_description(),
				'simple_description' => $module->get_simple_description(),
				'enabled'            => $this->is_enabled( $id ),
				'enabled_by_default' => $module->is_enabled_by_default(),
				'ready'              => $module->is_ready(),
				'requires_cloud'     => $module->requires_cloud(),
				'booted'             => $this->is_booted( $id ),
			];
		}

		return $catalog;
	}
}
