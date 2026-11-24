<?php
/**
 * Weighted 0–100 on-page score. Mirrors the editor analyzer.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

/**
 * Scores keyword, snippet, content, and readability checks.
 */
final class ScoreCalculator {
	private const TITLE_FONT            = 20;
	private const DESCRIPTION_FONT      = 14;
	private const TITLE_PIXEL_MAX       = 580;
	private const DESCRIPTION_PIXEL_MAX = 920;

	/**
	 * Inspector.
	 *
	 * @var ContentInspector
	 */
	private ContentInspector $content;

	/**
	 * English word-form matcher. Other locales stay on exact phrases.
	 *
	 * @var WordForms
	 */
	private WordForms $forms;

	/**
	 * Constructor.
	 *
	 * @param ContentInspector|null $content Optional inspector.
	 */
	public function __construct( ?ContentInspector $content = null ) {
		$this->content = $content ?? new ContentInspector();
		$this->forms   = new WordForms( $this->content );
	}

	/**
	 * Score one page.
	 *
	 * @param array<string, mixed> $input focus_keyword, secondary_keywords, seo_title, meta_description, slug, content_html, post_type, locale, noindex, site_url, cornerstone.
	 * @return array<string, mixed>
	 */
	public function analyze( array $input ): array {
		$keyword     = trim( (string) ( $input['focus_keyword'] ?? '' ) );
		$has_keyword = '' !== $keyword;
		$secondary   = $this->forms->limit_secondary( $keyword, $input['secondary_keywords'] ?? [] );
		$cornerstone = ! empty( $input['cornerstone'] );
		$title       = trim( (string) ( $input['seo_title'] ?? '' ) );
		$description = trim( (string) ( $input['meta_description'] ?? '' ) );
		$slug        = (string) ( $input['slug'] ?? '' );
		$post_type   = (string) ( $input['post_type'] ?? 'post' );
		$locale      = (string) ( $input['locale'] ?? 'en' );
		$noindex     = ! empty( $input['noindex'] );
		$site_url    = (string) ( $input['site_url'] ?? '' );
		$facts       = $this->content->inspect( (string) ( $input['content_html'] ?? '' ), $site_url );

		$words      = $this->string_list( $facts['words'] ?? [] );
		$sentences  = $this->string_list( $facts['sentences'] ?? [] );
		$paragraphs = $this->string_list( $facts['paragraphs'] ?? [] );
		$headings   = is_array( $facts['headings'] ?? null ) ? $facts['headings'] : [];
		$images     = is_array( $facts['images'] ?? null ) ? $facts['images'] : [];
		$plain      = (string) ( $facts['plain'] ?? '' );
		$syllables  = (int) ( $facts['syllables'] ?? 0 );
		$internal   = (int) ( $facts['internal_links'] ?? 0 );
		$external   = (int) ( $facts['external_links'] ?? 0 );

		$word_count      = count( $words );
		$sentence_count  = count( $sentences );
		$paragraph_count = count( $paragraphs );
		$english         = $this->is_english( $locale );
		$keyword_words   = $has_keyword ? count( array_filter( explode( ' ', $this->content->normalize_words( $keyword ) ) ) ) : 0;
		$keyword_count   = $has_keyword ? $this->forms->count( $plain, $keyword, $english ) : 0;
		$density         = ( $has_keyword && $word_count > 0 )
			? round( ( ( $keyword_count * max( $keyword_words, 1 ) ) / $word_count ) * 100, 2 )
			: 0.0;

		$levels       = [];
		$h1_count     = 0;
		$subheads     = 0;
		$has_h2_h3    = false;
		$heading_text = [];
		foreach ( $headings as $heading ) {
			if ( ! is_array( $heading ) ) {
				continue;
			}
			$level    = (int) ( $heading['level'] ?? 0 );
			$levels[] = $level;
			if ( 1 === $level ) {
				++$h1_count;
			}
			if ( $level >= 2 ) {
				++$subheads;
			}
			if ( 2 === $level || 3 === $level ) {
				$has_h2_h3 = true;
			}
			if ( $level <= 3 ) {
				$heading_text[] = (string) ( $heading['text'] ?? '' );
			}
		}
		$skipped = $this->content->skipped_heading_levels( $levels );

		$images_with_alt = 0;
		$keyword_in_alt  = false;
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			$alt = trim( (string) ( $image['alt'] ?? '' ) );
			if ( '' !== $alt ) {
				++$images_with_alt;
			}
			if ( $has_keyword && $this->forms->contains( $alt, $keyword, $english ) ) {
				$keyword_in_alt = true;
			}
		}

