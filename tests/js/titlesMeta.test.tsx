import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { App } from '../../src/admin/App';
import type {
	PluginSettings,
	SettingsResponse,
	StatusResponse,
	UiPreferencesResponse,
} from '../../src/shared/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

function settingsFixture(): PluginSettings {
	return {
		default_ui_mode: 'simple',
		site_type: 'organization',
		site_name: 'Acme',
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
}

function metaPayload() {
	return {
		settings: {
			separator: '|',
			site_name: 'Acme',
			tagline: 'Calm software',
			homepage: {
				title: '%sitename% %sep% %tagline%',
				description: '%tagline%',
			},
			post_types: {
				post: {
					title: '%title% %sep% %sitename%',
					description: '%excerpt%',
					noindex: false,
					label: 'Posts',
				},
				page: {
					title: '%title%',
					description: '',
					noindex: true,
					label: 'Pages',
				},
			},
			taxonomies: {},
			archives: {
				author: {
					title: '%author% %sep% %sitename%',
					description: '',
					noindex: false,
				},
				not_found: {
					title: 'Page not found %sep% %sitename%',
					description: '',
					noindex: true,
				},
			},
			social: { default_image: 'https://example.com/share.jpg' },
			reading: { show_on_front: 'posts' },
		},
		variables: [
			{ token: '%sitename%', label: 'Site name' },
			{ token: '%sep%', label: 'Separator' },
		],
		separators: [ '-', '|', '·' ],
		conflict: false,
		head_paused: false,
		site_name: 'Acme',
	};
}

describe( 'Titles & Meta admin screen', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		const settings = settingsFixture();
		const settingsResponse: SettingsResponse = {
			settings,
			modules: [],
		};
		const statusResponse = {
			plugin_version: '0.1.0',
			php_version: '8.2',
			wp_version: '6.6',
			site: {
				name: 'Acme',
				url: 'https://example.com',
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
		} satisfies StatusResponse;
		const uiPrefsResponse: UiPreferencesResponse = {
			ui_mode: 'simple',
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
			if ( path === '/rankmyseo/v1/settings' && method === 'POST' ) {
				return settingsResponse;
			}
			if ( path === '/rankmyseo/v1/status' ) {
				return statusResponse;
			}
			if ( path === '/rankmyseo/v1/ui-preferences' && method === 'GET' ) {
				return uiPrefsResponse;
			}
			if (
				path === '/rankmyseo/v1/ui-preferences' &&
				method === 'POST'
			) {
				const data = ( options.data ?? {} ) as {
					ui_mode?: 'simple' | 'advanced';
				};
				if ( data.ui_mode ) {
					uiPrefsResponse.ui_mode = data.ui_mode;
				}
				return uiPrefsResponse;
			}
			if ( path === '/rankmyseo/v1/meta/settings' && method === 'GET' ) {
				return metaPayload();
			}
			if ( path === '/rankmyseo/v1/meta/settings' && method === 'POST' ) {
				return { ...metaPayload(), updated: true };
			}
			throw new Error( `Unexpected API call: ${ method } ${ path }` );
		} );

		window.rankmyseoAdmin = {
			version: '0.1.0',
			restUrl: '/wp-json/rankmyseo/v1',
			nonce: 'test-nonce',
			adminUrl: '/wp-admin/admin.php',
			siteName: 'Acme',
			siteUrl: 'https://example.com/',
			blogPublic: true,
			initialPage: 'meta',
			uiMode: 'simple',
			defaultUiMode: 'simple',
			wizardCompleted: true,
			wizardDismissed: true,
			canManage: true,
			canApproveAi: true,
		};
	} );

	it( 'shows the simple homepage preview and hides archive templates', async () => {
		render( <App /> );
		expect(
			await screen.findByRole( 'heading', { name: 'Search previews' } )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Homepage title' ) ).toHaveValue(
			'%sitename% %sep% %tagline%'
		);
		expect(
			screen.getByRole( 'article', { name: 'Desktop Google preview' } )
		).toHaveTextContent( 'Acme | Calm software' );
		expect( screen.getByLabelText( 'Show Posts in Google' ) ).toBeChecked();
		expect(
			screen.getByLabelText( 'Show Pages in Google' )
		).not.toBeChecked();
		expect(
			screen.queryByLabelText( 'Author archive' )
		).not.toBeInTheDocument();
	} );

	it( 'reveals templates in Advanced mode and saves them', async () => {
		render( <App /> );
		await screen.findByRole( 'heading', { name: 'Search previews' } );
		fireEvent.click(
			screen.getAllByRole( 'radio', { name: 'Advanced' } )[ 0 ]
		);

		expect(
			await screen.findByRole( 'heading', { name: 'Titles & Meta' } )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Author archive' ) ).toBeInTheDocument();
		expect( screen.getByText( /Post preview:/ ) ).toHaveTextContent(
			'Sample page | Acme'
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save search previews' } )
		);
		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/rankmyseo/v1/meta/settings',
				method: 'POST',
				data: expect.objectContaining( {
					separator: '|',
					homepage: {
						title: '%sitename% %sep% %tagline%',
						description: '%tagline%',
					},
				} ),
			} );
		} );
		expect(
			await screen.findByText( 'Search previews saved.' )
		).toBeInTheDocument();
	} );
} );
