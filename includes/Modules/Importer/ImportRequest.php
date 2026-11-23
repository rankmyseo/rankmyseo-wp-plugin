<?php
/**
 * Options for one preview or import batch.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Value object describing which source data a batch should copy.
 */
final class ImportRequest {
	/**
	 * Source ID: yoast, rankmath, or aioseo.
	 *
	 * @var string
	 */
	public string $source = '';

	/**
	 * Maximum source rows to read in this request.
	 *
	 * @var int
	 */
	public int $batch_size = 100;

	/**
	 * Continuation cursor, for example "posts:40". Empty starts at the beginning.
	 *
	 * @var string
	 */
	public string $cursor = '';

	/**
	 * Replace RankMySEO values that already differ.
	 *
	 * @var bool
	 */
	public bool $overwrite = false;

	/**
	 * Copy post meta.
	 *
	 * @var bool
	 */
	public bool $include_posts = true;

	/**
	 * Copy term meta.
	 *
	 * @var bool
	 */
	public bool $include_terms = true;

	/**
	 * Copy redirects.
	 *
	 * @var bool
	 */
	public bool $include_redirects = true;

	/**
	 * Copy site-level settings.
	 *
	 * @var bool
	 */
	public bool $include_settings = true;

	/**
	 * Existing import ID. Empty asks the service to create one.
	 *
	 * @var string
	 */
	public string $import_id = '';

	/**
	 * Phases to run, in execution order.
	 *
	 * @return array<int, string>
	 */
	public function phases(): array {
		$phases = [];

		if ( $this->include_settings ) {
			$phases[] = 'settings';
		}
		if ( $this->include_posts ) {
			$phases[] = 'posts';
		}
		if ( $this->include_terms ) {
			$phases[] = 'terms';
		}
		if ( $this->include_redirects ) {
			$phases[] = 'redirects';
		}

		return $phases;
	}
}
