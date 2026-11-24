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
	 * Title and excerpt for a permalink.
	 *
	 * Shop and other post-type archives are not posts, so url_to_postid() is 0.
	 * Those links use the Shop page title or the archive label instead of the raw URL.
	 *
	 * @param string $url Public URL.
	 * @return array{title: string, description: string}
	 */
	private function describe( string $url ): array {
		$title       = '';
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
			$title = $this->shop_title( $url );
		}
		if ( '' === $title ) {
			$title = $this->archive_label( $url );
		}
		if ( '' === $title ) {
			$title = $url;
		}

		return [
			'title'       => $title,
			'description' => $description,
		];
	}

	/**
	 * Shop page title when the URL is the WooCommerce shop archive.
	 *
	 * @param string $url Public URL.
	 * @return string
	 */
	private function shop_title( string $url ): string {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return '';
		}

		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id <= 0 || ! $this->matches_shop( $shop_id, $url ) ) {
			return '';
		}

		return $this->post_title( $shop_id );
	}

	/**
	 * Whether a URL is the Shop page or the product archive.
	 *
	 * @param int    $shop_id Shop page ID.
	 * @param string $url     Public URL.
	 * @return bool
	 */
	private function matches_shop( int $shop_id, string $url ): bool {
		$candidates = [];
		if ( function_exists( 'get_permalink' ) ) {
			$permalink = get_permalink( $shop_id );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				$candidates[] = $permalink;
			}
		}
		if ( function_exists( 'get_post_type_archive_link' ) ) {
			$archive = get_post_type_archive_link( 'product' );
			if ( is_string( $archive ) && '' !== $archive ) {
				$candidates[] = $archive;
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( $this->same_url( $candidate, $url ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Plural label for a post type archive URL.
	 *
	 * @param string $url Public URL.
	 * @return string
	 */
	private function archive_label( string $url ): string {
		if ( function_exists( 'get_post_types' ) && function_exists( 'get_post_type_archive_link' ) ) {
			$types = get_post_types( [ 'public' => true ], 'objects' );
			if ( is_array( $types ) ) {
				foreach ( $types as $type ) {
					if ( ! is_object( $type ) || empty( $type->name ) || empty( $type->has_archive ) ) {
						continue;
					}
					$link = get_post_type_archive_link( (string) $type->name );
					if ( ! is_string( $link ) || ! $this->same_url( $link, $url ) ) {
						continue;
					}
					$label = isset( $type->labels->name ) ? trim( wp_strip_all_tags( (string) $type->labels->name ) ) : '';
					if ( '' !== $label ) {
						return $label;
					}
				}
			}
		}

		$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
		$slug = trim( basename( untrailingslashit( $path ) ) );
		if ( '' === $slug || ! function_exists( 'get_post_type_object' ) || ! function_exists( 'get_post_type_archive_link' ) ) {
			return '';
		}

		$object = get_post_type_object( $slug );
		if ( ! is_object( $object ) ) {
			return '';
		}
		$name = '' !== $object->name ? $object->name : $slug;
		if ( empty( $object->has_archive ) && 'product' !== $name ) {
			return '';
		}
		$link = get_post_type_archive_link( $name );
		if ( ! is_string( $link ) || ! $this->same_url( $link, $url ) ) {
			return '';
		}

		return isset( $object->labels->name ) ? trim( wp_strip_all_tags( (string) $object->labels->name ) ) : '';
	}

	/**
	 * Plain title for a post, preferring get_the_title() when WordPress provides it.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function post_title( int $post_id ): string {
		if ( function_exists( 'get_the_title' ) ) {
			$title = get_the_title( $post_id );
			if ( is_string( $title ) ) {
				$title = trim( wp_strip_all_tags( $title ) );
				if ( '' !== $title ) {
					return $title;
				}
			}
		}

		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			return trim( wp_strip_all_tags( (string) $post->post_title ) );
		}

		return '';
	}

	/**
	 * Compare two public URLs without the query, fragment, or trailing slash.
	 *
	 * @param string $left  First URL.
	 * @param string $right Second URL.
	 * @return bool
	 */
	private function same_url( string $left, string $right ): bool {
		$normalize = static function ( string $url ): string {
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				return strtolower( untrailingslashit( $url ) );
			}
			$scheme = strtolower( (string) ( $parts['scheme'] ?? 'http' ) );
			$host   = strtolower( (string) $parts['host'] );
			if ( isset( $parts['port'] ) ) {
				$port = (int) $parts['port'];
				if ( $port > 0 ) {
					$host .= ':' . $port;
				}
			}
			$path = isset( $parts['path'] ) ? untrailingslashit( (string) $parts['path'] ) : '';

			return $scheme . '://' . $host . $path;
		};

		return '' !== $left && '' !== $right && $normalize( $left ) === $normalize( $right );
	}
}
