import { __ } from '@wordpress/i18n';
import { ErrorBanner } from '../shared/components/StatePrimitives';
import { AskAiDrawer } from './components/AskAiDrawer';
import { CommandBar } from './components/CommandBar';
import { Header } from './components/Header';
import { Navigation } from './components/Navigation';
import { SetupWizard } from './components/SetupWizard';
import { AdminProvider, useAdmin } from './context/AdminContext';
import { ConnectView } from './views/ConnectView';
import { AiQueueView } from './views/AiQueueView';
import { DashboardView } from './views/DashboardView';
import { ImporterView } from './views/ImporterView';
import { RedirectsView } from './views/redirects/RedirectsView';
import { SchemaView } from './views/SchemaView';
import { StoreView } from './views/store/StoreView';
import { SettingsView } from './views/SettingsView';
import { SitemapsView } from './views/SitemapsView';
import { MissingMetadataView } from './views/MissingMetadataView';
import { TitlesMetaView } from './views/TitlesMetaView';

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
		if ( activePage === 'sitemaps' ) {
			return <SitemapsView />;
		}
		if ( activePage === 'schema' ) {
			return <SchemaView />;
		}
		if ( activePage === 'store' ) {
			return <StoreView />;
		}
		if ( activePage === 'redirects' ) {
			return <RedirectsView />;
		}
		if ( activePage === 'import' ) {
			return <ImporterView />;
		}
		if ( activePage === 'meta' ) {
			return <TitlesMetaView />;
		}
		if ( activePage === 'missing' ) {
			return <MissingMetadataView />;
		}
		if ( activePage === 'cloud' ) {
			return <ConnectView />;
		}
		if ( activePage === 'ai-queue' ) {
			return <AiQueueView />;
		}
		return <DashboardView />;
	};

	return (
		<div className={ `rms-shell${ isChatOpen ? ' is-drawer-open' : '' }` }>
			<a className="rms-skip-link" href="#rms-main">
				{ __( 'Skip to content', 'rankmyseo' ) }
			</a>
			<Header />
			<Navigation />

			<main id="rms-main" className="rms-main" tabIndex={ -1 }>
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

			<AskAiDrawer />
			<CommandBar />
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
