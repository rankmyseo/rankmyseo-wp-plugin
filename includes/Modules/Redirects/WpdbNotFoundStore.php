<?php
/**
 * Database access for the deduplicated 404 log.
 *
 * @package RankMySEO
 */

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag -- Method contracts are documented on NotFoundStore.

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

use RankMySEO\Database\Schema;
use wpdb;

/**
 * Prepared-statement access to {$prefix}rankmyseo_404_logs.
 */
final class WpdbNotFoundStore implements NotFoundStore {
	/**
	 * Columns returned to the monitor.
	 */
	private const COLUMNS = 'id, request_path, path_hash, query_string, referer, user_agent, hits, status, first_seen_at, last_seen_at';

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
	public function find( int $id ): ?array {
		$sql = $this->prepare(
			'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE id = %d LIMIT 1",
			$id
		);
		$row = $this->get_row( $sql );
		return null === $row ? null : RowShape::not_found( $row );
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_by_hash( string $hash ): ?array {
		$sql = $this->prepare(
			'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE path_hash = %s LIMIT 1",
			$hash
		);
		$row = $this->get_row( $sql );
		return null === $row ? null : RowShape::not_found( $row );
	}

	/**
	 * {@inheritDoc}
	 */
	public function upsert( string $path, string $hash, string $query, string $referer, string $user_agent, string $now ): int {
		$sql = $this->prepare(
			"INSERT INTO {$this->table()} (request_path, path_hash, query_string, referer, user_agent, hits, status, first_seen_at, last_seen_at) VALUES (%s, %s, %s, %s, %s, %d, %s, %s, %s) ON DUPLICATE KEY UPDATE hits = IF(status = 'ignored', hits, hits + 1), last_seen_at = IF(status = 'ignored', last_seen_at, VALUES(last_seen_at)), query_string = IF(status = 'ignored', query_string, VALUES(query_string)), referer = IF(status = 'ignored', referer, VALUES(referer)), user_agent = IF(status = 'ignored', user_agent, VALUES(user_agent))",
			$path,
			$hash,
			$query,
			$referer,
			$user_agent,
			1,
			'open',
			$now,
			$now
		);

		$this->affected( $sql );

		$found = $this->find_by_hash( $hash );
		return null === $found ? 0 : (int) $found['id'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( array $filters ): array {
		$where = [ '1 = %d' ];
		$args  = [ 1 ];

		$status = (string) ( $filters['status'] ?? '' );
		if ( in_array( $status, [ 'open', 'redirected', 'ignored' ], true ) ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}

		$search = trim( (string) ( $filters['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like    = '%' . $this->wpdb->esc_like( $search ) . '%';
			$where[] = 'request_path LIKE %s';
			$args[]  = $like;
		}

		$page      = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$per_page  = max( 1, min( 100, (int) ( $filters['per_page'] ?? 20 ) ) );
		$offset    = ( $page - 1 ) * $per_page;
		$orderby   = 'last_seen_at' === ( $filters['orderby'] ?? '' ) ? 'last_seen_at' : 'hits';
		$order     = 'ASC' === strtoupper( (string) ( $filters['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$where_sql = implode( ' AND ', $where );

		$count_sql = $this->prepare(
			"SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}",
			...$args
		);
		$total     = (int) $this->get_var( $count_sql );

		$list_args = array_merge( $args, [ $per_page, $offset ] );
		$list_sql  = $this->prepare(
			'SELECT ' . self::COLUMNS . " FROM {$this->table()} WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d",
			...$list_args
		);

		return [
			'items' => array_map(
				static fn( array $row ): array => RowShape::not_found( $row ),
				$this->get_results( $list_sql )
			),
			'total' => $total,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_status( int $id, string $status ): bool {
		if ( ! in_array( $status, [ 'open', 'redirected', 'ignored' ], true ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table(),
			[ 'status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
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
	public function ignore_ids( array $ids ): int {
		$ids = $this->positive_ids( $ids );
		if ( [] === $ids ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$args         = array_merge( [ 'ignored' ], $ids );
		$sql          = $this->prepare(
			"UPDATE {$this->table()} SET status = %s WHERE id IN ({$placeholders})",
			...$args
		);

		return $this->affected( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_older_than( string $cutoff_mysql ): int {
		$sql = $this->prepare(
			"DELETE FROM {$this->table()} WHERE last_seen_at < %s",
			$cutoff_mysql
		);

		return $this->affected( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function trim_to( int $max_rows ): int {
		$max_rows = max( 1, $max_rows );
		$count    = (int) $this->get_var(
			$this->prepare( 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE id > %d', 0 )
		);

		if ( $count <= $max_rows ) {
			return 0;
		}

		$excess = $count - $max_rows;
		$sql    = $this->prepare(
			"DELETE FROM {$this->table()} ORDER BY last_seen_at ASC, id ASC LIMIT %d",
			$excess
		);

		return $this->affected( $sql );
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
	 * Validated 404 log table name.
	 *
	 * @return string
	 */
	private function table(): string {
		$name = Schema::get_table_name( '404_logs', $this->wpdb->prefix );
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $name ) ) {
			return 'rankmyseo_404_logs';
		}

		return $name;
	}

	/**
	 * Prepare a SQL statement.
	 *
	 * @param string $query SQL with placeholders.
	 * @param mixed  ...$args Placeholder values.
	 * @return string
	 */
	private function prepare( string $query, ...$args ): string {
		if ( [] === $args ) {
			$args = [ 1 ];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are bound here. Identifiers are validated.
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
