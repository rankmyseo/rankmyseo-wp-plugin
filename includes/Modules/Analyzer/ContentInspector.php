<?php
/**
 * Local HTML inspection for the server-side SEO score.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

/**
 * Extracts words, headings, links, and images without remote requests.
 */
final class ContentInspector {
	/**
	 * Inspect post HTML.
	 *
	 * @param string $html     Post content HTML.
	 * @param string $site_url Site origin used to classify links.
	 * @return array<string, mixed>
	 */
	public function inspect( string $html, string $site_url ): array {
		$plain      = $this->plain_text( $html );
		$words      = $this->words_of( $plain );
		$sentences  = $this->sentences_of( $plain );
		$paragraphs = $this->paragraphs_of( $html );
		$headings   = $this->headings_of( $html );
		$images     = $this->images_of( $html );
		$links      = $this->link_counts( $html, $site_url );
		$syllables  = 0;
		foreach ( $words as $word ) {
			$syllables += $this->count_syllables( $word );
		}

		return [
			'plain'          => $plain,
			'words'          => $words,
			'sentences'      => $sentences,
			'paragraphs'     => $paragraphs,
			'headings'       => $headings,
			'images'         => $images,
			'internal_links' => $links['internal'],
			'external_links' => $links['external'],
			'syllables'      => $syllables,
		];
	}

