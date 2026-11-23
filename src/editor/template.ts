export interface TemplateVars {
	[ key: string ]: string | undefined;
}

/**
 * Client copy of the PHP template engine: replace known tokens and drop dangling separators.
 * @param template
 * @param vars
 */
export function replaceTemplate(
	template: string,
	vars: TemplateVars
): string {
	const rendered = template.replace(
		/%([a-z0-9_]+)%/gi,
		( match, raw: string ) => {
			const key = raw.toLowerCase();
			if ( Object.prototype.hasOwnProperty.call( vars, key ) ) {
				return vars[ key ] ?? '';
			}
			return match;
		}
	);

	return cleanup( rendered, vars.sep ?? '' );
}

function cleanup( value: string, sep: string ): string {
	let next = value.replace( /\s+/gu, ' ' ).trim();
	if ( sep === '' ) {
		return next;
	}

	const quoted = sep.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	const edges = new RegExp(
		`^(?:${ quoted }\\s*)+|(?:\\s*${ quoted })+$`,
		'u'
	);
	const dupes = new RegExp( `(?:\\s*${ quoted }\\s*){2,}`, 'u' );

	let guard = 0;
	while ( guard < 8 ) {
		const cleaned = next
			.replace( edges, '' )
			.replace( dupes, ` ${ sep } ` )
			.replace( /\s+/gu, ' ' )
			.trim();
		if ( cleaned === next ) {
			break;
		}
		next = cleaned;
		guard += 1;
	}

	return next;
}
