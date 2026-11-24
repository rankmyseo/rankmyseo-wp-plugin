<?php
/**
 * Builds a SchemaContext from WordPress query state.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

use RankMySEO\Core\Settings;
use WP_Post;

/**
 * Translates the current request or a specific post into graph-builder input.
 */
final class ContextFactory {
	/**
	 * Schema settings.
	 *
	 * @var SchemaSettings
	 */
	private SchemaSettings $schema_settings;

	/**
	 * Product reader.
	 *
	 * @var WooProductReader
	 */
	private WooProductReader $products;

	/**
	 * Core plugin settings, used as identity fallbacks.
	 *
	 * @var Settings|null
	 */
	private ?Settings $core;

	/**
	 * Constructor.
	 *
	 * @param SchemaSettings        $schema_settings Schema settings.
	 * @param WooProductReader|null $products       Product reader.
	 * @param Settings|null         $core            Core settings.
	 */
	public function __construct( SchemaSettings $schema_settings, ?WooProductReader $products = null, ?Settings $core = null ) {
		$this->schema_settings = $schema_settings;
		$this->core            = $core;
		$currency              = (string) $schema_settings->all()['currency'];
		$this->products        = $products ?? new WooProductReader( $currency );
	}

	/**
	 * Context for the current front-end request.
	 *
	 * @return SchemaContext
	 */
	public function for_request(): SchemaContext {
		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
			if ( $post_id > 0 ) {
				return $this->for_post( $post_id );
			}
		}

		$context = $this->for_homepage();
		if ( function_exists( 'is_search' ) && is_search() ) {
			$context->webpage_type = 'SearchResultsPage';
			$context->schema_type  = 'SearchResultsPage';
			$context->title        = __( 'Search results', 'rankmyseo' );
			$query                 = function_exists( 'get_search_query' ) ? (string) get_search_query() : '';
			$context->permalink    = home_url( '/?s=' . rawurlencode( $query ) );
		} elseif ( function_exists( 'is_archive' ) && is_archive() ) {
			$context->webpage_type = 'CollectionPage';
			$context->schema_type  = 'CollectionPage';
			$context->title        = '' !== $context->title ? $context->title : __( 'Archive', 'rankmyseo' );
		}

