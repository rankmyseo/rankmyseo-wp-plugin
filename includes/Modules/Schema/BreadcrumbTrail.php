<?php
/**
 * Shared breadcrumb trail for JSON-LD and the visible breadcrumb.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

use WP_Post;
use WP_Term;

/**
 * Builds the same ordered name/url trail the BreadcrumbList schema uses.
 */
final class BreadcrumbTrail {
	/**
	 * Trail for the current front-end query.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	public function for_request(): array {
		if ( $this->is_front() ) {
			return $this->filter( [ $this->home_crumb() ] );
		}

		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
			$post    = $post_id > 0 ? get_post( $post_id ) : null;
			if ( $post instanceof WP_Post ) {
				return $this->for_post( $post );
			}
		}

		if ( function_exists( 'is_search' ) && is_search() ) {
			return $this->filter( $this->search_trail() );
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return $this->filter( $this->product_category_trail() );
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			return $this->filter( $this->shop_and_term_trail() );
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return $this->filter( $this->shop_trail() );
		}

		if ( $this->is_term_archive() ) {
			return $this->filter( $this->term_archive_trail() );
		}

		if ( function_exists( 'is_post_type_archive' ) && is_post_type_archive() ) {
			return $this->filter( $this->post_type_archive_trail() );
		}

		if ( function_exists( 'is_author' ) && is_author() ) {
			return $this->filter( $this->author_trail() );
		}

		if ( function_exists( 'is_date' ) && is_date() ) {
			return $this->filter( $this->date_trail() );
		}

		// The front-page flag is known only when the query runs.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPStan identifier.
		// @phpstan-ignore booleanNot.alwaysTrue
		if ( function_exists( 'is_home' ) && is_home() && ! $this->is_front() ) {
			return $this->filter( $this->posts_page_trail() );
		}

		return $this->filter( [ $this->home_crumb() ] );
	}

	/**
	 * Trail for one post, page, or product.
	 *
	 * @param WP_Post $post Post object.
	 * @return list<array{name: string, url: string}>
	 */
	public function for_post( WP_Post $post ): array {
		$crumbs    = [ $this->home_crumb() ];
		$crumbs    = array_merge( $crumbs, $this->ancestors_for_post( $post ) );
		$permalink = get_permalink( $post );
		$crumbs[]  = [
			'name' => $this->plain( (string) $post->post_title, 110 ),
			'url'  => is_string( $permalink ) ? $permalink : '',
		];

		return $this->filter( $crumbs );
	}

	/**
	 * Primary category name for a post, used as the article section.
	 *
	 * Pages and other types have no section. The name matches the category
	 * crumb this class puts on the trail.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function article_section( WP_Post $post ): string {
		if ( 'post' !== $post->post_type || ! function_exists( 'get_the_category' ) ) {
			return '';
		}

		$categories = get_the_category( (int) $post->ID );
		if ( ! is_array( $categories ) || ! isset( $categories[0] ) || ! $categories[0] instanceof WP_Term ) {
			return '';
		}

		return $this->plain( (string) $categories[0]->name, 80 );
	}

	/**
	 * Crumbs that sit between Home and the current post.
	 *
	 * @param WP_Post $post Post object.
	 * @return list<array{name: string, url: string}>
	 */
	private function ancestors_for_post( WP_Post $post ): array {
		if ( 'page' === $post->post_type || ( (int) $post->post_parent > 0 && 'post' !== $post->post_type && 'product' !== $post->post_type ) ) {
			return $this->page_ancestors( $post );
		}

		if ( 'post' === $post->post_type ) {
			return $this->category_chain( (int) $post->ID );
		}

		if ( 'product' === $post->post_type ) {
			$crumbs = [];
			$shop   = $this->shop_crumb();
			if ( null !== $shop ) {
				$crumbs[] = $shop;
			}
			return array_merge( $crumbs, $this->product_category_chain( (int) $post->ID ) );
		}

		$taxonomy = $this->primary_taxonomy( $post );
		if ( '' === $taxonomy ) {
			return $this->page_ancestors( $post );
		}

		return $this->term_chain_for_post( (int) $post->ID, $taxonomy );
	}

	/**
	 * Whether this request is the site front page.
	 *
	 * @return bool
	 */
	private function is_front(): bool {
		return function_exists( 'is_front_page' ) && is_front_page();
	}