		$intro            = implode( ' ', array_slice( $words, 0, 100 ) );
		$reading          = $this->flesch( $word_count, $sentence_count, $syllables );
		$target           = ( 'product' === $post_type ? 150 : 300 ) * ( $cornerstone ? 3 : 1 );
		$average_sentence = $sentence_count > 0 ? round( $word_count / $sentence_count, 1 ) : 0.0;
		$paragraph_words  = 0;
		$long_paragraphs  = 0;
		foreach ( $paragraphs as $paragraph ) {
			$parts            = preg_split( '/\s+/', $paragraph );
			$count            = is_array( $parts ) ? count( array_filter( $parts ) ) : 0;
			$paragraph_words += $count;
			if ( $count > 150 ) {
				++$long_paragraphs;
			}
		}
		$average_paragraph = $paragraph_count > 0 ? round( $paragraph_words / $paragraph_count, 1 ) : 0.0;

		$title_score = 0;
		if ( $has_keyword && $this->forms->contains( $title, $keyword, $english ) ) {
			$title_score = $this->forms->near_start( $title, $keyword, $english ) ? 6 : 4;
		}

		$density_score = 0;
		$density_empty = 'improve';
		if ( $has_keyword && $density > 2.5 ) {
			$density_score = 0;
			$density_empty = 'critical';
		} elseif ( $has_keyword && $density >= 0.5 ) {
			$density_score = 3;
		} elseif ( $has_keyword && $density > 0 ) {
			$density_score = 1;
		}

		$title_length       = $this->title_points( $title );
		$description_length = $this->description_points( $description );

		$word_score = 0;
		if ( $word_count >= $target ) {
			$word_score = 8;
		} elseif ( $word_count >= (int) ceil( $target / 2 ) ) {
			$word_score = 4;
		}

		$heading_score = 0;
		if ( 1 === $h1_count ) {
			$heading_score += 3;
		}
		if ( $has_h2_h3 ) {
			$heading_score += 2;
		}
		if ( ! $skipped ) {
			$heading_score += 2;
		}

		$image_score = 0;
		$image_total = count( $images );
		if ( $image_total > 0 && $images_with_alt === $image_total ) {
			$image_score = 5;
		} elseif ( $images_with_alt > 0 ) {
			$image_score = 2;
		}

		$needed_subheads = $word_count < 300 ? 0 : (int) ceil( $word_count / 300 );
		$subhead_score   = 0;
		if ( 0 === $needed_subheads || $subheads >= $needed_subheads ) {
			$subhead_score = 3;
		} elseif ( $subheads > 0 ) {
			$subhead_score = 1;
		}

		$paragraph_score = 0;
		if ( $paragraph_count > 0 && 0 === $long_paragraphs ) {
			$paragraph_score = 5;
		} elseif ( $paragraph_count > $long_paragraphs ) {
			$paragraph_score = 2;
		}

		$sentence_score = 0;
		if ( $sentence_count > 0 && $average_sentence <= 20 ) {
			$sentence_score = 4;
		} elseif ( $sentence_count > 0 && $average_sentence <= 25 ) {
			$sentence_score = 2;
		}

		$reading_score = 0;
		if ( ! $english ) {
			$reading_score = 3;
		} elseif ( null !== $reading['ease'] && $reading['ease'] >= 60 ) {
			$reading_score = 3;
		} elseif ( null !== $reading['ease'] && $reading['ease'] >= 30 ) {
			$reading_score = 2;
		}