	/**
	 * Whether heading levels skip a step.
	 *
	 * @param array<int, int> $levels Heading levels in document order.
	 * @return bool
	 */
	public function skipped_heading_levels( array $levels ): bool {
		$count = count( $levels );
		for ( $index = 1; $index < $count; $index++ ) {
			if ( $levels[ $index ] > $levels[ $index - 1 ] + 1 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Strip tags and scripts to a single line of text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public function plain_text( string $html ): string {
		$without = (string) preg_replace( '/<script\b[^>]*>.*?<\/script>/is', ' ', $html );
		$without = (string) preg_replace( '/<style\b[^>]*>.*?<\/style>/is', ' ', $without );
		$without = (string) preg_replace( '/<!--.*?-->/s', ' ', $without );
		$without = (string) preg_replace( '/<[^>]+>/', ' ', $without );
		$without = str_ireplace(
			[ '&nbsp;', '&amp;', '&lt;', '&gt;', '&quot;', '&#039;', '&#39;' ],
			[ ' ', '&', '<', '>', '"', "'", "'" ],
			$without
		);
		$without = (string) preg_replace( '/\s+/', ' ', $without );
		return trim( $without );
	}

	/**
	 * Normalize text for phrase matching.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	public function normalize_words( string $value ): string {
		$lower = mb_strtolower( $value );
		$clean = (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $lower );
		$clean = trim( (string) preg_replace( '/\s+/u', ' ', $clean ) );
		return $clean;
	}

	/**
	 * Word tokens.
	 *
	 * @param string $text Plain text.
	 * @return list<string>
	 */
	public function words_of( string $text ): array {
		$text = trim( $text );
		if ( '' === $text ) {
			return [];
		}
		$parts = preg_split( '/\s+/', $text );
		if ( ! is_array( $parts ) ) {
			return [];
		}
		$words = [];
		foreach ( $parts as $token ) {
			$clean = (string) preg_replace( '/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', (string) $token );
			if ( '' !== $clean ) {
				$words[] = $clean;
			}
		}
		return $words;
	}

	/**
	 * Whether the phrase appears as whole words.
	 *
	 * @param string $text    Haystack.
	 * @param string $keyword Phrase.
	 * @return bool
	 */
	public function contains_phrase( string $text, string $keyword ): bool {
		$needle = $this->normalize_words( $keyword );
		if ( '' === $needle ) {
			return false;
		}
		$hay = ' ' . $this->normalize_words( $text ) . ' ';
		return str_contains( $hay, ' ' . $needle . ' ' );
	}

	/**
	 * Count whole-phrase occurrences.
	 *
	 * @param string $text    Haystack.
	 * @param string $keyword Phrase.
	 * @return int
	 */
	public function count_phrase( string $text, string $keyword ): int {
		$needle = $this->normalize_words( $keyword );
		if ( '' === $needle ) {
			return 0;
		}
		$hay = $this->normalize_words( $text );
		if ( '' === $hay ) {
			return 0;
		}
		$count = 0;
		$from  = 0;
		$step  = max( mb_strlen( $needle ), 1 );
		while ( $from <= mb_strlen( $hay ) ) {
			$found = mb_strpos( $hay, $needle, $from );
			if ( false === $found ) {
				break;
			}
			$before      = 0 === $found || ' ' === mb_substr( $hay, $found - 1, 1 );
			$after_index = $found + mb_strlen( $needle );
			$after       = mb_strlen( $hay ) === $after_index || ' ' === mb_substr( $hay, $after_index, 1 );
			if ( $before && $after ) {
				++$count;
			}
			$from = $found + $step;
		}
		return $count;
	}

	/**
	 * Whether the phrase starts near the beginning of the text.
	 *
	 * @param string $text    Haystack.
	 * @param string $keyword Phrase.
	 * @return bool
	 */
	public function keyword_near_start( string $text, string $keyword ): bool {
		$needle = $this->normalize_words( $keyword );
		if ( '' === $needle || ! $this->contains_phrase( $text, $keyword ) ) {
			return false;
		}
		$hay = $this->normalize_words( $text );
		return str_starts_with( $hay, $needle ) || mb_strpos( $hay, $needle ) <= 20;
	}

	/**
	 * Slug form of a phrase.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	public function slugify( string $value ): string {
		$words = $this->normalize_words( $value );
		return '' === $words ? '' : str_replace( ' ', '-', $words );
	}

	/**
	 * Whether a slug contains the keyword.
	 *
	 * @param string $slug    Post slug.
	 * @param string $keyword Focus keyword.
	 * @return bool
	 */
	public function slug_contains( string $slug, string $keyword ): bool {
		$needle = $this->slugify( $keyword );
		if ( '' === $needle ) {
			return false;
		}
		return str_contains( $this->slugify( $slug ), $needle );
	}

	/**
	 * Approximate English syllable count. Mirrors the editor engine.
	 *
	 * @param string $word One word.
	 * @return int
	 */
	public function count_syllables( string $word ): int {
		$cleaned = strtolower( (string) preg_replace( '/[^a-z]/', '', $word ) );
		if ( '' === $cleaned ) {
			return 0;
		}
		if ( strlen( $cleaned ) <= 3 ) {
			return 1;
		}
		$trimmed = (string) preg_replace( '/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $cleaned );
		$trimmed = (string) preg_replace( '/^y/', '', $trimmed );
		preg_match_all( '/[aeiouy]{1,2}/', $trimmed, $matches );
		$count = count( $matches[0] );
		return max( 1, $count );
	}

	/**
	 * Pixel-width estimate used by the search preview.
	 *
	 * @param string $text      Visible text.
	 * @param int    $font_size Font size in pixels.
	 * @return int
	 */
	public function estimate_pixel_width( string $text, int $font_size ): int {
		$narrow = [ 'i', 'j', 'l', 'I', '1', '|', "'", '`', '.', ',', ':', ';', '!' ];
		$wide   = [ 'm', 'w', 'M', 'W', '@', '%' ];
		$width  = 0.0;
		$length = mb_strlen( $text );
		for ( $index = 0; $index < $length; $index++ ) {
			$char = mb_substr( $text, $index, 1 );
			if ( ' ' === $char ) {
				$width += $font_size * 0.33;
			} elseif ( in_array( $char, $narrow, true ) ) {
				$width += $font_size * 0.4;
			} elseif ( in_array( $char, $wide, true ) ) {
				$width += $font_size * 0.9;
			} else {
				$width += $font_size * 0.55;
			}
		}
		return (int) round( $width );
	}

	/**
	 * Classify one href.
	 *
	 * @param string $href     Link target.
	 * @param string $site_url Site origin.
	 * @return string internal, external, or ignore.
	 */
	public function classify_link( string $href, string $site_url ): string {
		$href = trim( $href );
		if ( '' === $href || str_starts_with( $href, '#' ) ) {
			return 'ignore';
		}
		$lower = strtolower( $href );
		if ( str_starts_with( $lower, 'mailto:' ) || str_starts_with( $lower, 'tel:' ) || str_starts_with( $lower, 'javascript:' ) ) {
			return 'ignore';
		}
		if ( str_starts_with( $href, '/' ) || str_starts_with( $href, '?' ) || str_starts_with( $href, './' ) || str_starts_with( $href, '../' ) ) {
			return 'internal';
		}

		$parts = wp_parse_url( $href );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return 'internal';
		}
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return 'ignore';
		}
		$site      = wp_parse_url( '' !== $site_url ? $site_url : home_url( '/' ) );
		$site_host = is_array( $site ) ? strtolower( (string) ( $site['host'] ?? '' ) ) : '';
		$host      = strtolower( (string) $parts['host'] );
		return $host === $site_host ? 'internal' : 'external';
	}

	/**
	 * Sentences that contain at least one word.
	 *
	 * @param string $plain Plain text.
	 * @return list<string>
	 */
	private function sentences_of( string $plain ): array {
		$parts = preg_split( '/[.!?]+/', $plain );
		if ( ! is_array( $parts ) ) {
			$parts = [];
		}
		$sentences = [];
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( [] !== $this->words_of( $part ) ) {
				$sentences[] = $part;
			}
		}
		if ( [] === $sentences && [] !== $this->words_of( $plain ) ) {
			return [ trim( $plain ) ];
		}
		return $sentences;
	}

	/**
	 * Paragraphs from p tags. Matches the editor when no paragraphs exist.
	 *
	 * @param string $html HTML.
	 * @return list<string>
	 */
	private function paragraphs_of( string $html ): array {
		preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches );
		$paragraphs = [];
		$inners     = $matches[1];
		if ( is_array( $inners ) ) {
			foreach ( $inners as $inner ) {
				$text = $this->plain_text( (string) $inner );
				if ( [] !== $this->words_of( $text ) ) {
					$paragraphs[] = $text;
				}
			}
		}
		if ( [] !== $paragraphs ) {
			return $paragraphs;
		}
		$plain = $this->plain_text( $html );
		return [] === $this->words_of( $plain ) ? [] : [ $plain ];
	}

