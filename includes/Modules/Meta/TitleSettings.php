<?php
/**
 * Global title, archive, and social default settings.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

use RankMySEO\Core\Settings;

/**
 * Stores per-type templates outside the compact core settings option.
 */
final class TitleSettings {
	public const OPTION_KEY = 'rankmyseo_title_settings';

	/**
	 * Core settings, used for the shared title separator.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Core settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Return sanitized templates merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$clean              = $this->sanitize( $stored );
		$clean['separator'] = $this->separator();

		return $clean;
	}

	/**
	 * Return templates plus live post type and taxonomy labels for the admin UI.
	 *
	 * @return array<string, mixed>
	 */
	public function for_editor(): array {
		$settings               = $this->all();
		$settings['post_types'] = $this->merge_types(
			isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : [],
			$this->discover_post_types(),
			TemplateDefaults::post_type_template()
		);
		$settings['taxonomies'] = $this->merge_types(
			isset( $settings['taxonomies'] ) && is_array( $settings['taxonomies'] ) ? $settings['taxonomies'] : [],
			$this->discover_taxonomies(),
			TemplateDefaults::taxonomy_template()
		);
		$settings['reading']    = [
			'show_on_front'  => (string) get_option( 'show_on_front', 'posts' ),
			'page_on_front'  => (int) get_option( 'page_on_front', 0 ),
			'page_for_posts' => (int) get_option( 'page_for_posts', 0 ),
		];
		$settings['site_name']  = $this->site_name();
		$settings['tagline']    = $this->tagline();

		return $settings;
	}

	/**
	 * Persist a settings payload.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public function update( array $input ): array {
		if ( isset( $input['separator'] ) ) {
			$this->settings->update(
				[
					'title_separator' => (string) $input['separator'],
				]
			);
		}

		$current = $this->all();
		unset( $current['separator'], $current['reading'], $current['site_name'], $current['tagline'] );

		$next = $this->sanitize( array_replace_recursive( $current, $input ) );
		update_option( self::OPTION_KEY, $next, true );

		return $this->for_editor();
	}

	/**
	 * Whether head tags should print even when another SEO plugin is active.
	 *
	 * @return bool
	 */
	public function is_force_head(): bool {
		$all = $this->all();
		return ! empty( $all['force_head_output'] );
	}

	/**
	 * Persist the force-head flag.
	 *
	 * @param bool $force Whether to print tags anyway.
	 * @return void
	 */
	public function set_force_head( bool $force ): void {
		$this->update( [ 'force_head_output' => $force ] );
	}

	/**
	 * Shared separator from core settings.
	 *
	 * @return string
	 */
	public function separator(): string {
		$separator = (string) $this->settings->get( 'title_separator', '|' );
		return in_array( $separator, Settings::ALLOWED_SEPARATORS, true ) ? $separator : '|';
	}

	/**
	 * Site name used in templates.
	 *
	 * @return string
	 */
	public function site_name(): string {
		$configured = trim( (string) $this->settings->get( 'site_name', '' ) );
		if ( '' !== $configured ) {
			return $configured;
		}

		return Text::plain( (string) get_bloginfo( 'name' ) );
	}

	/**
	 * Site tagline.
	 *
	 * @return string
	 */
	public function tagline(): string {
		return Text::plain( (string) get_bloginfo( 'description' ) );
	}

	/**
	 * Sanitize a stored or submitted document.
	 *
	 * @param array<string, mixed> $input Raw document.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$defaults = TemplateDefaults::blueprint();

		$homepage_in = isset( $input['homepage'] ) && is_array( $input['homepage'] ) ? $input['homepage'] : [];
		$social_in   = isset( $input['social'] ) && is_array( $input['social'] ) ? $input['social'] : [];

		$document = [
			'homepage'          => [
				'title'       => MetaSanitizer::sanitize_title( $homepage_in['title'] ?? $defaults['homepage']['title'] ),
				'description' => MetaSanitizer::sanitize_description( $homepage_in['description'] ?? $defaults['homepage']['description'] ),
			],
			'post_types'        => $this->sanitize_type_map(
				isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? $input['post_types'] : [],
				isset( $defaults['post_types'] ) && is_array( $defaults['post_types'] ) ? $defaults['post_types'] : []
			),
			'taxonomies'        => $this->sanitize_type_map(
				isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ? $input['taxonomies'] : [],
				isset( $defaults['taxonomies'] ) && is_array( $defaults['taxonomies'] ) ? $defaults['taxonomies'] : []
			),
			'archives'          => $this->sanitize_archives(
				isset( $input['archives'] ) && is_array( $input['archives'] ) ? $input['archives'] : [],
				isset( $defaults['archives'] ) && is_array( $defaults['archives'] ) ? $defaults['archives'] : []
			),
			'social'            => [
				'default_image' => MetaSanitizer::sanitize_image( $social_in['default_image'] ?? '' ),
				'twitter_card'  => MetaSanitizer::sanitize_twitter_card( $social_in['twitter_card'] ?? 'summary_large_image' ),
			],
			'image_alt'         => $this->image_alt_value( $input['image_alt'] ?? ( $defaults['image_alt'] ?? '%title%' ) ),
			'force_head_output' => $this->to_bool( $input['force_head_output'] ?? $defaults['force_head_output'] ),
		];

		return $document;
	}

	/**
	 * Site-wide image description pattern.
	 *
	 * @return string
	 */
	public function image_alt_pattern(): string {
		$all     = $this->all();
		$pattern = isset( $all['image_alt'] ) ? (string) $all['image_alt'] : '%title%';
		return '' !== $pattern ? $pattern : '%title%';
	}