	/**
	 * Home crumb.
	 *
	 * @return array{name: string, url: string}
	 */
	private function home_crumb(): array {
		return [
			'name' => __( 'Home', 'rankmyseo' ),
			'url'  => trailingslashit( untrailingslashit( home_url( '/' ) ) ),
		];
	}

	/**
	 * Search results crumb.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function search_trail(): array {
		$query = function_exists( 'get_search_query' ) ? (string) get_search_query() : '';
		$label = '' !== $query
			? sprintf(
				/* translators: %s: search words. */
				__( 'Search results for “%s”', 'rankmyseo' ),
				$this->plain( $query, 80 )
			)
			: __( 'Search results', 'rankmyseo' );

		return [
			$this->home_crumb(),
			[
				'name' => $label,
				'url'  => home_url( '/?s=' . rawurlencode( $query ) ),
			],
		];
	}

	/**
	 * Hierarchical page ancestors, root first.
	 *
	 * @param WP_Post $post Post object.
	 * @return list<array{name: string, url: string}>
	 */
	private function page_ancestors( WP_Post $post ): array {
		if ( ! function_exists( 'get_post_ancestors' ) ) {
			return [];
		}

		$crumbs    = [];
		$ancestors = array_reverse( get_post_ancestors( $post ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_post( (int) $ancestor_id );
			if ( ! $ancestor instanceof WP_Post ) {
				continue;
			}
			$permalink = get_permalink( $ancestor );
			$crumbs[]  = [
				'name' => $this->plain( (string) $ancestor->post_title, 110 ),
				'url'  => is_string( $permalink ) ? $permalink : '',
			];
		}

		return $crumbs;
	}

	/**
	 * Primary category and its parents, root first.
	 *
	 * @param int $post_id Post ID.
	 * @return list<array{name: string, url: string}>
	 */
	private function category_chain( int $post_id ): array {
		if ( ! function_exists( 'get_the_category' ) ) {
			return [];
		}

		$categories = get_the_category( $post_id );
		if ( ! is_array( $categories ) || ! isset( $categories[0] ) || ! $categories[0] instanceof WP_Term ) {
			return [];
		}

		return $this->term_chain( $categories[0] );
	}

	/**
	 * Primary product category and its parents.
	 *
	 * @param int $post_id Product ID.
	 * @return list<array{name: string, url: string}>
	 */
	private function product_category_chain( int $post_id ): array {
		return $this->term_chain_for_post( $post_id, 'product_cat' );
	}

	/**
	 * First term of a taxonomy, with parents, for one post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return list<array{name: string, url: string}>
	 */
	private function term_chain_for_post( int $post_id, string $taxonomy ): array {
		if ( ! function_exists( 'get_the_terms' ) ) {
			return [];
		}

		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || ! isset( $terms[0] ) || ! $terms[0] instanceof WP_Term ) {
			return [];
		}

		return $this->term_chain( $terms[0] );
	}

	/**
	 * A term and its parents, root first.
	 *
	 * @param WP_Term $term Term object.
	 * @return list<array{name: string, url: string}>
	 */
	private function term_chain( WP_Term $term ): array {
		$chain   = [];
		$current = $term;
		$guard   = 0;
		while ( $current instanceof WP_Term && $guard < 10 ) {
			array_unshift(
				$chain,
				[
					'name' => $this->plain( (string) $current->name, 110 ),
					'url'  => $this->term_link( $current ),
				]
			);
			$parent_id = (int) $current->parent;
			if ( $parent_id <= 0 || ! function_exists( 'get_term' ) ) {
				break;
			}
			$current = get_term( $parent_id, (string) $current->taxonomy );
			++$guard;
		}

		return $chain;
	}

	/**
	 * Product category archive: Home, shop, then the category hierarchy.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function product_category_trail(): array {
		return $this->shop_and_term_trail();
	}

	/**
	 * Home, the shop when it exists, and the queried term hierarchy.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function shop_and_term_trail(): array {
		$crumbs = [ $this->home_crumb() ];
		$shop   = $this->shop_crumb();
		if ( null !== $shop ) {
			$crumbs[] = $shop;
		}
		$term = $this->queried_term();
		if ( $term instanceof WP_Term ) {
			$crumbs = array_merge( $crumbs, $this->term_chain( $term ) );
		}

		return $crumbs;
	}

	/**
	 * Shop archive trail.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function shop_trail(): array {
		$crumbs = [ $this->home_crumb() ];
		$shop   = $this->shop_crumb();
		if ( null !== $shop ) {
			$crumbs[] = $shop;
		}

		return $crumbs;
	}

	/**
	 * Shop page crumb when WooCommerce is active.
	 *
	 * @return array{name: string, url: string}|null
	 */
	private function shop_crumb(): ?array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return null;
		}

		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id <= 0 ) {
			return null;
		}

		$shop = get_post( $shop_id );
		if ( ! $shop instanceof WP_Post ) {
			return null;
		}

		$permalink = get_permalink( $shop );
		return [
			'name' => $this->plain( (string) $shop->post_title, 110 ),
			'url'  => is_string( $permalink ) ? $permalink : '',
		];
	}

	/**
	 * Category, tag, or custom taxonomy archive.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function term_archive_trail(): array {
		$crumbs = [ $this->home_crumb() ];
		$term   = $this->queried_term();
		if ( $term instanceof WP_Term ) {
			$crumbs = array_merge( $crumbs, $this->term_chain( $term ) );
		}

		return $crumbs;
	}

	/**
	 * Post type archive crumb.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function post_type_archive_trail(): array {
		$type  = function_exists( 'get_query_var' ) ? (string) get_query_var( 'post_type' ) : '';
		$label = '' !== $type ? $type : __( 'Archive', 'rankmyseo' );
		if ( function_exists( 'get_post_type_object' ) && '' !== $type ) {
			$object = get_post_type_object( $type );
			if ( is_object( $object ) && isset( $object->labels->name ) ) {
				$label = (string) $object->labels->name;
			}
		}

		$url = '';
		if ( function_exists( 'get_post_type_archive_link' ) && '' !== $type ) {
			$link = get_post_type_archive_link( $type );
			$url  = is_string( $link ) ? $link : '';
		}
		if ( '' === $url && '' !== $type ) {
			$url = home_url( '/' . $type . '/' );
		}

		return [
			$this->home_crumb(),
			[
				'name' => $this->plain( $label, 110 ),
				'url'  => $url,
			],
		];
	}

	/**
	 * Author archive crumb.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function author_trail(): array {
		$name = __( 'Author', 'rankmyseo' );
		$id   = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( function_exists( 'get_queried_object' ) ) {
			$object = get_queried_object();
			if ( is_object( $object ) && isset( $object->display_name ) && is_string( $object->display_name ) ) {
				$name = $this->plain( $object->display_name, 110 );
			}
		}

		$url = function_exists( 'get_author_posts_url' ) && $id > 0
			? (string) get_author_posts_url( $id )
			: home_url( '/author/' . $id . '/' );

		return [
			$this->home_crumb(),
			[
				'name' => $name,
				'url'  => $url,
			],
		];
	}

	/**
	 * Year, month, and day archive crumbs.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function date_trail(): array {
		$year   = function_exists( 'get_query_var' ) ? (int) get_query_var( 'year' ) : 0;
		$month  = function_exists( 'get_query_var' ) ? (int) get_query_var( 'monthnum' ) : 0;
		$day    = function_exists( 'get_query_var' ) ? (int) get_query_var( 'day' ) : 0;
		$crumbs = [ $this->home_crumb() ];

		if ( $year > 0 ) {
			$crumbs[] = [
				'name' => (string) $year,
				'url'  => home_url( '/' . $year . '/' ),
			];
		}
		if ( $month > 0 && $year > 0 ) {
			$crumbs[] = [
				'name' => $this->month_name( $month, $year ),
				'url'  => home_url( '/' . $year . '/' . sprintf( '%02d', $month ) . '/' ),
			];
		}
		if ( $day > 0 && $month > 0 && $year > 0 ) {
			$crumbs[] = [
				'name' => (string) $day,
				'url'  => home_url( '/' . $year . '/' . sprintf( '%02d', $month ) . '/' . sprintf( '%02d', $day ) . '/' ),
			];
		}

		return $crumbs;
	}

	/**
	 * Blog posts page when a static front page is set.
	 *
	 * @return list<array{name: string, url: string}>
	 */
	private function posts_page_trail(): array {
		$crumbs  = [ $this->home_crumb() ];
		$page_id = (int) get_option( 'page_for_posts' );
		$page    = $page_id > 0 ? get_post( $page_id ) : null;
		if ( $page instanceof WP_Post ) {
			$permalink = get_permalink( $page );
			$crumbs[]  = [
				'name' => $this->plain( (string) $page->post_title, 110 ),
				'url'  => is_string( $permalink ) ? $permalink : '',
			];
		}

		return $crumbs;
	}

	/**
	 * Whether the query is a category, tag, or taxonomy archive.
	 *
	 * @return bool
	 */
	private function is_term_archive(): bool {
		if ( function_exists( 'is_category' ) && is_category() ) {
			return true;
		}
		if ( function_exists( 'is_tag' ) && is_tag() ) {
			return true;
		}

		return function_exists( 'is_tax' ) && is_tax();
	}

	/**
	 * Queried term, when the request is a term archive.
	 *
	 * @return WP_Term|null
	 */
	private function queried_term(): ?WP_Term {
		if ( ! function_exists( 'get_queried_object' ) ) {
			return null;
		}

		$object = get_queried_object();
		return $object instanceof WP_Term ? $object : null;
	}

	/**
	 * Public URL for a term.
	 *
	 * @param WP_Term $term Term object.
	 * @return string
	 */
	private function term_link( WP_Term $term ): string {
		if ( 'category' === $term->taxonomy && function_exists( 'get_category_link' ) ) {
			$link = get_category_link( $term );
			return is_string( $link ) ? $link : '';
		}
		if ( function_exists( 'get_term_link' ) ) {
			$link = get_term_link( $term, (string) $term->taxonomy );
			return is_string( $link ) ? $link : '';
		}

		return '';
	}

	/**
	 * First public taxonomy used for a custom post type trail.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function primary_taxonomy( WP_Post $post ): string {
		if ( ! function_exists( 'get_object_taxonomies' ) ) {
			return '';
		}

		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		if ( ! is_array( $taxonomies ) ) {
			return '';
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( is_string( $taxonomy ) && '' !== $taxonomy && 'post_format' !== $taxonomy ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * Localized month name.
	 *
	 * @param int $month Month number.
	 * @param int $year  Year.
	 * @return string
	 */
	private function month_name( int $month, int $year ): string {
		$timestamp = gmmktime( 0, 0, 0, $month, 1, $year );
		if ( false === $timestamp ) {
			return (string) $month;
		}
		if ( function_exists( 'date_i18n' ) ) {
			return (string) date_i18n( 'F', $timestamp );
		}

		return gmdate( 'F', $timestamp );
	}

	/**
	 * Strip tags and trim.
	 *
	 * @param string $value Raw text.
	 * @param int    $max   Maximum characters.
	 * @return string
	 */
	private function plain( string $value, int $max ): string {
		$text = trim( wp_strip_all_tags( $value ) );
		if ( mb_strlen( $text ) > $max ) {
			return mb_substr( $text, 0, $max );
		}

		return $text;
	}

	/**
	 * Drop empty names and let other code adjust the trail.
	 *
	 * @param list<array{name: string, url: string}> $crumbs Crumbs.
	 * @return list<array{name: string, url: string}>
	 */
	private function filter( array $crumbs ): array {
		$clean = [];
		foreach ( $crumbs as $crumb ) {
			$name = trim( (string) ( $crumb['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$clean[] = [
				'name' => $name,
				'url'  => (string) ( $crumb['url'] ?? '' ),
			];
		}

		/**
		 * Filter the breadcrumb trail shared by JSON-LD and the visible breadcrumb.
		 *
		 * @param list<array{name: string, url: string}> $clean Crumbs in order.
		 */
		$filtered = apply_filters( 'rankmyseo_breadcrumb_trail', $clean );
		if ( ! is_array( $filtered ) ) {
			return $clean;
		}

		$next = [];
		foreach ( $filtered as $crumb ) {
			if ( ! is_array( $crumb ) || ! isset( $crumb['name'] ) ) {
				continue;
			}
			$name = trim( (string) $crumb['name'] );
			if ( '' === $name ) {
				continue;
			}
			$next[] = [
				'name' => $name,
				'url'  => isset( $crumb['url'] ) ? (string) $crumb['url'] : '',
			];
		}

		return $next;
	}
}
