<?php
/**
 * Database access for redirect rules.
 *
 * @package RankMySEO
 */

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag -- Method contracts are documented on RedirectStore.

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

use RankMySEO\Database\Schema;
use wpdb;

/**
 * Prepared-statement access to {$prefix}rankmyseo_redirects.
 */
final class WpdbRedirectStore implements RedirectStore {
	/**
	 * Columns returned to the service.
	 */
	private const COLUMNS = 'id, source_path, source_hash, match_type, target_url, status_code, is_active, hits, last_hit_at, source_origin, created_at, updated_at';

	/**
	 * Maximum dynamic rules loaded for a single request.
	 */
	private const RULE_CAP = 200;

	/**
	 * WordPress database adapter.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database adapter.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_active_exact_by_hash( string $hash ): ?array {
		$sql = $this->prepare(
			'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE is_active = 1 AND match_type = %s AND source_hash = %s LIMIT 1",
			'exact',
			$hash
		);

		$row = $this->get_row( $sql );
		return null === $row ? null : RowShape::redirect( $row );
	}

	/**
	 * {@inheritDoc}
	 */
	public function active_rules( string $match_type, int $limit ): array {
		$limit = max( 1, min( self::RULE_CAP, $limit ) );
		$sql   = $this->prepare(
			'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE is_active = 1 AND match_type = %s ORDER BY id ASC LIMIT %d",
			$match_type,
			$limit
		);

		return array_map(
			static fn( array $row ): array => RowShape::redirect( $row ),
			$this->get_results( $sql )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function find( int $id ): ?array {
		$sql = $this->prepare(
			'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE id = %d LIMIT 1",
			$id
		);
		$row = $this->get_row( $sql );
		return null === $row ? null : RowShape::redirect( $row );
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_duplicate( string $hash, string $match_type, int $except_id = 0 ): ?array {
		$sql = $this->prepare(
			'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE source_hash = %s AND match_type = %s AND id != %d LIMIT 1",
			$hash,
			$match_type,
			$except_id
		);
		$row = $this->get_row( $sql );
		return null === $row ? null : RowShape::redirect( $row );
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert( array $row ): int {
		$now    = gmdate( 'Y-m-d H:i:s' );
		$target = $row['target_url'] ?? null;
		$ok     = $this->wpdb->insert(
			$this->table(),
			[
				'source_path'   => (string) ( $row['source_path'] ?? '' ),
				'source_hash'   => (string) ( $row['source_hash'] ?? '' ),
				'match_type'    => (string) ( $row['match_type'] ?? 'exact' ),
				'target_url'    => is_string( $target ) && '' !== $target ? $target : null,
				'status_code'   => (int) ( $row['status_code'] ?? 301 ),
				'is_active'     => empty( $row['is_active'] ) ? 0 : 1,
				'hits'          => (int) ( $row['hits'] ?? 0 ),
				'last_hit_at'   => null,
				'source_origin' => (string) ( $row['source_origin'] ?? 'manual' ),
				'created_at'    => (string) ( $row['created_at'] ?? $now ),
				'updated_at'    => (string) ( $row['updated_at'] ?? $now ),
			],
			[ '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $ok ) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update( int $id, array $fields ): bool {
		$data   = [];
		$format = [];

		$map = [
			'source_path'   => '%s',
			'source_hash'   => '%s',
			'match_type'    => '%s',
			'target_url'    => '%s',
			'status_code'   => '%d',
			'is_active'     => '%d',
			'source_origin' => '%s',
			'updated_at'    => '%s',
		];

		foreach ( $map as $column => $placeholder ) {
			if ( ! array_key_exists( $column, $fields ) ) {
				continue;
			}
			$value = $fields[ $column ];
			if ( 'is_active' === $column ) {
				$value = empty( $value ) ? 0 : 1;
			}
			if ( 'target_url' === $column && ( null === $value || '' === $value ) ) {
				$value = null;
			}
			$data[ $column ] = $value;
			$format[]        = $placeholder;
		}

		if ( [] === $data ) {
			return false;
		}

		if ( ! isset( $data['updated_at'] ) ) {
			$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
			$format[]           = '%s';
		}

		$result = $this->wpdb->update(
			$this->table(),
			$data,
			[ 'id' => $id ],
			$format,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( int $id ): bool {
		$result = $this->wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
		return false !== $result && $result > 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_ids( array $ids ): int {
		$ids = $this->positive_ids( $ids );
		if ( [] === $ids ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = $this->prepare(
			"DELETE FROM {$this->table()} WHERE id IN ({$placeholders})",
			...$ids
		);

		return $this->affected( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_active_ids( array $ids, bool $active ): int {
		$ids = $this->positive_ids( $ids );
		if ( [] === $ids ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$args         = array_merge( [ $active ? 1 : 0, gmdate( 'Y-m-d H:i:s' ) ], $ids );
		$sql          = $this->prepare(
			"UPDATE {$this->table()} SET is_active = %d, updated_at = %s WHERE id IN ({$placeholders})",
			...$args
		);

		return $this->affected( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( array $filters ): array {
		[$where, $args] = $this->where_clause( $filters );
		$page           = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$per_page       = max( 1, min( 100, (int) ( $filters['per_page'] ?? 20 ) ) );
		$offset         = ( $page - 1 ) * $per_page;
		$orderby        = $this->orderby_column( (string) ( $filters['orderby'] ?? 'id' ) );
		$order          = 'ASC' === strtoupper( (string) ( $filters['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';

		$count_sql = $this->prepare(
			"SELECT COUNT(*) FROM {$this->table()} WHERE {$where}",
			...$args
		);
		$total     = (int) $this->get_var( $count_sql );

		$list_args = array_merge( $args, [ $per_page, $offset ] );
		$list_sql  = $this->prepare(
			'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			...$list_args
		);

		$items = array_map(
			static fn( array $row ): array => RowShape::redirect( $row ),
			$this->get_results( $list_sql )
		);

		return [
			'items' => $items,
			'total' => $total,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function increment_hits( int $id, int $throttle_seconds ): bool {
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( $throttle_seconds > 0 ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - $throttle_seconds );
			$sql    = $this->prepare(
				"UPDATE {$this->table()} SET hits = hits + 1, last_hit_at = %s WHERE id = %d AND (last_hit_at IS NULL OR last_hit_at < %s)",
				$now,
				$id,
				$cutoff
			);
		} else {
			$sql = $this->prepare(
				"UPDATE {$this->table()} SET hits = hits + 1, last_hit_at = %s WHERE id = %d",
				$now,
				$id
			);
		}

		return $this->affected( $sql ) > 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function exact_target_map( int $except_id = 0 ): array {
		$sql  = $this->prepare(
			"SELECT source_path, target_url FROM {$this->table()} WHERE is_active = 1 AND match_type = %s AND id != %d AND target_url IS NOT NULL AND target_url != '' LIMIT 5000",
			'exact',
			$except_id
		);
		$rows = $this->get_results( $sql );
		$map  = [];

		foreach ( $rows as $row ) {
			$source = (string) ( $row['source_path'] ?? '' );
			$target = (string) ( $row['target_url'] ?? '' );
			if ( '' !== $source && '' !== $target ) {
				$map[ $source ] = $target;
			}
		}

		return $map;
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_for_retarget(): array {
		$sql = $this->prepare(
			'SELECT id, source_path, target_url, match_type, status_code FROM ' . $this->table() . ' WHERE is_active = %d LIMIT 5000',
			1
		);

		return $this->get_results( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function export_rows(): array {
		$sql = $this->prepare(
			'SELECT ' . self::COLUMNS . ' FROM ' . $this->table() . ' WHERE id > %d ORDER BY id ASC LIMIT 10000',
			0
		);

		return array_map(
			static fn( array $row ): array => RowShape::redirect( $row ),
			$this->get_results( $sql )
		);
	}

	/**
	 * Build a WHERE clause and placeholder arguments from admin filters.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array{0: string, 1: list<mixed>}
	 */
	private function where_clause( array $filters ): array {
		$where = [ '1 = %d' ];
		$args  = [ 1 ];

		$search = trim( (string) ( $filters['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like    = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where[] = '(source_path LIKE %s OR target_url LIKE %s)';
			$args[]  = $like;
			$args[]  = $like;
		}

		$status = (int) ( $filters['status_code'] ?? 0 );
		if ( $status > 0 ) {
			$where[] = 'status_code = %d';
			$args[]  = $status;
		}

		$match_type = (string) ( $filters['match_type'] ?? '' );
		if ( in_array( $match_type, [ 'exact', 'prefix', 'regex' ], true ) ) {
			$where[] = 'match_type = %s';
			$args[]  = $match_type;
		}

		$origin = (string) ( $filters['origin'] ?? '' );
		if ( in_array( $origin, [ 'manual', 'auto_slug', 'import', 'ai' ], true ) ) {
			$where[] = 'source_origin = %s';
			$args[]  = $origin;
		}

		if ( array_key_exists( 'active', $filters ) && null !== $filters['active'] && '' !== $filters['active'] ) {
			$where[] = 'is_active = %d';
			$args[]  = empty( $filters['active'] ) ? 0 : 1;
		}

		return [ implode( ' AND ', $where ), $args ];
	}

	/**
	 * Whitelist an ORDER BY column.
	 *
	 * @param string $orderby Requested column.
	 * @return string
	 */
	private function orderby_column( string $orderby ): string {
		$allowed = [
			'id'          => 'id',
			'hits'        => 'hits',
			'updated_at'  => 'updated_at',
			'source_path' => 'source_path',
			'last_hit_at' => 'last_hit_at',
			'status_code' => 'status_code',
		];

		return $allowed[ $orderby ] ?? 'id';
	}

	/**
	 * Keep positive integer IDs only.
	 *
	 * @param int[] $ids Raw IDs.
	 * @return int[]
	 */
	private function positive_ids( array $ids ): array {
		$clean = [];
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Validated redirects table name.
	 *
	 * @return string
	 */
	private function table(): string {
		$name = Schema::get_table_name( 'redirects', $this->wpdb->prefix );
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $name ) ) {
			return 'rankmyseo_redirects';
		}

		return $name;
	}

	/**
	 * Prepare a SQL statement.
	 *
	 * Table names are validated identifiers, never request data.
	 *
	 * @param string $query SQL with placeholders.
	 * @param mixed  ...$args Placeholder values.
	 * @return string
	 */
	private function prepare( string $query, ...$args ): string {
		if ( [] === $args ) {
			$args = [ 1 ];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are bound on the next line. Identifiers are validated.
		$prepared = $this->wpdb->prepare( $query, ...$args );
		return is_string( $prepared ) ? $prepared : '';
	}

	/**
	 * Fetch one associative row.
	 *
	 * @param string $sql Prepared SQL.
	 * @return array<string, mixed>|null
	 */
	private function get_row( string $sql ): ?array {
		if ( '' === $sql ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch associative rows.
	 *
	 * @param string $sql Prepared SQL.
	 * @return list<array<string, mixed>>
	 */
	private function get_results( string $sql ): array {
		if ( '' === $sql ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$list = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$list[] = $row;
			}
		}

		return $list;
	}

	/**
	 * Fetch a scalar.
	 *
	 * @param string $sql Prepared SQL.
	 * @return string|null
	 */
	private function get_var( string $sql ): ?string {
		if ( '' === $sql ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$value = $this->wpdb->get_var( $sql );
		if ( null === $value || false === $value ) {
			return null;
		}

		return (string) $value;
	}

	/**
	 * Run a prepared write and return affected rows.
	 *
	 * @param string $sql Prepared SQL.
	 * @return int
	 */
	private function affected( string $sql ): int {
		if ( '' === $sql ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );
		return is_int( $result ) ? $result : 0;
	}
}
