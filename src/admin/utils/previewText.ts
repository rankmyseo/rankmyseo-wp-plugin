const TEXT_KEYS = new Set( [
	'suggested',
	'seoTitle',
	'title',
	'seo_title',
	'seoDescription',
	'description',
	'metaDescription',
	'content',
	'body',
	'current',
	'canonical',
	'schemaType',
	'schema_type',
	'imageAlt',
	'alt',
] );

function isBlankTextPayload( value: string ): boolean {
	if ( ! value.startsWith( '{' ) ) {
		return false;
	}
	try {
		const parsed: unknown = JSON.parse( value );
		if (
			! parsed ||
			typeof parsed !== 'object' ||
			Array.isArray( parsed )
		) {
			return false;
		}
		const record = parsed as Record< string, unknown >;
		const keys = Object.keys( record );
		if ( ! keys.some( ( key ) => TEXT_KEYS.has( key ) ) ) {
			return false;
		}
		return keys.every( ( key ) => {
			const item = record[ key ];
			if ( TEXT_KEYS.has( key ) ) {
				return typeof item !== 'string' || item.trim() === '';
			}
			return (
				item === '' ||
				item === null ||
				( Array.isArray( item ) && item.length === 0 )
			);
		} );
	} catch {
		return false;
	}
}

/**
 * Text safe to show in a before/after card.
 *
 * Empty titles are sometimes stored as {"seoTitle":""}. That JSON should not
 * be struck through beside the suggestion.
 * @param value Stored before or after text.
 */
export function readablePreview( value: string ): string {
	const trimmed = value.trim();
	if ( trimmed === '' || isBlankTextPayload( trimmed ) ) {
		return '';
	}
	return value;
}
