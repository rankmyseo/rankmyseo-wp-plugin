import { Button } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	disconnectCloud,
	fetchCloudInsights,
	fetchCloudStatus,
	pollCloudPairing,
	rotateCloudToken,
	startCloudPairing,
	syncCloudNow,
} from '../../shared/api';
import {
	EmptyState,
	ErrorBanner,
	SkeletonCard,
	StatusBadge,
} from '../../shared/components/StatePrimitives';
import type { CloudInsights, CloudPublicState } from '../../shared/types';
import { useAdmin } from '../context/AdminContext';

function errorMessage( error: unknown ): string {
	if (
		error &&
		typeof error === 'object' &&
		'message' in error &&
		typeof error.message === 'string' &&
		error.message.trim() !== ''
	) {
		return error.message;
	}
	return __( 'RankMySEO could not complete that request.', 'rankmyseo' );
}

function healthTone(
	health: CloudPublicState[ 'health' ]
): 'up' | 'warn' | 'down' | 'flat' {
	if ( health === 'connected' ) {
		return 'up';
	}
	if ( health === 'degraded' ) {
		return 'warn';
	}
	if ( health === 'reconnect' ) {
		return 'down';
	}
	return 'flat';
}

function healthLabel( health: CloudPublicState[ 'health' ] ): string {
	if ( health === 'connected' ) {
		return __( 'Connected', 'rankmyseo' );
	}
	if ( health === 'degraded' ) {
		return __( 'Degraded', 'rankmyseo' );
	}
	if ( health === 'reconnect' ) {
		return __( 'Reconnect needed', 'rankmyseo' );
	}
	return __( 'Not connected', 'rankmyseo' );
}

