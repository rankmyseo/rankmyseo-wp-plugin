import { Button } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent, KeyboardEvent as ReactKeyboardEvent } from 'react';
import {
	approveAiAction,
	fetchCloudChatHistory,
	fetchCloudChatTurn,
	rejectAiAction,
	rollbackAiAction,
	sendCloudChat,
	sendLocalChat,
} from '../../shared/api';
import { StatusBadge } from '../../shared/components/StatePrimitives';
import type {
	AiAction,
	ChatActionCardData,
	ChatMessage,
	LocalChatHandoff,
	LocalChatItem,
	LocalChatResponse,
	NavPageId,
} from '../../shared/types';
import { useAdmin } from '../context/AdminContext';
import { InlineActionCard } from './InlineActionCard';
import {
	buildLocalAssistantReply,
	getStarterPrompts,
} from '../utils/localAssistant';
import {
	InlineActionCardSlot,
	type InlineActionCardSlotProps,
} from './InlineActionCardSlot';

export interface AskAiDrawerProps {
	onActionApprove?: InlineActionCardSlotProps[ 'onApprove' ];
	onActionReject?: InlineActionCardSlotProps[ 'onReject' ];
	onActionUndo?: InlineActionCardSlotProps[ 'onUndo' ];
}

const SCREENS: NavPageId[] = [
	'dashboard',
	'meta',
	'missing',
	'schema',
	'store',
	'sitemaps',
	'redirects',
	'import',
	'cloud',
	'ai-queue',
	'settings',
];

function isNavPage( value: string | undefined ): value is NavPageId {
	return Boolean( value && SCREENS.includes( value as NavPageId ) );
}

function isSafeLink( url: string ): boolean {
	return (
		url.startsWith( 'https://' ) ||
		url.startsWith( 'http://' ) ||
		url.startsWith( '/' )
	);
}

function readActionCards( value: unknown ): ChatActionCardData[] | undefined {
	if ( ! Array.isArray( value ) ) {
		return undefined;
	}

	return value.filter( ( card ): card is ChatActionCardData => {
		return Boolean(
			card && typeof card === 'object' && 'id' in card && 'title' in card
		);
	} );
}

function mapLocalReply( response: LocalChatResponse ): ChatMessage {
	const handoff: LocalChatHandoff | null = response.cloud_handoff
		? {
				title: response.cloud_handoff.title || '',
				message: response.cloud_handoff.message || '',
				page: isNavPage( response.cloud_handoff.page )
					? response.cloud_handoff.page
					: 'cloud',
		  }
		: null;

	return {
		id: `rms-assistant-${ Date.now() }-${ Math.random()
			.toString( 36 )
			.slice( 2, 7 ) }`,
		role: 'assistant',
		content:
			response.explanation ||
			__(
				'I checked this site locally and have nothing new to add.',
				'rankmyseo'
			),
		createdAt: new Date().toISOString(),
		items: Array.isArray( response.items ) ? response.items : [],
		followUps: Array.isArray( response.follow_ups )
			? response.follow_ups
			: [],
		cloudHandoff: handoff,
		operatorEnabled: response.operator_slot?.enabled === true,
		actionCards: readActionCards( response.action_cards ),
	};
}

// Reserved mount for a later connected assistant.
// Offline replies keep this empty so the local inspector stays self-contained.
export function OperatorChatSlot( {
	enabled,
}: {
	enabled: boolean;
} ): JSX.Element | null {
	if ( ! enabled ) {
		return null;
	}

	return (
		<div className="rms-operator-slot" data-slot="operator-chat">
			{ __(
				'Connected assistant replies will appear here.',
				'rankmyseo'
			) }
		</div>
	);
}

function ChatItems( {
	items,
}: {
	items: LocalChatItem[];
} ): JSX.Element | null {
	if ( items.length === 0 ) {
		return null;
	}

	return (
		<ul className="rms-chat-items">
			{ items.map( ( item ) => (
				<li key={ `${ item.type }-${ item.id }-${ item.slug }` }>
					{ item.edit_link && isSafeLink( item.edit_link ) ? (
						<a href={ item.edit_link }>
							{ item.title || __( 'Edit', 'rankmyseo' ) }
						</a>
					) : (
						<strong>
							{ item.title || __( 'Untitled', 'rankmyseo' ) }
						</strong>
					) }
					{ item.tip && (
						<p className="rms-chat-item-tip">{ item.tip }</p>
					) }
				</li>
			) ) }
		</ul>
	);
}

