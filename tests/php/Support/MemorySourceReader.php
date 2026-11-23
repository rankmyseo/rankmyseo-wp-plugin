<?php
/**
 * In-memory source reader for importer tests.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests\Support;

use RankMySEO\Modules\Importer\SourceReader;

/**
 * Stores Yoast, Rank Math, and All in One SEO fixtures.
 */
final class MemorySourceReader implements SourceReader {
	/**
	 * Active plugin basenames.
	 *
	 * @var list<string>
	 */
	public array $active = [];

	/**
	 * Posts keyed by ID.
	 *
	 * @var array<int, array{label: string, meta: array<string, mixed>}>
	 */
	public array $posts = [];

	/**
	 * Terms keyed by ID.
	 *
	 * @var array<int, array{label: string, meta: array<string, mixed>}>
	 */
	public array $terms = [];

	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	public array $options = [];

	/**
	 * Plugin tables keyed by unprefixed suffix.
	 *
	 * @var array<string, list<array<string, mixed>>>
	 */
	public array $tables = [];

	/**
	 * {@inheritDoc}
	 */
	public function plugin_active( array $files ): bool {
		foreach ( $files as $file ) {
			if ( in_array( $file, $this->active, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_posts_with_meta( array $keys, array $exclude_ids = [] ): int {
		return count( $this->post_ids_with_meta( $keys, 0, count( $this->posts ), $exclude_ids ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function post_ids_with_meta( array $keys, int $offset, int $limit, array $exclude_ids = [] ): array {
		return $this->ids_with_meta( $this->posts, $keys, $offset, $limit, $exclude_ids );
	}

	/**
	 * {@inheritDoc}
	 */
	public function post_meta( int $post_id, array $keys ): array {
		return $this->pick( $this->posts[ $post_id ]['meta'] ?? [], $keys );
	}

	/**
	 * {@inheritDoc}
	 */
	public function post_label( int $post_id ): string {
		return (string) ( $this->posts[ $post_id ]['label'] ?? '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_terms_with_meta( array $keys ): int {
		return count( $this->term_ids_with_meta( $keys, 0, count( $this->terms ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function term_ids_with_meta( array $keys, int $offset, int $limit ): array {
		return $this->ids_with_meta( $this->terms, $keys, $offset, $limit, [] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function term_meta( int $term_id, array $keys ): array {
		return $this->pick( $this->terms[ $term_id ]['meta'] ?? [], $keys );
	}

	/**
	 * {@inheritDoc}
	 */
	public function term_label( int $term_id ): string {
		return (string) ( $this->terms[ $term_id ]['label'] ?? '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function option( string $key ) {
		return $this->options[ $key ] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function table_exists( string $suffix ): bool {
		return isset( $this->tables[ $suffix ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_table( string $suffix ): int {
		return count( $this->tables[ $suffix ] ?? [] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function table_rows( string $suffix, int $offset, int $limit ): array {
		$rows = $this->tables[ $suffix ] ?? [];
		usort(
			$rows,
			static function ( array $left, array $right ): int {
				return ( (int) ( $left['id'] ?? 0 ) ) <=> ( (int) ( $right['id'] ?? 0 ) );
			}
		);
		return array_slice( $rows, $offset, $limit );
	}

	/**
	 * {@inheritDoc}
	 */
	public function table_column( string $suffix, string $column ): array {
		$ids = [];
		foreach ( $this->tables[ $suffix ] ?? [] as $row ) {
			$id = (int) ( $row[ $column ] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Page object IDs that have a non-empty selected meta value.
	 *
	 * @param array<int, array{label: string, meta: array<string, mixed>}> $objects Objects.
	 * @param list<string>                                                  $keys    Meta keys.
	 * @param int                                                           $offset  Offset.
	 * @param int                                                           $limit   Limit.
	 * @param list<int>                                                     $exclude IDs to skip.
	 * @return list<int>
	 */
	private function ids_with_meta( array $objects, array $keys, int $offset, int $limit, array $exclude ): array {
		$ids = [];
		foreach ( $objects as $id => $object ) {
			if ( in_array( (int) $id, $exclude, true ) ) {
				continue;
			}
			$meta = $object['meta'] ?? [];
			foreach ( $keys as $key ) {
				if ( ! isset( $meta[ $key ] ) ) {
					continue;
				}
				if ( '' === $meta[ $key ] || null === $meta[ $key ] || [] === $meta[ $key ] ) {
					continue;
				}
				$ids[] = (int) $id;
				break;
			}
		}
		sort( $ids );
		return array_slice( $ids, $offset, $limit );
	}

	/**
	 * Copy selected keys.
	 *
	 * @param array<string, mixed> $meta Source meta.
	 * @param list<string>         $keys Keys to keep.
	 * @return array<string, mixed>
	 */
	private function pick( array $meta, array $keys ): array {
		$found = [];
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $meta ) ) {
				$found[ $key ] = $meta[ $key ];
			}
		}
		return $found;
	}
}
