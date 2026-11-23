import type { ContentFacts } from './types';
import { countSyllables, plainText, wordsOf } from './text';

function sentencesOf( plain: string ): string[] {
	const parts = plain
		.split( /[.!?]+/ )
		.map( ( part ) => part.trim() )
		.filter( ( part ) => wordsOf( part ).length > 0 );
	if ( parts.length === 0 && wordsOf( plain ).length > 0 ) {
		return [ plain.trim() ];
	}
	return parts;
}

function paragraphsOf( html: string, plain: string ): string[] {
	const fromTags = [ ...html.matchAll( /<p\b[^>]*>([\s\S]*?)<\/p>/gi ) ]
		.map( ( match ) => plainText( match[ 1 ] ?? '' ) )
		.filter( ( paragraph ) => wordsOf( paragraph ).length > 0 );
	if ( fromTags.length > 0 ) {
		return fromTags;
	}
	return plain
		.split( /\n\s*\n/ )
		.map( ( paragraph ) => paragraph.trim() )
		.filter( ( paragraph ) => wordsOf( paragraph ).length > 0 );
}

function headingsOf( html: string ): Array< { level: number; text: string } > {
	return [ ...html.matchAll( /<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/gi ) ].map(
		( match ) => ( {
			level: Number( match[ 1 ] ),
			text: plainText( match[ 2 ] ?? '' ),
		} )
	);
}

function imagesOf( html: string ): Array< { alt: string } > {
	return [ ...html.matchAll( /<img\b[^>]*>/gi ) ].map( ( match ) => {
		const tag = match[ 0 ];
		const alt = tag.match(
			/\balt\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i
		);
		return { alt: ( alt?.[ 1 ] ?? alt?.[ 2 ] ?? alt?.[ 3 ] ?? '' ).trim() };
	} );
}

export function classifyLink(
	href: string,
	siteUrl: string
): 'internal' | 'external' | 'ignore' {
	const trimmed = href.trim();
	if (
		! trimmed ||
		trimmed.startsWith( '#' ) ||
		/^mailto:/i.test( trimmed ) ||
		/^tel:/i.test( trimmed ) ||
		/^javascript:/i.test( trimmed )
	) {
		return 'ignore';
	}
	if (
		trimmed.startsWith( '/' ) ||
		trimmed.startsWith( '?' ) ||
		trimmed.startsWith( './' ) ||
		trimmed.startsWith( '../' )
	) {
		return 'internal';
	}

	const base = siteUrl || 'https://example.com';
	try {
		const url = new URL( trimmed, base );
		if ( url.protocol !== 'http:' && url.protocol !== 'https:' ) {
			return 'ignore';
		}
		const site = new URL( base );
		return url.hostname === site.hostname ? 'internal' : 'external';
	} catch {
		return 'ignore';
	}
}

function linkCounts(
	html: string,
	siteUrl: string
): { internal: number; external: number } {
	let internal = 0;
	let external = 0;
	for ( const match of html.matchAll(
		/<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|'([^']*)')[^>]*>/gi
	) ) {
		const kind = classifyLink( match[ 1 ] ?? match[ 2 ] ?? '', siteUrl );
		if ( kind === 'internal' ) {
			internal += 1;
		} else if ( kind === 'external' ) {
			external += 1;
		}
	}
	return { internal, external };
}

export function inspectContent( html: string, siteUrl: string ): ContentFacts {
	const plain = plainText( html );
	const words = wordsOf( plain );
	const sentences = sentencesOf( plain );
	const paragraphs = paragraphsOf( html, plain );
	const headings = headingsOf( html );
	const images = imagesOf( html );
	const links = linkCounts( html, siteUrl );
	const syllables = words.reduce(
		( total, word ) => total + countSyllables( word ),
		0
	);

	return {
		plain,
		words,
		sentences,
		paragraphs,
		headings,
		images,
		internalLinks: links.internal,
		externalLinks: links.external,
		syllables,
	};
}

export function skippedHeadingLevels( levels: number[] ): boolean {
	for ( let index = 1; index < levels.length; index++ ) {
		if ( levels[ index ] > levels[ index - 1 ] + 1 ) {
			return true;
		}
	}
	return false;
}
