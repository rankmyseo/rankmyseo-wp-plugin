<?php
/**
 * One SEO plugin the importer can read.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Detects and pages a single source without writing or deleting its data.
 */
interface SourceAdapter {
	/**
	 * Machine ID: yoast, rankmath, or aioseo.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Name shown on the import card.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Counts and whether the plugin is active or only leftover data.
	 *
	 * @return array{active: bool, found: bool, posts: int, terms: int, redirects: int, settings: int}
	 */
	public function detect(): array;

	/**
	 * Count records for one phase.
	 *
	 * @param string $phase settings, posts, terms, or redirects.
	 * @return int
	 */
	public function count( string $phase ): int;

	/**
	 * Page one phase.
	 *
	 * `scanned` is how far the cursor should move, even when a row maps to nothing.
	 *
	 * @param string $phase  Phase name.
	 * @param int    $offset Zero-based offset.
	 * @param int    $limit  Maximum source rows.
	 * @return array{scanned: int, items: array<int, WorkItem>}
	 */
	public function slice( string $phase, int $offset, int $limit ): array;
}
