import { __ } from '@wordpress/i18n';
import {
	deltaLabel,
	PAGE_ACTIONS,
	positionLabel,
	type EditorInsight,
} from '../insights';

interface CloudInsightsProps {
	insight: EditorInsight | null;
	onAskAi: () => void;
}

function isSafeUrl( url: string ): boolean {
	return (
		url.startsWith( 'https://' ) ||
		url.startsWith( 'http://' ) ||
		url.startsWith( '/' )
	);
}

export function CloudInsights( {
	insight,
	onAskAi,
}: CloudInsightsProps ): JSX.Element | null {
	if ( ! insight || ! insight.connected || ! insight.matched ) {
		return null;
	}

	const position = positionLabel( insight.keyword );
	const action = insight.page_action;
	const knownAction =
		action &&
		PAGE_ACTIONS.includes(
			action.action as ( typeof PAGE_ACTIONS )[ number ]
		);
	if ( '' === position && ! knownAction ) {
		return null;
	}

	const delta = deltaLabel( insight.keyword?.positions_gained ?? null );
	const queueUrl = isSafeUrl( insight.queue_url ) ? insight.queue_url : '';

	return (
		<section
			className="rms-cloud-insight"
			aria-label={ __( 'RankMySEO for this page', 'rankmyseo' ) }
		>
			{ insight.keyword && '' !== position && (
				<p className="rms-cloud-insight-rank">
					<span className="rms-cloud-insight-keyword">
						{ insight.keyword.keyword }
					</span>
					<span
						className={ `rms-position-badge${
							position === 'Outside top 20' ? ' is-outside' : ''
						}` }
					>
						{ position }
					</span>
					{ '' !== delta && (
						<span className="rms-position-delta">{ delta }</span>
					) }
				</p>
			) }
			{ knownAction && action && (
				<p className="rms-cloud-insight-action">
					<span className={ `rms-verdict is-${ action.action }` }>
						{ action.label || action.action }
					</span>
					<span>{ action.summary }</span>
				</p>
			) }
			<p className="rms-cloud-insight-links">
				<button
					type="button"
					className="rms-link-button"
					onClick={ onAskAi }
				>
					{ __( 'Ask about this result', 'rankmyseo' ) }
				</button>
				{ '' !== queueUrl && (
					<a href={ queueUrl }>
						{ insight.pending_count > 0
							? __( 'Review pending suggestions', 'rankmyseo' )
							: __( 'Open approval queue', 'rankmyseo' ) }
					</a>
				) }
			</p>
		</section>
	);
}