		$checks = [
			$this->check( 'keyword_set', 'keyword', $has_keyword ? 4 : 0, 4, 'critical', [ 'present' => $has_keyword ] ),
			$this->check(
				'keyword_in_title',
				'keyword',
				$title_score,
				6,
				$has_keyword ? 'critical' : 'improve',
				[
					'present'   => $title_score > 0,
					'nearStart' => 6 === $title_score,
				]
			),
			$this->check( 'keyword_in_description', 'keyword', ( $has_keyword && $this->forms->contains( $description, $keyword, $english ) ) ? 5 : 0, 5, 'improve', [ 'present' => $has_keyword && $this->forms->contains( $description, $keyword, $english ) ] ),
			$this->check(
				'keyword_in_slug',
				'keyword',
				( $has_keyword && $this->forms->slug_contains( $slug, $keyword, $english ) ) ? 5 : 0,
				5,
				'improve',
				[
					'present' => $has_keyword && $this->forms->slug_contains( $slug, $keyword, $english ),
					'slug'    => $slug,
				]
			),
			$this->check( 'keyword_in_intro', 'keyword', ( $has_keyword && $this->forms->contains( $intro, $keyword, $english ) ) ? 5 : 0, 5, 'improve', [ 'present' => $has_keyword && $this->forms->contains( $intro, $keyword, $english ) ] ),
			$this->check( 'keyword_in_headings', 'keyword', ( $has_keyword && $this->forms->contains( implode( ' ', $heading_text ), $keyword, $english ) ) ? 5 : 0, 5, 'improve', [ 'present' => $has_keyword && $this->forms->contains( implode( ' ', $heading_text ), $keyword, $english ) ] ),
			$this->check(
				'keyword_in_alt',
				'keyword',
				$keyword_in_alt ? 2 : 0,
				2,
				'improve',
				[
					'present' => $keyword_in_alt,
					'images'  => $image_total,
				]
			),
			$this->check(
				'keyword_density',
				'keyword',
				$density_score,
				3,
				$density_empty,
				[
					'density'  => $density,
					'count'    => $keyword_count,
					'stuffing' => $density > 2.5,
				]
			),
			$this->check(
				'title_length',
				'snippet',
				$title_length['score'],
				12,
				( '' === $title || mb_strlen( $title ) < 15 ) ? 'critical' : 'improve',
				[
					'characters' => mb_strlen( $title ),
					'pixels'     => $title_length['pixels'],
					'min'        => 30,
					'max'        => 60,
				]
			),
			$this->check(
				'description_length',
				'snippet',
				$description_length['score'],
				10,
				( '' === $description || mb_strlen( $description ) < 40 ) ? 'critical' : 'improve',
				[
					'characters' => mb_strlen( $description ),
					'pixels'     => $description_length['pixels'],
					'min'        => 120,
					'max'        => 155,
				]
			),
			$this->check( 'indexable', 'snippet', $noindex ? 0 : 3, 3, 'critical', [ 'noindex' => $noindex ] ),
			$this->check(
				'word_count',
				'content',
				$word_score,
				8,
				$word_count < (int) ceil( $target / 2 ) ? 'critical' : 'improve',
				[
					'words'  => $word_count,
					'target' => $target,
				]
			),
			$this->check(
				'heading_hierarchy',
				'content',
				$heading_score,
				7,
				0 === $heading_score ? 'critical' : 'improve',
				[
					'h1'       => $h1_count,
					'subheads' => $subheads,
					'skipped'  => $skipped,
				]
			),
			$this->check(
				'image_alts',
				'content',
				$image_score,
				5,
				'improve',
				[
					'images'  => $image_total,
					'withAlt' => $images_with_alt,
				]
			),
			$this->check(
				'internal_links',
				'content',
				$this->internal_link_score( $internal, $cornerstone ),
				3,
				$cornerstone ? 'critical' : 'improve',
				[
					'count'       => $internal,
					'cornerstone' => $cornerstone,
					'minimum'     => $cornerstone ? 3 : 1,
				]
			),
			$this->check( 'external_links', 'content', $external > 0 ? 2 : 0, 2, 'improve', [ 'count' => $external ] ),
			$this->check(
				'paragraph_length',
				'readability',
				$paragraph_score,
				5,
				'improve',
				[
					'paragraphs'     => $paragraph_count,
					'longParagraphs' => $long_paragraphs,
					'average'        => $average_paragraph,
				]
			),
			$this->check(
				'sentence_length',
				'readability',
				$sentence_score,
				4,
				'improve',
				[
					'sentences' => $sentence_count,
					'average'   => $average_sentence,
				]
			),
			$this->check(
				'subheading_distribution',
				'readability',
				$subhead_score,
				3,
				( $word_count >= 300 && 0 === $subheads ) ? 'critical' : 'improve',
				[
					'subheads' => $subheads,
					'needed'   => $needed_subheads,
				]
			),
			$this->check(
				'readability',
				'readability',
				$reading_score,
				3,
				'improve',
				[
					'english' => $english,
					'flesch'  => $reading['ease'] ?? '',
					'grade'   => $reading['grade'] ?? '',
				]
			),
		];

