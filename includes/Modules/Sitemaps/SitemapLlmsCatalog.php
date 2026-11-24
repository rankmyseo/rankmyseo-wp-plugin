<?php
/**
 * Reads llms.txt links from the sitemap source.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

use WP_Post;

/**
 * Turns sitemap URL entries into titled links without a second exclusion pass.
 */
final class SitemapLlmsCatalog implements LlmsCatalog {
	/**
	 * Sitemap content source. Its providers and entries already drop noindex URLs.
	 *
	 * @var SitemapSource
	 */
	private SitemapSource $source;

	/**
	 * Constructor.
	 *
	 * @param SitemapSource $source Sitemap content source.
	 */
	public function __construct( SitemapSource $source ) {
		$this->source = $source;
	}

	/**
	 * {@inheritDoc}
	 */
	public function post_types(): array {
		$types = [];
		foreach ( $this->source->providers() as $provider ) {
			if ( 'post' !== $provider['kind'] || '' === $provider['name'] ) {
				continue;
			}
			$types[ $provider['name'] ] = '' !== $provider['label'] ? $provider['label'] : $provider['name'];
		}

		return $types;
	}

	/**
	 * Titled links for one post type.
	 *
	 * @param string $post_type Post type name.
	 * @param int    $limit     Maximum links.
	 * @return list<array{title: string, url: string, description: string}>
	 */
	public function links_for( string $post_type, int $limit ): array {
		if ( $limit < 1 || ! isset( $this->post_types()[ $post_type ] ) ) {
			return [];
		}

		$entries = $this->source->entries( 'post', $post_type, 1, $limit );
		$links   = [];
		foreach ( $entries as $entry ) {
			if ( count( $links ) >= $limit ) {
				break;
			}
			$url = isset( $entry['loc'] ) ? (string) $entry['loc'] : '';
			if ( '' === $url ) {
				continue;
			}
			$described = $this->describe( $url );
			$links[]   = [
				'title'       => $described['title'],
				'url'         => $url,
				'description' => $described['description'],
			];
		}

		return $links;
	}

	/**
	 * Title and excerpt for a permalink, falling back to the URL itself.
	 *
	 * @param string $url Public URL.
	 * @return array{title: string, description: string}
	 */
	private function describe( string $url ): array {
		$title       = $url;
		$description = '';
		if ( function_exists( 'url_to_postid' ) ) {
			$post_id = (int) url_to_postid( $url );
			$post    = $post_id > 0 ? get_post( $post_id ) : null;
			if ( $post instanceof WP_Post ) {
				$title       = trim( wp_strip_all_tags( (string) $post->post_title ) );
				$description = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
			}
		}

		if ( '' === $title ) {
			$title = $url;
		}

		return [
			'title'       => $title,
			'description' => $description,
		];
	}
}
