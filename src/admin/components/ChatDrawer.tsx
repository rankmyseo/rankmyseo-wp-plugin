import { Button } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent, KeyboardEvent as ReactKeyboardEvent } from 'react';
import { StatusBadge } from '../../shared/components/StatePrimitives';
import {
	approveAiAction,
	fetchCloudChatHistory,
	fetchCloudChatTurn,
	rejectAiAction,
	rollbackAiAction,
	sendCloudChat,
} from '../../shared/api';
import type { AiAction, ChatMessage } from '../../shared/types';
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

export interface ChatDrawerProps {
	onActionApprove?: InlineActionCardSlotProps[ 'onApprove' ];
	onActionReject?: InlineActionCardSlotProps[ 'onReject' ];
	onActionUndo?: InlineActionCardSlotProps[ 'onUndo' ];
}

export function ChatDrawer( {
	onActionApprove,
	onActionReject,
	onActionUndo,
}: ChatDrawerProps ): JSX.Element | null {
	const {
		isChatOpen,
		closeChat,
		status,
		settings,
		uiMode,
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
		( rawPrompt: string ): void => {
			const clean = rawPrompt.trim();
			if ( ! clean ) {
				return;
			}

			const userMsg: ChatMessage = {
				id: `rms-user-${ Date.now() }`,
				role: 'user',
				content: clean,
				createdAt: new Date().toISOString(),
			};

			if ( ! isConnected ) {
				const replyMsg = buildLocalAssistantReply(
					clean,
					status,
					settings,
					uiMode
				);
				setMessages( ( prev ) => [ ...prev, userMsg, replyMsg ] );
				setDraft( '' );
				return;
			}

			setMessages( ( prev ) => [ ...prev, userMsg ] );
			setDraft( '' );
			void ( async () => {
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
			} )();
		},
		[ isConnected, settings, status, uiMode ]
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
			submitPrompt( pendingChatPrompt );
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

		inputRef.current?.focus();

		const handleKeyDown = ( event: KeyboardEvent ): void => {
			if ( event.key === 'Escape' ) {
				closeChat();
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
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
		submitPrompt( draft );
	};

	const handleTextareaKeyDown = (
		event: ReactKeyboardEvent< HTMLTextAreaElement >
	): void => {
		if ( event.key === 'Enter' && ! event.shiftKey ) {
			event.preventDefault();
			submitPrompt( draft );
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
								onClick={ () => submitPrompt( starter.prompt ) }
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
							disabled={ ! draft.trim() }
						>
							{ __( 'Send', 'rankmyseo' ) }
						</Button>
					</div>
				</form>
			</aside>
		</div>
	);
}
