export const TITLE_FONT = 20;
export const DESCRIPTION_FONT = 14;
export const TITLE_MIN_CHARS = 30;
export const TITLE_MAX_CHARS = 60;
export const DESCRIPTION_MIN_CHARS = 120;
export const DESCRIPTION_MAX_CHARS = 155;
export const DESKTOP_TITLE_PX = 580;
export const MOBILE_TITLE_PX = 480;
export const DESKTOP_DESCRIPTION_PX = 920;
export const MOBILE_DESCRIPTION_PX = 680;

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

export type MeterStatus = 'empty' | 'short' | 'good' | 'long';

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

export function meterStatus(
	length: number,
	min: number,
	max: number,
	pixelWidth: number,
	pixelMax: number
): MeterStatus {
	if ( length === 0 ) {
		return 'empty';
	}
	if ( length < min ) {
		return 'short';
	}
	if ( length > max || pixelWidth > pixelMax ) {
		return 'long';
	}
	return 'good';
}

export function truncateToPixels(
	text: string,
	fontSize: number,
	maxPx: number
): string {
	if ( estimatePixelWidth( text, fontSize ) <= maxPx ) {
		return text;
	}

	let cut = text;
	while (
		cut.length > 0 &&
		estimatePixelWidth( `${ cut }…`, fontSize ) > maxPx
	) {
		cut = cut.slice( 0, -1 );
	}

	return `${ cut.trimEnd() }…`;
}
