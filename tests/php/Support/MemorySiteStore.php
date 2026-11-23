<?php
/**
 * In-memory RankMySEO store for importer tests.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests\Support;

use RankMySEO\Modules\Importer\SiteStore;

/**
 * Destination store that records meta, options, redirects, and snapshots.
 */
final class MemorySiteStore implements SiteStore {
	/**
	 * Meta values. A missing key means the value was never stored.
	 *
	 * @var array<string, array<int, array<string, string>>>
	 */
	public array $meta = [];

	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	public array $options = [];

	/**
	 * Redirect rows keyed by ID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $redirects = [];

	/**
	 * Snapshot rows.
	 *
	 * @var list<array<string, mixed>>
	 */
	public array $backups = [];

	/**
	 * Next redirect ID.
	 *
	 * @var int
	 */
	private int $next_redirect = 1;

	/**
	 * Next backup ID.
	 *
	 * @var int
	 */
	private int $next_backup = 1;

	/**
	 * {@inheritDoc}
	 */
	public function get_meta( string $object_type, int $object_id, string $key ): ?string {
		if ( ! isset( $this->meta[ $object_type ][ $object_id ] ) || ! array_key_exists( $key, $this->meta[ $object_type ][ $object_id ] ) ) {
			return null;
		}
		return $this->meta[ $object_type ][ $object_id ][ $key ];
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_meta( string $object_type, int $object_id, string $key, string $value ): void {
		$this->meta[ $object_type ][ $object_id ][ $key ] = $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_meta( string $object_type, int $object_id, string $key ): void {
		unset( $this->meta[ $object_type ][ $object_id ][ $key ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_option( string $key ) {
		return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_option( string $key, $value ): void {
		$this->options[ $key ] = $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_option( string $key ): void {
		unset( $this->options[ $key ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert_redirect( array $row ): int {
		$id                  = $this->next_redirect++;
		$row['id']           = $id;
		$this->redirects[ $id ] = $row;
		return $id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_redirect_by_hash( string $hash ): ?array {
		foreach ( $this->redirects as $row ) {
			if ( ( $row['source_hash'] ?? '' ) === $hash ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_redirect( array $row ): void {
		$id = (int) ( $row['id'] ?? 0 );
		if ( $id < 1 || ! isset( $this->redirects[ $id ] ) ) {
			return;
		}
		$this->redirects[ $id ] = $row;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_redirect( int $id ): void {
		unset( $this->redirects[ $id ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert_backup( string $batch_id, string $source, string $object_type, int $object_id, array $previous ): void {
		$this->backups[] = [
			'id'            => $this->next_backup++,
			'batch_id'      => $batch_id,
			'source_plugin' => $source,
			'object_type'   => $object_type,
			'object_id'     => $object_id,
			'previous'      => $previous,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function backups( string $batch_id ): array {
		$rows = [];
		foreach ( $this->backups as $backup ) {
			if ( ( $backup['batch_id'] ?? '' ) === $batch_id ) {
				$rows[] = $backup;
			}
		}
		return $rows;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_backups( string $batch_id ): void {
		$this->backups = array_values(
			array_filter(
				$this->backups,
				static function ( array $backup ) use ( $batch_id ): bool {
					return ( $backup['batch_id'] ?? '' ) !== $batch_id;
				}
			)
		);
	}
}
