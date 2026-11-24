<?php
/**
 * Stores incoming and outgoing internal-link counts.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Links;

use RankMySEO\Database\Schema;
use wpdb;

/**
 * Edge list plus one count row per indexed post.
 */
final class LinkRepository {
	/**
	 * Database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Edge table name.
	 *
	 * @var string
	 */
	private string $edges;

	/**
	 * Count table name.
	 *
	 * @var string
	 */
	private string $counts;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb   = $wpdb;
		$this->edges  = Schema::get_table_name( 'link_edges', $wpdb->prefix );
		$this->counts = Schema::get_table_name( 'link_counts', $wpdb->prefix );
	}

	/**
	 * Replace every outgoing link from one post and refresh incoming counts.
	 *
	 * @param int             $source_id    Source post ID.
	 * @param array<int, int> $target_ids Published target post IDs.
	 * @param bool            $cornerstone  Whether the source is cornerstone content.
	 * @return void
	 */
	public function replace_outgoing( int $source_id, array $target_ids, bool $cornerstone ): void {
		$targets = [];
		foreach ( $target_ids as $target_id ) {
			$target_id = (int) $target_id;
			if ( $target_id > 0 && $target_id !== $source_id ) {
				$targets[ $target_id ] = $target_id;
			}
		}
		$old = $this->target_ids( $source_id );
		$this->delete_edges( $source_id );
		foreach ( $targets as $target_id ) {
			$this->wpdb->insert(
				$this->edges,
				[
					'source_id' => $source_id,
					'target_id' => $target_id,
				],
				[ '%d', '%d' ]
			);
		}
		$this->upsert(
			$source_id,
			count( $targets ),
			$this->incoming_count( $source_id ),
			$cornerstone
		);
		foreach ( array_unique( array_merge( $old, array_values( $targets ) ) ) as $affected ) {
			if ( (int) $affected === $source_id ) {
				continue;
			}
			$this->refresh_incoming( (int) $affected );
		}
	}

	/**
	 * Remove a post from the index and refresh posts it used to link.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function remove_post( int $post_id ): void {
		$old = $this->target_ids( $post_id );
		$this->delete_edges( $post_id );
		$this->wpdb->delete( $this->edges, [ 'target_id' => $post_id ], [ '%d' ] );
		$this->wpdb->delete( $this->counts, [ 'post_id' => $post_id ], [ '%d' ] );
		foreach ( $old as $target_id ) {
			$this->refresh_incoming( $target_id );
		}
	}

	/**
	 * Stored counts for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{outgoing: int, incoming: int}
	 */
	public function counts_for( int $post_id ): array {
		$row = $this->row( $post_id );
		return [
			'outgoing' => (int) ( $row['outgoing_count'] ?? 0 ),
			'incoming' => (int) ( $row['incoming_count'] ?? 0 ),
		];
	}

	/**
	 * Post IDs whose saved incoming count is zero.
	 *
	 * @return list<int>
	 */
	public function zero_incoming_ids(): array {
		$rows = $this->wpdb->get_results(
			"SELECT post_id, incoming_count FROM {$this->counts}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from Schema.
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$ids = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( (int) ( $row['incoming_count'] ?? 0 ) === 0 ) {
				$ids[] = (int) ( $row['post_id'] ?? 0 );
			}
		}
		return array_values( array_filter( $ids ) );
	}

	/**
	 * How many other posts link to this one.
	 *
	 * @param int $target_id Target post ID.
	 * @return int
	 */
	public function incoming_count( int $target_id ): int {
		$sql = "SELECT source_id FROM {$this->edges} WHERE target_id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, $target_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		return is_array( $rows ) ? count( $rows ) : 0;
	}

	/**
	 * Target IDs currently stored for a source post.
	 *
	 * @param int $source_id Source post ID.
	 * @return list<int>
	 */
	private function target_ids( int $source_id ): array {
		$sql = "SELECT target_id FROM {$this->edges} WHERE source_id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, $source_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$ids = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$ids[] = (int) ( $row['target_id'] ?? 0 );
			}
		}
		return array_values( array_filter( $ids ) );
	}

	/**
	 * Delete outgoing edges for one source.
	 *
	 * @param int $source_id Source post ID.
	 * @return void
	 */
	private function delete_edges( int $source_id ): void {
		$this->wpdb->delete( $this->edges, [ 'source_id' => $source_id ], [ '%d' ] );
	}

	/**
	 * Recompute incoming links for a post that already has, or needs, a row.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function refresh_incoming( int $post_id ): void {
		$incoming = $this->incoming_count( $post_id );
		$row      = $this->row( $post_id );
		if ( null === $row ) {
			if ( $incoming <= 0 ) {
				return;
			}
			$this->upsert( $post_id, 0, $incoming, false );
			return;
		}
		$this->wpdb->update(
			$this->counts,
			[
				'incoming_count' => $incoming,
				'updated_at'     => current_time( 'mysql', true ),
			],
			[ 'post_id' => $post_id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Insert or update one count row.
	 *
	 * @param int  $post_id      Post ID.
	 * @param int  $outgoing     Outgoing link count.
	 * @param int  $incoming     Incoming link count.
	 * @param bool $cornerstone  Cornerstone flag.
	 * @return void
	 */
	private function upsert( int $post_id, int $outgoing, int $incoming, bool $cornerstone ): void {
		$data = [
			'outgoing_count' => $outgoing,
			'incoming_count' => $incoming,
			'is_cornerstone' => $cornerstone ? 1 : 0,
			'updated_at'     => current_time( 'mysql', true ),
		];
		if ( null === $this->row( $post_id ) ) {
			$data['post_id'] = $post_id;
			$this->wpdb->insert( $this->counts, $data );
			return;
		}
		$this->wpdb->update( $this->counts, $data, [ 'post_id' => $post_id ] );
	}

	/**
	 * One count row.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private function row( int $post_id ): ?array {
		$sql = "SELECT post_id, outgoing_count, incoming_count, is_cornerstone FROM {$this->counts} WHERE post_id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, $post_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $prepared, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}
}
