import {
	fireEvent,
	render,
	screen,
	waitFor,
	within,
} from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { App } from '../../src/admin/App';
import type {
	CloudInsights,
	CloudPublicState,
	PluginSettings,
	SettingsResponse,
	StatusResponse,
	UiPreferencesResponse,
} from '../../src/shared/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

function disconnectedState(): CloudPublicState {
	return {
		connected: false,
		reconnect_required: false,
		health: 'disconnected',
		site_name: 'Acme Store',
		site_host: 'acme.example',
		site_url: 'https://acme.example/',
		domain: 'acme.example',
		project_id: '',
		connection_id: '',
		plan_id: '',
		plan_label: '',
		token_prefix: '',
		last_sync_at: '',
		last_error: '',
		pairing: null,
	};
}

function connectedState(): CloudPublicState {
	return {
		...disconnectedState(),
		connected: true,
		health: 'connected',
		plan_id: 'starter',
		plan_label: 'Starter',
		project_id: 'proj_acme',
		connection_id: 'conn_acme',
		token_prefix: 'rms_wp_ab12cd',
		last_sync_at: '2026-11-23T12:00:00+00:00',
	};
}

function insightsFixture(): CloudInsights {
	return {
		connected: true,
		stale: false,
		cached: true,
		fetched_at: '2026-11-23T12:00:00+00:00',
		message: '',
		project: {
			id: 'proj_acme',
			name: 'Acme',
			domain: 'acme.example',
		},
		keywords: {
			tracked: 1,
			rows: [
				{
					keyword: 'rank tracker',
					position: null,
					url: 'https://acme.example/guide/',
				},
			],
		},
		issues: [
			{
				id: 'titles',
				category: 'titles',
				count: 2,
				impact: 'medium',
			},
		],
		page_updates: [
			{
				url: 'https://acme.example/guide/',
				action: 'refresh',
				summary: 'Refresh the guide',
			},
		],
	};
}

function setupApi( options?: {
	uiMode?: 'simple' | 'advanced';
	state?: CloudPublicState;
	statusFails?: boolean;
} ): { polls: number; posts: string[] } {
	const tracker = { polls: 0, posts: [] as string[] };
	const settings: PluginSettings = {
		default_ui_mode: 'simple',
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
			woocommerce: true,
		},
	};
	const settingsResponse: SettingsResponse = {
		settings,
		modules: [],
	};
	const statusResponse: StatusResponse = {
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
		modules: { enabled_count: 1, total_count: 1, items: [] },
		cloud: { connected: false, tier: 'local' },
		onboarding: { wizard_completed: true, wizard_dismissed: true },
		capabilities: { can_manage: true, can_approve_ai: true },
	};
	const prefs: UiPreferencesResponse = {
		ui_mode: options?.uiMode ?? 'simple',
		default_ui_mode: 'simple',
		wizard_completed: true,
		wizard_dismissed: true,
		can_manage: true,
		can_approve_ai: true,
	};
	let cloud = options?.state ?? disconnectedState();

	mockedApiFetch.mockImplementation( async ( request ) => {
		const path = request.path ?? '';
		const method = ( request.method ?? 'GET' ).toUpperCase();

		if ( path === '/rankmyseo/v1/settings' && method === 'GET' ) {
			return settingsResponse;
		}
		if ( path === '/rankmyseo/v1/status' && method === 'GET' ) {
			return statusResponse;
		}
		if ( path === '/rankmyseo/v1/ui-preferences' && method === 'GET' ) {
			return prefs;
		}
		if ( path === '/rankmyseo/v1/cloud/status' && method === 'GET' ) {
			if ( options?.statusFails ) {
				throw new Error( 'Could not load the connection.' );
			}
			return cloud;
		}
		if ( path === '/rankmyseo/v1/cloud/pair/start' && method === 'POST' ) {
			tracker.posts.push( 'start' );
			cloud = {
				...disconnectedState(),
				pairing: {
					code: 'ABCD-EFGH',
					pair_url: 'https://app.rankmyseo.com/pair-wp/ABCD-EFGH',
					expires_at: '2099-01-01T00:00:00Z',
				},
			};
			return { pair_status: 'pending', state: cloud };
		}
		if ( path === '/rankmyseo/v1/cloud/pair/poll' && method === 'POST' ) {
			tracker.polls += 1;
			tracker.posts.push( 'poll' );
			if ( tracker.polls < 2 ) {
				return { pair_status: 'pending', state: cloud };
			}
			cloud = connectedState();
			return { pair_status: 'approved', state: cloud };
		}
		if (
			path.startsWith( '/rankmyseo/v1/cloud/insights' ) &&
			method === 'GET'
		) {
			return insightsFixture();
		}
		if ( path === '/rankmyseo/v1/cloud/sync' && method === 'POST' ) {
			tracker.posts.push( 'sync' );
			return { state: cloud, processed: 1, message: '' };
		}
		if (
			path === '/rankmyseo/v1/cloud/rotate-token' &&
			method === 'POST'
		) {
			tracker.posts.push( 'rotate' );
			return { state: cloud };
		}
		if ( path === '/rankmyseo/v1/cloud/disconnect' && method === 'POST' ) {
			tracker.posts.push( 'disconnect' );
			cloud = disconnectedState();
			return { state: cloud };
		}
		throw new Error( `Unexpected API call: ${ method } ${ path }` );
	} );

	return tracker;
}

