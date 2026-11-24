import { normalizeWords } from './text';

/**
 * Lightweight English word-form and synonym keys.
 * Non-English matching stays exact so other languages are not judged by English stems.
 */

const IRREGULAR: Record< string, string > = {
	children: 'child',
	men: 'man',
	women: 'woman',
	people: 'person',
	feet: 'foot',
	teeth: 'tooth',
	mice: 'mouse',
	geese: 'goose',
	is: 'be',
	am: 'be',
	are: 'be',
	was: 'be',
	were: 'be',
	been: 'be',
	being: 'be',
	has: 'have',
	had: 'have',
	having: 'have',
	does: 'do',
	did: 'do',
	done: 'do',
	doing: 'do',
	goes: 'go',
	went: 'go',
	gone: 'go',
	going: 'go',
	ran: 'run',
	made: 'make',
	makes: 'make',
	making: 'make',
	bought: 'buy',
	buys: 'buy',
	buying: 'buy',
	sold: 'sell',
	sells: 'sell',
	selling: 'sell',
	wrote: 'write',
	written: 'write',
	writes: 'write',
	writing: 'write',
	took: 'take',
	taken: 'take',
	takes: 'take',
	taking: 'take',
	came: 'come',
	comes: 'come',
	coming: 'come',
	saw: 'see',
	seen: 'see',
	sees: 'see',
	seeing: 'see',
	got: 'get',
	gotten: 'get',
	gets: 'get',
	getting: 'get',
	said: 'say',
	says: 'say',
	saying: 'say',
	better: 'good',
	best: 'good',
	worse: 'bad',
	worst: 'bad',
};

