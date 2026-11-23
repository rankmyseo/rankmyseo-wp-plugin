<?php
/**
 * Opt-in data removal for uninstall.php.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Core;

use RankMySEO\Database\Migrator;
use wpdb;

/**
 * Deletes RankMySEO data only when the administrator opted in.
 */
final class Uninstaller {
	/**
	 * Whether uninstall should remove stored data.
	 *
	 * Deletion runs only when delete_data_on_uninstall is enabled. Older saves
	 * that only stored keep_data_on_uninstall = false are treated the same way.
	 *
	 * @param mixed $settings Stored rankmyseo_settings option.
	 * @return bool
	 */
	public static function should_delete( $settings ): bool {
		if ( ! is_array( $settings ) ) {
			return false;
		}

		if ( array_key_exists( 'delete_data_on_uninstall', $settings ) ) {
			return self::is_truthy( $settings['delete_data_on_uninstall'] );
		}

		if ( array_key_exists( 'keep_data_on_uninstall', $settings ) ) {
			return ! self::is_truthy( $settings['keep_data_on_uninstall'] );
		}

		return false;
	}

	/**
	 * Remove options, transients, cron hooks, meta, capabilities, and tables.
	 *
	 * @param wpdb $wpdb WordPress database handle.
	 * @return void
	 */
	public static function purge( wpdb $wpdb ): void {
		Deactivator::clear_scheduled_hooks();
		Capabilities::remove_caps();

		$migrator = new Migrator( $wpdb );
		$migrator->drop_tables();

		self::delete_like(
			$wpdb,
			$wpdb->options,
			'option_name',
			[
				'rankmyseo_',
				'_transient_rankmyseo_',
				'_transient_timeout_rankmyseo_',
				'_site_transient_rankmyseo_',
				'_site_transient_timeout_rankmyseo_',
			]
		);

		self::delete_like(
			$wpdb,
			$wpdb->postmeta,
			'meta_key',
			[ '_rankmyseo_' ]
		);

		$termmeta = $wpdb->termmeta;
		if ( is_string( $termmeta ) && '' !== $termmeta ) {
			self::delete_like(
				$wpdb,
				$termmeta,
				'meta_key',
				[ '_rankmyseo_' ]
			);
		}

		self::delete_like(
			$wpdb,
			$wpdb->usermeta,
			'meta_key',
			[ 'rankmyseo_', '_rankmyseo_' ]
		);
	}

	/**
	 * Coerce a stored flag the same way settings sanitization does.
	 *
	 * @param mixed $value Raw option value.
	 * @return bool
	 */
	private static function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, [ '1', 'true', 'yes', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $lower, [ '0', 'false', 'no', 'off', '' ], true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}

	/**
	 * Delete rows whose column starts with one of the prefixes.
	 *
	 * @param wpdb               $wpdb     Database handle.
	 * @param string             $table    Prefixed table name from $wpdb.
	 * @param string             $column   Allowlisted column name.
	 * @param array<int, string> $prefixes Prefixes to match.
	 * @return void
	 */
	private static function delete_like( wpdb $wpdb, string $table, string $column, array $prefixes ): void {
		if ( '' === $table || [] === $prefixes ) {
			return;
		}

		if ( ! in_array( $column, [ 'option_name', 'meta_key' ], true ) ) {
			return;
		}

		$clauses = [];
		$args    = [];
		foreach ( $prefixes as $prefix ) {
			$clauses[] = $column . ' LIKE %s';
			$args[]    = $wpdb->esc_like( $prefix ) . '%';
		}

		$sql = 'DELETE FROM `' . esc_sql( $table ) . '` WHERE ' . implode( ' OR ', $clauses );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are bound here. Table and column names are allowlisted.
		$wpdb->query( $wpdb->prepare( $sql, ...$args ) );
	}
}
