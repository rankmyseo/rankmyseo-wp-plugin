import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';

export function SkeletonCard( { lines = 3 }: { lines?: number } ): JSX.Element {
	return (
		<div className="rms-card rms-skeleton-card" aria-busy="true">
			<div className="rms-skeleton-line rms-skeleton-title" />
			{ Array.from( { length: lines } ).map( ( _, idx ) => (
				<div
					key={ idx }
					className="rms-skeleton-line"
					style={ { width: idx === lines - 1 ? '62%' : '100%' } }
				/>
			) ) }
		</div>
	);
}

interface EmptyStateProps {
	title: string;
	description: string;
	actionLabel?: string;
	onAction?: () => void;
	secondaryAction?: ReactNode;
}

export function EmptyState( {
	title,
	description,
	actionLabel,
	onAction,
	secondaryAction,
}: EmptyStateProps ): JSX.Element {
	return (
		<div className="rms-card rms-empty-state">
			<div className="rms-empty-icon" aria-hidden="true">
				<span className="rms-empty-bar rms-empty-bar-1" />
				<span className="rms-empty-bar rms-empty-bar-2" />
				<span className="rms-empty-bar rms-empty-bar-3" />
			</div>
			<h3 className="rms-empty-title">{ title }</h3>
			<p className="rms-empty-description">{ description }</p>
			<div className="rms-empty-actions">
				{ actionLabel && onAction && (
					<Button
						variant="primary"
						className="rms-btn-primary"
						onClick={ onAction }
					>
						{ actionLabel }
					</Button>
				) }
				{ secondaryAction }
			</div>
		</div>
	);
}

interface ErrorBannerProps {
	message: string;
	onRetry?: () => void;
}

export function ErrorBanner( {
	message,
	onRetry,
}: ErrorBannerProps ): JSX.Element {
	return (
		<div className="rms-error-banner" role="alert">
			<div className="rms-error-banner-text">
				<strong>{ __( 'Something went wrong', 'rankmyseo' ) }</strong>
				<span>{ message }</span>
			</div>
			{ onRetry && (
				<Button variant="secondary" onClick={ onRetry }>
					{ __( 'Try again', 'rankmyseo' ) }
				</Button>
			) }
		</div>
	);
}

export type BadgeTone = 'up' | 'warn' | 'down' | 'flat' | 'accent';

export function StatusBadge( {
	tone = 'flat',
	children,
}: {
	tone?: BadgeTone;
	children: ReactNode;
} ): JSX.Element {
	return (
		<span className={ `rms-badge rms-badge-${ tone }` }>{ children }</span>
	);
}
