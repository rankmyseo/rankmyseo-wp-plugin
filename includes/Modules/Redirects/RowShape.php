<?php
/**
 * Normalizes database rows into stable redirect and 404 shapes.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Casts raw table rows into the arrays returned by services and REST.
 */
final class RowShape {
	/**
	 * Normalize a redirects table row.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 * @return array<string, mixed>
	 */
	public static function redirect( array $row ): array {
		$target = $row['target_url'] ?? null;
		$target = is_string( $target ) && '' !== $target ? $target : null;

		$last_hit = $row['last_hit_at'] ?? null;

		return [
			'id'            => (int) ( $row['id'] ?? 0 ),
			'source_path'   => (string) ( $row['source_path'] ?? '' ),
			'source_hash'   => (string) ( $row['source_hash'] ?? '' ),
			'match_type'    => (string) ( $row['match_type'] ?? 'exact' ),
			'target_url'    => $target,
			'status_code'   => (int) ( $row['status_code'] ?? 301 ),
			'is_active'     => (bool) (int) ( $row['is_active'] ?? 0 ),
			'hits'          => (int) ( $row['hits'] ?? 0 ),
			'last_hit_at'   => is_string( $last_hit ) && '' !== $last_hit ? $last_hit : null,
			'source_origin' => (string) ( $row['source_origin'] ?? 'manual' ),
			'created_at'    => (string) ( $row['created_at'] ?? '' ),
			'updated_at'    => (string) ( $row['updated_at'] ?? '' ),
		];
	}

	/**
	 * Normalize a 404 log row.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 * @return array<string, mixed>
	 */
	public static function not_found( array $row ): array {
		return [
			'id'            => (int) ( $row['id'] ?? 0 ),
			'request_path'  => (string) ( $row['request_path'] ?? '' ),
			'path_hash'     => (string) ( $row['path_hash'] ?? '' ),
			'query_string'  => (string) ( $row['query_string'] ?? '' ),
			'referer'       => (string) ( $row['referer'] ?? '' ),
			'user_agent'    => (string) ( $row['user_agent'] ?? '' ),
			'hits'          => (int) ( $row['hits'] ?? 0 ),
			'status'        => (string) ( $row['status'] ?? 'open' ),
			'first_seen_at' => (string) ( $row['first_seen_at'] ?? '' ),
			'last_seen_at'  => (string) ( $row['last_seen_at'] ?? '' ),
		];
	}
}
