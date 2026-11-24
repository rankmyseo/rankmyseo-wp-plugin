import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import type { ReactNode } from 'react';
import {
	analyze,
	categoryLabel,
	checkDetail,
	simpleTip,
	statusLabel,
	type AnalysisResult,
	type AnalyzerInput,
	type CheckResult,
} from '../../analyzer';
import { ScoreRing } from './ScoreRing';

export interface CannibalizationMatch {
	id: number;
	title: string;
	editLink: string;
	postType: string;
}

const CATEGORIES = [
	'keyword',
	'snippet',
	'content',
	'readability',
	'product',
] as const;

function useDebouncedValue< T >( value: T, delay: number ): T {
	const [ debounced, setDebounced ] = useState( value );
	useEffect( () => {
		if ( delay <= 0 ) {
			setDebounced( value );
			return undefined;
		}
		const timer = window.setTimeout( () => setDebounced( value ), delay );
		return () => window.clearTimeout( timer );
	}, [ value, delay ] );
	return debounced;
}

function bandMessage( band: AnalysisResult[ 'band' ] ): string {
	if ( band === 'no_keyword' ) {
		return __( 'Add a focus keyword to score this page.', 'rankmyseo' );
	}
	if ( band === 'good' ) {
		return __( 'This page covers the basics.', 'rankmyseo' );
	}
	return __(
		'A few changes will make this page easier to find.',
		'rankmyseo'
	);
}

function priority( status: CheckResult[ 'status' ] ): number {
	if ( status === 'critical' ) {
		return 0;
	}
	if ( status === 'improve' ) {
		return 1;
	}
	return 2;
}

export function AnalyzerPanel( {
	input,
	mode,
	debounceMs = 300,
	matches = [],
	onAnalysis,
	aside = null,
}: {
	input: AnalyzerInput;
	mode: 'simple' | 'advanced';
	debounceMs?: number;
	matches?: CannibalizationMatch[];
	onAnalysis?: ( result: AnalysisResult, signature: string ) => void;
	aside?: ReactNode;
} ): JSX.Element {
	const signature = JSON.stringify( input );
	const debouncedSignature = useDebouncedValue( signature, debounceMs );
	const analysis = useMemo(
		() => analyze( JSON.parse( debouncedSignature ) as AnalyzerInput ),
		[ debouncedSignature ]
	);

	useEffect( () => {
		onAnalysis?.( analysis, debouncedSignature );
	}, [ analysis, debouncedSignature, onAnalysis ] );

	const tips = analysis.checks
		.filter( ( item ) => item.status !== 'pass' )
		.sort(
			( left, right ) =>
				priority( left.status ) - priority( right.status )
		)
		.slice( 0, 3 );
	const live = `${ analysis.score }, ${
		analysis.checks.filter( ( item ) => item.status === 'critical' ).length
	}`;

	return (
		<section
			className="rms-analyzer"
			aria-label={ __( 'SEO score', 'rankmyseo' ) }
		>
			<div className="rms-score-row">
				<ScoreRing score={ analysis.score } band={ analysis.band } />
				<div>
					<p className="rms-score-caption">
						{ __( 'SEO score', 'rankmyseo' ) }
					</p>
					<p className="rms-score-band">
						{ bandMessage( analysis.band ) }
					</p>
				</div>
			</div>
			{ aside }
			<p className="rms-sr-only" aria-live="polite">
				{ live }
			</p>
			<ul className="rms-keyword-pills">
				{ analysis.keywords.map( ( item ) => (
					<li key={ `${ item.role }-${ item.keyword }` }>
						<span
							className={ `rms-keyword-pill is-${ item.status }` }
						>
							{ item.role === 'primary'
								? __( 'Focus', 'rankmyseo' )
								: __( 'Also', 'rankmyseo' ) }
							{ ': ' }
							{ item.keyword || __( 'None', 'rankmyseo' ) }
							{ ' · ' }
							{ statusLabel( item.status ) }
						</span>
					</li>
				) ) }
			</ul>

			{ matches.length > 0 && (
				<div className="rms-analyzer-banner" role="status">
					<p>
						{ __(
							'Another published page already targets this focus keyword.',
							'rankmyseo'
						) }
					</p>
					<ul>
						{ matches.map( ( match ) => (
							<li key={ match.id }>
								<a href={ match.editLink }>{ match.title }</a>
							</li>
						) ) }
					</ul>
				</div>
			) }

			{ mode === 'simple' ? (
				<ul className="rms-check-list">
					{ tips.length === 0 ? (
						<li>
							{ __(
								'This page looks ready for search.',
								'rankmyseo'
							) }
						</li>
					) : (
						tips.map( ( item ) => (
							<li
								key={ item.id }
								className={ `is-${ item.status }` }
							>
								<strong>{ statusLabel( item.status ) }</strong>
								<span>{ simpleTip( item ) }</span>
							</li>
						) )
					) }
				</ul>
			) : (
				<div className="rms-check-groups">
					{ CATEGORIES.filter( ( category ) =>
						analysis.checks.some(
							( item ) => item.category === category
						)
					).map( ( category ) => (
						<div key={ category }>
							<h3>{ categoryLabel( category ) }</h3>
							<ul className="rms-check-list">
								{ analysis.checks
									.filter(
										( item ) => item.category === category
									)
									.map( ( item ) => (
										<li
											key={ item.id }
											className={ `is-${ item.status }` }
										>
											<strong>
												{ statusLabel( item.status ) }
												<span className="rms-sr-only">
													{ ` ${ item.score }/${ item.maxScore }` }
												</span>
											</strong>
											<span>{ simpleTip( item ) }</span>
											<em>{ checkDetail( item ) }</em>
										</li>
									) ) }
							</ul>
						</div>
					) ) }
				</div>
			) }
		</section>
	);
}
