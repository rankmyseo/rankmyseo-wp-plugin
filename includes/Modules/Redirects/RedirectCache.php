<?php
/**
 * Object-cache layer for redirect lookups.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Generation-counter cache so rule writes invalidate every lookup in O(1).
 */
final class RedirectCache {
	/**
	 * Object-cache group.
	 */
	private const GROUP = 'rankmyseo_redirects';

	/**
	 * Read a cached value.
	 *
	 * @param string $key Logical cache key.
	 * @return array{found: bool, value: mixed}
	 */
	public function get( string $key ): array {
		if ( ! function_exists( 'wp_cache_get' ) ) {
			return [
				'found' => false,
				'value' => null,
			];
		}

		$stored = wp_cache_get( $this->storage_key( $key ), self::GROUP );
		if ( ! is_array( $stored ) || ! array_key_exists( 'value', $stored ) ) {
			return [
				'found' => false,
				'value' => null,
			];
		}

		return [
			'found' => true,
			'value' => $stored['value'],
		];
	}

	/**
	 * Store a value, including an explicit miss (null).
	 *
	 * @param string $key   Logical cache key.
	 * @param mixed  $value Value to store.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		if ( ! function_exists( 'wp_cache_set' ) ) {
			return;
		}

		wp_cache_set(
			$this->storage_key( $key ),
			[ 'value' => $value ],
			self::GROUP,
			300
		);
	}

	/**
	 * Invalidate every key by bumping the generation counter.
	 *
	 * @return void
	 */
	public function bust(): void {
		if ( ! function_exists( 'wp_cache_set' ) || ! function_exists( 'wp_cache_get' ) ) {
			return;
		}

		wp_cache_set( 'generation', $this->generation() + 1, self::GROUP );
	}

	/**
	 * Build a generation-scoped storage key.
	 *
	 * @param string $key Logical cache key.
	 * @return string
	 */
	private function storage_key( string $key ): string {
		return $this->generation() . ':' . $key;
	}

	/**
	 * Current generation counter.
	 *
	 * @return int
	 */
	private function generation(): int {
		if ( ! function_exists( 'wp_cache_get' ) ) {
			return 0;
		}

		$generation = wp_cache_get( 'generation', self::GROUP );
		return is_numeric( $generation ) ? (int) $generation : 0;
	}
}
