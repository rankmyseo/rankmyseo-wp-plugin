<?php
/**
 * Inserts AI redirects into wp_rankmyseo_redirects.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Database\Schema;
use wpdb;

/**
 * Creates exact redirects with a SHA-256 source hash and a loop check.
 *
 * Columns match the redirects table: source_path, source_hash, match_type,
 * target_url, status_code, is_active, source_origin.
 */
final class RedirectWriter {
	/**
	 * WordPress database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb Database handle.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Insert one exact redirect.
	 *
	 * @param array<string, mixed> $after Proposed redirect fields.
	 * @return array{ok: true, id: int, source_hash: string}|array{ok: false, message: string}
	 */
	public function create( array $after ): array {
		$source = $this->normalize_path( PayloadSanitizer::text_field( $after, array( 'source_path', 'source', 'from' ) ) );
		$target = PayloadSanitizer::text_field( $after, array( 'target_url', 'target', 'to', 'suggested' ) );
		$code   = isset( $after['status_code'] ) && is_numeric( $after['status_code'] ) ? (int) $after['status_code'] : 301;

		if ( '' === $source || '/' === $source || str_starts_with( $source, '/wp-admin' ) || '/wp-login.php' === $source ) {
			return array(
				'ok'      => false,
				'message' => __( 'That address cannot be redirected.', 'rankmyseo' ),
			);
		}
		if ( ! in_array( $code, array( 301, 302, 307, 410, 451 ), true ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'That redirect type is not supported.', 'rankmyseo' ),
			);
		}

		$target_url = '';
		if ( ! in_array( $code, array( 410, 451 ), true ) ) {
			$target_url = $this->target_url( $target );
			if ( '' === $target_url ) {
				return array(
					'ok'      => false,
					'message' => __( 'The new address must be a normal link.', 'rankmyseo' ),
				);
			}
			if ( $source === $this->normalize_path( $target_url ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'A redirect cannot point at itself.', 'rankmyseo' ),
				);
			}
			if ( $this->creates_loop( $source, $target_url ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'That redirect would send visitors in a circle.', 'rankmyseo' ),
				);
			}
		}

		$hash = hash( 'sha256', $source );
		if ( $this->hash_exists( $hash ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'A redirect for that address already exists.', 'rankmyseo' ),
			);
		}

		$now = current_time( 'mysql', true );
		$now = is_string( $now ) ? $now : gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $this->wpdb->insert(
			$this->table(),
			array(
				'source_path'   => substr( $source, 0, 512 ),
				'source_hash'   => $hash,
				'match_type'    => 'exact',
				'target_url'    => '' !== $target_url ? substr( $target_url, 0, 1024 ) : null,
				'status_code'   => $code,
				'is_active'     => 1,
				'hits'          => 0,
				'source_origin' => 'ai',
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
		if ( false === $ok ) {
			return array(
				'ok'      => false,
				'message' => __( 'The redirect could not be saved.', 'rankmyseo' ),
			);
		}

		return array(
			'ok'          => true,
			'id'          => (int) $this->wpdb->insert_id,
			'source_hash' => $hash,
		);
	}

	/**
	 * Remove a redirect created by an action.
	 *
	 * @param int $id Redirect ID.
	 * @return void
	 */
	public function delete( int $id ): void {
		if ( $id <= 0 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $this->table(), array( 'id' => $id ) );
	}

	/**
	 * Active redirects used for the loop walk.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function active_rules(): array {
		$sql = "SELECT id, source_path, target_url, source_hash FROM {$this->table()} WHERE is_active = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, 1 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$clean = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$clean[] = $row;
			}
		}
		return $clean;
	}

	/**
	 * Normalize a path the same way the redirect matcher will.
	 *
	 * @param string $value Path or URL.
	 * @return string
	 */
	public function normalize_path( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( 1 === preg_match( '#^https?://#i', $value ) ) {
			$parsed = wp_parse_url( $value, PHP_URL_PATH );
			$value  = is_string( $parsed ) && '' !== $parsed ? $parsed : '/';
		}
		$value = '/' . ltrim( (string) preg_replace( '#/+#', '/', $value ), '/' );
		if ( '/' !== $value ) {
			$value = untrailingslashit( $value );
		}
		return strtolower( $value );
	}

	/**
	 * Walk existing rules and reject a cycle.
	 *
	 * @param string $source     New source path.
	 * @param string $target_url New target URL.
	 * @return bool
	 */
	private function creates_loop( string $source, string $target_url ): bool {
		$map = array();
		foreach ( $this->active_rules() as $rule ) {
			$from = $this->normalize_path( (string) ( $rule['source_path'] ?? '' ) );
			$to   = $this->normalize_path( (string) ( $rule['target_url'] ?? '' ) );
			if ( '' !== $from && '' !== $to ) {
				$map[ $from ] = $to;
			}
		}
		$cursor = $this->normalize_path( $target_url );
		$seen   = array( $source => true );
		for ( $hop = 0; $hop < 8; $hop++ ) {
			if ( '' === $cursor ) {
				return false;
			}
			if ( isset( $seen[ $cursor ] ) ) {
				return true;
			}
			$seen[ $cursor ] = true;
			if ( ! isset( $map[ $cursor ] ) ) {
				return false;
			}
			$cursor = $map[ $cursor ];
		}
		return true;
	}

	/**
	 * Whether an active exact rule already owns this hash.
	 *
	 * @param string $hash SHA-256 hex.
	 * @return bool
	 */
	private function hash_exists( string $hash ): bool {
		$sql = "SELECT id FROM {$this->table()} WHERE source_hash = %s AND is_active = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is the Schema allowlist.
		$prepared = $this->wpdb->prepare( $sql, $hash, 1 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $prepared, ARRAY_A );
		return is_array( $row );
	}

	/**
	 * Absolute http(s) target, or a same-site path expanded to a URL.
	 *
	 * @param string $target Raw target.
	 * @return string
	 */
	private function target_url( string $target ): string {
		$target = trim( $target );
		if ( '' === $target || str_starts_with( strtolower( $target ), 'javascript:' ) || str_starts_with( strtolower( $target ), 'data:' ) ) {
			return '';
		}
		if ( str_starts_with( $target, '/' ) ) {
			return home_url( $target );
		}
		return PayloadSanitizer::url( $target );
	}

	/**
	 * Prefixed table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return Schema::get_table_name( 'redirects', $this->wpdb->prefix );
	}
}
