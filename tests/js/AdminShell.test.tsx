import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { App } from '../../src/admin/App';
import { InlineActionCardSlot } from '../../src/admin/components/InlineActionCardSlot';
import type {
	PluginSettings,
	SettingsResponse,
	StatusResponse,
	UiPreferencesResponse,
} from '../../src/shared/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

function createDefaultFixtures(): {
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
			woocommerce: true,
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
		{
			id: 'cloud' as const,
			name: 'RankMySEO Cloud',
			description: 'Optional RankMySEO Cloud connection.',
			simple_description: 'Optional live Google rankings.',
			enabled: false,
			enabled_by_default: false,
			ready: false,
			requires_cloud: true,
			booted: false,
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
					'404_logs': { name: 'wp_rankmyseo_404_logs', exists: true },
				},
			},
			modules: {
				enabled_count: 2,
				total_count: 3,
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

function setupApiMock( overrides?: Partial< UiPreferencesResponse > ): {
	state: ReturnType< typeof createDefaultFixtures >;
} {
	const state = createDefaultFixtures();
	if ( overrides ) {
		state.uiPrefsResponse = { ...state.uiPrefsResponse, ...overrides };
	}

	mockedApiFetch.mockImplementation( async ( options ) => {
		const path = options.path ?? '';
		const method = ( options.method ?? 'GET' ).toUpperCase();

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
				modules: state.settingsResponse.modules.map( ( m ) => ( {
					...m,
					enabled: state.settings.modules[ m.id ],
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

	return { state };
}

describe( 'RankMySEO Admin SPA Shell', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
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
	} );

	it( 'shows the 3-step Setup Wizard in Simple mode and allows skipping to Advanced mode', async () => {
		setupApiMock();
		render( <App /> );

		expect(
			await screen.findByText( '1. Site Basics' )
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Skip to Advanced Setup' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/rankmyseo/v1/ui-preferences',
				method: 'POST',
				data: {
					wizard_dismissed: true,
					ui_mode: 'advanced',
				},
			} );
		} );

		expect(
			screen.queryByText( '1. Site Basics' )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Schema' } )
		).toBeInTheDocument();
	} );

	it( 'switches between Simple and Advanced modes via the top bar toggle', async () => {
		setupApiMock( { wizard_dismissed: true } );
		render( <App /> );

		const advancedRadio = await screen.findByRole( 'radio', {
			name: 'Advanced',
		} );
		fireEvent.click( advancedRadio );

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/rankmyseo/v1/ui-preferences',
				method: 'POST',
				data: { ui_mode: 'advanced' },
			} );
		} );

		expect(
			screen.getByRole( 'button', { name: 'AI Queue' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Sitemaps' } )
		).toBeInTheDocument();
	} );

	it( 'persists settings and module toggles against /rankmyseo/v1/settings', async () => {
		setupApiMock( { wizard_dismissed: true } );
		render( <App /> );

		const settingsTab = await screen.findByRole( 'button', {
			name: 'Settings',
		} );
		fireEvent.click( settingsTab );

		const siteNameInput =
			await screen.findByLabelText( 'Site or brand name' );
		fireEvent.change( siteNameInput, {
			target: { value: 'Updated Store' },
		} );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save Settings' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/rankmyseo/v1/settings',
					method: 'POST',
					data: expect.objectContaining( {
						site_name: 'Updated Store',
					} ),
				} )
			);
		} );
	} );

	it( 'opens the Ask AI drawer, answers starter prompts locally, and closes on Escape', async () => {
		setupApiMock( { wizard_dismissed: true } );
		render( <App /> );

		const askAiButton = await screen.findByRole( 'button', {
			name: 'Ask AI',
		} );
		fireEvent.click( askAiButton );

		expect(
			screen.getByRole( 'dialog', { name: 'RankMySEO AI Assistant' } )
		).toBeInTheDocument();

		const starterChip = screen.getByRole( 'button', {
			name: "How is my site's SEO?",
		} );
		fireEvent.click( starterChip );

		expect(
			await screen.findByText( /Local check for Acme Store/i )
		).toBeInTheDocument();

		fireEvent.keyDown( document, { key: 'Escape' } );

		await waitFor( () => {
			expect(
				screen.queryByRole( 'dialog', {
					name: 'RankMySEO AI Assistant',
				} )
			).not.toBeInTheDocument();
		} );
	} );

	it( 'renders the ready InlineActionCardSlot with diff preview and action callbacks', () => {
		const onApprove = jest.fn();
		const onReject = jest.fn();

		render(
			<InlineActionCardSlot
				card={ {
					id: 'act-1',
					title: 'Update SEO Title',
					targetLabel: '/pricing (Page #42)',
					riskTier: 'low',
					beforeText: 'Pricing - Acme',
					proposedText: 'Team Pricing Plans | Acme',
					rationale: 'Includes the focus keyword at the front.',
					status: 'pending',
				} }
				onApprove={ onApprove }
				onReject={ onReject }
			/>
		);

		expect( screen.getByText( 'Update SEO Title' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Low risk' ) ).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Approve & Apply' } )
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Reject' } ) );

		expect( onApprove ).toHaveBeenCalledWith( 'act-1' );
		expect( onReject ).toHaveBeenCalledWith( 'act-1' );
	} );
} );