export function AskAiDrawer( {
	onActionApprove,
	onActionReject,
	onActionUndo,
}: AskAiDrawerProps ): JSX.Element | null {
	const {
		isChatOpen,
		closeChat,
		status,
		settings,
		uiMode,
		activePage,
		navigateTo,
		setUiMode,
		pendingChatPrompt,
		consumePendingChatPrompt,
	} = useAdmin();

	const [ messages, setMessages ] = useState< ChatMessage[] >( () => [
		{
			id: 'rms-welcome',
			role: 'assistant',
			content: __(
				'Hi! Ask a question about your site SEO or pick a starter prompt below. Right now I run 100% locally using your live WordPress site status with zero external calls.',
				'rankmyseo'
			),
			createdAt: new Date().toISOString(),
		},
	] );
	const [ draft, setDraft ] = useState< string >( '' );
	const [ busy, setBusy ] = useState( false );
	const inputRef = useRef< HTMLTextAreaElement | null >( null );
	const endRef = useRef< HTMLDivElement | null >( null );
	const isConnected = status?.cloud.connected ?? false;
	const canApprove = window.rankmyseoAdmin?.canApproveAi ?? false;

	const rememberActions = useCallback(
		( id: number, next: AiAction ): void => {
			setMessages( ( prev ) =>
				prev.map( ( message ) => ( {
					...message,
					aiActions: message.aiActions?.map( ( action ) =>
						action.id === id ? next : action
					),
				} ) )
			);
		},
		[]
	);

	const submitPrompt = useCallback(
		async ( rawPrompt: string ): Promise< void > => {
			const clean = rawPrompt.trim();
			if ( ! clean || busy ) {
				return;
			}

			const userMsg: ChatMessage = {
				id: `rms-user-${ Date.now() }`,
				role: 'user',
				content: clean,
				createdAt: new Date().toISOString(),
			};

			setDraft( '' );
			setBusy( true );

			if ( isConnected ) {
				setMessages( ( prev ) => [ ...prev, userMsg ] );
				try {
					const started = await sendCloudChat( clean, {
						url: status?.site.url,
						title: settings?.site_name,
					} );
					let turn = started;
					if ( started.state === 'running' && started.turn_id ) {
						turn = await fetchCloudChatTurn( started.turn_id );
					}
					const replyMsg: ChatMessage = {
						id: `rms-cloud-${ turn.turn_id || Date.now() }`,
						role: 'assistant',
						content:
							turn.reply ||
							turn.message ||
							__(
								'RankMySEO is still working on that. Open the queue in a moment.',
								'rankmyseo'
							),
						createdAt: new Date().toISOString(),
						aiActions: turn.actions ?? [],
					};
					setMessages( ( prev ) => [ ...prev, replyMsg ] );
				} catch ( caught ) {
					const replyMsg: ChatMessage = {
						id: `rms-cloud-error-${ Date.now() }`,
						role: 'assistant',
						content:
							caught instanceof Error
								? caught.message
								: __(
										'RankMySEO could not answer just now.',
										'rankmyseo'
								  ),
						createdAt: new Date().toISOString(),
					};
					setMessages( ( prev ) => [ ...prev, replyMsg ] );
				}
				setBusy( false );
				return;
			}

			let reply: ChatMessage;
			try {
				const response = await sendLocalChat( {
					message: clean,
					screen: activePage,
				} );
				reply = mapLocalReply( response );
			} catch {
				reply = buildLocalAssistantReply(
					clean,
					status,
					settings,
					uiMode
				);
			}

			setMessages( ( prev ) => [ ...prev, userMsg, reply ] );
			setBusy( false );
		},
		[ activePage, busy, isConnected, settings, status, uiMode ]
	);

	useEffect( () => {
		if ( ! isChatOpen || ! isConnected ) {
			return undefined;
		}
		let cancelled = false;
		void fetchCloudChatHistory()
			.then( ( history ) => {
				if ( cancelled || ! history.messages?.length ) {
					return;
				}
				setMessages(
					history.messages.map( ( message ) => ( {
						id: message.id || `rms-history-${ message.role }`,
						role: message.role,
						content: message.content,
						createdAt: new Date().toISOString(),
					} ) )
				);
			} )
			.catch( () => undefined );
		return () => {
			cancelled = true;
		};
	}, [ isChatOpen, isConnected ] );

	useEffect( () => {
		if ( isChatOpen && pendingChatPrompt ) {
			void submitPrompt( pendingChatPrompt );
			consumePendingChatPrompt();
		}
	}, [
		consumePendingChatPrompt,
		isChatOpen,
		pendingChatPrompt,
		submitPrompt,
	] );

	useEffect( () => {
		if ( ! isChatOpen ) {
			return undefined;
		}

		const active = inputRef.current?.ownerDocument.activeElement;
		const returnFocus = active instanceof HTMLElement ? active : null;
		inputRef.current?.focus();

		const handleKeyDown = ( event: KeyboardEvent ): void => {
			if ( event.key === 'Escape' ) {
				closeChat();
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => {
			document.removeEventListener( 'keydown', handleKeyDown );
			returnFocus?.focus();
		};
	}, [ closeChat, isChatOpen ] );

	useEffect( () => {
		if ( isChatOpen && endRef.current?.scrollIntoView ) {
			endRef.current.scrollIntoView( { behavior: 'smooth' } );
		}
	}, [ isChatOpen, messages.length ] );

	if ( ! isChatOpen ) {
		return null;
	}

	const starters = getStarterPrompts();

	const handleSubmit = ( event: FormEvent ): void => {
		event.preventDefault();
		void submitPrompt( draft );
	};

	const handleTextareaKeyDown = (
		event: ReactKeyboardEvent< HTMLTextAreaElement >
	): void => {
		if ( event.key === 'Enter' && ! event.shiftKey ) {
			event.preventDefault();
			void submitPrompt( draft );
		}
	};

	return (
		<div className="rms-drawer-backdrop" role="presentation">
			<div
				className="rms-drawer-scrim"
				aria-hidden="true"
				onClick={ closeChat }
			/>
			<aside
				id="rms-ai-chat-drawer"
				className="rms-chat-drawer"
				role="dialog"
				aria-modal="false"
				aria-label={ __( 'RankMySEO AI Assistant', 'rankmyseo' ) }
			>
				<div className="rms-chat-header">
					<div className="rms-chat-header-title">
						<h2>{ __( 'AI Assistant', 'rankmyseo' ) }</h2>
						<StatusBadge tone={ isConnected ? 'up' : 'flat' }>
							{ isConnected
								? __( 'Cloud AI', 'rankmyseo' )
								: __( 'Local helper', 'rankmyseo' ) }
						</StatusBadge>
					</div>
					<Button
						variant="tertiary"
						className="rms-chat-close"
						aria-label={ __( 'Close AI Assistant', 'rankmyseo' ) }
						onClick={ closeChat }
					>
						{ __( 'Close', 'rankmyseo' ) }
					</Button>
				</div>

				<div
					className="rms-chat-messages"
					role="log"
					aria-live="polite"
					aria-relevant="additions"
				>
					{ messages.map( ( message ) => (
						<div
							key={ message.id }
							className={ `rms-chat-bubble rms-chat-bubble-${ message.role }` }
						>
							<p className="rms-chat-bubble-text">
								{ message.content }
							</p>

							{ message.role === 'assistant' && (
								<OperatorChatSlot
									enabled={ message.operatorEnabled === true }
								/>
							) }

							{ message.items && (
								<ChatItems items={ message.items } />
							) }

							{ message.cloudHandoff && (
								<div className="rms-chat-handoff">
									<p>{ message.cloudHandoff.message }</p>
									<button
										type="button"
										className="rms-chip-btn"
										onClick={ () =>
											navigateTo(
												message.cloudHandoff?.page &&
													isNavPage(
														message.cloudHandoff
															.page
													)
													? message.cloudHandoff.page
													: 'cloud'
											)
										}
									>
										{ message.cloudHandoff.title ||
											__(
												'Review Cloud Setup',
												'rankmyseo'
											) }
									</button>
								</div>
							) }

							{ message.followUps &&
								message.followUps.length > 0 && (
									<div className="rms-chat-quick-links">
										{ message.followUps.map( ( follow ) => (
											<button
												key={ `${ message.id }-${ follow.id }` }
												type="button"
												className="rms-chip-btn"
												onClick={ () => {
													void submitPrompt(
														follow.prompt
													);
												} }
											>
												{ follow.label }
											</button>
										) ) }
									</div>
								) }

							{ message.quickLinks &&
								message.quickLinks.length > 0 && (
									<div className="rms-chat-quick-links">
										{ message.quickLinks.map( ( link ) => (
											<button
												key={ `${ message.id }-${ link.pageId }-${ link.label }` }
												type="button"
												className="rms-chip-btn"
												onClick={ () => {
													if ( link.switchMode ) {
														void setUiMode(
															link.switchMode
														);
													}
													navigateTo( link.pageId );
												} }
											>
												{ link.label }
											</button>
										) ) }
									</div>
								) }

							{ message.aiActions &&
								message.aiActions.length > 0 && (
									<div className="rms-chat-action-slots">
										{ message.aiActions.map( ( action ) => (
											<InlineActionCard
												key={ action.id }
												action={ action }
												canApprove={ canApprove }
												onApprove={ ( id, after ) => {
													void approveAiAction(
														id,
														after
													).then( ( result ) =>
														rememberActions(
															id,
															result.action
														)
													);
												} }
												onReject={ ( id ) => {
													void rejectAiAction(
														id
													).then( ( result ) =>
														rememberActions(
															id,
															result.action
														)
													);
												} }
												onRollback={ ( id ) => {
													void rollbackAiAction(
														id
													).then( ( result ) =>
														rememberActions(
															id,
															result.action
														)
													);
												} }
											/>
										) ) }
									</div>
								) }

							{ message.actionCards &&
								message.actionCards.length > 0 && (
									<div className="rms-chat-action-slots">
										{ message.actionCards.map( ( card ) => (
											<InlineActionCardSlot
												key={ card.id }
												card={ card }
												onApprove={ onActionApprove }
												onReject={ onActionReject }
												onUndo={ onActionUndo }
											/>
										) ) }
									</div>
								) }
						</div>
					) ) }
					{ busy && (
						<p className="rms-chat-status" role="status">
							{ isConnected
								? __( 'Asking RankMySEO…', 'rankmyseo' )
								: __(
										'Checking this site locally…',
										'rankmyseo'
								  ) }
						</p>
					) }
					<div ref={ endRef } />
				</div>

				<div className="rms-chat-starters">
					<span className="rms-chat-starters-label">
						{ __( 'Suggested questions', 'rankmyseo' ) }
					</span>
					<div className="rms-chat-starters-grid">
						{ starters.map( ( starter ) => (
							<button
								key={ starter.id }
								type="button"
								className="rms-starter-chip"
								onClick={ () => {
									void submitPrompt( starter.prompt );
								} }
							>
								{ starter.label }
							</button>
						) ) }
					</div>
				</div>

				<form className="rms-chat-composer" onSubmit={ handleSubmit }>
					<label htmlFor="rms-chat-input" className="rms-sr-only">
						{ __(
							'Ask a question about your site SEO',
							'rankmyseo'
						) }
					</label>
					<textarea
						id="rms-chat-input"
						ref={ inputRef }
						rows={ 2 }
						className="rms-chat-textarea"
						placeholder={ __(
							'Ask how to improve your site SEO…',
							'rankmyseo'
						) }
						value={ draft }
						onChange={ ( event ) => setDraft( event.target.value ) }
						onKeyDown={ handleTextareaKeyDown }
					/>
					<div className="rms-chat-composer-bar">
						<span className="rms-chat-composer-hint">
							{ isConnected
								? __(
										'Connected · RankMySEO answers for this site',
										'rankmyseo'
								  )
								: __(
										'Local mode · Zero external requests',
										'rankmyseo'
								  ) }
						</span>
						<Button
							type="submit"
							variant="primary"
							className="rms-btn-primary"
							disabled={ ! draft.trim() || busy }
						>
							{ __( 'Send', 'rankmyseo' ) }
						</Button>
					</div>
				</form>
			</aside>
		</div>
	);
}
