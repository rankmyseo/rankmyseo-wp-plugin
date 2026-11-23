import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { App } from '../../src/admin/App';
import type {
	PluginSettings,
	SettingsResponse,
	StatusResponse,
	UiPreferencesResponse,
} from '../../src/shared/types';
import type {
	NotFoundLog,
	RedirectRule,
	RedirectSettingsPayload,
} from '../../src/admin/views/redirects/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

function rule( overrides: Partial< RedirectRule > = {} ): RedirectRule {
	return {
		id: 7,
		source_path: '/old-page',
		source_hash: 'abc',
		match_type: 'exact',
		target_url: '/new-page',
		status_code: 301,
		is_active: true,
		hits: 4,
		last_hit_at: null,
		source_origin: 'manual',
		created_at: '2026-01-01 00:00:00',
		updated_at: '2026-01-01 00:00:00',
		...overrides,
	};
}

function log( overrides: Partial< NotFoundLog > = {} ): NotFoundLog {
	return {
		id: 3,
		request_path: '/missing-page',
		path_hash: 'def',
		query_string: '',
		referer: '',
		user_agent: 'Mozilla',
		hits: 2,
		status: 'open',
		first_seen_at: '2026-01-01 00:00:00',
		last_seen_at: '2026-01-02 00:00:00',
		...overrides,
	};
}

function createFixtures( uiMode: 'simple' | 'advanced' ): {
	settings: PluginSettings;
	settingsResponse: SettingsResponse;
	statusResponse: StatusResponse;
	uiPrefsResponse: UiPreferencesResponse;
	redirectSettings: RedirectSettingsPayload;
	rules: RedirectRule[];
	logs: NotFoundLog[];
} {
	const settings: PluginSettings = {
		default_ui_mode: uiMode,
		site_type: 'organization',
		site_name: 'Acme Store',
		title_separator: '|',
		keep_data_on_uninstall: true,
		telemetry_opt_in: false,
		wizard_completed: true,
		wizard_dismissed: true,
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
			id: 'redirects' as const,
			name: 'Redirects & 404s',
			description: '301 redirects and deduplicated 404 monitor.',
			simple_description: 'Spot broken links and redirect visitors.',
			enabled: true,
			enabled_by_default: true,
			ready: true,
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
				tables: {},
			},
			modules: {
				enabled_count: 1,
				total_count: 1,
				items: modules,
			},
			cloud: { connected: false, tier: 'local' },
			onboarding: {
				wizard_completed: true,
				wizard_dismissed: true,
			},
			capabilities: {
				can_manage: true,
				can_approve_ai: true,
			},
		},
		uiPrefsResponse: {
			ui_mode: uiMode,
			default_ui_mode: uiMode,
			wizard_completed: true,
			wizard_dismissed: true,
			can_manage: true,
			can_approve_ai: true,
		},
		redirectSettings: {
			auto_slug: true,
			preserve_query: true,
			strip_trailing_slash: true,
			ignore_patterns: [],
			bot_patterns: [],
			max_rows: 10000,
			ttl_days: 30,
		},
		rules: [
			rule(),
			rule( {
				id: 8,
				source_path: '/new-page',
				target_url: '/final',
				hits: 1,
			} ),
			rule( {
				id: 9,
				source_path: '^/blog/(.+)$',
				match_type: 'regex',
				target_url: '/news/$1',
				status_code: 302,
				hits: 0,
			} ),
		],
		logs: [ log() ],
	};
}

function setupApi( uiMode: 'simple' | 'advanced' ) {
	const state = createFixtures( uiMode );

	mockedApiFetch.mockImplementation( async ( options ) => {
		const path = options.path ?? '';
		const method = ( options.method ?? 'GET' ).toUpperCase();

		if ( path === '/rankmyseo/v1/settings' && method === 'GET' ) {
			return state.settingsResponse;
		}
		if ( path === '/rankmyseo/v1/status' && method === 'GET' ) {
			return state.statusResponse;
		}
		if ( path === '/rankmyseo/v1/ui-preferences' && method === 'GET' ) {
			return state.uiPrefsResponse;
		}
		if ( path === '/rankmyseo/v1/redirects/settings' && method === 'GET' ) {
			return { settings: state.redirectSettings };
		}
		if (
			path === '/rankmyseo/v1/redirects/settings' &&
			method === 'POST'
		) {
			state.redirectSettings = {
				...state.redirectSettings,
				...( options.data as Partial< RedirectSettingsPayload > ),
			};
			return { settings: state.redirectSettings };
		}
		if (
			path.startsWith( '/rankmyseo/v1/redirects?' ) &&
			method === 'GET'
		) {
			return {
				items: state.rules,
				total: state.rules.length,
				page: 1,
				per_page: 20,
			};
		}
		if (
			path.startsWith( '/rankmyseo/v1/404-logs?' ) &&
			method === 'GET'
		) {
			return {
				items: state.logs,
				total: state.logs.length,
				page: 1,
				per_page: 20,
			};
		}
		if ( path === '/rankmyseo/v1/redirects' && method === 'POST' ) {
			const data = options.data as {
				source: string;
				target?: string;
				status_code: number;
				match_type: string;
			};
			const created = rule( {
				id: 21,
				source_path: data.source,
				target_url: data.target ?? null,
				status_code: data.status_code as RedirectRule[ 'status_code' ],
				match_type: data.match_type as RedirectRule[ 'match_type' ],
			} );
			state.rules = [ created, ...state.rules ];
			return {
				redirect: created,
				warning: data.source === '/a' ? 'redirect_chain' : '',
				chain: data.source === '/a' ? [ '/a', '/b', '/c' ] : [],
				updated: false,
			};
		}
		if (
			path === '/rankmyseo/v1/404-logs/3/redirect' &&
			method === 'POST'
		) {
			state.logs = [];
			return {
				redirect: rule( { id: 30, source_path: '/missing-page' } ),
				warning: '',
				chain: [],
				updated: false,
				log: log( { status: 'redirected' } ),
			};
		}
		if (
			path === '/rankmyseo/v1/redirects/test-url' &&
			method === 'POST'
		) {
			return {
				matched: true,
				path: '/blog/hello',
				query: '',
				redirect_id: 9,
				match_type: 'regex',
				status_code: 302,
				target_url: 'https://acme.example/news/hello',
				source_path: '^/blog/(.+)$',
			};
		}
		if (
			path === '/rankmyseo/v1/redirects/export?format=csv' &&
			method === 'GET'
		) {
			return {
				filename: 'rankmyseo-redirects.csv',
				mime_type: 'text/csv',
				content: 'source_path,match_type\n/old,exact\n',
			};
		}
		throw new Error( `Unexpected API call: ${ method } ${ path }` );
	} );

	return state;
}

