import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	StatusBadge,
	type BadgeTone,
} from '../../shared/components/StatePrimitives';
import type { ChatActionCardData, RiskTier } from '../../shared/types';

export interface InlineActionCardSlotProps {
	card: ChatActionCardData;
	onApprove?: ( cardId: string ) => void;
	onReject?: ( cardId: string ) => void;
	onUndo?: ( cardId: string ) => void;
}

const RISK_TONE: Record< RiskTier, BadgeTone > = {
	low: 'up',
	medium: 'warn',
	high: 'down',
};

export function InlineActionCardSlot( {
	card,
	onApprove,
	onReject,
	onUndo,
}: InlineActionCardSlotProps ): JSX.Element {
	const riskLabels: Record< RiskTier, string > = {
		low: __( 'Low risk', 'rankmyseo' ),
		medium: __( 'Medium risk', 'rankmyseo' ),
		high: __( 'High risk', 'rankmyseo' ),
	};

	return (
		<div className="rms-action-card" data-status={ card.status }>
			<div className="rms-action-card-header">
				<div>
					<strong className="rms-action-card-title">
						{ card.title }
					</strong>
					<div className="rms-action-card-target rms-mono">
						{ card.targetLabel }
					</div>
				</div>
				<StatusBadge tone={ RISK_TONE[ card.riskTier ] }>
					{ riskLabels[ card.riskTier ] }
				</StatusBadge>
			</div>

			<div className="rms-diff-block">
				<div className="rms-diff-row rms-diff-before">
					<span className="rms-diff-label">
						{ __( 'Before', 'rankmyseo' ) }
					</span>
					<del>
						<span className="rms-sr-only">
							{ __( 'Removed:', 'rankmyseo' ) }{ ' ' }
						</span>
						{ card.beforeText || __( '(empty)', 'rankmyseo' ) }
					</del>
				</div>
				<div className="rms-diff-row rms-diff-after">
					<span className="rms-diff-label">
						{ __( 'After', 'rankmyseo' ) }
					</span>
					<ins>
						<span className="rms-sr-only">
							{ __( 'Added:', 'rankmyseo' ) }{ ' ' }
						</span>
						{ card.proposedText }
					</ins>
				</div>
			</div>

			<p className="rms-action-card-rationale">{ card.rationale }</p>

			<div className="rms-action-card-footer">
				{ card.status === 'pending' && (
					<>
						<Button
							variant="primary"
							className="rms-btn-primary"
							size="small"
							onClick={ () => onApprove?.( card.id ) }
						>
							{ __( 'Approve & Apply', 'rankmyseo' ) }
						</Button>
						<Button
							variant="tertiary"
							size="small"
							onClick={ () => onReject?.( card.id ) }
						>
							{ __( 'Reject', 'rankmyseo' ) }
						</Button>
					</>
				) }
				{ card.status === 'applied' && (
					<>
						<StatusBadge tone="up">
							{ __( 'Applied', 'rankmyseo' ) }
						</StatusBadge>
						<Button
							variant="secondary"
							size="small"
							onClick={ () => onUndo?.( card.id ) }
						>
							{ __( 'Undo', 'rankmyseo' ) }
						</Button>
					</>
				) }
				{ card.status === 'rejected' && (
					<StatusBadge tone="flat">
						{ __( 'Rejected', 'rankmyseo' ) }
					</StatusBadge>
				) }
				{ card.status === 'rolled_back' && (
					<StatusBadge tone="warn">
						{ __( 'Rolled back', 'rankmyseo' ) }
					</StatusBadge>
				) }
			</div>
		</div>
	);
}
