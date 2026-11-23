<?php
/**
 * Role and capability management for RankMySEO.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Core;

/**
 * Manages custom WordPress capabilities used by RankMySEO.
 */
final class Capabilities {
	/**
	 * Capability required to manage plugin settings, modules, sitemaps, and redirects.
	 */
	public const CAP_MANAGE = 'rankmyseo_manage';

	/**
	 * Capability required to review, approve, reject, or roll back AI actions.
	 */
	public const CAP_APPROVE_AI = 'rankmyseo_approve_ai';

	/**
	 * Default role-to-capability mapping applied on plugin activation.
	 *
	 * @return array<string, list<string>>
	 */
	public static function get_role_map(): array {
		return [
			'administrator' => [
				self::CAP_MANAGE,
				self::CAP_APPROVE_AI,
			],
			'editor'        => [
				self::CAP_APPROVE_AI,
			],
		];
	}

	/**
	 * Return all custom capabilities defined by RankMySEO.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return [
			self::CAP_MANAGE,
			self::CAP_APPROVE_AI,
		];
	}

	/**
	 * Grant custom capabilities to default WordPress roles.
	 *
	 * @return void
	 */
	public static function add_caps(): void {
		foreach ( self::get_role_map() as $role_slug => $caps ) {
			$role = get_role( $role_slug );

			if ( null === $role ) {
				continue;
			}

			foreach ( $caps as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Remove RankMySEO custom capabilities from all registered roles.
	 *
	 * @return void
	 */
	public static function remove_caps(): void {
		$wp_roles = wp_roles();

		foreach ( array_keys( $wp_roles->roles ) as $role_slug ) {
			$role = get_role( (string) $role_slug );

			if ( null === $role ) {
				continue;
			}

			foreach ( self::all() as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Check whether the current user can manage RankMySEO settings.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::CAP_MANAGE ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check whether the current user can approve or roll back AI actions.
	 *
	 * @return bool
	 */
	public static function current_user_can_approve_ai(): bool {
		return current_user_can( self::CAP_APPROVE_AI ) || self::current_user_can_manage();
	}
}
