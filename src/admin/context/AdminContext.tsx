import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import {
	completeOnboarding,
	fetchSettings,
	fetchStatus,
	fetchUiPreferences,
	saveSettingsRequest,
	saveUiPreferencesRequest,
	type SettingsUpdatePayload,
} from '../../shared/api';
import type {
	BootstrapConfig,
	ModuleDescriptor,
	ModuleId,
	NavPageId,
	OnboardingCompletePayload,
	PluginSettings,
	StatusResponse,
	UiMode,
} from '../../shared/types';

export interface AdminNotice {
	tone: 'up' | 'warn' | 'down' | 'flat';
	message: string;
}

interface AdminContextValue {
	bootstrap: BootstrapConfig;
	activePage: NavPageId;
	navigateTo: ( pageId: NavPageId ) => void;
	uiMode: UiMode;
	setUiMode: ( nextMode: UiMode ) => Promise< void >;
	settings: PluginSettings | null;
	modules: ModuleDescriptor[];
	status: StatusResponse | null;
	loading: boolean;
	saving: boolean;
	error: string | null;
	notice: AdminNotice | null;
	clearNotice: () => void;
	refreshAll: () => Promise< void >;
	saveSettings: (
		payload: SettingsUpdatePayload,
		successMessage?: string
	) => Promise< boolean >;
	toggleModule: ( moduleId: ModuleId, enabled: boolean ) => Promise< void >;
	showWizard: boolean;
	completeWizard: ( payload: OnboardingCompletePayload ) => Promise< void >;
	dismissWizard: ( switchToAdvanced?: boolean ) => Promise< void >;
	reopenWizard: () => void;
	isChatOpen: boolean;
	pendingChatPrompt: string | null;
	openChat: ( prompt?: string ) => void;
	closeChat: () => void;
	consumePendingChatPrompt: () => void;
}

const DEFAULT_BOOTSTRAP: BootstrapConfig = {
	version: '1.0.0',
	restUrl: '/wp-json/rankmyseo/v1',
	nonce: '',
	adminUrl: '/wp-admin/admin.php',
	siteName: '',
	siteUrl: '',
	blogPublic: true,
	initialPage: 'dashboard',
	uiMode: 'simple',
	defaultUiMode: 'simple',
	wizardCompleted: false,
	wizardDismissed: false,
	canManage: true,
	canApproveAi: true,
};

const PAGE_TO_SLUG: Record< NavPageId, string > = {
	dashboard: 'rankmyseo',
	meta: 'rankmyseo-meta',
	missing: 'rankmyseo-missing',
	schema: 'rankmyseo-schema',
	store: 'rankmyseo-store',
	sitemaps: 'rankmyseo-sitemaps',
	redirects: 'rankmyseo-redirects',
	import: 'rankmyseo-import',
	cloud: 'rankmyseo-cloud',
	'ai-queue': 'rankmyseo-ai-queue',
	settings: 'rankmyseo-settings',
};

const AdminContext = createContext< AdminContextValue | null >( null );

function readBootstrap(): BootstrapConfig {
	if ( typeof window !== 'undefined' && window.rankmyseoAdmin ) {
		return { ...DEFAULT_BOOTSTRAP, ...window.rankmyseoAdmin };
	}
	return DEFAULT_BOOTSTRAP;
}

