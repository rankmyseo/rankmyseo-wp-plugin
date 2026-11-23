<?php
/**
 * Request data consumed by the JSON-LD graph builder.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Immutable-by-convention bag of values for one page's schema graph.
 */
final class SchemaContext {
	/**
	 * Canonical home URL.
	 *
	 * @var string
	 */
	public string $home_url = '';

	/**
	 * URL of the page being described.
	 *
	 * @var string
	 */
	public string $permalink = '';

	/**
	 * Publisher name.
	 *
	 * @var string
	 */
	public string $site_name = '';

	/**
	 * Short site or business description.
	 *
	 * @var string
	 */
	public string $site_description = '';

	/**
	 * `organization` or `person`.
	 *
	 * @var string
	 */
	public string $entity_type = 'organization';

	/**
	 * Logo or profile image URL.
	 *
	 * @var string
	 */
	public string $logo_url = '';

	/**
	 * Public telephone number.
	 *
	 * @var string
	 */
	public string $telephone = '';

	/**
	 * Profile URLs (sameAs).
	 *
	 * @var list<string>
	 */
	public array $same_as = [];

	/**
	 * Whether WebSite should include a SearchAction.
	 *
	 * @var bool
	 */
	public bool $search_enabled = true;

	/**
	 * BCP 47 language tag.
	 *
	 * @var string
	 */
	public string $language = 'en-US';

	/**
	 * Whether the current URL is the site front page.
	 *
	 * @var bool
	 */
	public bool $is_front_page = false;

	/**
	 * WebPage subtype (`WebPage`, `AboutPage`, `CollectionPage`, ...).
	 *
	 * @var string
	 */
	public string $webpage_type = 'WebPage';

	/**
	 * Content schema type, or `none` to suppress article/product/FAQ nodes.
	 *
	 * @var string
	 */
	public string $schema_type = 'WebPage';

	/**
	 * Page title / headline.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * Page description.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * Primary image URL.
	 *
	 * @var string
	 */
	public string $image_url = '';

	/**
	 * ISO 8601 publish date.
	 *
	 * @var string
	 */
	public string $date_published = '';

	/**
	 * ISO 8601 modified date.
	 *
	 * @var string
	 */
	public string $date_modified = '';

	/**
	 * Visible word count.
	 *
	 * @var int
	 */
	public int $word_count = 0;

	/**
	 * Article section (primary category name).
	 *
	 * @var string
	 */
	public string $article_section = '';

	/**
	 * Author display name.
	 *
	 * @var string
	 */
	public string $author_name = '';

	/**
	 * Whether the author is the site-wide Person identity.
	 *
	 * @var bool
	 */
	public bool $author_is_site_person = false;

	/**
	 * Breadcrumb items in order.
	 *
	 * @var list<array{name: string, url: string}>
	 */
	public array $breadcrumbs = [];

	/**
	 * FAQ questions and answers.
	 *
	 * @var list<array{question: string, answer: string}>
	 */
	public array $faqs = [];

	/**
	 * WooCommerce (or manual) product snapshot.
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $product = null;

	/**
	 * LocalBusiness field snapshot, or null when omitted.
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $local_business = null;

	/**
	 * Validated custom JSON-LD nodes that replace the generated graph.
	 *
	 * @var list<array<string, mixed>>|null
	 */
	public ?array $custom_graph = null;
}
