<?php
/**
 * Indexable links the llms.txt generator can list.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Supplies public post types and the indexable URLs already kept in the sitemap.
 */
interface LlmsCatalog {
	/**
	 * Public post type labels keyed by name.
	 *
	 * @return array<string, string>
	 */
	public function post_types(): array;

	/**
	 * Indexable links for one post type.
	 *
	 * Callers only ask for types the sitemap already includes. Implementations
	 * should return published, public, non-noindex URLs.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $limit     Maximum links to return.
	 * @return list<array{title: string, url: string, description: string}>
	 */
	public function links_for( string $post_type, int $limit ): array;
}
