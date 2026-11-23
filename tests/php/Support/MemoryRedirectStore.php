<?php
/**
 * In-memory redirect store for unit tests.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests\Support;

use RankMySEO\Modules\Redirects\RedirectStore;
use RankMySEO\Modules\Redirects\RowShape;

/**
 * Array-backed RedirectStore used by PHPUnit.
 */
final class MemoryRedirectStore implements RedirectStore {
	/**
	 * Stored rows keyed by ID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $rows = [];

	/**
	 * Exact-hash lookup counter.
	 *
	 * @var int
	 */
	public int $exact_lookups = 0;

	/**
	 * Next ID.
	 *
	 * @var int
	 */
	private int $next_id = 1;

	/**
	 * {@inheritDoc}
	 */
	public function find_active_exact_by_hash( string $hash ): ?array {
		++$this->exact_lookups;
		foreach ( $this->rows as $row ) {
			if ( 1 === (int) $row['is_active'] && 'exact' === $row['match_type'] && $hash === $row['source_hash'] ) {
				return RowShape::redirect( $row );
			}
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function active_rules( string $match_type, int $limit ): array {
		$rules = [];
		foreach ( $this->rows as $row ) {
			if ( 1 === (int) $row['is_active'] && $match_type === $row['match_type'] ) {
				$rules[] = RowShape::redirect( $row );
			}
			if ( count( $rules ) >= $limit ) {
				break;
			}
		}

		return $rules;
	}

	/**
	 * {@inheritDoc}
	 */
	public function find( int $id ): ?array {
		if ( ! isset( $this->rows[ $id ] ) ) {
			return null;
		}

		return RowShape::redirect( $this->rows[ $id ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_duplicate( string $hash, string $match_type, int $except_id = 0 ): ?array {
		foreach ( $this->rows as $id => $row ) {
			if ( $id === $except_id ) {
				continue;
			}
			if ( $hash === $row['source_hash'] && $match_type === $row['match_type'] ) {
				return RowShape::redirect( $row );
			}
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert( array $row ): int {
		$id                 = $this->next_id++;
		$row['id']          = $id;
		$row['is_active']   = empty( $row['is_active'] ) ? 0 : 1;
		$row['hits']        = (int) ( $row['hits'] ?? 0 );
		$row['last_hit_at'] = $row['last_hit_at'] ?? null;
		$this->rows[ $id ]  = $row;
		return $id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update( int $id, array $fields ): bool {
		if ( ! isset( $this->rows[ $id ] ) ) {
			return false;
		}

		foreach ( $fields as $key => $value ) {
			if ( 'is_active' === $key ) {
				$value = empty( $value ) ? 0 : 1;
			}
			$this->rows[ $id ][ $key ] = $value;
		}

		$this->rows[ $id ]['updated_at'] = gmdate( 'Y-m-d H:i:s' );
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
	public function set_active_ids( array $ids, bool $active ): int {
		$count = 0;
		foreach ( $ids as $id ) {
			if ( $this->update( (int) $id, [ 'is_active' => $active ] ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function search( array $filters ): array {
		$items = [];
		foreach ( $this->rows as $row ) {
			$shaped = RowShape::redirect( $row );
			$search = strtolower( (string) ( $filters['search'] ?? '' ) );
			if ( '' !== $search && ! str_contains( strtolower( (string) $shaped['source_path'] ), $search ) && ! str_contains( strtolower( (string) ( $shaped['target_url'] ?? '' ) ), $search ) ) {
				continue;
			}
			$status = (int) ( $filters['status_code'] ?? 0 );
			if ( $status > 0 && $status !== (int) $shaped['status_code'] ) {
				continue;
			}
			$match_type = (string) ( $filters['match_type'] ?? '' );
			if ( '' !== $match_type && $match_type !== $shaped['match_type'] ) {
				continue;
			}
			$items[] = $shaped;
		}

		$total    = count( $items );
		$page     = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$per_page = max( 1, (int) ( $filters['per_page'] ?? 20 ) );
		$offset   = ( $page - 1 ) * $per_page;

		return [
			'items' => array_slice( $items, $offset, $per_page ),
			'total' => $total,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function increment_hits( int $id, int $throttle_seconds ): bool {
		if ( ! isset( $this->rows[ $id ] ) ) {
			return false;
		}

		$last = $this->rows[ $id ]['last_hit_at'] ?? null;
		if ( $throttle_seconds > 0 && is_string( $last ) && '' !== $last ) {
			$stamp = strtotime( $last . ' UTC' );
			if ( false !== $stamp && $stamp > time() - $throttle_seconds ) {
				return false;
			}
		}

		$this->rows[ $id ]['hits']        = (int) $this->rows[ $id ]['hits'] + 1;
		$this->rows[ $id ]['last_hit_at'] = gmdate( 'Y-m-d H:i:s' );
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function exact_target_map( int $except_id = 0 ): array {
		$map = [];
		foreach ( $this->rows as $id => $row ) {
			if ( $id === $except_id || 1 !== (int) $row['is_active'] || 'exact' !== $row['match_type'] ) {
				continue;
			}
			$target = $row['target_url'] ?? '';
			if ( ! is_string( $target ) || '' === $target ) {
				continue;
			}
			$map[ (string) $row['source_path'] ] = $target;
		}
		return $map;
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_for_retarget(): array {
		$list = [];
		foreach ( $this->rows as $row ) {
			if ( 1 !== (int) $row['is_active'] ) {
				continue;
			}
			$list[] = [
				'id'          => (int) $row['id'],
				'source_path' => (string) $row['source_path'],
				'target_url'  => $row['target_url'] ?? '',
				'match_type'  => (string) $row['match_type'],
				'status_code' => (int) $row['status_code'],
			];
		}
		return $list;
	}

	/**
	 * {@inheritDoc}
	 */
	public function export_rows(): array {
		$list = [];
		foreach ( $this->rows as $row ) {
			$list[] = RowShape::redirect( $row );
		}
		return $list;
	}
}
