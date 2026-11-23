<?php
/**
 * Destination keys that match the titles, redirects, and schema modules.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Canonical meta keys, option keys, and template tokens owned by sibling modules.
 *
 * Post and term meta match PR #6 (`_rankmyseo_og_image` and `_rankmyseo_twitter_image`
 * hold either a URL or an attachment ID). Redirect rows match the foundation
 * `rankmyseo_redirects` table. Schema identity matches `rankmyseo_schema_settings`.
 */
final class MetaCatalog {
	public const TITLE               = '_rankmyseo_title';
	public const DESCRIPTION         = '_rankmyseo_description';
	public const CANONICAL           = '_rankmyseo_canonical';
	public const ROBOTS              = '_rankmyseo_robots';
	public const FOCUS_KEYWORD       = '_rankmyseo_focus_keyword';
	public const SECONDARY_KEYWORDS  = '_rankmyseo_secondary_keywords';
	public const OG_TITLE            = '_rankmyseo_og_title';
	public const OG_DESCRIPTION      = '_rankmyseo_og_description';
	public const OG_IMAGE            = '_rankmyseo_og_image';
	public const TWITTER_TITLE       = '_rankmyseo_twitter_title';
	public const TWITTER_DESCRIPTION = '_rankmyseo_twitter_description';
	public const TWITTER_IMAGE       = '_rankmyseo_twitter_image';
	public const TWITTER_CARD        = '_rankmyseo_twitter_card';

	public const OPTION_CORE      = 'rankmyseo_settings';
	public const OPTION_TITLES    = 'rankmyseo_title_settings';
	public const OPTION_SCHEMA    = 'rankmyseo_schema_settings';
	public const OPTION_HISTORY   = 'rankmyseo_import_log';
	public const OPTION_REDIRECTS = 'rankmyseo_redirects_settings';

	public const TITLE_MAX       = 255;
	public const DESCRIPTION_MAX = 500;
	public const KEYWORD_MAX     = 200;
	public const SECONDARY_MAX   = 5;

	/**
	 * Template tokens accepted by the titles module.
	 *
	 * @var array<int, string>
	 */
	public const TOKENS = [
		'title',
		'sep',
		'sitename',
		'tagline',
		'excerpt',
		'category',
		'page',
		'currentyear',
		'currentmonth',
		'author',
		'term_title',
		'term_description',
		'searchphrase',
		'date',
		'pt_plural',
	];

	/**
	 * Human labels for mapped fields.
	 *
	 * @return array<string, string>
	 */
	public static function field_labels(): array {
		return [
			self::TITLE               => __( 'SEO title', 'rankmyseo' ),
			self::DESCRIPTION         => __( 'Meta description', 'rankmyseo' ),
			self::CANONICAL           => __( 'Canonical URL', 'rankmyseo' ),
			self::ROBOTS              => __( 'Robots', 'rankmyseo' ),
			self::FOCUS_KEYWORD       => __( 'Focus keyword', 'rankmyseo' ),
			self::SECONDARY_KEYWORDS  => __( 'Secondary keywords', 'rankmyseo' ),
			self::OG_TITLE            => __( 'Social title', 'rankmyseo' ),
			self::OG_DESCRIPTION      => __( 'Social description', 'rankmyseo' ),
			self::OG_IMAGE            => __( 'Social image', 'rankmyseo' ),
			self::TWITTER_TITLE       => __( 'X title', 'rankmyseo' ),
			self::TWITTER_DESCRIPTION => __( 'X description', 'rankmyseo' ),
			self::TWITTER_IMAGE       => __( 'X image', 'rankmyseo' ),
			self::TWITTER_CARD        => __( 'X card', 'rankmyseo' ),
		];
	}
}
