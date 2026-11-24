import { __ } from '@wordpress/i18n';
import type { LlmsGroup } from './sitemaps';

export interface LlmsPreviewInput {
	siteName: string;
	summary: string;
	intro: string;
	homeUrl: string;
	groups: LlmsGroup[];
	includeUrls: string;
	excludeUrls: string;
}

function oneLine( text: string, max: number ): string {
	const collapsed = text
		.replace( /<[^>]*>/g, '' )
		.replace( /\s+/g, ' ' )
		.trim();
	return collapsed.length > max
		? collapsed.slice( 0, max ).trimEnd()
		: collapsed;
}

function canonical( url: string, homeUrl: string ): string {
	let value = url.trim();
	if ( value === '' || /[\s()]/.test( value ) ) {
		return '';
	}
	if ( value.startsWith( '/' ) ) {
		value = `${ homeUrl.replace( /\/$/, '' ) }${ value }`;
	}

	let parsed: URL;
	try {
		parsed = new URL( value );
	} catch {
		return '';
	}
	if ( parsed.protocol !== 'http:' && parsed.protocol !== 'https:' ) {
		return '';
	}

	let path = parsed.pathname || '/';
	if ( path !== '/' ) {
		path = path.replace( /\/$/, '' );
	}

	return `${ parsed.protocol }//${ parsed.host.toLowerCase() }${ path }`;
}

function introText( intro: string ): string {
	return intro
		.split( /\r?\n/ )
		.map( ( line ) => line.trim().replace( /^[# \t]+/, '' ) )
		.filter( ( line ) => line !== '' )
		.join( '\n' );
}

function linkText( title: string ): string {
	const text = oneLine( title, 180 ).replace( /[[\]()]/g, '' );
	return text !== '' ? text : __( 'Untitled', 'rankmyseo' );
}

function titleFromUrl( url: string, siteName: string ): string {
	let path = '';
	try {
		path = new URL( url ).pathname;
	} catch {
		path = '';
	}
	const segment = path.replace( /\/$/, '' ).split( '/' ).pop() ?? '';
	if ( segment === '' ) {
		return siteName;
	}
	return oneLine( segment.replace( /[-_]/g, ' ' ), 120 );
}

/**
 * Live markdown preview. Mirrors the PHP document so notes update without a request.
 *
 * @param input Saved groups plus the unsaved note and URL lists.
 */
export function renderLlmsMarkdown( input: LlmsPreviewInput ): string {
	const name = oneLine( input.siteName, 120 ) || __( 'Site', 'rankmyseo' );
	const summary =
		oneLine( input.summary, 300 ) || `Public pages on ${ name }.`;
	const intro = introText( input.intro );
	const excluded = new Set(
		input.excludeUrls
			.split( /\r?\n/ )
			.map( ( url ) => canonical( url, input.homeUrl ) )
			.filter( ( url ) => url !== '' )
	);
	const used = new Set< string >();
	const groups = input.groups
		.map( ( group ) => ( {
			label: group.label,
			links: group.links.filter( ( link ) => {
				const key = canonical( link.url, input.homeUrl );
				if ( key === '' || excluded.has( key ) || used.has( key ) ) {
					return false;
				}
				used.add( key );
				return true;
			} ),
		} ) )
		.filter( ( group ) => group.links.length > 0 );

	const included = input.includeUrls
		.split( /\r?\n/ )
		.map( ( url ) => url.trim() )
		.filter( ( url ) => url !== '' )
		.flatMap( ( url ) => {
			const key = canonical( url, input.homeUrl );
			if (
				key === '' ||
				excluded.has( key ) ||
				used.has( key ) ||
				used.size >= 500
			) {
				return [];
			}
			used.add( key );
			return [
				{
					title: titleFromUrl( url, name ),
					url,
					description: '',
				},
			];
		} );

	if ( included.length > 0 ) {
		groups.push( {
			label: __( 'Included links', 'rankmyseo' ),
			links: included,
		} );
	}

	const lines = [ `# ${ name }`, '', `> ${ summary }`, '' ];
	if ( intro !== '' ) {
		lines.push( intro, '' );
	}
	groups.forEach( ( group ) => {
		lines.push( `## ${ oneLine( group.label, 80 ) }`, '' );
		group.links.forEach( ( link ) => {
			const title = linkText( link.title );
			const detail = oneLine( link.description, 180 );
			lines.push(
				detail === ''
					? `- [${ title }](${ link.url })`
					: `- [${ title }](${ link.url }): ${ detail }`
			);
		} );
		lines.push( '' );
	} );

	return `${ lines.join( '\n' ).trimEnd() }\n`;
}
