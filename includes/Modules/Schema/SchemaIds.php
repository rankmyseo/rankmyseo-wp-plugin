<?php
/**
 * Stable Schema.org @id builders.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Builds canonical @id values that link graph nodes together.
 */
final class SchemaIds {
	/**
	 * Site-level fragments.
	 *
	 * @var list<string>
	 */
	public const SITE_FRAGMENTS = [
		'organization',
		'person',
		'website',
		'logo',
		'localbusiness',
	];

	/**
	 * Page-level fragments.
	 *
	 * @var list<string>
	 */
	public const PAGE_FRAGMENTS = [
		'webpage',
		'breadcrumb',
		'article',
		'product',
		'faq',
		'author',
	];

	/**
	 * Build an @id for a URL and fragment.
	 *
	 * Site URLs keep a trailing slash (`https://example.com/#website`).
	 * Other permalinks stay unslashed (`https://example.com/post#article`).
	 *
	 * @param string $url      Page or site URL.
	 * @param string $fragment Fragment without a leading hash.
	 * @param string $home_url Canonical site home URL.
	 * @return string
	 */
	public static function node_id( string $url, string $fragment, string $home_url ): string {
		$fragment = ltrim( $fragment, '#' );
		$base     = untrailingslashit( $url );
		$home     = untrailingslashit( $home_url );

		if ( $base === $home ) {
			return trailingslashit( $home ) . '#' . $fragment;
		}

		return $base . '#' . $fragment;
	}
}
