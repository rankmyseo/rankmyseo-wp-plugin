import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export interface LinkSuggestion {
	id: number;
	title: string;
	url: string;
	score: number;
	reasons: string[];
	cornerstone?: boolean;
	orphan?: boolean;
}

function reasonLabel( reason: string ): string {
	switch ( reason ) {
		case 'shared_keyword':
			return __( 'Same keyword', 'rankmyseo' );
		case 'title':
			return __( 'Similar title', 'rankmyseo' );
		case 'slug':
			return __( 'Similar link', 'rankmyseo' );
		case 'taxonomy':
			return __( 'Same category', 'rankmyseo' );
		case 'cornerstone':
			return __( 'Cornerstone', 'rankmyseo' );
		case 'orphan':
			return __( 'No incoming links', 'rankmyseo' );
		default:
			return reason;
	}
}

function escapeHtml( value: string ): string {
	return value
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

export function linkHtml( url: string, title: string ): string {
	return `<a href="${ escapeHtml( url ) }">${ escapeHtml( title ) }</a>`;
}

export function LinkSuggestions( {
	postId,
	focusKeyword,
	secondaryKeywords,
	title,
	slug,
	suggestions,
	onInsert,
}: {
	postId: number;
	focusKeyword: string;
	secondaryKeywords: string[];
	title: string;
	slug: string;
	suggestions?: LinkSuggestion[];
	onInsert?: ( url: string, label: string ) => void;
} ): JSX.Element | null {
	const [ rows, setRows ] = useState< LinkSuggestion[] >( suggestions ?? [] );
	const [ copied, setCopied ] = useState( 0 );
	const provided = suggestions !== undefined;
	const secondaryKey = secondaryKeywords.slice( 0, 5 ).join( ', ' );

	useEffect( () => {
		if ( provided ) {
			setRows( suggestions );
			return undefined;
		}
		if (
			postId <= 0 &&
			focusKeyword.trim() === '' &&
			title.trim() === ''
		) {
			setRows( [] );
			return undefined;
		}
		let active = true;
		const params = new URLSearchParams( {
			post_id: String( postId ),
			focus: focusKeyword,
			title,
			slug,
			secondary: secondaryKey,
		} );
		const timer = window.setTimeout( () => {
			void apiFetch( {
				path: `/rankmyseo/v1/links/suggestions?${ params.toString() }`,
			} )
				.then( ( response ) => {
					if ( ! active ) {
						return;
					}
					const list = (
						response as { suggestions?: LinkSuggestion[] }
					 ).suggestions;
					setRows( Array.isArray( list ) ? list : [] );
				} )
				.catch( () => {
					if ( active ) {
						setRows( [] );
					}
				} );
		}, 300 );
		return () => {
			active = false;
			window.clearTimeout( timer );
		};
	}, [
		provided,
		suggestions,
		postId,
		focusKeyword,
		secondaryKey,
		title,
		slug,
	] );

	if ( rows.length === 0 ) {
		return null;
	}

	const copy = async ( row: LinkSuggestion ): Promise< void > => {
		const text = row.url;
		try {
			await navigator.clipboard.writeText( text );
		} catch {
			const area = document.createElement( 'textarea' );
			area.value = text;
			document.body.appendChild( area );
			area.select();
			document.execCommand( 'copy' );
			area.remove();
		}
		setCopied( row.id );
	};

	return (
		<section
			className="rms-link-suggestions"
			aria-label={ __( 'Suggested internal links', 'rankmyseo' ) }
		>
			<h3>{ __( 'Suggested links on this site', 'rankmyseo' ) }</h3>
			<p className="rms-muted">
				{ __(
					'These published pages share a keyword, title, or category. Cornerstone pages and pages nothing else links to are preferred.',
					'rankmyseo'
				) }
			</p>
			<ul>
				{ rows.map( ( row ) => (
					<li key={ row.id }>
						<div>
							<strong>{ row.title }</strong>
							<span className="rms-muted">
								{ row.reasons.map( reasonLabel ).join( ' · ' ) }
							</span>
						</div>
						<div className="rms-link-actions">
							<Button
								variant="secondary"
								onClick={ () => {
									void copy( row );
								} }
							>
								{ copied === row.id
									? __( 'Copied', 'rankmyseo' )
									: __( 'Copy link', 'rankmyseo' ) }
							</Button>
							{ onInsert && (
								<Button
									variant="primary"
									className="rms-btn-primary"
									onClick={ () =>
										onInsert( row.url, row.title )
									}
								>
									{ __( 'Insert link', 'rankmyseo' ) }
								</Button>
							) }
						</div>
						{ copied === row.id && (
							<p className="rms-sr-only" role="status">
								{ sprintf(
									/* translators: %s: link address. */
									__( 'Copied %s', 'rankmyseo' ),
									row.url
								) }
							</p>
						) }
					</li>
				) ) }
			</ul>
		</section>
	);
}
