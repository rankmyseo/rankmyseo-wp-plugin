<?php
/**
 * Lightweight English word forms and a short synonym list.
 *
 * Non-English locales stay on exact phrases so they are not scored with English stems.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

/**
 * Offline matcher for plurals, common verb and noun stems, and a few synonyms.
 */
final class WordForms {
	/**
	 * Irregular English forms mapped to a base.
	 *
	 * @var array<string, string>
	 */
	private const IRREGULAR = [
		'children' => 'child',
		'men'      => 'man',
		'women'    => 'woman',
		'people'   => 'person',
		'feet'     => 'foot',
		'teeth'    => 'tooth',
		'mice'     => 'mouse',
		'geese'    => 'goose',
		'is'       => 'be',
		'am'       => 'be',
		'are'      => 'be',
		'was'      => 'be',
		'were'     => 'be',
		'been'     => 'be',
		'being'    => 'be',
		'has'      => 'have',
		'had'      => 'have',
		'having'   => 'have',
		'does'     => 'do',
		'did'      => 'do',
		'done'     => 'do',
		'doing'    => 'do',
		'goes'     => 'go',
		'went'     => 'go',
		'gone'     => 'go',
		'going'    => 'go',
		'ran'      => 'run',
		'made'     => 'make',
		'makes'    => 'make',
		'making'   => 'make',
		'bought'   => 'buy',
		'buys'     => 'buy',
		'buying'   => 'buy',
		'sold'     => 'sell',
		'sells'    => 'sell',
		'selling'  => 'sell',
		'wrote'    => 'write',
		'written'  => 'write',
		'writes'   => 'write',
		'writing'  => 'write',
		'took'     => 'take',
		'taken'    => 'take',
		'takes'    => 'take',
		'taking'   => 'take',
		'came'     => 'come',
		'comes'    => 'come',
		'coming'   => 'come',
		'saw'      => 'see',
		'seen'     => 'see',
		'sees'     => 'see',
		'seeing'   => 'see',
		'got'      => 'get',
		'gotten'   => 'get',
		'gets'     => 'get',
		'getting'  => 'get',
		'said'     => 'say',
		'says'     => 'say',
		'saying'   => 'say',
		'better'   => 'good',
		'best'     => 'good',
		'worse'    => 'bad',
		'worst'    => 'bad',
	];

	/**
	 * Small synonym groups. The first word is the canon.
	 *
	 * @var list<list<string>>
	 */
	private const SYNONYM_GROUPS = [
		[ 'buy', 'purchase' ],
		[ 'cheap', 'inexpensive', 'affordable' ],
		[ 'big', 'large' ],
		[ 'small', 'little' ],
		[ 'fast', 'quick', 'rapid' ],
		[ 'begin', 'start' ],
		[ 'end', 'finish' ],
		[ 'help', 'assist' ],
		[ 'child', 'kid' ],
		[ 'car', 'automobile' ],
		[ 'house', 'home' ],
		[ 'photo', 'picture', 'image' ],
		[ 'info', 'information' ],
	];

	/**
	 * Inspector used for the same normalization as the rest of the scorer.
	 *
	 * @var ContentInspector
	 */
	private ContentInspector $content;

	/**
	 * Synonym canon keyed by word.
	 *
	 * @var array<string, string>
	 */
	private array $synonym = [];

	/**
	 * Constructor.
	 *
	 * @param ContentInspector|null $content Optional inspector.
	 */
	public function __construct( ?ContentInspector $content = null ) {
		$this->content = $content ?? new ContentInspector();
		foreach ( self::SYNONYM_GROUPS as $group ) {
			$canon = $group[0];
			foreach ( $group as $word ) {
				$this->synonym[ $word ] = $canon;
			}
		}
	}

	/**
	 * Whether the phrase occurs, using English word forms only when asked.
	 *
	 * @param string $text    Haystack.
	 * @param string $keyword Phrase.
	 * @param bool   $english Whether English stemming applies.
	 * @return bool
	 */
	public function contains( string $text, string $keyword, bool $english ): bool {
		return $this->match( $text, $keyword, $english )['found'];
	}

	/**
	 * Count non-overlapping phrase hits.
	 *
	 * @param string $text    Haystack.
	 * @param string $keyword Phrase.
	 * @param bool   $english Whether English stemming applies.
	 * @return int
	 */
	public function count( string $text, string $keyword, bool $english ): int {
		return $this->match( $text, $keyword, $english )['count'];
	}

