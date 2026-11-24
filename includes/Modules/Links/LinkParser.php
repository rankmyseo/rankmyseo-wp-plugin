<?php
/**
 * Reads internal links out of post HTML without making a request.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Links;

use RankMySEO\Modules\Analyzer\ContentInspector;

/**
 * Collects same-site http(s) links from stored post content.
 */
final class LinkParser {
	/**
	 * Content inspector used only to classify hrefs.
	 *
	 * @var ContentInspector
	 */
	private ContentInspector $content;

	/**
	 * Constructor.
	 *
	 * @param ContentInspector|null $content Optional inspector.
	 */
	public function __construct( ?ContentInspector $content = null ) {
		$this->content = $content ?? new ContentInspector();
	}

	/**
	 * Absolute internal URLs found in the HTML.
	 *
	 * @param string $html     Post content.
	 * @param string $site_url Site origin used for relative links.
	 * @return list<string>
	 */
	public function internal_urls( string $html, string $site_url ): array {
		$urls = [];
		if ( ! preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')[^>]*>/i', $html, $matches, PREG_SET_ORDER ) ) {
			return [];
		}
		foreach ( $matches as $match ) {
			$href = '' !== ( $match[1] ?? '' ) ? (string) $match[1] : (string) ( $match[2] ?? '' );
			if ( 'internal' !== $this->content->classify_link( $href, $site_url ) ) {
				continue;
			}
			$absolute = $this->absolute( $href, $site_url );
			if ( '' !== $absolute ) {
				$urls[] = $absolute;
			}
		}
		return $urls;
	}

	/**
	 * Turn a relative href into an absolute URL on this site.
	 *
	 * @param string $href     Link target.
	 * @param string $site_url Site origin.
	 * @return string
	 */
	public function absolute( string $href, string $site_url ): string {
		$href = trim( $href );
		if ( '' === $href ) {
			return '';
		}
		$hash = strpos( $href, '#' );
		if ( false !== $hash ) {
			$href = substr( $href, 0, $hash );
		}
		$query = strpos( $href, '?' );
		if ( false !== $query ) {
			$href = substr( $href, 0, $query );
		}
		if ( str_starts_with( $href, '//' ) ) {
			$href = 'https:' . $href;
		} elseif ( str_starts_with( $href, '/' ) ) {
			$href = rtrim( $site_url, '/' ) . $href;
		} elseif ( ! preg_match( '#^https?://#i', $href ) ) {
			$href = rtrim( $site_url, '/' ) . '/' . ltrim( $href, '/' );
		}
		return $this->normalize( $href );
	}

	/**
	 * Compare URLs by scheme, host, and path without a trailing slash.
	 *
	 * @param string $url Absolute or relative URL.
	 * @return string
	 */
	public function normalize( string $url ): string {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) ) {
			return strtolower( untrailingslashit( trim( $url ) ) );
		}
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path   = untrailingslashit( (string) ( $parts['path'] ?? '' ) );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
		if ( '' === $host ) {
			return strtolower( $path );
		}
		return $scheme . '://' . $host . $path;
	}
}
