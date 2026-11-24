<?php
/**
 * Optional removal of the product and product-category permalink bases.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

use WP_Post;
use WP_Term;

/**
 * Strips a permalink base and redirects the old base with a 301.
 *
 * A page that already uses the short link is left alone, so the shop never steals an existing page.
 */
final class PermalinkBases {
	/**
	 * Store settings.
	 *
	 * @var WooSettings
	 */
	private WooSettings $settings;

	/**
	 * Optional resolver `(string $path, string $kind): ?string` returning the new URL.
	 *
	 * @var callable|null
	 */
	private $resolver;

	/**
	 * Constructor.
	 *
	 * @param WooSettings   $settings Store settings.
	 * @param callable|null $resolver Optional target resolver.
	 */
	public function __construct( WooSettings $settings, ?callable $resolver = null ) {
		$this->settings = $settings;
		$this->resolver = $resolver;
	}

	/**
	 * Register link, request, and redirect hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'post_type_link', [ $this, 'filter_post_link' ], 20, 2 );
		add_filter( 'term_link', [ $this, 'filter_term_link' ], 20, 3 );
		add_filter( 'request', [ $this, 'filter_request' ] );
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
	}

	/**
	 * Remove the product base from generated product links.
	 *
	 * @param string $url  Generated permalink.
	 * @param mixed  $post Product post.
	 * @return string
	 */
	public function filter_post_link( string $url, $post ): string {
		$settings = $this->settings->all();
		if ( empty( $settings['remove_product_base'] ) || ! is_object( $post ) ) {
			return $url;
		}

		$type = isset( $post->post_type ) ? (string) $post->post_type : '';
		if ( 'product' !== $type ) {
			return $url;
		}

		return CatalogPaths::strip_base( $url, (string) $settings['legacy_product_base'] );
	}

	/**
	 * Remove the category base from generated product category links.
	 *
	 * @param string $url      Generated term link.
	 * @param mixed  $term     Term object.
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 */
	public function filter_term_link( string $url, $term, string $taxonomy ): string {
		unset( $term );
		$settings = $this->settings->all();
		if ( 'product_cat' !== $taxonomy || empty( $settings['remove_category_base'] ) ) {
			return $url;
		}

		return CatalogPaths::strip_base( $url, (string) $settings['legacy_category_base'] );
	}

	/**
	 * Let a short product or category link resolve when no page already owns it.
	 *
	 * @param array<string, mixed> $vars Request vars.
	 * @return array<string, mixed>
	 */
	public function filter_request( array $vars ): array {
		if ( ! empty( $vars['pagename'] ) || ! empty( $vars['page_id'] ) || ! empty( $vars['attachment'] ) ) {
			return $vars;
		}

		$slug = isset( $vars['name'] ) && is_string( $vars['name'] ) ? trim( $vars['name'], '/' ) : '';
		if ( '' === $slug || str_contains( $slug, '/' ) ) {
			return $vars;
		}

		$settings = $this->settings->all();
		if ( ! empty( $settings['remove_product_base'] ) && null !== $this->resolve( $slug, 'product' ) ) {
			$vars['post_type'] = 'product';
			$vars['name']      = $slug;
			unset( $vars['product_cat'] );
			return $vars;
		}

		if ( ! empty( $settings['remove_category_base'] ) && null !== $this->resolve( $slug, 'category' ) ) {
			$vars['product_cat'] = $slug;
			unset( $vars['name'], $vars['post_type'] );
		}

		return $vars;
	}

	/**
	 * Redirect an old base URL to the short link when that short link belongs to the shop.
	 *
	 * @param string $request_path Request path or absolute URL.
	 * @param string $home_path    Optional subdirectory home path.
	 * @return string|null
	 */
	public function redirect_target( string $request_path, string $home_path = '' ): ?string {
		$relative = CatalogPaths::relative_path( $request_path, $home_path );
		$settings = $this->settings->all();
		$rules    = [
			[
				'enabled' => ! empty( $settings['remove_product_base'] ),
				'base'    => (string) $settings['legacy_product_base'],
				'kind'    => 'product',
			],
			[
				'enabled' => ! empty( $settings['remove_category_base'] ),
				'base'    => (string) $settings['legacy_category_base'],
				'kind'    => 'category',
			],
		];

		foreach ( $rules as $rule ) {
			if ( ! $rule['enabled'] ) {
				continue;
			}
			$base = trim( (string) $rule['base'], '/' );
			if ( '' === $base || ! str_starts_with( $relative, $base . '/' ) ) {
				continue;
			}
			$rest = trim( substr( $relative, strlen( $base ) ), '/' );
			if ( '' === $rest ) {
				continue;
			}
			$target = $this->resolve( $rest, (string) $rule['kind'] );
			if ( ! is_string( $target ) || '' === $target || CatalogPaths::same_path( $request_path, $target ) ) {
				continue;
			}

			return $target;
		}

		return null;
	}

	/**
	 * Send a 301 when the current request still uses a removed base.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed as a path and compared with a resolved permalink.
		$home    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$target  = $this->redirect_target( $request, $home );
		if ( null === $target ) {
			return;
		}

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Used only when wp_safe_redirect() is unavailable. Execution stops immediately.
		wp_redirect( $target, 301 );
		exit;
	}

	/**
	 * Resolve a product or category path to its short URL.
	 *
	 * @param string $path Slug path.
	 * @param string $kind `product` or `category`.
	 * @return string|null
	 */
	private function resolve( string $path, string $kind ): ?string {
		if ( null !== $this->resolver ) {
			$resolved = ( $this->resolver )( $path, $kind );
			return is_string( $resolved ) && '' !== $resolved ? $resolved : null;
		}

		if ( 'product' === $kind ) {
			return $this->resolve_product( $path );
		}

		return $this->resolve_category( $path );
	}

	/**
	 * Product permalink when no page already uses the slug.
	 *
	 * @param string $path Product slug path.
	 * @return string|null
	 */
	private function resolve_product( string $path ): ?string {
		if ( ! function_exists( 'get_page_by_path' ) ) {
			return null;
		}

		$page = get_page_by_path( $path, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			return null;
		}

		$product = get_page_by_path( $path, OBJECT, 'product' );
		if ( ! $product instanceof WP_Post ) {
			return null;
		}

		$link = get_permalink( $product );
		return is_string( $link ) && '' !== $link ? $link : null;
	}

	/**
	 * Product category link.
	 *
	 * @param string $path Category slug path.
	 * @return string|null
	 */
	private function resolve_category( string $path ): ?string {
		if ( ! function_exists( 'get_term_by' ) ) {
			return null;
		}

		$slug = $path;
		if ( str_contains( $path, '/' ) ) {
			$parts = explode( '/', $path );
			$slug  = (string) end( $parts );
		}

		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term instanceof WP_Term || ! function_exists( 'get_term_link' ) ) {
			return null;
		}

		$link = get_term_link( $term );
		return is_string( $link ) && '' !== $link ? $link : null;
	}
}
