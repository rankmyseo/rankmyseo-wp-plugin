const NARROW = new Set( [
	'i',
	'j',
	'l',
	'I',
	'1',
	'|',
	"'",
	'`',
	'.',
	',',
	':',
	';',
	'!',
] );
const WIDE = new Set( [ 'm', 'w', 'M', 'W', '@', '%' ] );

export function estimatePixelWidth( text: string, fontSize: number ): number {
	let width = 0;
	for ( const char of text ) {
		if ( char === ' ' ) {
			width += fontSize * 0.33;
		} else if ( NARROW.has( char ) ) {
			width += fontSize * 0.4;
		} else if ( WIDE.has( char ) ) {
			width += fontSize * 0.9;
		} else {
			width += fontSize * 0.55;
		}
	}
	return Math.round( width );
}

export function plainText( html: string ): string {
	const without = html
		.replace( /<script[\s\S]*?<\/script>/gi, ' ' )
		.replace( /<style[\s\S]*?<\/style>/gi, ' ' )
		.replace( /<!--[\s\S]*?-->/g, ' ' )
		.replace( /<[^>]+>/g, ' ' )
		.replace( /&nbsp;/gi, ' ' )
		.replace( /&amp;/gi, '&' )
		.replace( /&lt;/gi, '<' )
		.replace( /&gt;/gi, '>' )
		.replace( /&quot;/gi, '"' )
		.replace( /&#0?39;/gi, "'" );
	return without.replace( /\s+/g, ' ' ).trim();
}

export function normalizeWords( value: string ): string {
	return value
		.toLowerCase()
		.replace( /[^\p{L}\p{N}]+/gu, ' ' )
		.trim()
		.replace( /\s+/g, ' ' );
}

export function wordsOf( text: string ): string[] {
	const plain = text.trim();
	if ( ! plain ) {
		return [];
	}
	return plain
		.split( /\s+/ )
		.map( ( token ) =>
			token.replace( /^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '' )
		)
		.filter( Boolean );
}

export function containsPhrase( text: string, keyword: string ): boolean {
	const needle = normalizeWords( keyword );
	if ( ! needle ) {
		return false;
	}
	const hay = ` ${ normalizeWords( text ) } `;
	return hay.includes( ` ${ needle } ` );
}

export function countPhrase( text: string, keyword: string ): number {
	const needle = normalizeWords( keyword );
	if ( ! needle ) {
		return 0;
	}
	const hay = normalizeWords( text );
	if ( ! hay ) {
		return 0;
	}
	let count = 0;
	let from = 0;
	while ( from <= hay.length ) {
		const found = hay.indexOf( needle, from );
		if ( found < 0 ) {
			break;
		}
		const before = found === 0 || hay.charAt( found - 1 ) === ' ';
		const afterIndex = found + needle.length;
		const after =
			afterIndex === hay.length || hay.charAt( afterIndex ) === ' ';
		if ( before && after ) {
			count += 1;
		}
		from = found + Math.max( needle.length, 1 );
	}
	return count;
}

export function keywordNearStart( text: string, keyword: string ): boolean {
	const needle = normalizeWords( keyword );
	if ( ! needle || ! containsPhrase( text, keyword ) ) {
		return false;
	}
	const hay = normalizeWords( text );
	return hay.startsWith( needle ) || hay.indexOf( needle ) <= 20;
}

export function slugify( value: string ): string {
	const words = normalizeWords( value );
	return words ? words.replace( / /g, '-' ) : '';
}

export function slugContains( slug: string, keyword: string ): boolean {
	const needle = slugify( keyword );
	if ( ! needle ) {
		return false;
	}
	return slugify( slug ).includes( needle );
}

export function countSyllables( word: string ): number {
	const cleaned = word.toLowerCase().replace( /[^a-z]/g, '' );
	if ( ! cleaned ) {
		return 0;
	}
	if ( cleaned.length <= 3 ) {
		return 1;
	}
	const trimmed = cleaned
		.replace( /(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '' )
		.replace( /^y/, '' );
	const groups = trimmed.match( /[aeiouy]{1,2}/g );
	return Math.max( 1, groups ? groups.length : 1 );
}

export function isEnglishLocale( locale: string | undefined ): boolean {
	const code = ( locale || 'en' ).toLowerCase().replace( '_', '-' );
	return code.startsWith( 'en' );
}

export function wordTargetFor( postType: string | undefined ): number {
	return postType === 'product' ? 150 : 300;
}

export function roundDensity( value: number ): number {
	return Math.round( value * 100 ) / 100;
}
