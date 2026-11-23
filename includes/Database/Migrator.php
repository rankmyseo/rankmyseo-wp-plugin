<?php
/**
 * Versioned custom database schema migrator.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Database;

use wpdb;

/**
 * Runs versioned dbDelta migrations and optional uninstall table cleanup.
 */
final class Migrator {
	/**
	 * WordPress option storing the installed schema version integer.
	 */
	public const OPTION_DB_VERSION = 'rankmyseo_db_version';

	/**
	 * WordPress database adapter.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Optional custom dbDelta runner (used in unit tests).
	 *
	 * @var callable|null
	 */
	private $db_delta_runner;

	/**
	 * Constructor.
	 *
	 * @param wpdb          $wpdb            WordPress database instance.
	 * @param callable|null $db_delta_runner Optional callable matching dbDelta( string $sql ).
	 */
	public function __construct( wpdb $wpdb, ?callable $db_delta_runner = null ) {
		$this->wpdb            = $wpdb;
		$this->db_delta_runner = $db_delta_runner;
	}

	/**
	 * Return the installed schema version stored in options.
	 *
	 * @return int
	 */
	public function get_installed_version(): int {
		return (int) get_option( self::OPTION_DB_VERSION, 0 );
	}

	/**
	 * Return the target schema version defined in code.
	 *
	 * @return int
	 */
	public function get_target_version(): int {
		return Schema::DB_VERSION;
	}

	/**
	 * Check whether the database schema requires migration.
	 *
	 * @return bool
	 */
	public function needs_migration(): bool {
		return $this->get_installed_version() < $this->get_target_version();
	}

	/**
	 * Run dbDelta for all schema definitions and update the schema version option.
	 *
	 * @param bool $force Run migrations even if installed version matches target.
	 * @return bool True when migrations ran, false when already up to date.
	 */
	public function migrate( bool $force = false ): bool {
		if ( ! $force && ! $this->needs_migration() ) {
			return false;
		}

		$runner = $this->resolve_db_delta_runner();
		$sqls   = Schema::get_schema_sql(
			$this->wpdb->prefix,
			$this->wpdb->get_charset_collate()
		);

		foreach ( $sqls as $sql ) {
			$runner( $sql );
		}

		update_option( self::OPTION_DB_VERSION, $this->get_target_version(), false );

		return true;
	}

	/**
	 * Check existence of every plugin custom table in the database.
	 *
	 * @return array<string, array{name: string, exists: bool}>
	 */
	public function verify_tables(): array {
		$wpdb   = $this->wpdb;
		$tables = Schema::get_table_names( $wpdb->prefix );
		$status = [];

		foreach ( $tables as $key => $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
			);

			$status[ $key ] = [
				'name'   => $table_name,
				'exists' => ( $found === $table_name ),
			];
		}

		return $status;
	}

	/**
	 * Drop all RankMySEO custom tables (invoked during uninstall when cleanup is enabled).
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		foreach ( Schema::TABLE_SUFFIXES as $suffix ) {
			$table_name = esc_sql( $this->wpdb->prefix . $suffix );

			// Table names come from the fixed Schema::TABLE_SUFFIXES allowlist.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
		}

		delete_option( self::OPTION_DB_VERSION );
	}

	/**
	 * Resolve the dbDelta callable, loading wp-admin/includes/upgrade.php if needed.
	 *
	 * @return callable
	 */
	private function resolve_db_delta_runner(): callable {
		if ( null !== $this->db_delta_runner ) {
			return $this->db_delta_runner;
		}

		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) ) {
			$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( file_exists( $upgrade_file ) ) {
				require_once $upgrade_file;
			}
		}

		return static function ( string $sql ): void {
			if ( function_exists( 'dbDelta' ) ) {
				dbDelta( $sql );
			}
		};
	}
}
