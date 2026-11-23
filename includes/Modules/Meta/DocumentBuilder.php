<?php
/**
 * Turns a query snapshot plus stored meta into a title, robots, and head document.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Pure composer used by the front end and by unit tests.
 */
final class DocumentBuilder {
	/**
	 * Template engine.
	 *
	 * @var TemplateEngine
	 */
	private TemplateEngine $engine;

	/**
	 * Constructor.
	 *
	 * @param TemplateEngine|null $engine Optional engine instance.
	 */
	public function __construct( ?TemplateEngine $engine = null ) {
		$this->engine = $engine ?? new TemplateEngine();
	}

	/**
	 * Resolve the document title.
	 *
	 * @param QuerySnapshot        $snapshot  Current query.
	 * @param array<string, mixed> $meta      Object meta. Robots may be null when unset.
	 * @param array<string, mixed> $templates Global templates.
	 * @param string               $separator Title separator.
	 * @param string               $site_name Site name.
	 * @param string               $tagline   Site tagline.
	 * @return string
	 */
	public function title( QuerySnapshot $snapshot, array $meta, array $templates, string $separator, string $site_name, string $tagline ): string {
		$template = $this->template_for( $snapshot, $meta, $templates, 'title' );
		$rendered = $this->engine->replace( $template, $this->context( $snapshot, $separator, $site_name, $tagline ) );
		$filtered = apply_filters( 'rankmyseo/meta/title', $rendered ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		if ( ! is_string( $filtered ) ) {
			$filtered = $rendered;
		}

		return Text::plain( $filtered );
	}

	/**
	 * Resolve the meta description.
	 *
	 * @param QuerySnapshot        $snapshot  Current query.
	 * @param array<string, mixed> $meta      Object meta.
	 * @param array<string, mixed> $templates Global templates.
	 * @param string               $separator Title separator.
	 * @param string               $site_name Site name.
	 * @param string               $tagline   Site tagline.
	 * @return string
	 */
	public function description( QuerySnapshot $snapshot, array $meta, array $templates, string $separator, string $site_name, string $tagline ): string {
		$template = $this->template_for( $snapshot, $meta, $templates, 'description' );
		$rendered = $this->engine->replace( $template, $this->context( $snapshot, $separator, $site_name, $tagline ) );
		$filtered = apply_filters( 'rankmyseo/meta/description', $rendered ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		if ( ! is_string( $filtered ) ) {
			$filtered = $rendered;
		}

		return Text::plain( $filtered, MetaSanitizer::DESCRIPTION_MAX );
	}

	/**
	 * Resolve robots directives for the query.
	 *
	 * @param QuerySnapshot        $snapshot  Current query.
	 * @param array<string, mixed> $meta      Object meta. A null robots key means "inherit the type default".
	 * @param array<string, mixed> $templates Global templates.
	 * @return array<string, bool|int|string>
	 */
	public function robots( QuerySnapshot $snapshot, array $meta, array $templates ): array {
		$explicit = array_key_exists( 'robots', $meta ) && is_array( $meta['robots'] );
		$robots   = $explicit ? Robots::sanitize( $meta['robots'] ) : Robots::defaults();

		if ( ! $explicit && $this->default_noindex( $snapshot, $templates ) ) {
			$robots['noindex'] = true;
		}

		$filtered = apply_filters( 'rankmyseo/meta/robots', $robots ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		if ( ! is_array( $filtered ) ) {
			return $robots;
		}

		return Robots::sanitize( $filtered );
	}

	/**
	 * Build the head document for the query.
	 *
	 * @param QuerySnapshot        $snapshot  Current query.
	 * @param array<string, mixed> $meta      Object meta.
	 * @param array<string, mixed> $templates Global templates.
	 * @param string               $separator Title separator.
	 * @param string               $site_name Site name.
	 * @param string               $tagline   Site tagline.
	 * @return HeadDocument
	 */
	public function head( QuerySnapshot $snapshot, array $meta, array $templates, string $separator, string $site_name, string $tagline ): HeadDocument {
		$title       = $this->title( $snapshot, $meta, $templates, $separator, $site_name, $tagline );
		$description = $this->description( $snapshot, $meta, $templates, $separator, $site_name, $tagline );
		$social      = isset( $templates['social'] ) && is_array( $templates['social'] ) ? $templates['social'] : [];

		$document                  = new HeadDocument();
		$document->description     = $description;
		$document->canonical       = $this->canonical( $snapshot, $meta );
		$document->og_locale       = '' !== $snapshot->locale ? $snapshot->locale : 'en_US';
		$document->og_type         = $this->og_type( $snapshot );
		$document->og_title        = $this->first_text( $meta, 'og_title', $title );
		$document->og_description  = $this->first_text( $meta, 'og_description', $description );
		$document->og_url          = $document->canonical;
		$document->og_site_name    = $site_name;
		$document->og_image        = Text::image_url(
			isset( $meta['og_image'] ) ? (string) $meta['og_image'] : '',
			Text::image_url( isset( $social['default_image'] ) ? (string) $social['default_image'] : '', $snapshot->featured_image )
		);
		$document->og_image_width  = $snapshot->featured_width;
		$document->og_image_height = $snapshot->featured_height;
		$document->published_time  = $snapshot->published;
		$document->modified_time   = $snapshot->modified;

		$card = isset( $meta['twitter_card'] ) && is_string( $meta['twitter_card'] ) && '' !== $meta['twitter_card']
			? MetaSanitizer::sanitize_twitter_card( $meta['twitter_card'] )
			: MetaSanitizer::sanitize_twitter_card( $social['twitter_card'] ?? 'summary_large_image' );

		$document->twitter_card        = $card;
		$document->twitter_title       = $this->first_text( $meta, 'twitter_title', $document->og_title );
		$document->twitter_description = $this->first_text( $meta, 'twitter_description', $document->og_description );
		$document->twitter_image       = Text::image_url(
			isset( $meta['twitter_image'] ) ? (string) $meta['twitter_image'] : '',
			$document->og_image
		);

		if ( '' === $document->og_image ) {
			$document->og_image_width  = 0;
			$document->og_image_height = 0;
		}

		return $document;
	}

	/**
	 * Preview a template with an arbitrary variable bag.
	 *
	 * @param string               $title_template       Title template.
	 * @param string               $description_template Description template.
	 * @param array<string, mixed> $vars                 Sample variables.
	 * @return array{title: string, description: string}
	 */
	public function preview( string $title_template, string $description_template, array $vars ): array {
		$context = TemplateContext::from_array( $vars );

		return [
			'title'       => $this->engine->replace( MetaSanitizer::sanitize_title( $title_template ), $context ),
			'description' => $this->engine->replace( MetaSanitizer::sanitize_description( $description_template ), $context ),
		];
	}

	/**
	 * Pick the template string for a field.
	 *
	 * @param QuerySnapshot        $snapshot  Current query.
	 * @param array<string, mixed> $meta      Object meta.
	 * @param array<string, mixed> $templates Global templates.
	 * @param string               $field     title or description.
	 * @return string
	 */
	private function template_for( QuerySnapshot $snapshot, array $meta, array $templates, string $field ): string {
		$custom = isset( $meta[ $field ] ) ? trim( (string) $meta[ $field ] ) : '';
		if ( '' !== $custom && in_array( $snapshot->kind, [ 'singular', 'front_page', 'tax' ], true ) ) {
			return $this->with_page( $custom, $snapshot );
		}

		if ( in_array( $snapshot->kind, [ 'home', 'front_page' ], true ) ) {
			$homepage = isset( $templates['homepage'] ) && is_array( $templates['homepage'] ) ? $templates['homepage'] : [];
			$fallback = (string) ( $homepage[ $field ] ?? '' );
			if ( '' !== $fallback ) {
				return $this->with_page( $fallback, $snapshot );
			}
		}

		if ( in_array( $snapshot->kind, [ 'singular', 'front_page' ], true ) ) {
			$type_map = isset( $templates['post_types'] ) && is_array( $templates['post_types'] ) ? $templates['post_types'] : [];
			$row      = isset( $type_map[ $snapshot->post_type ] ) && is_array( $type_map[ $snapshot->post_type ] )
				? $type_map[ $snapshot->post_type ]
				: TemplateDefaults::post_type_template();

			return $this->with_page( (string) ( $row[ $field ] ?? '' ), $snapshot );
		}

		if ( 'tax' === $snapshot->kind ) {
			$tax_map = isset( $templates['taxonomies'] ) && is_array( $templates['taxonomies'] ) ? $templates['taxonomies'] : [];
			$row     = isset( $tax_map[ $snapshot->taxonomy ] ) && is_array( $tax_map[ $snapshot->taxonomy ] )
				? $tax_map[ $snapshot->taxonomy ]
				: TemplateDefaults::taxonomy_template();

			return $this->with_page( (string) ( $row[ $field ] ?? '' ), $snapshot );
		}

		if ( 'post_type_archive' === $snapshot->kind ) {
			$template = 'title' === $field ? '%pt_plural% %sep% %sitename%' : '';
			return $this->with_page( $template, $snapshot );
		}

		$archives = isset( $templates['archives'] ) && is_array( $templates['archives'] ) ? $templates['archives'] : [];
		$row      = isset( $archives[ $snapshot->kind ] ) && is_array( $archives[ $snapshot->kind ] ) ? $archives[ $snapshot->kind ] : [];

		return $this->with_page( (string) ( $row[ $field ] ?? '' ), $snapshot );
	}

	/**
	 * Append the page token on paginated views when the template does not mention it.
	 *
	 * @param string        $template Template string.
	 * @param QuerySnapshot $snapshot Current query.
	 * @return string
	 */
	private function with_page( string $template, QuerySnapshot $snapshot ): string {
		if ( $snapshot->page > 1 && '' !== $template && ! str_contains( $template, '%page%' ) ) {
			return $template . ' %sep% %page%';
		}

		return $template;
	}

	/**
	 * Context.
	 *
	 * @param QuerySnapshot $snapshot  Current query.
	 * @param string        $separator Separator.
	 * @param string        $site_name Site name.
	 * @param string        $tagline   Tagline.
	 * @return TemplateContext
	 */
	private function context( QuerySnapshot $snapshot, string $separator, string $site_name, string $tagline ): TemplateContext {
		$year  = '' !== $snapshot->current_year ? $snapshot->current_year : gmdate( 'Y' );
		$month = '' !== $snapshot->current_month ? $snapshot->current_month : gmdate( 'F' );
		$date  = '' !== $snapshot->current_date ? $snapshot->current_date : gmdate( 'F j, Y' );

		return TemplateContext::from_array(
			[
				'title'            => $snapshot->title,
				'sep'              => $separator,
				'sitename'         => $site_name,
				'tagline'          => $tagline,
				'sitedesc'         => $tagline,
				'excerpt'          => $snapshot->excerpt,
				'category'         => $snapshot->category,
				'tag'              => $snapshot->tag,
				'page'             => $snapshot->page > 1 ? sprintf( 'Page %d', $snapshot->page ) : '',
				'currentyear'      => $year,
				'currentmonth'     => $month,
				'currentdate'      => $date,
				'author'           => $snapshot->author,
				'searchphrase'     => $snapshot->search_phrase,
				'term_title'       => '' !== $snapshot->term_title ? $snapshot->term_title : $snapshot->title,
				'term_description' => $snapshot->term_description,
				'pt_single'        => $snapshot->pt_single,
				'pt_plural'        => $snapshot->pt_plural,
				'date'             => $snapshot->date_label,
			]
		);
	}

	/**
	 * Default noindex.
	 *
	 * @param QuerySnapshot        $snapshot  Current query.
	 * @param array<string, mixed> $templates Global templates.
	 * @return bool
	 */
	private function default_noindex( QuerySnapshot $snapshot, array $templates ): bool {
		if ( in_array( $snapshot->kind, [ 'singular', 'front_page' ], true ) ) {
			$map = isset( $templates['post_types'] ) && is_array( $templates['post_types'] ) ? $templates['post_types'] : [];
			$row = isset( $map[ $snapshot->post_type ] ) && is_array( $map[ $snapshot->post_type ] ) ? $map[ $snapshot->post_type ] : [];
			return ! empty( $row['noindex'] );
		}

		if ( 'tax' === $snapshot->kind ) {
			$map = isset( $templates['taxonomies'] ) && is_array( $templates['taxonomies'] ) ? $templates['taxonomies'] : [];
			$row = isset( $map[ $snapshot->taxonomy ] ) && is_array( $map[ $snapshot->taxonomy ] ) ? $map[ $snapshot->taxonomy ] : [];
			return ! empty( $row['noindex'] );
		}

		$archives = isset( $templates['archives'] ) && is_array( $templates['archives'] ) ? $templates['archives'] : [];
		$row      = isset( $archives[ $snapshot->kind ] ) && is_array( $archives[ $snapshot->kind ] ) ? $archives[ $snapshot->kind ] : [];

		return ! empty( $row['noindex'] );
	}

	/**
	 * Canonical.
	 *
	 * @param QuerySnapshot        $snapshot Current query.
	 * @param array<string, mixed> $meta     Object meta.
	 * @return string
	 */
	private function canonical( QuerySnapshot $snapshot, array $meta ): string {
		$custom = isset( $meta['canonical'] ) ? MetaSanitizer::sanitize_canonical( $meta['canonical'] ) : '';
		if ( '' !== $custom && in_array( $snapshot->kind, [ 'singular', 'front_page' ], true ) ) {
			return $custom;
		}

		return $snapshot->canonical;
	}

	/**
	 * Og type.
	 *
	 * @param QuerySnapshot $snapshot Current query.
	 * @return string
	 */
	private function og_type( QuerySnapshot $snapshot ): string {
		if ( 'singular' === $snapshot->kind && 'post' === $snapshot->post_type ) {
			return 'article';
		}

		return 'website';
	}

	/**
	 * First text.
	 *
	 * @param array<string, mixed> $meta     Object meta.
	 * @param string               $key      Meta field.
	 * @param string               $fallback Fallback text.
	 * @return string
	 */
	private function first_text( array $meta, string $key, string $fallback ): string {
		$value = isset( $meta[ $key ] ) ? trim( (string) $meta[ $key ] ) : '';
		return '' !== $value ? $value : $fallback;
	}
}
