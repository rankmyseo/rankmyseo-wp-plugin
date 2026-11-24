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
	SchemaPreviewResponse,
	SchemaSettings,
	SchemaSettingsResponse,
} from '../../src/admin/views/schema/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

function schemaSettings(): SchemaSettings {
	return {
		entity_type: 'organization',
		name: 'Acme Store',
		description: 'We sell mugs',
		logo_url: 'https://acme.example/logo.png',
		telephone: '+1-555-0100',
		same_as: [ 'https://acme.example/social' ],
		search_action: true,
		currency: 'USD',
		post_type_schema: {
			post: 'BlogPosting',
			page: 'WebPage',
			product: 'Product',
		},
		woocommerce_product: true,
		local_business_enabled: false,
		local_business_type: 'LocalBusiness',
		local_business_name: '',
		local_business_telephone: '',
		local_business_price_range: '',
		address_street: '',
		address_city: '',
		address_region: '',
		address_postal_code: '',
		address_country: '',
		geo_latitude: '',
		geo_longitude: '',
		opening_hours: [],
	};
}

function settingsResponse(): SchemaSettingsResponse {
	return {
		settings: schemaSettings(),
		postTypes: [
			{ name: 'post', label: 'Post' },
			{ name: 'page', label: 'Page' },
		],
		schemaTypes: [
			{ value: 'BlogPosting', label: 'Blog post' },
			{ value: 'Article', label: 'Article' },
			{ value: 'WebPage', label: 'Web page' },
			{ value: 'Product', label: 'Product' },
			{ value: 'none', label: 'None' },
		],
		localBusinessTypes: [
			{ value: 'LocalBusiness', label: 'Local business' },
			{ value: 'Restaurant', label: 'Restaurant' },
		],
		woocommerceActive: true,
		moduleEnabled: true,
		filters: [
			'rankmyseo_schema_graph',
			'rankmyseo_schema_piece_organization',
		],
	};
}

function previewResponse(): SchemaPreviewResponse {
	return {
		postId: 0,
		url: 'https://acme.example/',
		enabled: true,
		graph: {
			'@context': 'https://schema.org',
			'@graph': [
				{
					'@type': 'Organization',
					'@id': 'https://acme.example/#organization',
					name: 'Acme Store',
				},
			],
		},
		conflicts: [
			{
				id: 'woocommerce',
				level: 'info',
				message:
					'WooCommerce product markup is turned off while RankMySEO product schema is on, so product pages are not described twice.',
			},
		],
	};
}

function installApiMock(): { settings: SchemaSettings } {
	const state = {
		settings: schemaSettings(),
		schema: settingsResponse(),
		ui: {
			ui_mode: 'simple',
			default_ui_mode: 'simple',
			wizard_completed: true,
			wizard_dismissed: true,
			can_manage: true,
			can_approve_ai: true,
		} as UiPreferencesResponse,
	};

	const pluginSettings: PluginSettings = {
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

	const pluginResponse: SettingsResponse = {
		settings: pluginSettings,
		modules: [
			{
				id: 'schema',
				name: 'Structured Data (Schema)',
				description: 'JSON-LD graph',
				simple_description: 'Site details',
				enabled: true,
				enabled_by_default: true,
				ready: true,
				requires_cloud: false,
				booted: true,
			},
		],
	};

	const status: StatusResponse = {
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
			items: pluginResponse.modules,
		},
		cloud: { connected: false, tier: 'local' },
		onboarding: { wizard_completed: true, wizard_dismissed: true },
		capabilities: { can_manage: true, can_approve_ai: true },
	};

	mockedApiFetch.mockImplementation( async ( options ) => {
		const path = options.path ?? '';
		const method = ( options.method ?? 'GET' ).toUpperCase();

		if ( path === '/rankmyseo/v1/settings' && method === 'GET' ) {
			return pluginResponse;
		}
		if ( path === '/rankmyseo/v1/status' && method === 'GET' ) {
			return status;
		}
		if ( path === '/rankmyseo/v1/ui-preferences' && method === 'GET' ) {
			return state.ui;
		}
		if ( path === '/rankmyseo/v1/ui-preferences' && method === 'POST' ) {
			state.ui = {
				...state.ui,
				...( options.data as Partial< UiPreferencesResponse > ),
			};
			return state.ui;
		}
		if ( path === '/rankmyseo/v1/schema/settings' && method === 'GET' ) {
			return { ...state.schema, settings: state.settings };
		}
		if ( path === '/rankmyseo/v1/schema/settings' && method === 'POST' ) {
			state.settings = {
				...state.settings,
				...( options.data as SchemaSettings ),
			};
			return { ...state.schema, settings: state.settings, updated: true };
		}
		if (
			path.startsWith( '/rankmyseo/v1/schema/preview' ) &&
			method === 'GET'
		) {
			return previewResponse();
		}
		if ( path === '/rankmyseo/v1/schema/validate' && method === 'POST' ) {
			return {
				valid: true,
				message: 'This JSON-LD is valid and safe to store on a page.',
			};
		}

		throw new Error( `Unexpected API call: ${ method } ${ path }` );
	} );

	return state;
}

describe( 'Schema settings screen', () => {
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
			initialPage: 'schema',
			uiMode: 'simple',
			defaultUiMode: 'simple',
			wizardCompleted: true,
			wizardDismissed: true,
			canManage: true,
			canApproveAi: true,
		};
	} );

	it( 'shows the simple identity card, product toggle, and FAQ note', async () => {
		installApiMock();
		render( <App /> );

		expect(
			await screen.findByRole( 'button', {
				name: /Business or organization/i,
			} )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /Person/i } )
		).toHaveAttribute( 'aria-pressed', 'false' );

		fireEvent.click( screen.getByRole( 'button', { name: /^Person/ } ) );
		expect(
			screen.getByRole( 'button', { name: /^Person/ } )
		).toHaveAttribute( 'aria-pressed', 'true' );

		expect(
			screen.getByLabelText( 'Logo image address' )
		).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Default type for posts' )
		).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Describe products automatically' )
		).toBeInTheDocument();
		expect( screen.getByText( /May 7, 2026/ ) ).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save site details' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/rankmyseo/v1/schema/settings',
					method: 'POST',
					data: expect.objectContaining( {
						entity_type: 'person',
						name: 'Acme Store',
						woocommerce_product: true,
					} ),
				} )
			);
		} );
	} );

	it( 'shows the advanced mapping table, preview, copy button, and conflict notice', async () => {
		const writeText = jest.fn().mockResolvedValue( undefined );
		Object.defineProperty( navigator, 'clipboard', {
			configurable: true,
			value: { writeText },
		} );
		installApiMock();
		render( <App /> );

		fireEvent.click(
			await screen.findByRole( 'radio', { name: 'Advanced' } )
		);

		expect(
			await screen.findByRole( 'columnheader', { name: 'Schema type' } )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Street address' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Publish local business details' )
		).toBeInTheDocument();
		expect( screen.getByText( /not described twice/ ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'JSON-LD preview' ) ).toHaveTextContent(
			'https://schema.org'
		);
		expect(
			screen.getByText( 'rankmyseo_schema_graph' )
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Copy JSON-LD' } )
		);

		await waitFor( () => {
			expect( writeText ).toHaveBeenCalledWith(
				expect.stringContaining( '"@context": "https://schema.org"' )
			);
		} );
		expect(
			screen.getByRole( 'button', { name: 'Copied JSON-LD' } )
		).toBeInTheDocument();
	} );
} );
