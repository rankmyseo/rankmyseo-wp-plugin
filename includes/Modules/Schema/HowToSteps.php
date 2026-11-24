<?php
/**
 * Reads ordered steps from post content for HowTo JSON-LD.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Turns an ordered list, or numbered lines, into HowTo steps.
 */
final class HowToSteps {
	/**
	 * Maximum steps stored on one page.
	 */
	private const MAX_STEPS = 30;

	/**
	 * Steps from visible content.
	 *
	 * Ordered-list items win. Numbered lines are used when the content has no list.
	 *
	 * @param string $html Post content.
	 * @return list<array{name: string, text: string}>
	 */
	public static function from_html( string $html ): array {
		$steps = self::from_ordered_lists( $html );
		if ( [] === $steps ) {
			$steps = self::from_numbered_lines( $html );
		}

		return $steps;
	}

	/**
	 * Items inside `<ol>` lists.
	 *
	 * @param string $html Post content.
	 * @return list<array{name: string, text: string}>
	 */
	private static function from_ordered_lists( string $html ): array {
		$steps = [];
		if ( ! preg_match_all( '/<ol\b[^>]*>(.*?)<\/ol>/is', $html, $lists ) ) {
			return [];
		}

		foreach ( $lists[1] as $list ) {
			if ( ! preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', (string) $list, $items ) ) {
				continue;
			}
			foreach ( $items[1] as $item ) {
				$step = self::step( (string) $item );
				if ( null === $step ) {
					continue;
				}
				$steps[] = $step;
				if ( count( $steps ) >= self::MAX_STEPS ) {
					return $steps;
				}
			}
		}

		return $steps;
	}

	/**
	 * Lines that start with `1.` style numbering.
	 *
	 * @param string $html Post content.
	 * @return list<array{name: string, text: string}>
	 */
	private static function from_numbered_lines( string $html ): array {
		$plain = wp_strip_all_tags( $html );
		if ( ! preg_match_all( '/(?:^|\R)\s*\d+[.)]\s+(\S[^\r\n]*)/u', $plain, $lines ) ) {
			return [];
		}

		$steps = [];
		foreach ( $lines[1] as $line ) {
			$step = self::step( (string) $line );
			if ( null === $step ) {
				continue;
			}
			$steps[] = $step;
			if ( count( $steps ) >= self::MAX_STEPS ) {
				break;
			}
		}

		return $steps;
	}

	/**
	 * One step, or null when the text is empty.
	 *
	 * @param string $raw Raw HTML or text.
	 * @return array{name: string, text: string}|null
	 */
	private static function step( string $raw ): ?array {
		$text = trim( wp_strip_all_tags( $raw ) );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $text ) {
			return null;
		}

		$name = $text;
		if ( mb_strlen( $name ) > 110 ) {
			$name = rtrim( mb_substr( $name, 0, 110 ) );
		}

		return [
			'name' => $name,
			'text' => $text,
		];
	}
}