		$primary_score = 0;
		foreach ( $checks as $item ) {
			$primary_score += (int) $item['score'];
		}
		$primary_score = max( 0, min( 100, $primary_score ) );

		$keyword_checks = array_values(
			array_filter(
				$checks,
				static fn( array $item ): bool => 'keyword' === ( $item['category'] ?? '' )
			)
		);
		$keyword_points = 0;
		foreach ( $keyword_checks as $item ) {
			$keyword_points += (int) $item['score'];
		}
		$keywords         = [
			[
				'keyword' => $keyword,
				'role'    => 'primary',
				'status'  => $has_keyword ? $this->group_status( $keyword_checks ) : 'critical',
				'score'   => $keyword_points,
				'checks'  => $keyword_checks,
			],
		];
		$secondary_scores = [];
		foreach ( $secondary as $phrase ) {
			$assessment         = $this->secondary_assessment( $phrase, $title, $description, $intro, implode( ' ', $heading_text ), $plain, $word_count, $english );
			$keywords[]         = $assessment;
			$secondary_scores[] = (int) $assessment['score'];
		}
		$score = $primary_score;
		if ( [] !== $secondary_scores ) {
			$average = array_sum( $secondary_scores ) / count( $secondary_scores );
			$score   = (int) round( ( $primary_score * 0.8 ) + ( $average * 0.2 ) );
		}
		$score = max( 0, min( 100, $score ) );

