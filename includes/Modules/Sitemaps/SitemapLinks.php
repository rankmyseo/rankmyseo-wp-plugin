<?php
/**
 * URL helpers for XML sitemap routes.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Builds public sitemap URLs and paginates URL lists.
 */
final class SitemapLinks {
	/**
	 * Absolute URL of the sitemap index.
	 *
	 * @return string
	 */
	public static function index_url(): string {
		return home_url( '/sitemap_index.xml' );
	}

	/**
	 * Absolute URL of the XSL stylesheet.
	 *
	 * @return string
	 */
	public static function xsl_url(): string {
		return home_url( '/rankmyseo-sitemap.xsl' );
	}

	/**
	 * Absolute URL of one paginated sitemap file.
	 *
	 * Page 1 omits the page number (`post-sitemap.xml`). Later pages append it
	 * (`post-sitemap2.xml`).
	 *
	 * @param string $slug Provider slug.
	 * @param int    $page Page number, starting at 1.
	 * @return string
	 */
	public static function child_url( string $slug, int $page ): string {
		$safe_slug = rawurlencode( $slug );
		$suffix    = $page > 1 ? (string) $page : '';
		return home_url( '/' . $safe_slug . '-sitemap' . $suffix . '.xml' );
	}

	/**
	 * Number of pages required to list $total URLs.
	 *
	 * @param int $total    Total indexable URLs.
	 * @param int $per_page Maximum URLs per file.
	 * @return int
	 */
	public static function page_count( int $total, int $per_page ): int {
		if ( $total < 1 || $per_page < 1 ) {
			return 0;
		}

		return (int) ceil( $total / $per_page );
	}

	/**
	 * Return one page of an already filtered list.
	 *
	 * @param array<int, mixed> $items    Filtered items.
	 * @param int               $page     Page number, starting at 1.
	 * @param int               $per_page Page size.
	 * @return array<int, mixed>
	 */
	public static function slice( array $items, int $page, int $per_page ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$offset   = ( $page - 1 ) * $per_page;

		return array_values( array_slice( $items, $offset, $per_page ) );
	}
}
