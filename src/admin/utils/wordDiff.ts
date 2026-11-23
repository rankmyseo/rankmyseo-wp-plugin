export type DiffKind = 'same' | 'add' | 'del';

export interface DiffPart {
	kind: DiffKind;
	text: string;
}

function tokens( value: string ): string[] {
	return value.match( /\s+|\S+/g ) ?? [];
}

/**
 * Word-level diff. Long strings fall back to a single before and after pair.
 *
 * @param before Previous text.
 * @param after  Proposed text.
 */
export function diffWords( before: string, after: string ): DiffPart[] {
	const left = tokens( before );
	const right = tokens( after );
	if ( left.length > 80 || right.length > 80 ) {
		const fallback: DiffPart[] = [];
		if ( before ) {
			fallback.push( { kind: 'del', text: before } );
		}
		if ( after ) {
			fallback.push( { kind: 'add', text: after } );
		}
		return fallback;
	}

	const rows = left.length + 1;
	const cols = right.length + 1;
	const scores: number[][] = [];
	for ( let row = 0; row < rows; row += 1 ) {
		scores[ row ] = new Array< number >( cols ).fill( 0 );
	}
	for ( let i = left.length - 1; i >= 0; i -= 1 ) {
		for ( let j = right.length - 1; j >= 0; j -= 1 ) {
			scores[ i ][ j ] =
				left[ i ] === right[ j ]
					? scores[ i + 1 ][ j + 1 ] + 1
					: Math.max( scores[ i + 1 ][ j ], scores[ i ][ j + 1 ] );
		}
	}

	const parts: DiffPart[] = [];
	const push = ( kind: DiffKind, text: string ): void => {
		const last = parts[ parts.length - 1 ];
		if ( last && last.kind === kind ) {
			last.text += text;
			return;
		}
		parts.push( { kind, text } );
	};

	let i = 0;
	let j = 0;
	while ( i < left.length && j < right.length ) {
		if ( left[ i ] === right[ j ] ) {
			push( 'same', left[ i ] );
			i += 1;
			j += 1;
		} else if ( scores[ i + 1 ][ j ] >= scores[ i ][ j + 1 ] ) {
			push( 'del', left[ i ] );
			i += 1;
		} else {
			push( 'add', right[ j ] );
			j += 1;
		}
	}
	while ( i < left.length ) {
		push( 'del', left[ i ] );
		i += 1;
	}
	while ( j < right.length ) {
		push( 'add', right[ j ] );
		j += 1;
	}
	return parts;
}