export function AdminProvider( {
	children,
}: {
	children: ReactNode;
} ): JSX.Element {
	const [ bootstrap ] = useState< BootstrapConfig >( readBootstrap );
	const [ activePage, setActivePage ] = useState< NavPageId >(
		bootstrap.initialPage
	);
	const [ uiMode, setUiModeState ] = useState< UiMode >( bootstrap.uiMode );
	const [ settings, setSettings ] = useState< PluginSettings | null >( null );
	const [ modules, setModules ] = useState< ModuleDescriptor[] >( [] );
	const [ status, setStatus ] = useState< StatusResponse | null >( null );
	const [ loading, setLoading ] = useState< boolean >( true );
	const [ saving, setSaving ] = useState< boolean >( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ notice, setNotice ] = useState< AdminNotice | null >( null );
	const [ wizardDismissed, setWizardDismissed ] = useState< boolean >(
		bootstrap.wizardCompleted || bootstrap.wizardDismissed
	);
	const [ wizardForcedOpen, setWizardForcedOpen ] =
		useState< boolean >( false );
	const [ isChatOpen, setIsChatOpen ] = useState< boolean >( false );
	const [ pendingChatPrompt, setPendingChatPrompt ] = useState<
		string | null
	>( null );

	const showNotice = useCallback(
		( tone: AdminNotice[ 'tone' ], message: string ): void => {
			setNotice( { tone, message } );
		},
		[]
	);

	const clearNotice = useCallback( (): void => {
		setNotice( null );
	}, [] );

	const refreshAll = useCallback( async (): Promise< void > => {
		setLoading( true );
		setError( null );
		try {
			const [ settingsRes, statusRes, prefsRes ] = await Promise.all( [
				fetchSettings(),
				fetchStatus(),
				fetchUiPreferences(),
			] );

			setSettings( settingsRes.settings );
			setModules( settingsRes.modules );
			setStatus( statusRes );
			setUiModeState( prefsRes.ui_mode );
			setWizardDismissed(
				prefsRes.wizard_completed || prefsRes.wizard_dismissed
			);
		} catch ( err ) {
			const msg =
				err instanceof Error
					? err.message
					: __(
							'Could not load RankMySEO settings. Please try again.',
							'rankmyseo'
					  );
			setError( msg );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		void refreshAll();
	}, [ refreshAll ] );

	// Keyboard shortcut Cmd/Ctrl+J toggles the AI Assistant drawer across RankMySEO screens.
	useEffect( () => {
		const onKeyDown = ( event: KeyboardEvent ): void => {
			if (
				( event.metaKey || event.ctrlKey ) &&
				event.key.toLowerCase() === 'j'
			) {
				event.preventDefault();
				setIsChatOpen( ( prev ) => ! prev );
			}
		};

		window.addEventListener( 'keydown', onKeyDown );
		return () => window.removeEventListener( 'keydown', onKeyDown );
	}, [] );

	const navigateTo = useCallback( ( pageId: NavPageId ): void => {
		setActivePage( pageId );
		if ( typeof window !== 'undefined' && window.history?.replaceState ) {
			try {
				const url = new URL( window.location.href );
				url.searchParams.set( 'page', PAGE_TO_SLUG[ pageId ] );
				window.history.replaceState( {}, '', url.toString() );
			} catch {
				// Ignore malformed URLs in test environments.
			}
		}
	}, [] );

	const setUiMode = useCallback(
		async ( nextMode: UiMode ): Promise< void > => {
			if ( nextMode === uiMode ) {
				return;
			}
			const previous = uiMode;
			setUiModeState( nextMode );
			try {
				const res = await saveUiPreferencesRequest( {
					ui_mode: nextMode,
				} );
				setUiModeState( res.ui_mode );
				showNotice(
					'up',
					nextMode === 'advanced'
						? __(
								'Switched to Advanced Mode. All granular controls are visible.',
								'rankmyseo'
						  )
						: __(
								'Switched to Simple Mode. Your advanced settings stay safely preserved.',
								'rankmyseo'
						  )
				);
			} catch {
				setUiModeState( previous );
				showNotice(
					'down',
					__(
						'Could not save your experience mode preference.',
						'rankmyseo'
					)
				);
			}
		},
		[ showNotice, uiMode ]
	);

	const saveSettings = useCallback(
		async (
			payload: SettingsUpdatePayload,
			successMessage?: string
		): Promise< boolean > => {
			setSaving( true );
			try {
				const res = await saveSettingsRequest( payload );
				setSettings( res.settings );
				setModules( res.modules );
				const nextStatus = await fetchStatus();
				setStatus( nextStatus );
				if ( successMessage ) {
					showNotice( 'up', successMessage );
				}
				return true;
			} catch {
				showNotice(
					'down',
					__(
						'Could not save settings. Please try again.',
						'rankmyseo'
					)
				);
				return false;
			} finally {
				setSaving( false );
			}
		},
		[ showNotice ]
	);

	const toggleModule = useCallback(
		async ( moduleId: ModuleId, enabled: boolean ): Promise< void > => {
			const previousSettings = settings;
			const previousModules = modules;

			if ( settings ) {
				setSettings( {
					...settings,
					modules: {
						...settings.modules,
						[ moduleId ]: enabled,
					},
				} );
			}
			setModules( ( prev ) =>
				prev.map( ( item ) =>
					item.id === moduleId ? { ...item, enabled } : item
				)
			);

			const ok = await saveSettings(
				{ modules: { [ moduleId ]: enabled } },
				enabled
					? __( 'Module enabled.', 'rankmyseo' )
					: __( 'Module disabled.', 'rankmyseo' )
			);

			if ( ! ok ) {
				setSettings( previousSettings );
				setModules( previousModules );
			}
		},
		[ modules, saveSettings, settings ]
	);

	const completeWizard = useCallback(
		async ( payload: OnboardingCompletePayload ): Promise< void > => {
			setSaving( true );
			try {
				const res = await completeOnboarding( payload );
				setSettings( res.settings );
				if ( res.module_catalog && res.module_catalog.length > 0 ) {
					setModules( res.module_catalog );
				} else {
					setModules( ( prev ) =>
						prev.map( ( item ) => ( {
							...item,
							enabled:
								res.settings.modules[ item.id ] ?? item.enabled,
						} ) )
					);
				}
				setWizardDismissed( true );
				setWizardForcedOpen( false );
				showNotice(
					'up',
					__(
						'Setup complete. Your site is ready for local SEO.',
						'rankmyseo'
					)
				);
			} catch {
				showNotice(
					'down',
					__( 'Could not save wizard progress.', 'rankmyseo' )
				);
			} finally {
				setSaving( false );
			}
		},
		[ showNotice ]
	);

	const dismissWizard = useCallback(
		async ( switchToAdvanced = false ): Promise< void > => {
			setWizardDismissed( true );
			setWizardForcedOpen( false );
			if ( switchToAdvanced ) {
				setUiModeState( 'advanced' );
			}
			try {
				await saveUiPreferencesRequest( {
					wizard_dismissed: true,
					...( switchToAdvanced ? { ui_mode: 'advanced' } : {} ),
				} );
				if ( switchToAdvanced ) {
					showNotice(
						'up',
						__(
							'Setup skipped — switched to Advanced Mode.',
							'rankmyseo'
						)
					);
				}
			} catch {
				showNotice(
					'down',
					__( 'Could not persist wizard dismissal.', 'rankmyseo' )
				);
			}
		},
		[ showNotice ]
	);

	const reopenWizard = useCallback( (): void => {
		setWizardForcedOpen( true );
		setActivePage( 'dashboard' );
	}, [] );

	const openChat = useCallback( ( prompt?: string ): void => {
		if ( prompt ) {
			setPendingChatPrompt( prompt );
		}
		setIsChatOpen( true );
	}, [] );

	const closeChat = useCallback( (): void => {
		setIsChatOpen( false );
	}, [] );

	const consumePendingChatPrompt = useCallback( (): void => {
		setPendingChatPrompt( null );
	}, [] );

	const showWizard =
		wizardForcedOpen ||
		( ! loading &&
			! wizardDismissed &&
			uiMode === 'simple' &&
			activePage === 'dashboard' );

	const value = useMemo< AdminContextValue >(
		() => ( {
			bootstrap,
			activePage,
			navigateTo,
			uiMode,
			setUiMode,
			settings,
			modules,
			status,
			loading,
			saving,
			error,
			notice,
			clearNotice,
			refreshAll,
			saveSettings,
			toggleModule,
			showWizard,
			completeWizard,
			dismissWizard,
			reopenWizard,
			isChatOpen,
			pendingChatPrompt,
			openChat,
			closeChat,
			consumePendingChatPrompt,
		} ),
		[
			activePage,
			bootstrap,
			clearNotice,
			closeChat,
			completeWizard,
			consumePendingChatPrompt,
			dismissWizard,
			error,
			isChatOpen,
			loading,
			modules,
			navigateTo,
			notice,
			openChat,
			pendingChatPrompt,
			refreshAll,
			reopenWizard,
			saveSettings,
			saving,
			setUiMode,
			settings,
			showWizard,
			status,
			toggleModule,
			uiMode,
		]
	);

	return (
		<AdminContext.Provider value={ value }>
			{ children }
		</AdminContext.Provider>
	);
}

export function useAdmin(): AdminContextValue {
	const context = useContext( AdminContext );
	if ( ! context ) {
		throw new Error( 'useAdmin must be used within AdminProvider' );
	}
	return context;
}
