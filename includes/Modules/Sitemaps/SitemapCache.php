<?php
/**
 * Generation-keyed transient cache for rendered sitemap XML.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Stores sitemap XML until a post, term, or manual flush bumps the generation.
 */
final class SitemapCache {
	/**
	 * Settings repository used for the generation counter.
	 *
	 * @var SitemapSettings
	 */
	private SitemapSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param SitemapSettings $settings Settings repository.
	 */
	public function __construct( SitemapSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Read a cached document.
	 *
	 * @param string $key Logical cache key.
	 * @return string|null
	 */
	public function get( string $key ): ?string {
		$stored = get_transient( $this->name( $key ) );
		return is_string( $stored ) && '' !== $stored ? $stored : null;
	}

	/**
	 * Store a rendered document for up to 12 hours.
	 *
	 * @param string $key   Logical cache key.
	 * @param string $value XML body.
	 * @return void
	 */
	public function set( string $key, string $value ): void {
		$ttl = defined( 'HOUR_IN_SECONDS' ) ? (int) HOUR_IN_SECONDS * 12 : 43200;
		set_transient( $this->name( $key ), $value, $ttl );
	}

	/**
	 * Invalidate every cached sitemap by advancing the generation.
	 *
	 * @return int
	 */
	public function flush(): int {
		return $this->settings->flush_cache();
	}

	/**
	 * Transient name scoped to the current generation.
	 *
	 * @param string $key Logical cache key.
	 * @return string
	 */
	private function name( string $key ): string {
		return 'rankmyseo_sm_' . $this->settings->cache_generation() . '_' . md5( $key );
	}
}
