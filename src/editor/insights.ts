export interface EditorKeywordInsight {
	keyword: string;
	position: number | null;
	outside_top_20: boolean;
	positions_gained: number | null;
}

export interface EditorPageAction {
	action: string;
	label: string;
	summary: string;
}

export interface EditorInsight {
	connected: boolean;
	matched: boolean;
	keyword: EditorKeywordInsight | null;
	page_action: EditorPageAction | null;
	pending_count: number;
	issue_count: number;
	queue_url: string;
}

export const PAGE_ACTIONS = [
	'refresh',
	'tune',
	'expand',
	'rewrite',
	'merge',
	'wait',
] as const;

export type PageActionName = ( typeof PAGE_ACTIONS )[ number ];

/**
 * Position text for a matched keyword. A null position is "Outside top 20"
 * only when the cache says so. Traffic and volume are never derived.
 *
 * @param keyword Matched keyword, or null.
 */
export function positionLabel( keyword: EditorKeywordInsight | null ): string {
	if ( ! keyword ) {
		return '';
	}
	if ( typeof keyword.position === 'number' ) {
		return `#${ keyword.position }`;
	}
	if ( keyword.position === null && keyword.outside_top_20 ) {
		return 'Outside top 20';
	}
	return '';
}

/**
 * Rank movement. Positive means the page climbed. Zero is no change.
 *
 * @param delta Positions gained, or null when unknown.
 */
export function deltaLabel( delta: number | null ): string {
	if ( delta === null || Number.isNaN( delta ) ) {
		return '';
	}
	if ( delta > 0 ) {
		return `Up ${ delta }`;
	}
	if ( delta < 0 ) {
		return `Down ${ Math.abs( delta ) }`;
	}
	return 'No change';
}

/**
 * Whether a cached row belongs to the post open in the editor.
 * Matching is by post ID or by the same path on this site.
 *
 * @param rowUrl     URL stored with the cached row.
 * @param pageUrl    URL of the post open in the editor.
 * @param rowPostId  Post ID stored with the cached row.
 * @param pagePostId Post ID open in the editor.
 */
export function insightMatchesUrl(
	rowUrl: string,
	pageUrl: string,
	rowPostId?: number | null,
	pagePostId?: number
): boolean {
	if (
		typeof rowPostId === 'number' &&
		rowPostId > 0 &&
		rowPostId === pagePostId
	) {
		return true;
	}
	return pathKey( rowUrl ) !== '' && pathKey( rowUrl ) === pathKey( pageUrl );
}

function pathKey( url: string ): string {
	const trimmed = url.trim();
	if ( '' === trimmed ) {
		return '';
	}
	try {
		const parsed = new URL( trimmed, 'https://example.com' );
		const path = parsed.pathname.replace( /\/+$/, '' ) || '/';
		const host = /^https?:/i.test( trimmed ) ? parsed.host : '';
		return `${ host }${ path }`.toLowerCase();
	} catch {
		return trimmed.replace( /\/+$/, '' ).toLowerCase();
	}
}
