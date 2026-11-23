<?php
/**
 * Normalized view of the current front-end query for title rendering.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Plain data describing the page that is about to be rendered.
 */
final class QuerySnapshot {
	/**
	 * Query kind: singular, home, front_page, tax, author, date, post_type_archive, search, not_found, or none.
	 *
	 * @var string
	 */
	public string $kind = 'none';

	/**
	 * Queried object ID.
	 *
	 * @var int
	 */
	public int $object_id = 0;

	/**
	 * Post type slug when the query has one.
	 *
	 * @var string
	 */
	public string $post_type = '';

	/**
	 * Taxonomy slug when the query is a term archive.
	 *
	 * @var string
	 */
	public string $taxonomy = '';

	/**
	 * Plain object title.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * Plain excerpt or content fallback.
	 *
	 * @var string
	 */
	public string $excerpt = '';

	/**
	 * Display name of the post author.
	 *
	 * @var string
	 */
	public string $author = '';

	/**
	 * Primary category name.
	 *
	 * @var string
	 */
	public string $category = '';

	/**
	 * Primary tag name.
	 *
	 * @var string
	 */
	public string $tag = '';

	/**
	 * Term name on taxonomy archives.
	 *
	 * @var string
	 */
	public string $term_title = '';

	/**
	 * Term description on taxonomy archives.
	 *
	 * @var string
	 */
	public string $term_description = '';

	/**
	 * Search phrase.
	 *
	 * @var string
	 */
	public string $search_phrase = '';

	/**
	 * Human date label for date archives.
	 *
	 * @var string
	 */
	public string $date_label = '';

	/**
	 * Singular post type label.
	 *
	 * @var string
	 */
	public string $pt_single = '';

	/**
	 * Plural post type label.
	 *
	 * @var string
	 */
	public string $pt_plural = '';

	/**
	 * Canonical permalink for the queried object.
	 *
	 * @var string
	 */
	public string $permalink = '';

	/**
	 * Preferred canonical URL before a custom override.
	 *
	 * @var string
	 */
	public string $canonical = '';

	/**
	 * Published time in ISO 8601.
	 *
	 * @var string
	 */
	public string $published = '';

	/**
	 * Modified time in ISO 8601.
	 *
	 * @var string
	 */
	public string $modified = '';

	/**
	 * Featured image URL.
	 *
	 * @var string
	 */
	public string $featured_image = '';

	/**
	 * Featured image width in pixels.
	 *
	 * @var int
	 */
	public int $featured_width = 0;

	/**
	 * Featured image height in pixels.
	 *
	 * @var int
	 */
	public int $featured_height = 0;

	/**
	 * Site locale.
	 *
	 * @var string
	 */
	public string $locale = '';

	/**
	 * Current year.
	 *
	 * @var string
	 */
	public string $current_year = '';

	/**
	 * Current month name.
	 *
	 * @var string
	 */
	public string $current_month = '';

	/**
	 * Current date.
	 *
	 * @var string
	 */
	public string $current_date = '';

	/**
	 * Pagination number. Zero means the first page.
	 *
	 * @var int
	 */
	public int $page = 0;
}