export function ConnectView(): JSX.Element {
	const { uiMode, navigateTo } = useAdmin();
	const [ state, setState ] = useState< CloudPublicState | null >( null );
	const [ insights, setInsights ] = useState< CloudInsights | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ confirmDisconnect, setConfirmDisconnect ] = useState( false );
	const pollTimer = useRef< ReturnType< typeof setInterval > | null >( null );

	const stopPolling = useCallback( () => {
		if ( pollTimer.current ) {
			clearInterval( pollTimer.current );
			pollTimer.current = null;
		}
	}, [] );

	const loadInsights = useCallback( async ( refresh = false ) => {
		const next = await fetchCloudInsights( refresh );
		setInsights( next );
	}, [] );

	const loadStatus = useCallback( async () => {
		const next = await fetchCloudStatus();
		setState( next );
		if ( next.connected ) {
			await loadInsights( false );
		} else {
			setInsights( null );
		}
	}, [ loadInsights ] );

	useEffect( () => {
		let active = true;
		setLoading( true );
		loadStatus()
			.catch( ( loadError: unknown ) => {
				if ( active ) {
					setError( errorMessage( loadError ) );
				}
			} )
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );
		return () => {
			active = false;
			stopPolling();
		};
	}, [ loadStatus, stopPolling ] );

	const poll = useCallback( async () => {
		setBusy( true );
		try {
			const result = await pollCloudPairing();
			setState( result.state );
			setError( null );
			if ( result.state.connected ) {
				stopPolling();
				await loadInsights( true );
			}
			if (
				result.pair_status === 'expired' ||
				result.pair_status === 'consumed'
			) {
				stopPolling();
			}
		} catch ( pollError ) {
			setError( errorMessage( pollError ) );
		} finally {
			setBusy( false );
		}
	}, [ loadInsights, stopPolling ] );

	useEffect( () => {
		if ( ! state?.pairing || state.connected ) {
			stopPolling();
			return undefined;
		}
		const expires = Date.parse( state.pairing.expires_at );
		if ( ! Number.isNaN( expires ) && expires <= Date.now() ) {
			stopPolling();
			return undefined;
		}
		pollTimer.current = setInterval( () => {
			void poll();
		}, 2500 );
		return () => {
			stopPolling();
		};
	}, [ poll, state?.connected, state?.pairing, stopPolling ] );

	const startPairing = async () => {
		setBusy( true );
		setError( null );
		try {
			const result = await startCloudPairing();
			setState( result.state );
			const pairUrl = result.state.pairing?.pair_url;
			if ( pairUrl ) {
				window.open( pairUrl, '_blank', 'noopener,noreferrer' );
			}
		} catch ( startError ) {
			setError( errorMessage( startError ) );
		} finally {
			setBusy( false );
		}
	};

	const syncNow = async () => {
		setBusy( true );
		setError( null );
		try {
			const result = await syncCloudNow();
			setState( result.state );
			await loadInsights( true );
		} catch ( syncError ) {
			setError( errorMessage( syncError ) );
		} finally {
			setBusy( false );
		}
	};

	const rotate = async () => {
		setBusy( true );
		setError( null );
		try {
			const result = await rotateCloudToken();
			setState( result.state );
		} catch ( rotateError ) {
			setError( errorMessage( rotateError ) );
		} finally {
			setBusy( false );
		}
	};

	const disconnect = async () => {
		setBusy( true );
		setError( null );
		try {
			const result = await disconnectCloud();
			setState( result.state );
			setInsights( null );
			setConfirmDisconnect( false );
			stopPolling();
		} catch ( disconnectError ) {
			setError( errorMessage( disconnectError ) );
		} finally {
			setBusy( false );
		}
	};

	if ( loading && ! state ) {
		return (
			<div className="rms-cloud">
				<SkeletonCard lines={ 4 } />
			</div>
		);
	}

	const connected = Boolean( state?.connected );
	const pairing = state?.pairing;

	return (
		<div className="rms-cloud rms-stack">
			<div className="rms-page-header">
				<div>
					<h1 className="rms-page-title">
						{ __( 'RankMySEO Cloud', 'rankmyseo' ) }
					</h1>
					<p className="rms-muted">
						{ __(
							'Connect this site when you want keyword rankings and suggested page edits. RankMySEO stays off until you do.',
							'rankmyseo'
						) }
					</p>
				</div>
				{ state && (
					<StatusBadge tone={ healthTone( state.health ) }>
						{ healthLabel( state.health ) }
					</StatusBadge>
				) }
			</div>

			{ error && (
				<ErrorBanner
					message={ error }
					onRetry={ () => {
						setError( null );
						void loadStatus().catch( ( loadError: unknown ) => {
							setError( errorMessage( loadError ) );
						} );
					} }
				/>
			) }

			{ state?.last_error && ! connected && (
				<div className="rms-error-banner" role="status">
					<div className="rms-error-banner-text">
						<strong>
							{ __( 'Connection needs attention', 'rankmyseo' ) }
						</strong>
						<span>{ state.last_error }</span>
					</div>
				</div>
			) }

			{ ! connected && ! pairing && (
				<section className="rms-card rms-stack">
					<h2 className="rms-section-title">
						{ __( 'Connect to RankMySEO', 'rankmyseo' ) }
					</h2>
					<p>
						{ __(
							'Connecting is optional. After you approve the code, RankMySEO receives this site URL, site name, WordPress version, and plugin version. Later syncs send public URLs, titles, descriptions, focus keywords, and on-page scores. Credentials stay encrypted on this site.',
							'rankmyseo'
						) }
					</p>
					<p className="rms-muted">
						<a
							href="https://rankmyseo.com/terms"
							target="_blank"
							rel="noreferrer noopener"
						>
							{ __( 'Terms of Service', 'rankmyseo' ) }
						</a>
						{ ' · ' }
						<a
							href="https://rankmyseo.com/privacy"
							target="_blank"
							rel="noreferrer noopener"
						>
							{ __( 'Privacy Policy', 'rankmyseo' ) }
						</a>
					</p>
					<div className="rms-cloud-actions">
						<Button
							variant="primary"
							className="rms-btn-primary"
							disabled={ busy }
							onClick={ () => {
								void startPairing();
							} }
						>
							{ __( 'Connect to RankMySEO', 'rankmyseo' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () => navigateTo( 'dashboard' ) }
						>
							{ __( 'Continue locally', 'rankmyseo' ) }
						</Button>
					</div>
					<p className="rms-muted">
						{ __(
							'Continue locally keeps every SEO tool on this site and does not contact RankMySEO.',
							'rankmyseo'
						) }
					</p>
				</section>
			) }

			{ ! connected && pairing && (
				<section className="rms-card rms-stack" aria-live="polite">
					<h2 className="rms-section-title">
						{ __( 'Approve this connection', 'rankmyseo' ) }
					</h2>
					<p>
						{ __(
							'Enter this code on RankMySEO, or open the approval page. This screen checks until the code is approved.',
							'rankmyseo'
						) }
					</p>
					<p className="rms-cloud-code">{ pairing.code }</p>
					<p>
						<a
							href={ pairing.pair_url }
							target="_blank"
							rel="noreferrer noopener"
						>
							{ __( 'Open the approval page', 'rankmyseo' ) }
						</a>
					</p>
					<div className="rms-cloud-actions">
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () => {
								void poll();
							} }
						>
							{ __( 'Check now', 'rankmyseo' ) }
						</Button>
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ () => navigateTo( 'dashboard' ) }
						>
							{ __( 'Continue locally', 'rankmyseo' ) }
						</Button>
					</div>
				</section>
			) }

			{ connected && state && (
				<>
					<section className="rms-card rms-stack">
						<h2 className="rms-section-title">
							{ state.site_name ||
								state.site_host ||
								__( 'This site', 'rankmyseo' ) }
						</h2>
						<p className="rms-muted">
							{ state.plan_label
								? state.plan_label
								: __( 'RankMySEO plan', 'rankmyseo' ) }
							{ state.domain ? ` · ${ state.domain }` : '' }
						</p>
						{ uiMode === 'advanced' && (
							<p className="rms-muted rms-mono">
								{ state.project_id
									? `${ __( 'Site ID', 'rankmyseo' ) }: ${
											state.project_id
									  }`
									: __( 'Site ID pending', 'rankmyseo' ) }
								{ state.token_prefix
									? ` · ${ state.token_prefix }`
									: '' }
							</p>
						) }
						<p className="rms-muted">
							{ state.last_sync_at
								? `${ __( 'Last sync', 'rankmyseo' ) }: `
								: __( 'No sync yet', 'rankmyseo' ) }
							{ state.last_sync_at && (
								<time dateTime={ state.last_sync_at }>
									{ state.last_sync_at }
								</time>
							) }
						</p>
						<div className="rms-cloud-actions">
							<Button
								variant="primary"
								className="rms-btn-primary"
								disabled={ busy }
								onClick={ () => {
									void syncNow();
								} }
							>
								{ __( 'Sync now', 'rankmyseo' ) }
							</Button>
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => {
									void loadInsights( true ).catch(
										( refreshError: unknown ) => {
											setError(
												errorMessage( refreshError )
											);
										}
									);
								} }
							>
								{ __( 'Refresh insights', 'rankmyseo' ) }
							</Button>
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => {
									void rotate();
								} }
							>
								{ __( 'Rotate token', 'rankmyseo' ) }
							</Button>
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => setConfirmDisconnect( true ) }
							>
								{ __( 'Disconnect', 'rankmyseo' ) }
							</Button>
						</div>
					</section>

					{ insights && <InsightsCards insights={ insights } /> }
				</>
			) }

			{ confirmDisconnect && (
				<div className="rms-cloud-dialog-backdrop">
					<div
						className="rms-cloud-dialog"
						role="alertdialog"
						aria-modal="true"
						aria-labelledby="rms-disconnect-title"
					>
						<h2
							id="rms-disconnect-title"
							className="rms-section-title"
						>
							{ __( 'Disconnect this site?', 'rankmyseo' ) }
						</h2>
						<p>
							{ __(
								'RankMySEO will stop receiving updates from this site. The saved connection is removed from WordPress. You can connect again later.',
								'rankmyseo'
							) }
						</p>
						<div className="rms-cloud-actions">
							<Button
								variant="primary"
								className="rms-btn-primary"
								disabled={ busy }
								onClick={ () => {
									void disconnect();
								} }
							>
								{ __( 'Disconnect', 'rankmyseo' ) }
							</Button>
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => setConfirmDisconnect( false ) }
							>
								{ __( 'Cancel', 'rankmyseo' ) }
							</Button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}

