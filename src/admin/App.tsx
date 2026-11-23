import { __ } from '@wordpress/i18n';
import { ErrorBanner } from '../shared/components/StatePrimitives';
import { ChatDrawer } from './components/ChatDrawer';
import { Header } from './components/Header';
import { Navigation } from './components/Navigation';
import { SetupWizard } from './components/SetupWizard';
import { AdminProvider, useAdmin } from './context/AdminContext';
import { ConnectView } from './views/ConnectView';
import { DashboardView } from './views/DashboardView';
import { ModulePlaceholderView } from './views/ModulePlaceholderView';
import { SettingsView } from './views/SettingsView';

function AdminShell(): JSX.Element {
	const {
		activePage,
		error,
		refreshAll,
		notice,
		clearNotice,
		showWizard,
		isChatOpen,
	} = useAdmin();

	const renderActiveView = (): JSX.Element => {
		if ( activePage === 'dashboard' ) {
			return <DashboardView />;
		}
		if ( activePage === 'settings' ) {
			return <SettingsView />;
		}
		if ( activePage === 'cloud' ) {
			return <ConnectView />;
		}
		return <ModulePlaceholderView pageId={ activePage } />;
	};

	return (
		<div className={ `rms-shell${ isChatOpen ? ' is-drawer-open' : '' }` }>
			<Header />
			<Navigation />

			<main className="rms-main">
				{ notice && (
					<div
						className={ `rms-toast rms-toast-${ notice.tone }` }
						role="status"
						aria-live="polite"
					>
						<span>{ notice.message }</span>
						<button
							type="button"
							className="rms-toast-dismiss"
							aria-label={ __( 'Dismiss notice', 'rankmyseo' ) }
							onClick={ clearNotice }
						>
							×
						</button>
					</div>
				) }

				{ error && (
					<ErrorBanner
						message={ error }
						onRetry={ () => {
							void refreshAll();
						} }
					/>
				) }

				{ showWizard && <SetupWizard /> }

				{ renderActiveView() }
			</main>

			<ChatDrawer />
		</div>
	);
}

export function App(): JSX.Element {
	return (
		<AdminProvider>
			<AdminShell />
		</AdminProvider>
	);
}
