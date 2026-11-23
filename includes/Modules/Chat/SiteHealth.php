<?php
/**
 * Deterministic local site-health score shared by the dashboard and chat.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Chat;

/**
 * Turns local audit counts into a 0–100 score and a plain-English summary.
 */
final class SiteHealth {
	/**
	 * SEO scores below this value are treated as needing work.
	 */
	public const LOW_SCORE_THRESHOLD = 60;

	/**
	 * Published items under this word count are treated as thin.
	 */
	public const THIN_WORD_COUNT = 300;

	/**
	 * Build a score, band, and one-sentence summary from local signals.
	 *
	 * @param array<string, mixed> $signals Local signals. No estimated traffic or rank data.
	 * @return array{score: int, label: string, band: string, summary: string}
	 */
	public static function summarize( array $signals ): array {
		$score = 100;

		if ( ! $signals['blog_public'] ) {
			$score -= 25;
		}

		if ( ! $signals['onboarding_completed'] ) {
			$score -= 8;
		}

		$score -= min( 24, max( 0, $signals['missing_descriptions'] ) * 3 );
		$score -= min( 18, max( 0, $signals['missing_titles'] ) * 3 );
		$score -= min( 12, max( 0, $signals['thin_posts'] ) * 2 );
		$score -= min( 15, max( 0, $signals['open_404s'] ) * 3 );
		$score -= min( 10, max( 0, $signals['low_scores'] ) );

		if ( ! $signals['sitemaps_enabled'] ) {
			$score -= 6;
		}

		if ( ! $signals['tables_healthy'] ) {
			$score -= 10;
		}

		$score = max( 0, min( 100, $score ) );

		if ( $score >= 80 ) {
			$band  = 'good';
			$label = __( 'Good', 'rankmyseo' );
		} elseif ( $score >= 50 ) {
			$band  = 'needs_work';
			$label = __( 'Needs work', 'rankmyseo' );
		} else {
			$band  = 'poor';
			$label = __( 'Poor', 'rankmyseo' );
		}

		$summary = self::summary_sentence( $signals, $label );

		return [
			'score'   => $score,
			'label'   => $label,
			'band'    => $band,
			'summary' => $summary,
		];
	}

	/**
	 * Plain-English status sentence. Numbers come only from the supplied signals.
	 *
	 * @param array<string, mixed> $signals Local signals.
	 * @param string               $label   Band label already translated.
	 * @return string
	 */
	private static function summary_sentence( array $signals, string $label ): string {
		$parts = [];

		if ( ! $signals['blog_public'] ) {
			$parts[] = __( 'Search engines are discouraged from indexing this site.', 'rankmyseo' );
		}

		if ( $signals['missing_descriptions'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of published posts or pages. */
				__( '%d published items are missing a search description.', 'rankmyseo' ),
				$signals['missing_descriptions']
			);
		}

		if ( $signals['missing_titles'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of published posts or pages. */
				__( '%d published items are missing an SEO title.', 'rankmyseo' ),
				$signals['missing_titles']
			);
		}

		if ( $signals['thin_posts'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of thin published posts or pages. */
				__( '%d published items look thin.', 'rankmyseo' ),
				$signals['thin_posts']
			);
		}

		if ( $signals['open_404s'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of unresolved broken links. */
				__( '%d broken links are still open.', 'rankmyseo' ),
				$signals['open_404s']
			);
		}

		if ( empty( $parts ) ) {
			if ( $signals['blog_public'] ) {
				return __( 'Local check looks calm. Search engines are allowed to index this site, and no urgent on-page gaps stood out.', 'rankmyseo' );
			}

			return __( 'Search engines are discouraged from indexing this site. Fix that before worrying about individual pages.', 'rankmyseo' );
		}

		return sprintf(
			/* translators: 1: health label such as Good or Needs work, 2: sentence listing local gaps. */
			__( 'Site health is %1$s. %2$s', 'rankmyseo' ),
			$label,
			implode( ' ', $parts )
		);
	}
}