		return $context;
	}

	/**
	 * Context for the site homepage.
	 *
	 * @return SchemaContext
	 */
	public function for_homepage(): SchemaContext {
		$context                 = $this->base_context();
		$context->is_front_page  = true;
		$context->permalink      = $context->home_url;
		$context->title          = $context->site_name;
		$context->description    = $context->site_description;
		$context->webpage_type   = 'WebPage';
		$context->schema_type    = 'WebPage';
		$context->breadcrumbs    = [
			[
				'name' => __( 'Home', 'rankmyseo' ),
				'url'  => trailingslashit( untrailingslashit( $context->home_url ) ),
			],
		];
		$context->local_business = $this->schema_settings->local_business_snapshot();

		return $context;
	}

	/**
	 * Context for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return SchemaContext
	 */
	public function for_post( int $post_id ): SchemaContext {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $this->for_homepage();
		}

		$context                 = $this->base_context();
		$permalink               = get_permalink( $post );
		$context->permalink      = is_string( $permalink ) && '' !== $permalink ? $permalink : $context->home_url;
		$context->title          = $this->plain( (string) $post->post_title, 200 );
		$context->description    = $this->description_from_post( $post );
		$context->date_published = $this->iso_date( (string) $post->post_date_gmt );
		$context->date_modified  = $this->iso_date( (string) $post->post_modified_gmt );
		$context->word_count     = $this->word_count( (string) $post->post_content );
		$context->image_url      = $this->thumbnail( $post_id );
		$context->author_name    = $this->author_name( (int) $post->post_author );

		$override = '';
		$meta     = get_post_meta( $post_id, SchemaMeta::META_TYPE, true );
		if ( is_string( $meta ) ) {
			$override = SchemaMeta::sanitize_type( $meta );
		}

		if ( '' !== $override ) {
			$schema_type = $override;
		} else {
			$schema_type = $this->schema_settings->schema_type_for_post_type( (string) $post->post_type );
			if ( 'product' === $post->post_type && 'Product' === $schema_type && ! $this->schema_settings->is_product_schema_enabled() ) {
				$schema_type = 'WebPage';
			}
		}

		if ( in_array( $schema_type, SchemaMeta::WEBPAGE_TYPES, true ) ) {
			$context->webpage_type = $schema_type;
		} else {
			$context->webpage_type = 'WebPage';
		}
		$context->schema_type           = $schema_type;
		$context->author_is_site_person = 'person' === $context->entity_type && $context->author_name === $context->site_name;
		$context->faqs                  = SchemaMeta::read_faqs( $post_id );

		$custom                = get_post_meta( $post_id, SchemaMeta::META_CUSTOM, true );
		$context->custom_graph = SchemaMeta::decode_graph( is_string( $custom ) ? $custom : '' );

		if ( 'Product' === $context->schema_type ) {
			$context->product = $this->products->read( $post_id );
		}

		$context->breadcrumbs    = $this->breadcrumbs( $post, $context );
		$context->local_business = $this->schema_settings->local_business_snapshot( 'LocalBusiness' === $context->schema_type );

		return $context;
	}

	/**
	 * Shared site identity fields.
	 *
	 * @return SchemaContext
	 */
	private function base_context(): SchemaContext {
		$settings = $this->schema_settings->for_admin( $this->fallback_name(), $this->fallback_type() );
		$context  = new SchemaContext();

		$context->home_url         = home_url( '/' );
		$context->permalink        = $context->home_url;
		$context->site_name        = (string) $settings['name'];
		$context->site_description = (string) $settings['description'];
		if ( '' === $context->site_description ) {
			$context->site_description = sanitize_text_field( (string) get_bloginfo( 'description' ) );
		}
		$context->entity_type    = (string) $settings['entity_type'];
		$context->logo_url       = (string) $settings['logo_url'];
		$context->telephone      = (string) $settings['telephone'];
		$same_as                 = $settings['same_as'];
		$context->same_as        = is_array( $same_as ) ? array_values( array_map( 'strval', $same_as ) ) : [];
		$context->search_enabled = (bool) $settings['search_action'];
		$context->language       = $this->language();

		return $context;
	}

	/**
	 * Publisher name fallback.
	 *
	 * @return string
	 */
	private function fallback_name(): string {
		if ( null !== $this->core ) {
			$name = sanitize_text_field( (string) $this->core->get( 'site_name', '' ) );
			if ( '' !== $name ) {
				return $name;
			}
		}

		return sanitize_text_field( (string) get_bloginfo( 'name' ) );
	}

	/**
	 * Publisher type fallback.
	 *
	 * @return string
	 */
	private function fallback_type(): string {
		if ( null === $this->core ) {
			return 'organization';
		}

		$type = sanitize_key( (string) $this->core->get( 'site_type', 'organization' ) );
		return in_array( $type, [ 'organization', 'person' ], true ) ? $type : 'organization';
	}

	/**
	 * BCP 47 language tag.
	 *
	 * @return string
	 */
	private function language(): string {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US';
		$locale = str_replace( '_', '-', $locale );

		return '' !== $locale ? $locale : 'en-US';
	}

	/**
	 * Description from the excerpt or the opening of the content.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function description_from_post( WP_Post $post ): string {
		$excerpt = $this->plain( (string) $post->post_excerpt, 300 );
		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		return $this->plain( (string) $post->post_content, 300 );
	}

	/**
	 * Featured image URL.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function thumbnail( int $post_id ): string {
		if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
			return '';
		}

		$url = get_the_post_thumbnail_url( $post_id, 'full' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Author display name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function author_name( int $user_id ): string {
		if ( $user_id <= 0 || ! function_exists( 'get_the_author_meta' ) ) {
			return '';
		}

		$name = get_the_author_meta( 'display_name', $user_id );
		return is_string( $name ) ? $this->plain( $name, 120 ) : '';
	}

	/**
	 * Breadcrumb trail for a post or page.
	 *
	 * @param WP_Post       $post    Post object.
	 * @param SchemaContext $context Page context. Article section is filled for posts.
	 * @return list<array{name: string, url: string}>
	 */
	private function breadcrumbs( WP_Post $post, SchemaContext $context ): array {
		$trail = BreadcrumbTrail::for_post( $post, $context->home_url, $context->title, $context->permalink );
		if ( '' !== $trail['section'] ) {
			$context->article_section = $trail['section'];
		}

		return $trail['crumbs'];
	}

	/**
	 * Convert a GMT MySQL datetime to ISO 8601.
	 *
	 * @param string $mysql_gmt GMT datetime.
	 * @return string
	 */
	private function iso_date( string $mysql_gmt ): string {
		$mysql_gmt = trim( $mysql_gmt );
		if ( '' === $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
			return '';
		}

		$timestamp = strtotime( $mysql_gmt . ' GMT' );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'c', $timestamp );
	}

	/**
	 * Count words in HTML content.
	 *
	 * @param string $html Post content.
	 * @return int
	 */
	private function word_count( string $html ): int {
		$text = trim( wp_strip_all_tags( $html ) );
		if ( '' === $text ) {
			return 0;
		}

		$parts = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * Strip tags and trim to a maximum length.
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
}