function boot( uiMode: 'simple' | 'advanced' ): void {
	window.rankmyseoAdmin = {
		version: '0.1.0',
		restUrl: '/wp-json/rankmyseo/v1',
		nonce: 'test-nonce',
		adminUrl: '/wp-admin/admin.php',
		siteName: 'Acme Store',
		siteUrl: 'https://acme.example/',
		blogPublic: true,
		initialPage: 'redirects',
		uiMode,
		defaultUiMode: uiMode,
		wizardCompleted: true,
		wizardDismissed: true,
		canManage: true,
		canApproveAi: true,
	};
}

describe( 'Redirects & 404s dual-mode UI', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		// jsdom may not implement object URLs used by CSV download.
		if ( typeof URL.createObjectURL !== 'function' ) {
			URL.createObjectURL = jest.fn( () => 'blob:redirects' );
			URL.revokeObjectURL = jest.fn();
		}
		HTMLAnchorElement.prototype.click = jest.fn();
	} );

	it( 'adds a permanent redirect and fixes a broken page in Simple mode', async () => {
		boot( 'simple' );
		setupApi( 'simple' );
		render( <App /> );

		expect(
			await screen.findByRole( 'heading', {
				name: 'Fix broken links & forward old pages',
			} )
		).toBeInTheDocument();

		expect(
			await screen.findByText(
				'Custom advanced rules active — switch to Advanced to edit'
			)
		).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'Old link' ), {
			target: { value: '/old' },
		} );
		fireEvent.change( screen.getByLabelText( 'New link' ), {
			target: { value: '/new' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Add redirect' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/rankmyseo/v1/redirects',
				method: 'POST',
				data: {
					source: '/old',
					target: '/new',
					status_code: 301,
					match_type: 'exact',
				},
			} );
		} );

		fireEvent.click(
			screen.getByRole( 'checkbox', {
				name: 'Automatically forward visitors when a page address changes',
			} )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/rankmyseo/v1/redirects/settings',
				method: 'POST',
				data: { auto_slug: false },
			} );
		} );

		expect(
			screen.getByRole( 'heading', {
				name: 'Broken pages visitors hit (404s)',
			} )
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Fix with redirect' } )
		);
		fireEvent.change( screen.getByLabelText( 'Send visitors to' ), {
			target: { value: '/plans' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Create redirect' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/rankmyseo/v1/404-logs/3/redirect',
				method: 'POST',
				data: {
					target_url: '/plans',
					status_code: 301,
				},
			} );
		} );
	} );

	it( 'tests a regex URL, warns on chains, and exports CSV in Advanced mode', async () => {
		boot( 'advanced' );
		setupApi( 'advanced' );
		render( <App /> );

		expect(
			await screen.findByRole( 'heading', { name: 'Redirects & 404s' } )
		).toBeInTheDocument();

		expect(
			( await screen.findAllByText( 'Chain' ) ).length
		).toBeGreaterThan( 0 );

		fireEvent.change( screen.getByLabelText( 'HTTP status' ), {
			target: { value: '410' },
		} );
		expect( screen.queryByLabelText( 'To URL' ) ).not.toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'HTTP status' ), {
			target: { value: '302' },
		} );
		fireEvent.click( screen.getByRole( 'radio', { name: 'Regex' } ) );
		fireEvent.change( screen.getByLabelText( 'Test a URL' ), {
			target: { value: '/blog/hello' },
		} );
		fireEvent.click( screen.getByRole( 'button', { name: 'Test URL' } ) );

		expect(
			await screen.findByText(
				'Sends visitors to https://acme.example/news/hello'
			)
		).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'From URL' ), {
			target: { value: '/a' },
		} );
		fireEvent.change( screen.getByLabelText( 'To URL' ), {
			target: { value: '/b' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save redirect' } )
		);

		expect( await screen.findByText( '/a → /b → /c' ) ).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Export CSV' } ) );

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/rankmyseo/v1/redirects/export?format=csv',
				method: 'GET',
			} );
		} );

		fireEvent.change( screen.getByLabelText( '404 ignore rules' ), {
			target: { value: '*/private/*' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save ignore rules' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/rankmyseo/v1/redirects/settings',
					method: 'POST',
					data: expect.objectContaining( {
						ignore_patterns: [ '*/private/*' ],
					} ),
				} )
			);
		} );
	} );
} );