	/**
	 * Title and description patterns for one post type.
	 *
	 * @param string $post_type Post type name.
	 * @return array{title: string, description: string}
	 */
	public function patterns_for( string $post_type ): array {
		$all   = $this->all();
		$types = isset( $all['post_types'] ) && is_array( $all['post_types'] ) ? $all['post_types'] : [];
		$row   = isset( $types[ $post_type ] ) && is_array( $types[ $post_type ] ) ? $types[ $post_type ] : TemplateDefaults::post_type_template();

		return [
			'title'       => (string) ( $row['title'] ?? '%title% %sep% %sitename%' ),
			'description' => (string) ( $row['description'] ?? '%excerpt%' ),
		];
	}

	/**
	 * Keep an image description pattern as a single plain line.
	 *
	 * @param mixed $value Raw pattern.
	 * @return string
	 */
	private function image_alt_value( $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '%title%';
		}

		return MetaSanitizer::sanitize_title( $value );
	}

	/**
	 * Sanitize type map.
	 *
	 * @param array<string, mixed> $input    Submitted map.
	 * @param array<string, mixed> $defaults Default map.
	 * @return array<string, array{title: string, description: string, noindex: bool}>
	 */
	private function sanitize_type_map( array $input, array $defaults ): array {
		$names = array_unique( array_merge( array_keys( $defaults ), array_keys( $input ) ) );
		$clean = [];

		foreach ( $names as $name ) {
			$key = sanitize_key( (string) $name );
			if ( '' === $key ) {
				continue;
			}

			$base = isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) ? $defaults[ $key ] : TemplateDefaults::post_type_template();
			$row  = isset( $input[ $name ] ) && is_array( $input[ $name ] ) ? $input[ $name ] : $base;

			$clean[ $key ] = [
				'title'       => MetaSanitizer::sanitize_title( $row['title'] ?? $base['title'] ?? '' ),
				'description' => MetaSanitizer::sanitize_description( $row['description'] ?? $base['description'] ?? '' ),
				'noindex'     => $this->to_bool( $row['noindex'] ?? $base['noindex'] ?? false ),
			];
		}

		return $clean;
	}

	/**
	 * Sanitize archives.
	 *
	 * @param array<string, mixed> $input    Submitted archives.
	 * @param array<string, mixed> $defaults Default archives.
	 * @return array<string, array{title: string, description: string, noindex: bool}>
	 */
	private function sanitize_archives( array $input, array $defaults ): array {
		$clean = [];

		foreach ( $defaults as $name => $base ) {
			if ( ! is_array( $base ) ) {
				continue;
			}
			$row            = isset( $input[ $name ] ) && is_array( $input[ $name ] ) ? $input[ $name ] : $base;
			$clean[ $name ] = [
				'title'       => MetaSanitizer::sanitize_title( $row['title'] ?? $base['title'] ?? '' ),
				'description' => MetaSanitizer::sanitize_description( $row['description'] ?? $base['description'] ?? '' ),
				'noindex'     => $this->to_bool( $row['noindex'] ?? $base['noindex'] ?? false ),
			];
		}

		return $clean;
	}

	/**
	 * Merge types.
	 *
	 * @param array<string, mixed>                                     $stored Stored rows.
	 * @param array<string, array{label: string, singular: string}>    $live   Live object labels.
	 * @param array{title: string, description: string, noindex: bool} $fallback Fallback template.
	 * @return array<string, array<string, mixed>>
	 */
	private function merge_types( array $stored, array $live, array $fallback ): array {
		$merged = $stored;

		foreach ( $live as $name => $info ) {
			if ( ! isset( $merged[ $name ] ) || ! is_array( $merged[ $name ] ) ) {
				$merged[ $name ] = $fallback;
			}
			$merged[ $name ]['label']    = $info['label'];
			$merged[ $name ]['singular'] = $info['singular'];
		}

		return $merged;
	}

	/**
	 * Discover post types.
	 *
	 * @return array<string, array{label: string, singular: string}>
	 */
	private function discover_post_types(): array {
		if ( ! function_exists( 'get_post_types' ) ) {
			return [
				'post' => [
					'label'    => 'Posts',
					'singular' => 'Post',
				],
				'page' => [
					'label'    => 'Pages',
					'singular' => 'Page',
				],
			];
		}

		$objects = get_post_types( [ 'public' => true ], 'objects' );
		$found   = [];

		if ( ! is_array( $objects ) ) {
			return $found;
		}

		foreach ( $objects as $name => $object ) {
			if ( ! $object instanceof \WP_Post_Type ) {
				continue;
			}
			$found[ (string) $name ] = [
				'label'    => Text::plain( (string) $object->labels->name ),
				'singular' => Text::plain( (string) $object->labels->singular_name ),
			];
		}

		return $found;
	}

	/**
	 * Discover taxonomies.
	 *
	 * @return array<string, array{label: string, singular: string}>
	 */
	private function discover_taxonomies(): array {
		if ( ! function_exists( 'get_taxonomies' ) ) {
			return [
				'category' => [
					'label'    => 'Categories',
					'singular' => 'Category',
				],
				'post_tag' => [
					'label'    => 'Tags',
					'singular' => 'Tag',
				],
			];
		}

		$objects = get_taxonomies( [ 'public' => true ], 'objects' );
		$found   = [];

		if ( ! is_array( $objects ) ) {
			return $found;
		}

		foreach ( $objects as $name => $object ) {
			if ( ! $object instanceof \WP_Taxonomy ) {
				continue;
			}
			$found[ (string) $name ] = [
				'label'    => Text::plain( (string) $object->labels->name ),
				'singular' => Text::plain( (string) $object->labels->singular_name ),
			];
		}

		return $found;
	}

	/**
	 * To bool.
	 *
	 * @param mixed $value Raw boolean.
	 * @return bool
	 */
	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, [ '1', 'true', 'yes', 'on' ], true );
	}
}
