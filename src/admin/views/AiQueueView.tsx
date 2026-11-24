import { Button, CheckboxControl, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	fetchAiActions,
	fetchAiPolicy,
	fetchAuditLog,
	approveAiAction,
	rejectAiAction,
	rollbackAiAction,
	saveAiPolicy,
	syncAiActions,
} from '../../shared/api';
import { ErrorBanner } from '../../shared/components/StatePrimitives';
import type {
	AiAction,
	AiPolicy,
	AuditEntry,
	UiMode,
} from '../../shared/types';
import { InlineActionCard } from '../components/InlineActionCard';
import { useAdmin } from '../context/AdminContext';

const POLICY_TYPES = [
	'update_title',
	'update_meta_description',
	'image_alt',
	'update_canonical',
	'update_schema',
	'add_redirect',
	'add_internal_link',
	'create_draft',
] as const;

export const DEFAULT_POLICY: AiPolicy = {
	enabled: false,
	actionTypes: [],
	postTypes: [],
	excludedUrls: [ '/' ],
	dailyCap: 10,
	articles: 'draft',
};

export interface AiQueueScreenProps {
	uiMode: UiMode;
	canApprove: boolean;
	connected: boolean;
	actions: AiAction[];
	policy: AiPolicy;
	audit: AuditEntry[];
	statusFilter: string;
	typeFilter: string;
	showJson: boolean;
	notice?: string;
	onStatusFilter: ( value: string ) => void;
	onTypeFilter: ( value: string ) => void;
	onToggleJson: () => void;
	onApprove: ( id: number, after?: Record< string, unknown > ) => void;
	onReject: ( id: number ) => void;
	onRollback: ( id: number ) => void;
	onPolicyChange: ( policy: AiPolicy ) => void;
	onSavePolicy: () => void;
	onSync: () => void;
}

function visibleActions(
	actions: AiAction[],
	uiMode: UiMode,
	statusFilter: string,
	typeFilter: string
): AiAction[] {
	return actions.filter( ( action ) => {
		if (
			uiMode === 'simple' &&
			action.status !== 'pending' &&
			action.status !== 'stale'
		) {
			return action.status === 'applied';
		}
		if ( statusFilter && action.status !== statusFilter ) {
			return false;
		}
		if ( typeFilter && action.action_type !== typeFilter ) {
			return false;
		}
		return true;
	} );
}

