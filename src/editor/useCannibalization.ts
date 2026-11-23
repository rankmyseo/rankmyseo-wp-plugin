import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import type { CannibalizationMatch } from './components/AnalyzerPanel';

interface CannibalizationResponse {
	matches?: CannibalizationMatch[];
}

export function useCannibalization(
	keyword: string,
	postId: number,
	enabled = true
): CannibalizationMatch[] {
	const [ matches, setMatches ] = useState< CannibalizationMatch[] >( [] );
	const trimmed = keyword.trim();

	useEffect( () => {
		if ( ! enabled || trimmed === '' ) {
			setMatches( ( current ) =>
				current.length === 0 ? current : []
			);
			return undefined;
		}

		let cancelled = false;
		const timer = window.setTimeout( () => {
			const params = new URLSearchParams( {
				keyword: trimmed,
				exclude_post_id: String( postId || 0 ),
			} );
			void apiFetch< CannibalizationResponse >( {
				path: `/rankmyseo/v1/analyzer/cannibalization?${ params.toString() }`,
			} )
				.then( ( response ) => {
					if ( ! cancelled ) {
						setMatches(
							Array.isArray( response?.matches )
								? response.matches
								: []
						);
					}
				} )
				.catch( () => {
					if ( ! cancelled ) {
						setMatches( [] );
					}
				} );
		}, 300 );

		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
	}, [ trimmed, postId, enabled ] );

	return matches;
}
