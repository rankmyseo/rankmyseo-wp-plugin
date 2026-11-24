<?php
/**
 * Shop archive canonical and noindex rules.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\WooCommerce;

/**
 * Opts in to cleaner canonicals and noindex for filtered shop URLs and sold-out products.
 */
final class CatalogRobots {
	/**
	 * Store settings.
	 *
	 * @var WooSettings
	 */
	private WooSettings $settings;

	/**
	 * Optional context provider for tests. Returns shop_archive, product, out_of_stock, query, and url.
	 *
	 * @var callable|null
	 */
	private $context_provider;

	/**
	 * Constructor.
	 *
	 * @param WooSettings   $settings          Store settings.
	 * @param callable|null $context_provider Optional `(): array` context.
	 */
	public function __construct( WooSettings $settings, ?callable $context_provider = null ) {
		$this->settings         = $settings;
		$this->context_provider = $context_provider;
	}

	/**
	 * Register front-end filters. No assets are enqueued.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_robots', [ $this, 'filter_wp_robots' ], 30 );
		add_filter( 'rankmyseo/meta/robots', [ $this, 'filter_robots' ] ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public extension point shared with the meta module.
		add_filter( 'rankmyseo/meta/canonical', [ $this, 'filter_canonical' ] ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public extension point shared with the meta module.
	}

	/**
	 * Add noindex to the WordPress robots array when a catalog rule matches.
	 *
	 * @param array<string, bool|string> $robots Existing directives.
	 * @return array<string, bool|string>
	 */
	public function filter_wp_robots( array $robots ): array {
		if ( $this->should_noindex( $this->context() ) ) {
			$robots['noindex'] = true;
		}

		return $robots;
	}

	/**
	 * Add noindex to RankMySEO's stored robots object when a catalog rule matches.
	 *
	 * @param array<string, mixed> $robots Robots object.
	 * @return array<string, mixed>
	 */
	public function filter_robots( array $robots ): array {
		if ( $this->should_noindex( $this->context() ) ) {
			$robots['noindex'] = true;
		}

		return $robots;
	}

	/**
	 * Point a filtered shop URL at the archive without sort and filter parameters.
	 *
	 * @param string $canonical Current canonical URL.
	 * @return string
	 */
	public function filter_canonical( string $canonical ): string {
		$settings = $this->settings->all();
		if ( empty( $settings['canonicalize_shop_filters'] ) ) {
			return $canonical;
		}

		$context = $this->context();
		if ( empty( $context['shop_archive'] ) ) {
			return $canonical;
		}

		$query = isset( $context['query'] ) && is_array( $context['query'] ) ? $context['query'] : [];
		if ( ! CatalogPaths::has_tracked_params( $query ) ) {
			return $canonical;
		}

		$source = '' !== $canonical ? $canonical : (string) ( $context['url'] ?? '' );
		if ( '' === $source ) {
			return $canonical;
		}

		return CatalogPaths::strip_tracked_params( $source );
	}

	/**
	 * Whether the current request should be left out of search results.
	 *
	 * @param array<string, mixed> $context Request context.
	 * @return bool
	 */
	public function should_noindex( array $context ): bool {
		$settings = $this->settings->all();
		$query    = isset( $context['query'] ) && is_array( $context['query'] ) ? $context['query'] : [];

		if ( ! empty( $settings['noindex_shop_filters'] ) && ! empty( $context['shop_archive'] ) && CatalogPaths::has_tracked_params( $query ) ) {
			return true;
		}

		return ! empty( $settings['noindex_out_of_stock'] ) && ! empty( $context['product'] ) && ! empty( $context['out_of_stock'] );
	}

	/**
	 * Current request context.
	 *
	 * @return array<string, mixed>
	 */
	private function context(): array {
		if ( null !== $this->context_provider ) {
			$provided = ( $this->context_provider )();
			return is_array( $provided ) ? $provided : [];
		}

		return [
			'shop_archive' => $this->is_shop_archive(),
			'product'      => function_exists( 'is_product' ) && is_product(),
			'out_of_stock' => $this->product_is_out_of_stock(),
			'query'        => $this->query_args(),
			'url'          => $this->current_url(),
		];
	}

	/**
	 * Whether the request is a shop, category, tag, or attribute archive.
	 *
	 * @return bool
	 */
	private function is_shop_archive(): bool {
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}
		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			return true;
		}

		return function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'product' );
	}

	/**
	 * Whether the current product cannot be bought.
	 *
	 * @return bool
	 */
	private function product_is_out_of_stock(): bool {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		$product    = wc_get_product( $product_id );
		if ( ! is_object( $product ) ) {
			return false;
		}

		if ( method_exists( $product, 'get_stock_status' ) && 'outofstock' === $product->get_stock_status() ) {
			return true;
		}

		return method_exists( $product, 'is_in_stock' ) && false === $product->is_in_stock();
	}

	/**
	 * Public query parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function query_args(): array {
		$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed for known catalog keys only.
		$query   = wp_parse_url( $request, PHP_URL_QUERY );
		$args    = [];
		if ( is_string( $query ) && '' !== $query ) {
			parse_str( $query, $args );
		}

		return $args;
	}

	/**
	 * Current request URL without the query string.
	 *
	 * @return string
	 */
	private function current_url(): string {
		if ( function_exists( 'home_url' ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only as a path.
			return home_url( $path );
		}

		return '';
	}
}