describe( 'RankMySEO Cloud connect view', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		window.open = jest.fn();
		window.rankmyseoAdmin = {
			version: '0.1.0',
			restUrl: '/wp-json/rankmyseo/v1',
			nonce: 'test-nonce',
			adminUrl: '/wp-admin/admin.php',
			siteName: 'Acme Store',
			siteUrl: 'https://acme.example/',
			blogPublic: true,
			initialPage: 'cloud',
			uiMode: 'simple',
			defaultUiMode: 'simple',
			wizardCompleted: true,
			wizardDismissed: true,
			canManage: true,
			canApproveAi: true,
		};
	} );

	it( 'pairs from one click and shows cached insights without credentials', async () => {
		const tracker = setupApi();
		render( <App /> );

		expect(
			await screen.findByText( /Connecting is optional/ )
		).toBeInTheDocument();
		expect( document.body.textContent ).not.toContain( 'rms_wp_' );
		expect( document.body.textContent ).not.toContain( 'whsec_' );
		expect( document.body.textContent ).not.toContain( 'poll_secret' );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Connect to RankMySEO' } )
		);

		expect( await screen.findByText( 'ABCD-EFGH' ) ).toBeInTheDocument();
		expect( window.open ).toHaveBeenCalledWith(
			'https://app.rankmyseo.com/pair-wp/ABCD-EFGH',
			'_blank',
			'noopener,noreferrer'
		);
		expect(
			screen.getByRole( 'link', { name: 'Open the approval page' } )
		).toHaveAttribute(
			'href',
			'https://app.rankmyseo.com/pair-wp/ABCD-EFGH'
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Check now' } ) );
		await waitFor( () => {
			expect(
				screen.getByRole( 'button', { name: 'Check now' } )
			).not.toBeDisabled();
		} );
		fireEvent.click( screen.getByRole( 'button', { name: 'Check now' } ) );

		expect( await screen.findByText( 'rank tracker' ) ).toBeInTheDocument();
		expect( screen.getByText( '—' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Refresh the guide' ) ).toBeInTheDocument();
		expect( screen.getByText( 'titles' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'proj_acme' ) ).not.toBeInTheDocument();
		expect( document.body.textContent ).not.toContain( 'rms_wp_' );
		expect( tracker.polls ).toBeGreaterThanOrEqual( 2 );
	} );

	it( 'stays local when the administrator continues without connecting', async () => {
		const tracker = setupApi();
		render( <App /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Continue locally' } )
		);

		expect(
			await screen.findByRole( 'button', { name: 'Ask AI for guidance' } )
		).toBeInTheDocument();
		expect( tracker.posts ).not.toContain( 'start' );
	} );

	it( 'shows the advanced site id and disconnects after confirmation', async () => {
		const tracker = setupApi( {
			uiMode: 'advanced',
			state: connectedState(),
		} );
		render( <App /> );

		expect( await screen.findByText( /proj_acme/ ) ).toBeInTheDocument();
		expect( screen.getByText( /rms_wp_ab12cd/ ) ).toBeInTheDocument();
		expect( document.body.textContent ).not.toContain(
			'rms_wp_ab12cdSECRET'
		);
		expect( screen.getByText( /Starter/ ) ).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Sync now' } ) );
		await waitFor( () => {
			expect( tracker.posts ).toContain( 'sync' );
		} );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Rotate token' } )
		);
		await waitFor( () => {
			expect( tracker.posts ).toContain( 'rotate' );
		} );

		fireEvent.click( screen.getByRole( 'button', { name: 'Disconnect' } ) );
		const dialog = screen.getByRole( 'alertdialog' );
		fireEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		expect( tracker.posts ).not.toContain( 'disconnect' );

		fireEvent.click( screen.getByRole( 'button', { name: 'Disconnect' } ) );
		fireEvent.click(
			within( screen.getByRole( 'alertdialog' ) ).getByRole( 'button', {
				name: 'Disconnect',
			} )
		);
		expect(
			await screen.findByRole( 'button', {
				name: 'Connect to RankMySEO',
			} )
		).toBeInTheDocument();
		expect( tracker.posts ).toContain( 'disconnect' );
	} );

	it( 'shows a calm error when connection status cannot load', async () => {
		setupApi( { statusFails: true } );
		render( <App /> );

		expect(
			await screen.findByText( 'Could not load the connection.' )
		).toBeInTheDocument();
	} );
} );
