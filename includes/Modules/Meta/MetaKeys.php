<?php
/**
 * SEO meta keys owned by the titles and social module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Canonical post and term meta keys for titles, robots, and social tags.
 */
final class MetaKeys {
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

	/**
	 * Analyzer score key. This module only reads it for the posts list column.
	 * The analyzer module owns registration and writes.
	 */
	public const SEO_SCORE = '_rankmyseo_seo_score';

	/**
	 * Return every meta key this module registers.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return [
			self::TITLE,
			self::DESCRIPTION,
			self::CANONICAL,
			self::ROBOTS,
			self::FOCUS_KEYWORD,
			self::SECONDARY_KEYWORDS,
			self::OG_TITLE,
			self::OG_DESCRIPTION,
			self::OG_IMAGE,
			self::TWITTER_TITLE,
			self::TWITTER_DESCRIPTION,
			self::TWITTER_IMAGE,
			self::TWITTER_CARD,
		];
	}
}
