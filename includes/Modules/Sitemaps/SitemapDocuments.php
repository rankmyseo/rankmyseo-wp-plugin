<?php
/**
 * Builds sitemap index and child documents from a content source.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Turns provider counts and URL pages into escaped XML.
 */
final class SitemapDocuments {
	/**
	 * Content source.
	 *
	 * @var SitemapSource
	 */
	private SitemapSource $source;

	/**
	 * Settings repository.
	 *
	 * @var SitemapSettings
	 */
	private SitemapSettings $settings;

	/**
	 * XML renderer.
	 *
	 * @var XmlRenderer
	 */
	private XmlRenderer $xml;

	/**
	 * Cache.
	 *
	 * @var SitemapCache
	 */
	private SitemapCache $cache;

	/**
	 * Constructor.
	 *
	 * @param SitemapSource   $source   Content source.
	 * @param SitemapSettings $settings Settings repository.
	 * @param XmlRenderer     $xml      XML renderer.
	 * @param SitemapCache    $cache    Cache.
	 */
	public function __construct( SitemapSource $source, SitemapSettings $settings, XmlRenderer $xml, SitemapCache $cache ) {
		$this->source   = $source;
		$this->settings = $settings;
		$this->xml      = $xml;
		$this->cache    = $cache;
	}

	/**
	 * Render the sitemap index, using the cache when present.
	 *
	 * @return string
	 */
	public function render_index(): string {
		$cached = $this->cache->get( 'index' );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$per_page = $this->settings->max_urls();
		$sitemaps = [];
		foreach ( $this->source->providers() as $provider ) {
			$pages = SitemapLinks::page_count( $this->source->count( $provider['kind'], $provider['name'] ), $per_page );
			for ( $page = 1; $page <= $pages; $page++ ) {
				$sitemaps[] = [
					'loc' => SitemapLinks::child_url( $provider['slug'], $page ),
				];
			}
		}

		$xml = $this->xml->render_index( $sitemaps, SitemapLinks::xsl_url() );
		$this->cache->set( 'index', $xml );
		return $xml;
	}

	/**
	 * Render one child sitemap, or null when the slug is unknown.
	 *
	 * @param string $slug Provider slug.
	 * @param int    $page Page number.
	 * @return string|null
	 */
	public function render_child( string $slug, int $page ): ?string {
		if ( 1 !== preg_match( '/^[a-z0-9_-]{1,40}$/', $slug ) || $page < 1 ) {
			return null;
		}

		if ( ! $this->source instanceof WordPressSitemapSource ) {
			$provider = $this->provider_by_slug( $slug );
		} else {
			$provider = $this->source->resolve( $slug );
		}

		if ( null === $provider ) {
			return null;
		}

		$cache_key = $slug . ':' . $page;
		$cached    = $this->cache->get( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$entries = $this->source->entries( $provider['kind'], $provider['name'], $page, $this->settings->max_urls() );
		$xml     = $this->xml->render_urlset( $entries, SitemapLinks::xsl_url() );
		$this->cache->set( $cache_key, $xml );
		return $xml;
	}

	/**
	 * XSL stylesheet body.
	 *
	 * @return string
	 */
	public function render_xsl(): string {
		return $this->xml->render_xsl();
	}

	/**
	 * Find a provider by slug on a generic source.
	 *
	 * @param string $slug Sitemap slug.
	 * @return array{kind: string, name: string, label: string, slug: string}|null
	 */
	private function provider_by_slug( string $slug ): ?array {
		foreach ( $this->source->providers() as $provider ) {
			if ( $provider['slug'] === $slug ) {
				return $provider;
			}
		}

		return null;
	}
}