export function AiQueueScreen( {
	uiMode,
	canApprove,
	connected,
	actions,
	policy,
	audit,
	statusFilter,
	typeFilter,
	showJson,
	notice,
	onStatusFilter,
	onTypeFilter,
	onToggleJson,
	onApprove,
	onReject,
	onRollback,
	onPolicyChange,
	onSavePolicy,
	onSync,
}: AiQueueScreenProps ): JSX.Element {
	const shown = visibleActions( actions, uiMode, statusFilter, typeFilter );
	const title =
		uiMode === 'simple'
			? __( 'Suggested changes', 'rankmyseo' )
			: __( 'AI approval queue', 'rankmyseo' );

	return (
		<div className="rms-stack rms-ai-queue">
			<div className="rms-card">
				<div className="rms-card-header">
					<h2>{ title }</h2>
					{ connected && (
						<Button variant="secondary" onClick={ onSync }>
							{ __( 'Refresh suggestions', 'rankmyseo' ) }
						</Button>
					) }
				</div>
				<p className="rms-muted">
					{ uiMode === 'simple'
						? __(
								'Nothing changes until you approve it. Undo puts the previous version back.',
								'rankmyseo'
						  )
						: __(
								'Filter the queue, inspect the full change, and choose which kinds of edits may apply on their own.',
								'rankmyseo'
						  ) }
				</p>
				{ notice && <p role="status">{ notice }</p> }
			</div>

			{ uiMode === 'advanced' && (
				<div className="rms-card rms-filter-row">
					<label htmlFor="rms-ai-status-filter">
						<span>{ __( 'Status', 'rankmyseo' ) }</span>
						<select
							id="rms-ai-status-filter"
							value={ statusFilter }
							onChange={ ( event ) =>
								onStatusFilter( event.target.value )
							}
						>
							<option value="">
								{ __( 'All statuses', 'rankmyseo' ) }
							</option>
							<option value="pending">
								{ __( 'Pending', 'rankmyseo' ) }
							</option>
							<option value="applied">
								{ __( 'Applied', 'rankmyseo' ) }
							</option>
							<option value="rejected">
								{ __( 'Rejected', 'rankmyseo' ) }
							</option>
							<option value="rolled_back">
								{ __( 'Rolled back', 'rankmyseo' ) }
							</option>
						</select>
					</label>
					<label htmlFor="rms-ai-type-filter">
						<span>{ __( 'Type', 'rankmyseo' ) }</span>
						<select
							id="rms-ai-type-filter"
							value={ typeFilter }
							onChange={ ( event ) =>
								onTypeFilter( event.target.value )
							}
						>
							<option value="">
								{ __( 'All types', 'rankmyseo' ) }
							</option>
							{ POLICY_TYPES.map( ( type ) => (
								<option key={ type } value={ type }>
									{ type }
								</option>
							) ) }
							<option value="content_patch">content_patch</option>
						</select>
					</label>
					<Button variant="secondary" onClick={ onToggleJson }>
						{ showJson
							? __( 'Hide full change', 'rankmyseo' )
							: __( 'Show full change', 'rankmyseo' ) }
					</Button>
				</div>
			) }

			{ shown.length === 0 && (
				<div className="rms-card">
					<p>
						{ __(
							'No suggestions are waiting. Connect RankMySEO to pull new ones.',
							'rankmyseo'
						) }
					</p>
				</div>
			) }

			{ shown.map( ( action ) => (
				<InlineActionCard
					key={ action.id }
					action={ action }
					canApprove={ canApprove }
					showJson={ uiMode === 'advanced' && showJson }
					onApprove={ onApprove }
					onReject={ onReject }
					onRollback={ onRollback }
				/>
			) ) }

			{ uiMode === 'advanced' && canApprove && (
				<section
					className="rms-card"
					aria-label={ __( 'Auto-apply policy', 'rankmyseo' ) }
				>
					<h2>{ __( 'Auto-apply', 'rankmyseo' ) }</h2>
					<p className="rms-muted">
						{ __(
							'Off until you turn it on. Page content always waits for approval, and new articles stay drafts.',
							'rankmyseo'
						) }
					</p>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __(
							'Allow selected changes to apply on their own',
							'rankmyseo'
						) }
						checked={ policy.enabled }
						onChange={ ( enabled: boolean ) =>
							onPolicyChange( { ...policy, enabled } )
						}
					/>
					<ul className="rms-policy-types">
						{ POLICY_TYPES.map( ( type ) => (
							<li key={ type }>
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ type }
									checked={ policy.actionTypes.includes(
										type
									) }
									onChange={ ( checked: boolean ) => {
										const actionTypes = checked
											? [ ...policy.actionTypes, type ]
											: policy.actionTypes.filter(
													( item ) => item !== type
											  );
										onPolicyChange( {
											...policy,
											actionTypes,
										} );
									} }
								/>
							</li>
						) ) }
						<li>
							<span className="rms-lock-badge">
								{ __(
									'content_patch · Approval only',
									'rankmyseo'
								) }
							</span>
						</li>
					</ul>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Post types', 'rankmyseo' ) }
						help={ __(
							'Comma-separated. Leave this empty and nothing applies on its own.',
							'rankmyseo'
						) }
						value={ policy.postTypes.join( ', ' ) }
						onChange={ ( value: string ) =>
							onPolicyChange( {
								...policy,
								postTypes: value
									.split( ',' )
									.map( ( item ) => item.trim() )
									.filter( Boolean ),
							} )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Daily cap', 'rankmyseo' ) }
						type="number"
						value={ String( policy.dailyCap ) }
						onChange={ ( value: string ) =>
							onPolicyChange( {
								...policy,
								dailyCap: Number( value ) || 0,
							} )
						}
					/>
					<Button
						variant="primary"
						className="rms-btn-primary"
						onClick={ onSavePolicy }
					>
						{ __( 'Save auto-apply settings', 'rankmyseo' ) }
					</Button>
				</section>
			) }

			{ uiMode === 'advanced' && (
				<section
					className="rms-card"
					aria-label={ __( 'Audit log', 'rankmyseo' ) }
				>
					<h2>{ __( 'Audit log', 'rankmyseo' ) }</h2>
					<table className="rms-audit-table">
						<thead>
							<tr>
								<th scope="col">
									{ __( 'When', 'rankmyseo' ) }
								</th>
								<th scope="col">
									{ __( 'Event', 'rankmyseo' ) }
								</th>
								<th scope="col">
									{ __( 'Who', 'rankmyseo' ) }
								</th>
								<th scope="col">
									{ __( 'Summary', 'rankmyseo' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ audit.length === 0 && (
								<tr>
									<td colSpan={ 4 }>
										{ __(
											'No audit entries yet.',
											'rankmyseo'
										) }
									</td>
								</tr>
							) }
							{ audit.map( ( entry ) => (
								<tr key={ entry.id }>
									<td>{ entry.created_at }</td>
									<td>{ entry.event_type }</td>
									<td>{ entry.actor_type }</td>
									<td>{ entry.summary }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</section>
			) }
		</div>
	);
}

export function AiQueueView(): JSX.Element {
	const { uiMode, status } = useAdmin();
	const canApprove = window.rankmyseoAdmin?.canApproveAi ?? false;
	const [ actions, setActions ] = useState< AiAction[] >( [] );
	const [ policy, setPolicy ] = useState< AiPolicy >( DEFAULT_POLICY );
	const [ audit, setAudit ] = useState< AuditEntry[] >( [] );
	const [ statusFilter, setStatusFilter ] = useState( '' );
	const [ typeFilter, setTypeFilter ] = useState( '' );
	const [ showJson, setShowJson ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		const load = async (): Promise< void > => {
			try {
				const loaded = await fetchAiActions();
				const entries = await fetchAuditLog();
				let nextPolicy = DEFAULT_POLICY;
				if ( canApprove ) {
					const policyResponse = await fetchAiPolicy();
					nextPolicy = policyResponse.policy;
				}
				if ( ! cancelled ) {
					setActions( loaded.actions );
					setAudit( entries.entries );
					setPolicy( nextPolicy );
				}
			} catch ( caught ) {
				if ( ! cancelled ) {
					setError(
						caught instanceof Error
							? caught.message
							: __(
									'Suggestions could not be loaded.',
									'rankmyseo'
							  )
					);
				}
			}
		};
		void load();
		return () => {
			cancelled = true;
		};
	}, [ canApprove ] );

	const replaceAction = ( next: AiAction ): void => {
		setActions( ( current ) =>
			current.map( ( item ) => ( item.id === next.id ? next : item ) )
		);
	};

	return (
		<>
			{ error && (
				<ErrorBanner
					message={ error }
					onRetry={ () => setError( '' ) }
				/>
			) }
			<AiQueueScreen
				uiMode={ uiMode }
				canApprove={ canApprove }
				connected={ status?.cloud.connected ?? false }
				actions={ actions }
				policy={ policy }
				audit={ audit }
				statusFilter={ statusFilter }
				typeFilter={ typeFilter }
				showJson={ showJson }
				notice={ notice }
				onStatusFilter={ setStatusFilter }
				onTypeFilter={ setTypeFilter }
				onToggleJson={ () => setShowJson( ( open ) => ! open ) }
				onApprove={ ( id, after ) => {
					void approveAiAction( id, after )
						.then( ( result ) => {
							replaceAction( result.action );
							setNotice( result.message );
						} )
						.catch( ( caught: unknown ) => {
							setError(
								caught instanceof Error
									? caught.message
									: __(
											'The change could not be applied.',
											'rankmyseo'
									  )
							);
						} );
				} }
				onReject={ ( id ) => {
					void rejectAiAction( id )
						.then( ( result ) => {
							replaceAction( result.action );
							setNotice( result.message );
						} )
						.catch( () => {
							setError(
								__(
									'The suggestion could not be skipped.',
									'rankmyseo'
								)
							);
						} );
				} }
				onRollback={ ( id ) => {
					void rollbackAiAction( id )
						.then( ( result ) => {
							replaceAction( result.action );
							setNotice( result.message );
						} )
						.catch( () => {
							setError(
								__(
									'The previous version could not be restored.',
									'rankmyseo'
								)
							);
						} );
				} }
				onPolicyChange={ setPolicy }
				onSavePolicy={ () => {
					void saveAiPolicy( policy )
						.then( ( result ) => {
							setPolicy( result.policy );
							setNotice(
								__( 'Auto-apply settings saved.', 'rankmyseo' )
							);
						} )
						.catch( () => {
							setError(
								__(
									'Auto-apply settings could not be saved.',
									'rankmyseo'
								)
							);
						} );
				} }
				onSync={ () => {
					void syncAiActions()
						.then( ( result ) => {
							setActions( result.actions );
							setNotice( result.message );
						} )
						.catch( () => {
							setError(
								__(
									'Suggestions could not be loaded.',
									'rankmyseo'
								)
							);
						} );
				} }
			/>
		</>
	);
}
