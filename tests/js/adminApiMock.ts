import apiFetch from '@wordpress/api-fetch';
import type {
	PluginSettings,
	SettingsResponse,
	StatusResponse,
	UiPreferencesResponse,
} from '../../src/shared/types';

export const mockedApiFetch = apiFetch as jest.MockedFunction<
	typeof apiFetch
>;

export function createDefaultFixtures(): {
	settings: PluginSettings;
	settingsResponse: SettingsResponse;
	statusResponse: StatusResponse;
	uiPrefsResponse: UiPreferencesResponse;
} {
	const settings: PluginSettings = {
		default_ui_mode: 'simple',
		site_type: 'organization',
		site_name: 'Acme Store',
		title_separator: '|',
		keep_data_on_uninstall: true,
		telemetry_opt_in: false,
		wizard_completed: false,
		wizard_dismissed: false,
		modules: {
			meta: true,
			analyzer: true,
			schema: true,
			sitemaps: true,
			redirects: true,
			importer: true,
			cloud: false,
			ai_queue: false,
		},
	};

	const modules = [
		{
			id: 'meta' as const,
			name: 'On-Page & Titles',
			description: 'Control SEO titles and meta descriptions.',
			simple_description: 'Customize how your pages look in Google.',
			enabled: true,
			enabled_by_default: true,
			ready: false,
			requires_cloud: false,
			booted: true,
		},
		{
			id: 'redirects' as const,
			name: 'Redirects & 404s',
			description: '301 redirects and deduplicated 404 monitor.',
			simple_description: 'Spot broken links and redirect visitors.',
			enabled: true,
			enabled_by_default: true,
			ready: false,
			requires_cloud: false,
			booted: true,
		},
	];

	return {
		settings,
		settingsResponse: { settings, modules },
		statusResponse: {
			plugin_version: '0.1.0',
			php_version: '8.3.6',
			wp_version: '6.8',
			site: {
				name: 'Acme Store',
				url: 'https://acme.example/',
				blog_public: true,
			},
			database: {
				installed_version: 1,
				target_version: 1,
				needs_migration: false,
				tables_healthy: true,
				tables: {
					redirects: { name: 'wp_rankmyseo_redirects', exists: true },
				},
			},
			modules: {
				enabled_count: 2,
				total_count: 2,
				items: modules,
			},
			cloud: {
				connected: false,
				tier: 'local',
			},
			onboarding: {
				wizard_completed: false,
				wizard_dismissed: false,
			},
			capabilities: {
				can_manage: true,
				can_approve_ai: true,
			},
		},
		uiPrefsResponse: {
			ui_mode: 'simple',
			default_ui_mode: 'simple',
			wizard_completed: false,
			wizard_dismissed: false,
			can_manage: true,
			can_approve_ai: true,
		},
	};
}

export function installAdminBootstrap(): void {
	window.rankmyseoAdmin = {
		version: '0.1.0',
		restUrl: '/wp-json/rankmyseo/v1',
		nonce: 'test-nonce',
		adminUrl: '/wp-admin/admin.php',
		siteName: 'Acme Store',
		siteUrl: 'https://acme.example/',
		blogPublic: true,
		initialPage: 'dashboard',
		uiMode: 'simple',
		defaultUiMode: 'simple',
		wizardCompleted: false,
		wizardDismissed: false,
		canManage: true,
		canApproveAi: true,
	};
}

export function setupApiMock(
	overrides?: Partial< UiPreferencesResponse >,
	extra?: ( path: string, method: string, data: unknown ) => unknown
): ReturnType< typeof createDefaultFixtures > {
	const state = createDefaultFixtures();
	if ( overrides ) {
		state.uiPrefsResponse = { ...state.uiPrefsResponse, ...overrides };
	}

	mockedApiFetch.mockImplementation( async ( options ) => {
		const path = options.path ?? '';
		const method = ( options.method ?? 'GET' ).toUpperCase();

		if ( extra ) {
			const handled = extra( path, method, options.data );
			if ( handled !== undefined ) {
				return handled;
			}
		}

		if ( path === '/rankmyseo/v1/settings' && method === 'GET' ) {
			return state.settingsResponse;
		}

		if ( path === '/rankmyseo/v1/settings' && method === 'POST' ) {
			const data = ( options.data ?? {} ) as Partial< PluginSettings >;
			state.settings = {
				...state.settings,
				...data,
				modules: {
					...state.settings.modules,
					...( data.modules ?? {} ),
				},
			};
			state.settingsResponse = {
				...state.settingsResponse,
				updated: true,
				settings: state.settings,
				modules: state.settingsResponse.modules.map( ( module ) => ( {
					...module,
					enabled: state.settings.modules[ module.id ],
				} ) ),
			};
			return state.settingsResponse;
		}

		if ( path === '/rankmyseo/v1/status' && method === 'GET' ) {
			return state.statusResponse;
		}

		if ( path === '/rankmyseo/v1/ui-preferences' && method === 'GET' ) {
			return state.uiPrefsResponse;
		}

		if ( path === '/rankmyseo/v1/ui-preferences' && method === 'POST' ) {
			const data = ( options.data ??
				{} ) as Partial< UiPreferencesResponse >;
			state.uiPrefsResponse = {
				...state.uiPrefsResponse,
				...data,
			};
			return state.uiPrefsResponse;
		}

		throw new Error( `Unexpected API call: ${ method } ${ path }` );
	} );

	return state;
}
