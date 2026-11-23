<?php
/**
 * Contract implemented by all RankMySEO feature modules.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules;

/**
 * Interface for pluggable RankMySEO modules.
 */
interface ModuleInterface {
	/**
	 * Unique machine identifier (e.g. "meta", "schema", "sitemaps", "redirects").
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable module name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Technical description shown in Advanced mode.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Plain-English description shown in Simple (Guided) mode.
	 *
	 * @return string
	 */
	public function get_simple_description(): string;

	/**
	 * Whether this module is enabled by default on fresh installs.
	 *
	 * @return bool
	 */
	public function is_enabled_by_default(): bool;

	/**
	 * Whether this module is implemented and ready (false for foundation stubs).
	 *
	 * @return bool
	 */
	public function is_ready(): bool;

	/**
	 * Whether this module requires an optional RankMySEO Cloud connection.
	 *
	 * @return bool
	 */
	public function requires_cloud(): bool;

	/**
	 * Register WordPress hooks and services for this module.
	 *
	 * Called only when the module is enabled in settings.
	 *
	 * @return void
	 */
	public function register(): void;
}