function InsightsCards( {
	insights,
}: {
	insights: CloudInsights;
} ): JSX.Element {
	const keywords = insights.keywords.rows.slice( 0, 5 );
	const issues = insights.issues.slice( 0, 5 );
	const pages = insights.page_updates.slice( 0, 5 );
	const empty =
		keywords.length === 0 && issues.length === 0 && pages.length === 0;

	return (
		<div className="rms-stack">
			{ insights.stale && insights.message && (
				<div className="rms-error-banner" role="status">
					<div className="rms-error-banner-text">
						<strong>
							{ __( 'Showing saved insights', 'rankmyseo' ) }
						</strong>
						<span>{ insights.message }</span>
					</div>
				</div>
			) }
			{ empty ? (
				<EmptyState
					title={ __( 'No insights yet', 'rankmyseo' ) }
					description={ __(
						'Sync this site to fill keyword rankings, next actions, and page edits.',
						'rankmyseo'
					) }
				/>
			) : (
				<div className="rms-cloud-grid">
					<section
						className="rms-card rms-stack"
						aria-label={ __( 'Keywords', 'rankmyseo' ) }
					>
						<h2 className="rms-section-title">
							{ __( 'Keywords', 'rankmyseo' ) }
						</h2>
						<p className="rms-muted rms-tabular">
							{ `${ insights.keywords.tracked } ${ __(
								'tracked',
								'rankmyseo'
							) }` }
						</p>
						{ keywords.length === 0 ? (
							<p className="rms-muted">
								{ __(
									'No tracked keywords yet.',
									'rankmyseo'
								) }
							</p>
						) : (
							<ul className="rms-cloud-list">
								{ keywords.map( ( row ) => (
									<li key={ `${ row.keyword }-${ row.url }` }>
										<span>{ row.keyword }</span>
										<strong className="rms-tabular">
											{ row.position === null
												? '—'
												: String( row.position ) }
										</strong>
									</li>
								) ) }
							</ul>
						) }
					</section>
					<section
						className="rms-card rms-stack"
						aria-label={ __( 'Next actions', 'rankmyseo' ) }
					>
						<h2 className="rms-section-title">
							{ __( 'Next actions', 'rankmyseo' ) }
						</h2>
						{ issues.length === 0 ? (
							<p className="rms-muted">
								{ __( 'No next actions yet.', 'rankmyseo' ) }
							</p>
						) : (
							<ul className="rms-cloud-list">
								{ issues.map( ( issue ) => (
									<li key={ issue.id }>
										<span>
											{ issue.category || issue.id }
										</span>
										<strong className="rms-tabular">
											{ issue.count }
										</strong>
									</li>
								) ) }
							</ul>
						) }
					</section>
					<section
						className="rms-card rms-stack"
						aria-label={ __( 'Page edits', 'rankmyseo' ) }
					>
						<h2 className="rms-section-title">
							{ __( 'Page edits', 'rankmyseo' ) }
						</h2>
						{ pages.length === 0 ? (
							<p className="rms-muted">
								{ __( 'No page edits yet.', 'rankmyseo' ) }
							</p>
						) : (
							<ul className="rms-cloud-list">
								{ pages.map( ( page ) => (
									<li
										key={ `${ page.url }-${ page.action }` }
									>
										<span>
											{ page.summary || page.action }
										</span>
									</li>
								) ) }
							</ul>
						) }
					</section>
				</div>
			) }
		</div>
	);
}