	/**
	 * Whether the first hit starts within the first 20 characters.
	 *
	 * @param string $text    Haystack.
	 * @param string $keyword Phrase.
	 * @param bool   $english Whether English stemming applies.
	 * @return bool
	 */
	public function near_start( string $text, string $keyword, bool $english ): bool {
		$hit = $this->match( $text, $keyword, $english );
		return $hit['found'] && $hit['offset'] <= 20;
	}

	/**
	 * Whether a slug contains the phrase.
	 *
	 * @param string $slug    Post slug.
	 * @param string $keyword Phrase.
	 * @param bool   $english Whether English stemming applies.
	 * @return bool
	 */
	public function slug_contains( string $slug, string $keyword, bool $english ): bool {
		return $this->contains( str_replace( '-', ' ', $slug ), $keyword, $english );
	}

	/**
	 * Whether two phrases are the same words, or the same English word forms.
	 *
	 * @param string $left    First phrase.
	 * @param string $right   Second phrase.
	 * @param bool   $english Whether English stemming applies.
	 * @return bool
	 */
	public function same_phrase( string $left, string $right, bool $english ): bool {
		$a = $this->tokens( $left );
		$b = $this->tokens( $right );
		if ( [] === $a || count( $a ) !== count( $b ) ) {
			return false;
		}
		foreach ( $a as $index => $token ) {
			if ( ! $this->tokens_match( $token, $b[ $index ], $english ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Count shared meaningful tokens.
	 *
	 * @param string $left    First label.
	 * @param string $right   Second label.
	 * @param bool   $english Whether English stemming applies.
	 * @return int
	 */
	public function overlap( string $left, string $right, bool $english ): int {
		$stop  = [ 'the', 'and', 'for', 'with', 'your', 'this', 'that', 'from', 'are', 'was', 'you', 'our' ];
		$used  = [];
		$count = 0;
		$hay   = $this->tokens( $right );
		foreach ( $this->tokens( $left ) as $token ) {
			if ( mb_strlen( $token ) < 3 || in_array( $token, $stop, true ) ) {
				continue;
			}
			foreach ( $hay as $index => $other ) {
				if ( isset( $used[ $index ] ) ) {
					continue;
				}
				if ( mb_strlen( $other ) < 3 || in_array( $other, $stop, true ) ) {
					continue;
				}
				if ( $this->tokens_match( $token, $other, $english ) ) {
					$used[ $index ] = true;
					++$count;
					break;
				}
			}
		}
		return $count;
	}

	/**
	 * Keep up to five secondary keywords, skipping blanks and the focus keyword.
	 *
	 * @param string $focus  Focus keyword.
	 * @param mixed  $values Raw list.
	 * @return list<string>
	 */
	public function limit_secondary( string $focus, $values ): array {
		if ( ! is_array( $values ) ) {
			return [];
		}
		$primary = $this->content->normalize_words( $focus );
		$seen    = [];
		$list    = [];
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}
			$trimmed = trim( $value );
			$key     = $this->content->normalize_words( $trimmed );
			if ( '' === $key || $key === $primary || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$list[]       = $trimmed;
			if ( count( $list ) >= 5 ) {
				break;
			}
		}
		return $list;
	}

	/**
	 * Locate a phrase and count non-overlapping hits.
	 *
	 * @param string $text    Haystack.
	 * @param string $keyword Phrase.
	 * @param bool   $english Whether English stemming applies.
	 * @return array{found: bool, offset: int, count: int}
	 */
	public function match( string $text, string $keyword, bool $english ): array {
		$needle = $this->tokens( $keyword );
		$hay    = $this->tokens( $text );
		if ( [] === $needle || [] === $hay ) {
			return [
				'found'  => false,
				'offset' => -1,
				'count'  => 0,
			];
		}

		$offsets = [];
		$cursor  = 0;
		$last    = count( $hay ) - 1;
		foreach ( $hay as $index => $token ) {
			$offsets[] = $cursor;
			$cursor   += mb_strlen( $token ) + ( $index === $last ? 0 : 1 );
		}

		$hits       = [];
		$width      = count( $needle );
		$last_start = count( $hay ) - $width;
		$index      = 0;
		while ( $index <= $last_start ) {
			$matched = true;
			for ( $step = 0; $step < $width; $step++ ) {
				if ( ! $this->tokens_match( $hay[ $index + $step ], $needle[ $step ], $english ) ) {
					$matched = false;
					break;
				}
			}
			if ( $matched ) {
				$hits[] = $offsets[ $index ];
				$index += $width;
			} else {
				++$index;
			}
		}

		return [
			'found'  => [] !== $hits,
			'offset' => $hits[0] ?? -1,
			'count'  => count( $hits ),
		];
	}

	/**
	 * Normalized tokens.
	 *
	 * @param string $value Raw text.
	 * @return list<string>
	 */
	private function tokens( string $value ): array {
		$normalized = $this->content->normalize_words( $value );
		if ( '' === $normalized ) {
			return [];
		}
		$parts = explode( ' ', $normalized );
		return array_values( array_filter( $parts, static fn( string $token ): bool => '' !== $token ) );
	}

	/**
	 * Whether two tokens share an exact form or, in English, a stem or synonym.
	 *
	 * @param string $left    First token.
	 * @param string $right   Second token.
	 * @param bool   $english Whether English stemming applies.
	 * @return bool
	 */
	private function tokens_match( string $left, string $right, bool $english ): bool {
		if ( $left === $right ) {
			return true;
		}
		if ( ! $english ) {
			return false;
		}
		$keys = $this->token_keys( $left );
		foreach ( array_keys( $this->token_keys( $right ) ) as $key ) {
			if ( isset( $keys[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keys for one English or exact token.
	 *
	 * @param string $word Token.
	 * @return array<string, bool>
	 */
	private function token_keys( string $word ): array {
		$keys = [];
		$this->add_key( $keys, $word );
		if ( isset( self::IRREGULAR[ $word ] ) ) {
			$this->add_key( $keys, self::IRREGULAR[ $word ] );
		}
		if ( mb_strlen( $word ) <= 3 ) {
			return $keys;
		}
		if ( mb_strlen( $word ) > 4 && str_ends_with( $word, 'ies' ) ) {
			$this->add_key( $keys, mb_substr( $word, 0, -3 ) . 'y' );
		}
		if ( mb_strlen( $word ) > 4 && 1 === preg_match( '/(?:ches|shes|sses|ses|xes|zes|oes)$/', $word ) ) {
			$this->add_key( $keys, mb_substr( $word, 0, -2 ) );
		}
		if ( mb_strlen( $word ) > 4 && str_ends_with( $word, 'ves' ) ) {
			$stem = mb_substr( $word, 0, -3 );
			$this->add_key( $keys, $stem . 'f' );
			$this->add_key( $keys, $stem . 'fe' );
		}
		if ( mb_strlen( $word ) > 5 && str_ends_with( $word, 'ing' ) ) {
			$base = mb_substr( $word, 0, -3 );
			$this->add_key( $keys, $base );
			$this->add_key( $keys, $base . 'e' );
			$length = mb_strlen( $base );
			if ( $length > 2 && mb_substr( $base, -1 ) === mb_substr( $base, -2, 1 ) ) {
				$this->add_key( $keys, mb_substr( $base, 0, -1 ) );
			}
		}
		if ( mb_strlen( $word ) > 4 && str_ends_with( $word, 'ed' ) ) {
			$base = mb_substr( $word, 0, -2 );
			$this->add_key( $keys, $base );
			$this->add_key( $keys, $base . 'e' );
			$length = mb_strlen( $base );
			if ( $length > 2 && mb_substr( $base, -1 ) === mb_substr( $base, -2, 1 ) ) {
				$this->add_key( $keys, mb_substr( $base, 0, -1 ) );
			}
			if ( str_ends_with( $word, 'ied' ) ) {
				$this->add_key( $keys, mb_substr( $word, 0, -3 ) . 'y' );
			}
		}
		if ( str_ends_with( $word, 's' ) && ! str_ends_with( $word, 'ss' ) ) {
			$this->add_key( $keys, mb_substr( $word, 0, -1 ) );
		}
		return $keys;
	}

	/**
	 * Store a key and its synonym canon.
	 *
	 * @param array<string, bool> $keys  Key set.
	 * @param string              $value Candidate.
	 * @return void
	 */
	private function add_key( array &$keys, string $value ): void {
		if ( mb_strlen( $value ) < 2 ) {
			return;
		}
		$keys[ $value ] = true;
		if ( isset( $this->synonym[ $value ] ) ) {
			$keys[ $this->synonym[ $value ] ] = true;
		}
	}
}
