<?php
/**
 * In-memory 404 log for unit tests.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests\Support;

use RankMySEO\Modules\Redirects\NotFoundStore;
use RankMySEO\Modules\Redirects\RowShape;

/**
 * Array-backed NotFoundStore used by PHPUnit.
 */
final class MemoryNotFoundStore implements NotFoundStore {
	/**
	 * Rows keyed by ID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $rows = [];

	/**
	 * Next ID.
	 *
	 * @var int
	 */
	private int $next_id = 1;

	/**
	 * {@inheritDoc}
	 */
	public function find( int $id ): ?array {
		return isset( $this->rows[ $id ] ) ? RowShape::not_found( $this->rows[ $id ] ) : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_by_hash( string $hash ): ?array {
		foreach ( $this->rows as $row ) {
			if ( $hash === $row['path_hash'] ) {
				return RowShape::not_found( $row );
			}
		}
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function upsert( string $path, string $hash, string $query, string $referer, string $user_agent, string $now ): int {
		foreach ( $this->rows as $id => $row ) {
			if ( $hash !== $row['path_hash'] ) {
				continue;
			}
			if ( 'ignored' === $row['status'] ) {
				return (int) $id;
			}
			$this->rows[ $id ]['hits']         = (int) $row['hits'] + 1;
			$this->rows[ $id ]['last_seen_at'] = $now;
			$this->rows[ $id ]['query_string'] = $query;
			$this->rows[ $id ]['referer']      = $referer;
			$this->rows[ $id ]['user_agent']   = $user_agent;
			return (int) $id;
		}

		$id                = $this->next_id++;
		$this->rows[ $id ] = [
			'id'            => $id,
			'request_path'  => $path,
			'path_hash'     => $hash,
			'query_string'  => $query,
			'referer'       => $referer,
			'user_agent'    => $user_agent,
			'hits'          => 1,
			'status'        => 'open',
			'first_seen_at' => $now,
			'last_seen_at'  => $now,
		];

		return $id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( array $filters ): array {
		$items = [];
		foreach ( $this->rows as $row ) {
			$shaped = RowShape::not_found( $row );
			$status = (string) ( $filters['status'] ?? '' );
			if ( '' !== $status && $status !== $shaped['status'] ) {
				continue;
			}
			$search = strtolower( (string) ( $filters['search'] ?? '' ) );
			if ( '' !== $search && ! str_contains( strtolower( (string) $shaped['request_path'] ), $search ) ) {
				continue;
			}
			$items[] = $shaped;
		}

		usort(
			$items,
			static function ( array $left, array $right ): int {
				return (int) $right['hits'] <=> (int) $left['hits'];
			}
		);

		$total    = count( $items );
		$page     = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$per_page = max( 1, (int) ( $filters['per_page'] ?? 20 ) );

		return [
			'items' => array_slice( $items, ( $page - 1 ) * $per_page, $per_page ),
			'total' => $total,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_status( int $id, string $status ): bool {
		if ( ! isset( $this->rows[ $id ] ) ) {
			return false;
		}
		$this->rows[ $id ]['status'] = $status;
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( int $id ): bool {
		if ( ! isset( $this->rows[ $id ] ) ) {
			return false;
		}
		unset( $this->rows[ $id ] );
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_ids( array $ids ): int {
		$count = 0;
		foreach ( $ids as $id ) {
			if ( $this->delete( (int) $id ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function ignore_ids( array $ids ): int {
		$count = 0;
		foreach ( $ids as $id ) {
			if ( $this->set_status( (int) $id, 'ignored' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_older_than( string $cutoff_mysql ): int {
		$count = 0;
		foreach ( $this->rows as $id => $row ) {
			if ( (string) $row['last_seen_at'] < $cutoff_mysql ) {
				unset( $this->rows[ $id ] );
				++$count;
			}
		}
		return $count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function trim_to( int $max_rows ): int {
		if ( count( $this->rows ) <= $max_rows ) {
			return 0;
		}

		uasort(
			$this->rows,
			static function ( array $left, array $right ): int {
				return strcmp( (string) $left['last_seen_at'], (string) $right['last_seen_at'] );
			}
		);

		$excess = count( $this->rows ) - $max_rows;
		$count  = 0;
		foreach ( array_keys( $this->rows ) as $id ) {
			if ( $count >= $excess ) {
				break;
			}
			unset( $this->rows[ $id ] );
			++$count;
		}

		return $count;
	}
}
