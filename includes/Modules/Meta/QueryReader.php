<?php
/**
 * Reads the main WordPress query into a QuerySnapshot.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Front-end query adapter. Unit tests build QuerySnapshot values directly.
 */
final class QueryReader {
	/**
	 * Capture the current front-end query.
	 *
	 * @return QuerySnapshot
	 */
	public function current(): QuerySnapshot {
		$snapshot = new QuerySnapshot();
		$this->fill_clock( $snapshot );

		if ( is_404() ) {
			$snapshot->kind  = 'not_found';
			$snapshot->title = __( 'Page not found', 'rankmyseo' );
			return $this->finish( $snapshot );
		}

		if ( is_search() ) {
			$snapshot->kind          = 'search';
			$snapshot->search_phrase = (string) get_search_query();
			$snapshot->title         = $snapshot->search_phrase;
			$snapshot->canonical     = (string) get_search_link();
			return $this->finish( $snapshot );
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$snapshot->kind = is_front_page() ? 'front_page' : 'singular';
				$this->fill_post( $snapshot, $post );
				return $this->finish( $snapshot );
			}
		}

		if ( is_front_page() && is_home() ) {
			$snapshot->kind      = 'home';
			$snapshot->title     = Text::plain( (string) get_bloginfo( 'name' ) );
			$snapshot->canonical = (string) home_url( '/' );
			return $this->finish( $snapshot );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$snapshot->kind             = 'tax';
				$snapshot->object_id        = (int) $term->term_id;
				$snapshot->taxonomy         = (string) $term->taxonomy;
				$snapshot->term_title       = Text::plain( (string) $term->name );
				$snapshot->term_description = Text::plain( (string) $term->description );
				$snapshot->title            = $snapshot->term_title;
				$link                       = get_term_link( $term );
				$snapshot->canonical        = is_string( $link ) ? $link : '';
				return $this->finish( $snapshot );
			}
		}

		if ( is_author() ) {
			$user           = get_queried_object();
			$snapshot->kind = 'author';
			if ( $user instanceof \WP_User ) {
				$snapshot->object_id = (int) $user->ID;
				$snapshot->author    = Text::plain( (string) $user->display_name );
				$snapshot->title     = $snapshot->author;
				$snapshot->canonical = (string) get_author_posts_url( (int) $user->ID );
			}
			return $this->finish( $snapshot );
		}

		if ( is_date() ) {
			$snapshot->kind       = 'date';
			$snapshot->date_label = $this->date_label();
			$snapshot->title      = $snapshot->date_label;
			return $this->finish( $snapshot );
		}

		$shop_id = $this->woocommerce_shop_page_id();
		if ( $shop_id > 0 ) {
			$shop = get_post( $shop_id );
			if ( $shop instanceof \WP_Post ) {
				$snapshot->kind = 'singular';
				$this->fill_post( $snapshot, $shop );
				return $this->finish( $snapshot );
			}
		}

		if ( is_post_type_archive() ) {
			$snapshot->kind = 'post_type_archive';
			$this->fill_post_type_archive( $snapshot );
			return $this->finish( $snapshot );
		}

		return $this->finish( $snapshot );
	}

	/**
	 * Fill post.
	 *
	 * @param QuerySnapshot $snapshot Snapshot to fill.
	 * @param \WP_Post      $post     Queried post.
	 * @return void
	 */
	private function fill_post( QuerySnapshot $snapshot, \WP_Post $post ): void {
		$snapshot->object_id = (int) $post->ID;
		$snapshot->post_type = (string) $post->post_type;
		$snapshot->title     = Text::plain( (string) $post->post_title );
		$snapshot->author    = Text::plain( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) );
		$snapshot->excerpt   = $this->excerpt_from_post( $post );
		$snapshot->category  = $this->primary_term( (int) $post->ID, (string) $post->post_type, [ 'category', 'product_cat' ] );
		$snapshot->tag       = $this->primary_term( (int) $post->ID, (string) $post->post_type, [ 'post_tag' ] );

		$object = get_post_type_object( (string) $post->post_type );
		if ( $object instanceof \WP_Post_Type ) {
			$snapshot->pt_single = $this->label_text( $object->labels, 'singular_name' );
			$snapshot->pt_plural = $this->label_text( $object->labels, 'name' );
		}

		$permalink           = get_permalink( $post );
		$snapshot->permalink = is_string( $permalink ) ? $permalink : '';
		$snapshot->canonical = $this->singular_canonical( $snapshot->permalink );
		$snapshot->published = $this->iso_date( (string) $post->post_date_gmt );
		$snapshot->modified  = $this->iso_date( (string) $post->post_modified_gmt );

		$thumb_id = (int) get_post_thumbnail_id( $post->ID );
		if ( $thumb_id > 0 ) {
			$image = wp_get_attachment_image_src( $thumb_id, 'full' );
			if ( is_array( $image ) && isset( $image[0] ) && is_string( $image[0] ) ) {
				$snapshot->featured_image  = $image[0];
				$snapshot->featured_width  = isset( $image[1] ) ? (int) $image[1] : 0;
				$snapshot->featured_height = isset( $image[2] ) ? (int) $image[2] : 0;
			}
		}
	}

	/**
	 * Excerpt from post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function excerpt_from_post( \WP_Post $post ): string {
		$excerpt = trim( (string) $post->post_excerpt );
		if ( '' === $excerpt ) {
			$excerpt = (string) wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 30, '' );
		}

		return Text::plain( $excerpt );
	}

	/**
	 * Singular canonical.
	 *
	 * @param string $permalink Permalink without the multipage suffix.
	 * @return string
	 */
	private function singular_canonical( string $permalink ): string {
		if ( '' === $permalink ) {
			return '';
		}

		$page = (int) get_query_var( 'page' );
		if ( $page < 2 ) {
			return $permalink;
		}

		if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
			return (string) add_query_arg( 'page', (string) $page, $permalink );
		}

		return trailingslashit( $permalink ) . user_trailingslashit( (string) $page, 'single_paged' );
	}

	/**
	 * Shop page WooCommerce serves at the product archive.
	 *
	 * That URL is not singular, so the generic archive branch would ignore the
	 * Shop page's title, description, robots, canonical, and social meta.
	 *
	 * @return int Shop page ID, or 0 when this request is not that archive.
	 */
	private function woocommerce_shop_page_id(): int {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}

		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id <= 0 ) {
			return 0;
		}

		$is_shop            = function_exists( 'is_shop' ) && is_shop();
		$is_product_archive = function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'product' );
		if ( ! $is_shop && ! $is_product_archive ) {
			return 0;
		}

		return $shop_id;
	}

	/**
	 * Fill post type archive.
	 *
	 * @param QuerySnapshot $snapshot Snapshot to fill.
	 * @return void
	 */
	private function fill_post_type_archive( QuerySnapshot $snapshot ): void {
		$post_type = get_query_var( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_type = (string) ( $post_type[0] ?? '' );
		}

		$object = get_post_type_object( (string) $post_type );
		if ( $object instanceof \WP_Post_Type ) {
			$snapshot->post_type = (string) $object->name;
			$snapshot->pt_single = $this->label_text( $object->labels, 'singular_name' );
			$snapshot->pt_plural = $this->label_text( $object->labels, 'name' );
			$snapshot->title     = $snapshot->pt_plural;
			$link                = get_post_type_archive_link( (string) $object->name );
			$snapshot->canonical = is_string( $link ) ? $link : '';
		}
	}

	/**
	 * Date label.
	 *
	 * @return string
	 */
	private function date_label(): string {
		$year  = (int) get_query_var( 'year' );
		$month = (int) get_query_var( 'monthnum' );
		$day   = (int) get_query_var( 'day' );

		if ( $year > 0 && $month > 0 && $day > 0 ) {
			return (string) wp_date( 'F j, Y', (int) gmmktime( 0, 0, 0, $month, $day, $year ) );
		}
		if ( $year > 0 && $month > 0 ) {
			return (string) wp_date( 'F Y', (int) gmmktime( 0, 0, 0, $month, 1, $year ) );
		}
		if ( $year > 0 ) {
			return (string) $year;
		}

		return '';
	}

	/**
	 * Primary term.
	 *
	 * @param int      $post_id    Post ID.
	 * @param string   $post_type  Post type.
	 * @param string[] $preferred  Preferred taxonomy slugs.
	 * @return string
	 */
	private function primary_term( int $post_id, string $post_type, array $preferred ): string {
		$taxonomies = get_object_taxonomies( $post_type, 'names' );
		if ( ! is_array( $taxonomies ) ) {
			return '';
		}

		foreach ( $preferred as $taxonomy ) {
			if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
				continue;
			}
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					return Text::plain( (string) $term->name );
				}
			}
		}

		return '';
	}

	/**
	 * Label text.
	 *
	 * @param object $labels Label object from a post type or taxonomy.
	 * @param string $field  Label field.
	 * @return string
	 */
	private function label_text( object $labels, string $field ): string {
		if ( ! isset( $labels->{$field} ) ) {
			return '';
		}

		return Text::plain( (string) $labels->{$field} );
	}

	/**
	 * Iso date.
	 *
	 * @param string $mysql GMT datetime.
	 * @return string
	 */
	private function iso_date( string $mysql ): string {
		if ( '' === $mysql || '0000-00-00 00:00:00' === $mysql ) {
			return '';
		}

		$iso = mysql2date( 'c', $mysql, false );
		return is_string( $iso ) ? $iso : '';
	}

	/**
	 * Fill clock.
	 *
	 * @param QuerySnapshot $snapshot Snapshot to fill.
	 * @return void
	 */
	private function fill_clock( QuerySnapshot $snapshot ): void {
		$snapshot->current_year  = (string) wp_date( 'Y' );
		$snapshot->current_month = (string) wp_date( 'F' );
		$snapshot->current_date  = (string) wp_date( 'F j, Y' );
		$locale                  = (string) get_locale();
		$snapshot->locale        = '' !== $locale ? str_replace( '-', '_', $locale ) : 'en_US';
	}

	/**
	 * Finish.
	 *
	 * @param QuerySnapshot $snapshot Snapshot to finish.
	 * @return QuerySnapshot
	 */
	private function finish( QuerySnapshot $snapshot ): QuerySnapshot {
		$paged = (int) get_query_var( 'paged' );
		if ( $paged < 2 ) {
			$content_page = (int) get_query_var( 'page' );
			$paged        = $content_page > 1 && 'singular' !== $snapshot->kind && 'front_page' !== $snapshot->kind ? $content_page : $paged;
		}

		if ( in_array( $snapshot->kind, [ 'singular', 'front_page' ], true ) ) {
			$content_page   = (int) get_query_var( 'page' );
			$snapshot->page = $content_page > 1 ? $content_page : 0;
		} else {
			$snapshot->page = $paged > 1 ? $paged : 0;
		}

		if ( $snapshot->page > 1 && '' !== $snapshot->canonical ) {
			$paged_link = get_pagenum_link( $snapshot->page );
			if ( is_string( $paged_link ) && '' !== $paged_link ) {
				$snapshot->canonical = $paged_link;
			}
		}

		return $snapshot;
	}
}