	/**
	 * Headings in document order.
	 *
	 * @param string $html HTML.
	 * @return list<array{level: int, text: string}>
	 */
	private function headings_of( string $html ): array {
		preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER );
		$headings = [];
		foreach ( $matches as $match ) {
			$headings[] = [
				'level' => (int) $match[1],
				'text'  => $this->plain_text( (string) $match[2] ),
			];
		}
		return $headings;
	}

	/**
	 * Image alt values. Missing alt is an empty string.
	 *
	 * @param string $html HTML.
	 * @return list<array{alt: string}>
	 */
	private function images_of( string $html ): array {
		preg_match_all( '/<img\b[^>]*>/i', $html, $tags );
		$images = [];
		$list   = $tags[0];
		if ( ! is_array( $list ) ) {
			return [];
		}
		foreach ( $list as $tag ) {
			$alt = '';
			if ( preg_match( '/\balt\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', (string) $tag, $found ) ) {
				$alt = $found[1];
				if ( '' === $alt && isset( $found[2] ) ) {
					$alt = $found[2];
				}
				if ( '' === $alt && isset( $found[3] ) ) {
					$alt = $found[3];
				}
				$alt = trim( $alt );
			}
			$images[] = [ 'alt' => $alt ];
		}
		return $images;
	}

	/**
	 * Count internal and external links.
	 *
	 * @param string $html     HTML.
	 * @param string $site_url Site origin.
	 * @return array{internal: int, external: int}
	 */
	private function link_counts( string $html, string $site_url ): array {
		preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')[^>]*>/i', $html, $matches, PREG_SET_ORDER );
		$internal = 0;
		$external = 0;
		foreach ( $matches as $match ) {
			$href = (string) ( $match[1] ?? $match[2] ?? '' );
			$kind = $this->classify_link( $href, $site_url );
			if ( 'internal' === $kind ) {
				++$internal;
			} elseif ( 'external' === $kind ) {
				++$external;
			}
		}
		return [
			'internal' => $internal,
			'external' => $external,
		];
	}
}
