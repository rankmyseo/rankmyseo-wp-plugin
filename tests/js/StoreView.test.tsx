import { fireEvent, render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { App } from '../../src/admin/App';
import type { WooSettings } from '../../src/admin/views/store/types';
import { defaultWooSettings } from '../../src/admin/views/store/types';
import type {
	PluginSettings,
	SettingsResponse,
	StatusResponse,
	UiPreferencesResponse,
} from '../../src/shared/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

function pluginSettings(): PluginSettings {
	return {
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
}

function installApiMock(
	woo: WooSettings,
	mode: 'simple' | 'advanced' = 'simple'
): { posted: WooSettings | null } {
	const state: { posted: WooSettings | null; woo: WooSettings } = {
		posted: null,
		woo,
	};
	const settings = pluginSettings();
	const settingsResponse: SettingsResponse = {
		settings,
		modules: [
			{
				id: 'woocommerce',
				name: 'Store',
				description: 'Store tools',
				simple_description: 'Shop details',
				enabled: true,
				enabled_by_default: true,
				ready: true,
				requires_cloud: false,
				booted: true,
			},
		],
	};
	const status: StatusResponse = {
		plugin_version: '1.0.0',
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
			items: settingsResponse.modules,
		},
		cloud: { connected: false, tier: 'local' },
		onboarding: { wizard_completed: true, wizard_dismissed: true },
		capabilities: { can_manage: true, can_approve_ai: true },
	};
	const ui: UiPreferencesResponse = {
		ui_mode: mode,
		default_ui_mode: 'simple',
		wizard_completed: true,
		wizard_dismissed: true,
		can_manage: true,
		can_approve_ai: true,
	};

	mockedApiFetch.mockImplementation( async ( options ) => {
		const path = options.path ?? '';
		const method = ( options.method ?? 'GET' ).toUpperCase();
		if ( path === '/rankmyseo/v1/settings' && method === 'GET' ) {
			return settingsResponse;
		}
		if ( path === '/rankmyseo/v1/status' && method === 'GET' ) {
			return status;
		}
		if ( path === '/rankmyseo/v1/ui-preferences' && method === 'GET' ) {
			return ui;
		}
		if (
			path === '/rankmyseo/v1/woocommerce/settings' &&
			method === 'GET'
		) {
			return {
				settings: state.woo,
				woocommerceActive: false,
				moduleEnabled: true,
				updated: false,
			};
		}
		if (
			path === '/rankmyseo/v1/woocommerce/settings' &&
			method === 'POST'
		) {
			state.posted = options.data as WooSettings;
			state.woo = options.data as WooSettings;
			return {
				settings: state.woo,
				woocommerceActive: false,
				moduleEnabled: true,
				updated: true,
			};
		}
		throw new Error( `Unexpected API call: ${ method } ${ path }` );
	} );

	return state;
}

function boot( mode: 'simple' | 'advanced' ): void {
	window.rankmyseoAdmin = {
		version: '1.0.0',
		restUrl: '/wp-json/rankmyseo/v1',
		nonce: 'test-nonce',
		adminUrl: '/wp-admin/admin.php',
		siteName: 'Acme Store',
		siteUrl: 'https://acme.example/',
		blogPublic: true,
		initialPage: 'store',
		uiMode: mode,
		defaultUiMode: 'simple',
		wizardCompleted: true,
		wizardDismissed: true,
		canManage: true,
		canApproveAi: true,
	};
}

describe( 'Store settings screen', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
	} );

	it( 'uses plain language in simple mode and hides handling time', async () => {
		boot( 'simple' );
		const woo = defaultWooSettings();
		woo.shipping_enabled = true;
		woo.shipping_handling_min = 2;
		installApiMock( woo );
		render( <App /> );

		expect(
			await screen.findByText( 'Show each product option in search' )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Custom advanced rules active — switch to Advanced to edit'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Handling time, minimum days' )
		).not.toBeInTheDocument();
		expect(
			screen.getByText(
				'WooCommerce is not active. These choices are saved and start working when WooCommerce is on.'
			)
		).toBeInTheDocument();
	} );

	it( 'shows handling time in advanced mode and saves the form', async () => {
		boot( 'advanced' );
		const woo = defaultWooSettings();
		woo.shipping_enabled = true;
		const state = installApiMock( woo, 'advanced' );
		render( <App /> );

		expect(
			await screen.findByText( 'Handling time, minimum days' )
		).toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save store settings' } )
		);
		expect(
			await screen.findByText( 'Store settings saved.' )
		).toBeInTheDocument();
		expect( state.posted?.shipping_enabled ).toBe( true );
		expect( state.posted?.variation_offers ).toBe( true );
	} );
} );
