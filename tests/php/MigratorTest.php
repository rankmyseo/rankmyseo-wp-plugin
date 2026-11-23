<?php
/**
 * Unit tests for RankMySEO\Database\Schema and Migrator.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RankMySEO\Database\Migrator;
use RankMySEO\Database\Schema;
use RankMySEO_Test_State;
use wpdb;

final class MigratorTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_schema_defines_all_expected_tables_with_dbdelta_formatting(): void {
		$sqls = Schema::get_schema_sql( 'wp_', 'DEFAULT CHARACTER SET utf8mb4' );

		$expected_keys = [
			'redirects',
			'404_logs',
			'ai_actions',
			'ai_snapshots',
			'audit_logs',
			'sync_queue',
			'import_backups',
		];

		$this->assertSame( $expected_keys, array_keys( $sqls ) );

		foreach ( $sqls as $key => $sql ) {
			$expected_table = Schema::get_table_name( $key, 'wp_' );
			$this->assertStringContainsString( "CREATE TABLE {$expected_table}", $sql );
			$this->assertStringContainsString( 'PRIMARY KEY  (id)', $sql );
			$this->assertStringContainsString( 'DEFAULT CHARACTER SET utf8mb4', $sql );
		}
	}

	public function test_schema_throws_on_unknown_table_key(): void {
		$this->expectException( InvalidArgumentException::class );
		Schema::get_table_name( 'nonexistent', 'wp_' );
	}

	public function test_migrator_runs_dbdelta_updates_version_and_is_idempotent(): void {
		$wpdb      = new wpdb();
		$executed  = [];
		$migrator  = new Migrator(
			$wpdb,
			static function ( string $sql ) use ( &$executed ): void {
				$executed[] = $sql;
			}
		);

		$this->assertTrue( $migrator->needs_migration() );
		$this->assertTrue( $migrator->migrate() );
		$this->assertCount( count( Schema::TABLE_SUFFIXES ), $executed );
		$this->assertSame( Schema::DB_VERSION, $migrator->get_installed_version() );
		$this->assertFalse( $migrator->needs_migration() );

		// Running again without force should be a no-op.
		$this->assertFalse( $migrator->migrate() );
		$this->assertCount( count( Schema::TABLE_SUFFIXES ), $executed );
	}

	public function test_verify_tables_and_drop_tables(): void {
		$wpdb                  = new wpdb();
		$wpdb->existing_tables = array_values( Schema::get_table_names( 'wp_' ) );

		$migrator = new Migrator( $wpdb, static function (): void {} );
		$migrator->migrate();

		$status = $migrator->verify_tables();
		$this->assertCount( count( Schema::TABLE_SUFFIXES ), $status );
		foreach ( $status as $table_info ) {
			$this->assertTrue( $table_info['exists'] );
		}

		$migrator->drop_tables();
		$this->assertSame( 0, $migrator->get_installed_version() );

		$drop_queries = array_values(
			array_filter(
				$wpdb->queries,
				static fn( string $query ): bool => 0 === strpos( $query, 'DROP TABLE IF EXISTS' )
			)
		);
		$this->assertCount( count( Schema::TABLE_SUFFIXES ), $drop_queries );
	}
}
