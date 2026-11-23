<?php
/**
 * Default title templates and the token catalog shown in the editor.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Factory defaults for post types, taxonomies, and special archives.
 */
final class TemplateDefaults {
	/**
	 * Tokens the editor may insert. Labels are translated for the admin UI.
	 *
	 * @return list<array{token: string, label: string, description: string}>
	 */
	public static function variable_catalog(): array {
		return [
			[
				'token'       => '%title%',
				'label'       => __( 'Title', 'rankmyseo' ),
				'description' => __( 'The page, post, or archive title.', 'rankmyseo' ),
			],
			[
				'token'       => '%sep%',
				'label'       => __( 'Separator', 'rankmyseo' ),
				'description' => __( 'The separator chosen in Titles & Meta.', 'rankmyseo' ),
			],
			[
				'token'       => '%sitename%',
				'label'       => __( 'Site name', 'rankmyseo' ),
				'description' => __( 'Your site name.', 'rankmyseo' ),
			],
			[
				'token'       => '%tagline%',
				'label'       => __( 'Tagline', 'rankmyseo' ),
				'description' => __( 'The site tagline from WordPress settings.', 'rankmyseo' ),
			],
			[
				'token'       => '%excerpt%',
				'label'       => __( 'Excerpt', 'rankmyseo' ),
				'description' => __( 'The excerpt, or the start of the content when the excerpt is empty.', 'rankmyseo' ),
			],
			[
				'token'       => '%category%',
				'label'       => __( 'Category', 'rankmyseo' ),
				'description' => __( 'The primary category name.', 'rankmyseo' ),
			],
			[
				'token'       => '%page%',
				'label'       => __( 'Page number', 'rankmyseo' ),
				'description' => __( 'The page number on paginated views. Empty on the first page.', 'rankmyseo' ),
			],
			[
				'token'       => '%currentyear%',
				'label'       => __( 'Current year', 'rankmyseo' ),
				'description' => __( 'The current year.', 'rankmyseo' ),
			],
			[
				'token'       => '%currentmonth%',
				'label'       => __( 'Current month', 'rankmyseo' ),
				'description' => __( 'The current month name.', 'rankmyseo' ),
			],
			[
				'token'       => '%author%',
				'label'       => __( 'Author', 'rankmyseo' ),
				'description' => __( 'The author display name.', 'rankmyseo' ),
			],
			[
				'token'       => '%term_title%',
				'label'       => __( 'Term name', 'rankmyseo' ),
				'description' => __( 'The category, tag, or other term name.', 'rankmyseo' ),
			],
			[
				'token'       => '%term_description%',
				'label'       => __( 'Term description', 'rankmyseo' ),
				'description' => __( 'The term description.', 'rankmyseo' ),
			],
			[
				'token'       => '%searchphrase%',
				'label'       => __( 'Search phrase', 'rankmyseo' ),
				'description' => __( 'The words someone searched for on your site.', 'rankmyseo' ),
			],
			[
				'token'       => '%date%',
				'label'       => __( 'Archive date', 'rankmyseo' ),
				'description' => __( 'The year, month, or day of a date archive.', 'rankmyseo' ),
			],
			[
				'token'       => '%pt_plural%',
				'label'       => __( 'Post type plural', 'rankmyseo' ),
				'description' => __( 'The plural label of the post type.', 'rankmyseo' ),
			],
		];
	}

	/**
	 * Default template for a public post type.
	 *
	 * @return array{title: string, description: string, noindex: bool}
	 */
	public static function post_type_template(): array {
		return [
			'title'       => '%title% %sep% %sitename%',
			'description' => '%excerpt%',
			'noindex'     => false,
		];
	}

	/**
	 * Default template for a public taxonomy.
	 *
	 * @return array{title: string, description: string, noindex: bool}
	 */
	public static function taxonomy_template(): array {
		return [
			'title'       => '%term_title% %sep% %sitename%',
			'description' => '%term_description%',
			'noindex'     => false,
		];
	}

	/**
	 * Full default settings document before a site customizes it.
	 *
	 * @return array<string, mixed>
	 */
	public static function blueprint(): array {
		return [
			'homepage'          => [
				'title'       => '%sitename% %sep% %tagline%',
				'description' => '%tagline%',
			],
			'post_types'        => [
				'post' => self::post_type_template(),
				'page' => self::post_type_template(),
			],
			'taxonomies'        => [
				'category' => self::taxonomy_template(),
				'post_tag' => self::taxonomy_template(),
			],
			'archives'          => [
				'author'    => [
					'title'       => '%author% %sep% %sitename%',
					'description' => '',
					'noindex'     => false,
				],
				'date'      => [
					'title'       => '%date% %sep% %sitename%',
					'description' => '',
					'noindex'     => false,
				],
				'search'    => [
					'title'       => '%searchphrase% %sep% %sitename%',
					'description' => '',
					'noindex'     => false,
				],
				'not_found' => [
					'title'       => __( 'Page not found', 'rankmyseo' ) . ' %sep% %sitename%',
					'description' => '',
					'noindex'     => true,
				],
			],
			'social'            => [
				'default_image' => '',
				'twitter_card'  => 'summary_large_image',
			],
			'force_head_output' => false,
		];
	}
}