const SYNONYM_GROUPS: string[][] = [
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

const SYNONYM: Record< string, string > = {};
for ( const group of SYNONYM_GROUPS ) {
	const canon = group[ 0 ];
	for ( const word of group ) {
		SYNONYM[ word ] = canon;
	}
}

export interface PhraseHit {
	found: boolean;
	offset: number;
	count: number;
}

export function tokenize( value: string ): string[] {
	const normalized = normalizeWords( value );
	if ( ! normalized ) {
		return [];
	}
	return normalized.split( ' ' ).filter( Boolean );
}

function addKey( keys: Set< string >, value: string ): void {
	if ( value.length >= 2 ) {
		keys.add( value );
		const canon = SYNONYM[ value ];
		if ( canon ) {
			keys.add( canon );
		}
	}
}

/**
 * English keys for one token: the token, a light stem, and a synonym canon.
 * @param word
 * @param english
 */
export function tokenKeys( word: string, english: boolean ): Set< string > {
	const keys = new Set< string >();
	addKey( keys, word );
	if ( ! english ) {
		return keys;
	}
	if ( IRREGULAR[ word ] ) {
		addKey( keys, IRREGULAR[ word ] );
	}
	if ( word.length <= 3 ) {
		return keys;
	}
	if ( word.length > 4 && word.endsWith( 'ies' ) ) {
		addKey( keys, `${ word.slice( 0, -3 ) }y` );
	}
	if (
		word.length > 4 &&
		/(?:ches|shes|sses|ses|xes|zes|oes)$/.test( word )
	) {
		addKey( keys, word.slice( 0, -2 ) );
	}
	if ( word.length > 4 && word.endsWith( 'ves' ) ) {
		addKey( keys, `${ word.slice( 0, -3 ) }f` );
		addKey( keys, `${ word.slice( 0, -3 ) }fe` );
	}
	if ( word.length > 5 && word.endsWith( 'ing' ) ) {
		const base = word.slice( 0, -3 );
		addKey( keys, base );
		addKey( keys, `${ base }e` );
		if (
			base.length > 2 &&
			base.charAt( base.length - 1 ) === base.charAt( base.length - 2 )
		) {
			addKey( keys, base.slice( 0, -1 ) );
		}
	}
	if ( word.length > 4 && word.endsWith( 'ed' ) ) {
		const base = word.slice( 0, -2 );
		addKey( keys, base );
		addKey( keys, `${ base }e` );
		if (
			base.length > 2 &&
			base.charAt( base.length - 1 ) === base.charAt( base.length - 2 )
		) {
			addKey( keys, base.slice( 0, -1 ) );
		}
		if ( word.endsWith( 'ied' ) ) {
			addKey( keys, `${ word.slice( 0, -3 ) }y` );
		}
	}
	if ( word.length > 3 && word.endsWith( 's' ) && ! word.endsWith( 'ss' ) ) {
		addKey( keys, word.slice( 0, -1 ) );
	}
	return keys;
}

export function tokensMatch(
	left: string,
	right: string,
	english: boolean
): boolean {
	if ( left === right ) {
		return true;
	}
	if ( ! english ) {
		return false;
	}
	const keys = tokenKeys( left, true );
	for ( const key of tokenKeys( right, true ) ) {
		if ( keys.has( key ) ) {
			return true;
		}
	}
	return false;
}

function tokenOffsets( tokens: string[] ): number[] {
	const offsets: number[] = [];
	let cursor = 0;
	tokens.forEach( ( token, index ) => {
		offsets.push( cursor );
		cursor += token.length + ( index === tokens.length - 1 ? 0 : 1 );
	} );
	return offsets;
}

/**
 * Find a phrase. English locales also accept plurals, verb stems, and a short synonym list.
 * @param text
 * @param keyword
 * @param english
 */
export function phraseMatch(
	text: string,
	keyword: string,
	english: boolean
): PhraseHit {
	const needle = tokenize( keyword );
	const hay = tokenize( text );
	if ( needle.length === 0 || hay.length === 0 ) {
		return { found: false, offset: -1, count: 0 };
	}
	const offsets = tokenOffsets( hay );
	const hits: number[] = [];
	const width = needle.length;
	for ( let index = 0; index <= hay.length - width;  ) {
		let matched = true;
		for ( let step = 0; step < width; step++ ) {
			if (
				! tokensMatch(
					hay[ index + step ] ?? '',
					needle[ step ] ?? '',
					english
				)
			) {
				matched = false;
				break;
			}
		}
		if ( matched ) {
			hits.push( offsets[ index ] ?? 0 );
			index += width;
		} else {
			index += 1;
		}
	}
	return {
		found: hits.length > 0,
		offset: hits[ 0 ] ?? -1,
		count: hits.length,
	};
}

export function phraseNearStart( hit: PhraseHit ): boolean {
	return hit.found && hit.offset <= 20;
}

export function slugMatches(
	slug: string,
	keyword: string,
	english: boolean
): boolean {
	return phraseMatch( slug.replace( /-/g, ' ' ), keyword, english ).found;
}

const STOP = new Set( [
	'the',
	'and',
	'for',
	'with',
	'your',
	'this',
	'that',
	'from',
	'are',
	'was',
	'you',
	'our',
] );

/**
 * Shared meaningful tokens between two labels.
 * @param left
 * @param right
 * @param english
 */
export function overlapCount(
	left: string,
	right: string,
	english: boolean
): number {
	const used = new Set< number >();
	let count = 0;
	for ( const token of tokenize( left ) ) {
		if ( token.length < 3 || STOP.has( token ) ) {
			continue;
		}
		const hay = tokenize( right );
		for ( let index = 0; index < hay.length; index++ ) {
			if ( used.has( index ) ) {
				continue;
			}
			const other = hay[ index ] ?? '';
			if ( other.length < 3 || STOP.has( other ) ) {
				continue;
			}
			if ( tokensMatch( token, other, english ) ) {
				used.add( index );
				count += 1;
				break;
			}
		}
	}
	return count;
}

export function samePhrase(
	left: string,
	right: string,
	english: boolean
): boolean {
	const a = tokenize( left );
	const b = tokenize( right );
	if ( a.length === 0 || a.length !== b.length ) {
		return false;
	}
	return a.every( ( token, index ) =>
		tokensMatch( token, b[ index ] ?? '', english )
	);
}

/**
 * Keep at most five unique secondary keywords, skipping the focus keyword.
 * @param focus
 * @param values
 */
export function limitSecondary(
	focus: string,
	values: string[] | undefined
): string[] {
	const primary = normalizeWords( focus );
	const seen = new Set< string >();
	const list: string[] = [];
	for ( const value of values ?? [] ) {
		const trimmed = value.trim();
		const key = normalizeWords( trimmed );
		if ( ! key || key === primary || seen.has( key ) ) {
			continue;
		}
		seen.add( key );
		list.push( trimmed );
		if ( list.length >= 5 ) {
			break;
		}
	}
	return list;
}
