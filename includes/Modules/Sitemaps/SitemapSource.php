<?php
/**
 * Content source contract for paginated XML sitemaps.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Supplies sitemap providers, counts, and one page of URL entries.
 */
interface SitemapSource {
	/**
	 * Providers that should appear in the index when they have URLs.
	 *
	 * @return list<array{kind: string, name: string, label: string, slug: string}>
	 */
	public function providers(): array;

	/**
	 * Count indexable URLs for one provider.
	 *
	 * @param string $kind Provider kind: post, taxonomy, or author.
	 * @param string $name Object name.
	 * @return int
	 */
	public function count( string $kind, string $name ): int;

	/**
	 * One page of indexable URL entries.
	 *
	 * @param string $kind     Provider kind.
	 * @param string $name     Object name.
	 * @param int    $page     Page number starting at 1.
	 * @param int    $per_page Page size.
	 * @return list<array{loc: string, lastmod: string, images: list<string>}>
	 */
	public function entries( string $kind, string $name, int $page, int $per_page ): array;
}
