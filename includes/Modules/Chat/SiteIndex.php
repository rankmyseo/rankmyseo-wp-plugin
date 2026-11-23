<?php
/**
 * Read-only local site index used by the offline SEO assistant.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Chat;

/**
 * Contract for deterministic, local-only site queries.
 *
 * Implementations must not perform outbound HTTP requests.
 */
interface SiteIndex {
	/**
	 * Count published posts and pages missing a custom SEO title.
	 *
	 * @return int
	 */
	public function count_missing_titles(): int;

	/**
	 * List published posts and pages missing a custom SEO title.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function list_missing_titles( int $limit ): array;

	/**
	 * Count published posts and pages missing a meta description.
	 *
	 * @return int
	 */
	public function count_missing_descriptions(): int;

	/**
	 * List published posts and pages missing a meta description.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function list_missing_descriptions( int $limit ): array;

	/**
	 * Count published posts and pages whose SEO score is below a threshold.
	 *
	 * @param int $threshold Scores strictly below this value are included.
	 * @return int
	 */
	public function count_low_scores( int $threshold ): int;

	/**
	 * List published posts and pages with a low SEO score.
	 *
	 * @param int $threshold Scores strictly below this value are included.
	 * @param int $limit     Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function list_low_scores( int $threshold, int $limit ): array;

	/**
	 * Count published posts and pages under a word-count threshold.
	 *
	 * @param int $min_words Minimum comfortable word count.
	 * @return int
	 */
	public function count_thin_posts( int $min_words ): int;

	/**
	 * List thin published posts and pages.
	 *
	 * @param int $min_words Minimum comfortable word count.
	 * @param int $limit     Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function list_thin_posts( int $min_words, int $limit ): array;

	/**
	 * Count unresolved 404 log rows.
	 *
	 * @return int
	 */
	public function count_open_404s(): int;

	/**
	 * List recent unresolved 404 log rows.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function list_open_404s( int $limit ): array;

	/**
	 * Return redirect totals from the local redirects table.
	 *
	 * @return array{total: int, active: int}
	 */
	public function redirect_counts(): array;

	/**
	 * Load one post with its SEO fields.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function get_post( int $post_id ): ?array;

	/**
	 * Find posts by numeric ID, slug, or title.
	 *
	 * @param string $lookup Numeric ID, slug, or title fragment.
	 * @param int    $limit  Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function find_posts( string $lookup, int $limit ): array;
}
