<?php
/**
 * Shared 0–100 score bands for the analyzer.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

/**
 * Maps a numeric score to a stable band and a short label.
 */
final class ScoreBands {
	/**
	 * Band for a score. Missing focus keyword stays neutral.
	 *
	 * @param int  $score       Score from 0 to 100.
	 * @param bool $has_keyword Whether a focus keyword is set.
	 * @return string good, needs_work, poor, or no_keyword.
	 */
	public static function band( int $score, bool $has_keyword ): string {
		if ( ! $has_keyword ) {
			return 'no_keyword';
		}
		if ( $score >= 80 ) {
			return 'good';
		}
		if ( $score >= 50 ) {
			return 'needs_work';
		}
		return 'poor';
	}

	/**
	 * Plain-language label for a band.
	 *
	 * @param string $band Band id.
	 * @return string
	 */
	public static function label( string $band ): string {
		switch ( $band ) {
			case 'good':
				return __( 'Good', 'rankmyseo' );
			case 'needs_work':
				return __( 'Needs work', 'rankmyseo' );
			case 'poor':
				return __( 'Poor', 'rankmyseo' );
			default:
				return __( 'No keyword', 'rankmyseo' );
		}
	}

	/**
	 * CSS slug for a band.
	 *
	 * @param string $band Band id.
	 * @return string
	 */
	public static function css_class( string $band ): string {
		return 'is-' . str_replace( '_', '-', $band );
	}
}
