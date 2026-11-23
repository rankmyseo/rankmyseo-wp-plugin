import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { BrandMark } from '../../shared/components/BrandMark';
import { ModeToggle } from '../../shared/components/ModeToggle';
import { StatusBadge } from '../../shared/components/StatePrimitives';
import { useAdmin } from '../context/AdminContext';

export function Header(): JSX.Element {
	const { uiMode, setUiMode, status, openChat, isChatOpen, navigateTo } =
		useAdmin();
	const isConnected = status?.cloud.connected ?? false;

	return (
		<header className="rms-header">
			<div className="rms-header-left">
				<button
					type="button"
					className="rms-brand-button"
					onClick={ () => navigateTo( 'dashboard' ) }
				>
					<BrandMark size={ 28 } />
					<span className="rms-brand-title">
						{ __( 'RankMySEO', 'rankmyseo' ) }
					</span>
				</button>
				<StatusBadge tone={ isConnected ? 'up' : 'flat' }>
					{ isConnected
						? __( 'Cloud connected', 'rankmyseo' )
						: __( 'Local mode', 'rankmyseo' ) }
				</StatusBadge>
			</div>

			<div className="rms-header-right">
				<ModeToggle
					mode={ uiMode }
					onChange={ ( nextMode ) => {
						void setUiMode( nextMode );
					} }
				/>
				<Button
					variant="primary"
					className="rms-btn-primary rms-ask-ai-btn"
					aria-expanded={ isChatOpen }
					aria-controls="rms-ai-chat-drawer"
					onClick={ () => openChat() }
				>
					{ __( 'Ask AI', 'rankmyseo' ) }
				</Button>
			</div>
		</header>
	);
}