		return [
			'score'             => $score,
			'primary_score'     => $primary_score,
			'band'              => ScoreBands::band( $score, $has_keyword ),
			'has_focus_keyword' => $has_keyword,
			'checks'            => $checks,
			'keywords'          => $keywords,
			'summary'           => [
				'word_count'               => $word_count,
				'keyword_count'            => $keyword_count,
				'keyword_density'          => $density,
				'flesch_reading_ease'      => $english ? $reading['ease'] : null,
				'flesch_kincaid_grade'     => $english ? $reading['grade'] : null,
				'average_sentence_length'  => $average_sentence,
				'average_paragraph_length' => $average_paragraph,
				'internal_links'           => $internal,
				'external_links'           => $external,
				'images'                   => $image_total,
				'images_with_alt'          => $images_with_alt,
				'h1_count'                 => $h1_count,
				'heading_count'            => count( $levels ),
			],
		];
	}

	/**
	 * Compact JSON stored in post meta.
	 *
	 * @param array<string, mixed> $result Analyzer result.
	 * @return array<string, mixed>
	 */
	public function summary_payload( array $result ): array {
		$checks = [];
		$list   = is_array( $result['checks'] ?? null ) ? $result['checks'] : [];
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$checks[] = [
				'id'        => (string) ( $item['id'] ?? '' ),
				'status'    => (string) ( $item['status'] ?? '' ),
				'score'     => (int) ( $item['score'] ?? 0 ),
				'max_score' => (int) ( $item['max_score'] ?? 0 ),
			];
		}
		$summary = is_array( $result['summary'] ?? null ) ? $result['summary'] : [];

		return [
			'score'           => (int) ( $result['score'] ?? 0 ),
			'band'            => (string) ( $result['band'] ?? 'poor' ),
			'word_count'      => (int) ( $summary['word_count'] ?? 0 ),
			'keyword_density' => (float) ( $summary['keyword_density'] ?? 0 ),
			'checks'          => $checks,
		];
	}

	/**
	 * Build one check row.
	 *
	 * @param string               $id           Check id.
	 * @param string               $category     Category id.
	 * @param int                  $score        Points earned.
	 * @param int                  $max_score    Points available.
	 * @param string               $empty_status Status when the score is zero.
	 * @param array<string, mixed> $metrics      Counts shown in Advanced mode.
	 * @return array<string, mixed>
	 */
	private function check( string $id, string $category, int $score, int $max_score, string $empty_status, array $metrics ): array {
		if ( $max_score > 0 && $score >= $max_score ) {
			$status = 'pass';
		} elseif ( $score <= 0 ) {
			$status = $empty_status;
		} else {
			$status = 'improve';
		}

		return [
			'id'        => $id,
			'category'  => $category,
			'status'    => $status,
			'score'     => $score,
			'max_score' => $max_score,
			'metrics'   => $metrics,
		];
	}

	/**
	 * Points for links to other pages on this site.
	 *
	 * Cornerstone pages need three links for full points.
	 *
	 * @param int  $count       Internal links on the page.
	 * @param bool $cornerstone Whether the page is cornerstone content.
	 * @return int
	 */
	private function internal_link_score( int $count, bool $cornerstone ): int {
		if ( ! $cornerstone ) {
			return $count > 0 ? 3 : 0;
		}
		if ( $count >= 3 ) {
			return 3;
		}
		if ( $count >= 1 ) {
			return 1;
		}
		return 0;
	}

	/**
	 * Worst status in a group of checks.
	 *
	 * @param list<array<string, mixed>> $checks Checks.
	 * @return string
	 */
	private function group_status( array $checks ): string {
		$statuses = [];
		foreach ( $checks as $check ) {
			$statuses[] = (string) ( $check['status'] ?? 'improve' );
		}
		if ( in_array( 'critical', $statuses, true ) ) {
			return 'critical';
		}
		if ( [] !== $statuses && ! in_array( 'improve', $statuses, true ) ) {
			return 'pass';
		}
		return 'improve';
	}

	/**
	 * Score one secondary keyword on its own 100-point rubric.
	 *
	 * @param string $phrase      Secondary keyword.
	 * @param string $title       SEO title.
	 * @param string $description Meta description.
	 * @param string $intro       First 100 words.
	 * @param string $headings    Heading text.
	 * @param string $plain       Page text.
	 * @param int    $word_count  Word count.
	 * @param bool   $english     Whether English word forms apply.
	 * @return array<string, mixed>
	 */
	private function secondary_assessment( string $phrase, string $title, string $description, string $intro, string $headings, string $plain, int $word_count, bool $english ): array {
		$count    = $this->forms->count( $plain, $phrase, $english );
		$words    = max( 1, count( array_filter( explode( ' ', $this->content->normalize_words( $phrase ) ) ) ) );
		$density  = $word_count > 0 ? round( ( ( $count * $words ) / $word_count ) * 100, 2 ) : 0.0;
		$stuffing = $density > 2.5;
		$in_title = $this->forms->contains( $title, $phrase, $english );
		$in_desc  = $this->forms->contains( $description, $phrase, $english );
		$in_place = $this->forms->contains( $headings, $phrase, $english ) || $this->forms->contains( $intro, $phrase, $english );
		$checks   = [
			$this->check(
				'secondary_in_content',
				'keyword',
				$count > 0 ? 40 : 0,
				40,
				'critical',
				[
					'present' => $count > 0,
					'keyword' => $phrase,
					'count'   => $count,
				]
			),
			$this->check(
				'secondary_in_title',
				'keyword',
				$in_title ? 20 : 0,
				20,
				'improve',
				[
					'present' => $in_title,
					'keyword' => $phrase,
				]
			),
			$this->check(
				'secondary_in_description',
				'keyword',
				$in_desc ? 15 : 0,
				15,
				'improve',
				[
					'present' => $in_desc,
					'keyword' => $phrase,
				]
			),
			$this->check(
				'secondary_in_headings',
				'keyword',
				$in_place ? 15 : 0,
				15,
				'improve',
				[
					'present' => $in_place,
					'keyword' => $phrase,
				]
			),
			$this->check(
				'secondary_density',
				'keyword',
				( $count > 0 && ! $stuffing ) ? 10 : 0,
				10,
				$stuffing ? 'critical' : 'improve',
				[
					'density'  => $density,
					'count'    => $count,
					'stuffing' => $stuffing,
					'keyword'  => $phrase,
				]
			),
		];
		$score    = 0;
		foreach ( $checks as $item ) {
			$score += (int) $item['score'];
		}
		if ( $stuffing ) {
			$status = 'critical';
		} elseif ( $score >= 70 ) {
			$status = 'pass';
		} elseif ( $score >= 40 ) {
			$status = 'improve';
		} else {
			$status = 'critical';
		}

		return [
			'keyword' => $phrase,
			'role'    => 'secondary',
			'status'  => $status,
			'score'   => $score,
			'checks'  => $checks,
		];
	}

	/**
	 * Title length points.
	 *
	 * @param string $title SEO title.
	 * @return array{score: int, pixels: int}
	 */
	private function title_points( string $title ): array {
		$length = mb_strlen( $title );
		$pixels = $this->content->estimate_pixel_width( $title, self::TITLE_FONT );
		if ( $length >= 30 && $length <= 60 && $pixels < self::TITLE_PIXEL_MAX ) {
			return [
				'score'  => 12,
				'pixels' => $pixels,
			];
		}
		if ( $length >= 20 && $length <= 70 && $pixels < 700 ) {
			return [
				'score'  => 6,
				'pixels' => $pixels,
			];
		}
		return [
			'score'  => 0,
			'pixels' => $pixels,
		];
	}

	/**
	 * Description length points.
	 *
	 * @param string $description Meta description.
	 * @return array{score: int, pixels: int}
	 */
	private function description_points( string $description ): array {
		$length = mb_strlen( $description );
		$pixels = $this->content->estimate_pixel_width( $description, self::DESCRIPTION_FONT );
		if ( $length >= 120 && $length <= 155 && $pixels < self::DESCRIPTION_PIXEL_MAX ) {
			return [
				'score'  => 10,
				'pixels' => $pixels,
			];
		}
		if ( $length >= 70 && $length <= 170 && $pixels < 1100 ) {
			return [
				'score'  => 5,
				'pixels' => $pixels,
			];
		}
		return [
			'score'  => 0,
			'pixels' => $pixels,
		];
	}

	/**
	 * Flesch reading ease and grade.
	 *
	 * @param int $words      Word count.
	 * @param int $sentences  Sentence count.
	 * @param int $syllables  Syllable count.
	 * @return array{ease: float|null, grade: float|null}
	 */
	private function flesch( int $words, int $sentences, int $syllables ): array {
		if ( 0 === $words || 0 === $sentences ) {
			return [
				'ease'  => null,
				'grade' => null,
			];
		}
		$words_per_sentence = $words / $sentences;
		$syllables_per_word = $syllables / $words;
		return [
			'ease'  => round( 206.835 - ( 1.015 * $words_per_sentence ) - ( 84.6 * $syllables_per_word ), 1 ),
			'grade' => round( ( 0.39 * $words_per_sentence ) + ( 11.8 * $syllables_per_word ) - 15.59, 1 ),
		];
	}

	/**
	 * English locales keep the Flesch check. Other languages are not penalized.
	 *
	 * @param string $locale Locale code.
	 * @return bool
	 */
	private function is_english( string $locale ): bool {
		$code = strtolower( str_replace( '_', '-', '' !== $locale ? $locale : 'en' ) );
		return str_starts_with( $code, 'en' );
	}

	/**
	 * List of strings.
	 *
	 * @param mixed $value Raw list.
	 * @return list<string>
	 */
	private function string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$list = [];
		foreach ( $value as $item ) {
			if ( is_string( $item ) && '' !== $item ) {
				$list[] = $item;
			}
		}
		return $list;
	}
}
