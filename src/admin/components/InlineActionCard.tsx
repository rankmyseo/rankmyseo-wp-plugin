import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	StatusBadge,
	type BadgeTone,
} from '../../shared/components/StatePrimitives';
import type { AiAction, RiskTier } from '../../shared/types';
import { readablePreview } from '../utils/previewText';
import { diffWords } from '../utils/wordDiff';

export interface InlineActionCardProps {
	action: AiAction;
	canApprove?: boolean;
	showJson?: boolean;
	onApprove?: ( id: number, after?: Record< string, unknown > ) => void;
	onReject?: ( id: number ) => void;
	onRollback?: ( id: number ) => void;
}

const RISK_TONE: Record< RiskTier, BadgeTone > = {
	low: 'up',
	medium: 'warn',
	high: 'down',
};

const RISK_LABEL: Record< RiskTier, string > = {
	low: __( 'Low', 'rankmyseo' ),
	medium: __( 'Medium', 'rankmyseo' ),
	high: __( 'High', 'rankmyseo' ),
};

export function InlineActionCard( {
	action,
	canApprove = true,
	showJson = false,
	onApprove,
	onReject,
	onRollback,
}: InlineActionCardProps ): JSX.Element {
	const [ editing, setEditing ] = useState( false );
	const [ draft, setDraft ] = useState( action.after_text );
	const [ confirmHigh, setConfirmHigh ] = useState( false );
	const beforeText = readablePreview( action.before_text );
	const parts = diffWords( beforeText, action.after_text );
	const pending = action.status === 'pending' || action.status === 'stale';
	const awaitingConfirm = action.risk_tier === 'high' && confirmHigh;

	const approve = (): void => {
		if ( action.risk_tier === 'high' && ! confirmHigh ) {
			setConfirmHigh( true );
			return;
		}
		const edited =
			editing && draft !== action.after_text
				? { suggested: draft }
				: undefined;
		onApprove?.( action.id, edited );
	};

	return (
		<article
			className="rms-action-card"
			data-status={ action.status }
			aria-label={ action.title }
		>
			<div className="rms-action-card-header">
				<div>
					<strong className="rms-action-card-title">
						{ action.title }
					</strong>
					<div className="rms-action-card-target rms-mono">
						{ action.target_url ||
							( action.wp_post_id
								? `#${ action.wp_post_id }`
								: __( 'This site', 'rankmyseo' ) ) }
					</div>
				</div>
				<StatusBadge tone={ RISK_TONE[ action.risk_tier ] }>
					{ RISK_LABEL[ action.risk_tier ] }
				</StatusBadge>
			</div>

			<p className="rms-action-card-rationale">
				{ action.rationale ||
					__(
						'Review the before and after, then decide.',
						'rankmyseo'
					) }
			</p>

			<div
				className="rms-diff-block"
				aria-label={ __( 'Before and after', 'rankmyseo' ) }
			>
				<p className="rms-word-diff">
					{ parts.map( ( part, index ) => {
						if ( part.kind === 'del' ) {
							return (
								<del
									key={ `d-${ index }` }
									className="rms-diff-token-del"
								>
									{ part.text }
								</del>
							);
						}
						if ( part.kind === 'add' ) {
							return (
								<ins
									key={ `a-${ index }` }
									className="rms-diff-token-add"
								>
									{ part.text }
								</ins>
							);
						}
						return (
							<span key={ `s-${ index }` }>{ part.text }</span>
						);
					} ) }
					{ parts.length === 0 && (
						<span>{ __( '(empty)', 'rankmyseo' ) }</span>
					) }
				</p>
			</div>

			{ showJson && (
				<pre className="rms-json-diff">
					{ JSON.stringify(
						{ before: action.before, after: action.after },
						null,
						2
					) }
				</pre>
			) }

			{ editing && pending && (
				<label
					className="rms-edit-field"
					htmlFor={ `rms-action-edit-${ action.id }` }
				>
					<span>{ __( 'Edit the suggestion', 'rankmyseo' ) }</span>
					<textarea
						id={ `rms-action-edit-${ action.id }` }
						rows={ 3 }
						value={ draft }
						onChange={ ( event ) => setDraft( event.target.value ) }
					/>
				</label>
			) }

			<div className="rms-action-card-footer">
				{ pending && canApprove && (
					<>
						<Button
							variant="primary"
							className="rms-btn-primary"
							size="small"
							onClick={ approve }
						>
							{ awaitingConfirm
								? __( 'Confirm apply', 'rankmyseo' )
								: __( 'Approve & Apply', 'rankmyseo' ) }
						</Button>
						<Button
							variant="secondary"
							size="small"
							onClick={ () => setEditing( ( open ) => ! open ) }
						>
							{ __( 'Edit', 'rankmyseo' ) }
						</Button>
						<Button
							variant="tertiary"
							size="small"
							onClick={ () => onReject?.( action.id ) }
						>
							{ __( 'Reject', 'rankmyseo' ) }
						</Button>
					</>
				) }
				{ action.status === 'applied' && canApprove && (
					<Button
						variant="secondary"
						size="small"
						onClick={ () => onRollback?.( action.id ) }
					>
						{ __( 'Undo / Rollback', 'rankmyseo' ) }
					</Button>
				) }
				{ action.status === 'applied' && (
					<StatusBadge tone="up">
						{ __( 'Applied', 'rankmyseo' ) }
					</StatusBadge>
				) }
				{ action.status === 'rejected' && (
					<StatusBadge tone="flat">
						{ __( 'Rejected', 'rankmyseo' ) }
					</StatusBadge>
				) }
				{ action.status === 'rolled_back' && (
					<StatusBadge tone="warn">
						{ __( 'Rolled back', 'rankmyseo' ) }
					</StatusBadge>
				) }
			</div>
		</article>
	);
}
