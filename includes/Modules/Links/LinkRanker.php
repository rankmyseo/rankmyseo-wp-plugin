<?php
/**
 * Ranks local internal-link targets from data already stored on the site.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Links;

use RankMySEO\Modules\Analyzer\WordForms;

/**
 * Deterministic ranker. Shared keywords, title, slug, and taxonomy overlap
 * come first. Cornerstone and orphan targets are boosted.
 */
final class LinkRanker {
	/**
	 * Word-form matcher.
	 *
	 * @var WordForms
	 */
	private WordForms $forms;

	/**
	 * Constructor.
	 *
	 * @param WordForms|null $forms Optional matcher.
	 */
	public function __construct( ?WordForms $forms = null ) {
		$this->forms = $forms ?? new WordForms();
	}

	/**
	 * Rank candidate posts for one source page.
	 *
	 * @param array<string, mixed>       $source     id, title, slug, focus_keyword, secondary_keywords, terms.
	 * @param list<array<string, mixed>> $candidates Published targets with the same fields plus url, cornerstone, incoming.
	 * @param bool                       $english    Whether English word forms apply.
	 * @return list<array<string, mixed>>
	 */
	public function rank( array $source, array $candidates, bool $english = true ): array {
		$ranked    = [];
		$source_id = (int) ( $source['id'] ?? 0 );
		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			$id = (int) ( $candidate['id'] ?? 0 );
			if ( $id <= 0 || $id === $source_id ) {
				continue;
			}
			$score   = 0;
			$reasons = [];
			if ( $this->shares_keyword( $source, $candidate, $english ) ) {
				$score    += 40;
				$reasons[] = 'shared_keyword';
			}
			$title_overlap = $this->forms->overlap( (string) ( $source['title'] ?? '' ), (string) ( $candidate['title'] ?? '' ), $english );
			if ( $title_overlap > 0 ) {
				$score    += min( 20, $title_overlap * 8 );
				$reasons[] = 'title';
			}
			$slug_overlap = $this->forms->overlap(
				str_replace( '-', ' ', (string) ( $source['slug'] ?? '' ) ),
				str_replace( '-', ' ', (string) ( $candidate['slug'] ?? '' ) ),
				$english
			);
			if ( $slug_overlap > 0 ) {
				$score    += min( 15, $slug_overlap * 6 );
				$reasons[] = 'slug';
			}
			$shared_terms = count(
				array_intersect(
					$this->terms( $source['terms'] ?? [] ),
					$this->terms( $candidate['terms'] ?? [] )
				)
			);
			if ( $shared_terms > 0 ) {
				$score    += min( 15, $shared_terms * 5 );
				$reasons[] = 'taxonomy';
			}
			if ( ! empty( $candidate['cornerstone'] ) ) {
				$score    += 15;
				$reasons[] = 'cornerstone';
			}
			$orphan = (int) ( $candidate['incoming'] ?? 0 ) === 0;
			if ( $orphan ) {
				$score    += 12;
				$reasons[] = 'orphan';
			}
			if ( $score <= 0 ) {
				continue;
			}
			$ranked[] = [
				'id'          => $id,
				'title'       => (string) ( $candidate['title'] ?? '' ),
				'url'         => (string) ( $candidate['url'] ?? '' ),
				'score'       => min( 100, $score ),
				'reasons'     => $reasons,
				'cornerstone' => ! empty( $candidate['cornerstone'] ),
				'orphan'      => $orphan,
			];
		}

		usort(
			$ranked,
			static function ( array $left, array $right ): int {
				if ( $left['score'] === $right['score'] ) {
					return $left['id'] <=> $right['id'];
				}
				return $right['score'] <=> $left['score'];
			}
		);

		return array_slice( $ranked, 0, 8 );
	}

	/**
	 * Whether the two pages target the same phrase, including English word forms.
	 *
	 * @param array<string, mixed> $source    Source page.
	 * @param array<string, mixed> $candidate Target page.
	 * @param bool                 $english   Whether English word forms apply.
	 * @return bool
	 */
	private function shares_keyword( array $source, array $candidate, bool $english ): bool {
		foreach ( $this->phrases( $source ) as $left ) {
			foreach ( $this->phrases( $candidate ) as $right ) {
				if ( $this->forms->same_phrase( $left, $right, $english ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Focus keyword plus secondary keywords.
	 *
	 * @param array<string, mixed> $page Page fields.
	 * @return list<string>
	 */
	private function phrases( array $page ): array {
		$phrases = [];
		$focus   = trim( (string) ( $page['focus_keyword'] ?? '' ) );
		if ( '' !== $focus ) {
			$phrases[] = $focus;
		}
		$secondary = $page['secondary_keywords'] ?? [];
		if ( is_array( $secondary ) ) {
			foreach ( $secondary as $phrase ) {
				if ( is_string( $phrase ) && '' !== trim( $phrase ) ) {
					$phrases[] = trim( $phrase );
				}
			}
		}
		return $phrases;
	}

	/**
	 * Term slugs.
	 *
	 * @param mixed $terms Raw list.
	 * @return list<string>
	 */
	private function terms( $terms ): array {
		if ( ! is_array( $terms ) ) {
			return [];
		}
		$list = [];
		foreach ( $terms as $term ) {
			if ( is_string( $term ) && '' !== $term ) {
				$list[] = $term;
			}
		}
		return $list;
	}
}
