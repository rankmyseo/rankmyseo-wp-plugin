import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { App } from '../../src/admin/App';
import type { SitemapStatus } from '../../src/shared/sitemaps';
import type {
	PluginSettings,
	SettingsResponse,
	StatusResponse,
	UiMode,
	UiPreferencesResponse,
} from '../../src/shared/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

function createSitemapStatus(
	overrides: Partial< SitemapStatus > = {}
): SitemapStatus {
	return {
		module_enabled: true,
		blog_public: true,
		sitemap_url: 'https://example.com/sitemap_index.xml',
		xsl_url: 'https://example.com/rankmyseo-sitemap.xsl',
		max_urls: 1000,
		include_images: true,
		include_authors: true,
		allow_crawl: true,
		hide_utility_pages: false,
		post_types: [
			{
				name: 'post',
				label: 'Posts',
				included: true,
				available: true,
			},
			{
				name: 'page',
				label: 'Pages',
				included: true,
				available: true,
			},
			{
				name: 'product',
				label: 'Products',
				included: true,
				available: false,
			},
		],
		taxonomies: [
			{
				name: 'category',
				label: 'Categories',
				included: true,
				available: true,
			},
		],
		excluded_post_ids: [],
		excluded_term_ids: [],
		crawl: {
			redirect_attachments: false,
			noindex_attachments: false,
			noindex_search: false,
			noindex_comment_pages: false,
			strip_replytocom: false,
			remove_rsd: false,
			remove_wlw: false,
			remove_shortlink: false,
		},
		indexnow: {
			enabled: false,
			key: 'a'.repeat( 32 ),
			key_url: `https://example.com/${ 'a'.repeat( 32 ) }.txt`,
			endpoint: 'https://api.indexnow.org/indexnow',
			log: [],
		},
		robots: {
			mode: 'preset',
			preset: 'default',
			custom: '',
			preview:
				'User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: https://example.com/sitemap_index.xml\n',
			physical_file: false,
			physical_path: '/var/www/html/robots.txt',
			warnings: [],
		},
		llms: {
			enabled: false,
			url: 'https://example.com/llms.txt',
			intro: '',
			include_urls: '',
			exclude_urls: '',
			post_types: [
				{
					name: 'post',
					label: 'Posts',
					included: true,
					available: true,
					sitemap_included: true,
				},
				{
					name: 'page',
					label: 'Pages',
					included: true,
					available: true,
					sitemap_included: true,
				},
				{
					name: 'product',
					label: 'Products',
					included: false,
					available: false,
					sitemap_included: false,
				},
			],
			site_name: 'Acme Store',
			summary: 'Shoes and care',
			home_url: 'https://example.com/',
			groups: [
				{
					label: 'Site',
					links: [
						{
							title: 'Acme Store',
							url: 'https://example.com/',
							description: '',
						},
					],
				},
			],
			preview: '# Acme Store\n\n> Shoes and care\n',
			link_count: 1,
			physical_file: false,
			physical_path: '/var/www/html/llms.txt',
			warnings: [],
		},
		...overrides,
	};
}

function installAdmin( uiMode: UiMode ): void {
	window.rankmyseoAdmin = {
		version: '0.1.0',
		restUrl: '/wp-json/rankmyseo/v1',
		nonce: 'test-nonce',
		adminUrl: '/wp-admin/admin.php',
		siteName: 'Acme Store',
		siteUrl: 'https://example.com',
		blogPublic: true,
		initialPage: 'sitemaps',
		uiMode,
		defaultUiMode: 'simple',
		wizardCompleted: true,
		wizardDismissed: true,
		canManage: true,
		canApproveAi: true,
	};
}

function settingsFixture(): {
	settings: PluginSettings;
	settingsResponse: SettingsResponse;
	statusResponse: StatusResponse;
	uiPrefs: UiPreferencesResponse;
} {
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
		},
	};

	return {
		settings,
		settingsResponse: {
			settings,
			modules: [
				{
					id: 'sitemaps',
					name: 'Sitemaps & Crawl',
					description: 'XML sitemaps',
					simple_description: 'Site map',
					enabled: true,
					enabled_by_default: true,
					ready: true,
					requires_cloud: false,
					booted: true,
				},
			],
		},
		statusResponse: {
			plugin_version: '0.1.0',
			php_version: '8.3.6',
			wp_version: '6.8',
			site: {
				name: 'Acme Store',
				url: 'https://example.com/',
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
			onboarding: {
				wizard_completed: true,
				wizard_dismissed: true,
			},
			capabilities: { can_manage: true, can_approve_ai: true },
		},
		uiPrefs: {
			ui_mode: 'simple',
			default_ui_mode: 'simple',
			wizard_completed: true,
			wizard_dismissed: true,
			can_manage: true,
			can_approve_ai: true,
		},
	};
}

describe( 'Sitemaps & Crawl screen', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
	} );

	it( 'shows the simple sitemap card, inclusion toggles, IndexNow explanation, and crawl safety toggle', async () => {
		installAdmin( 'simple' );
		const fixtures = settingsFixture();
		let sitemap = createSitemapStatus();

		mockedApiFetch.mockImplementation( async ( options ) => {
			const path = options.path ?? '';
			if ( path === '/rankmyseo/v1/sitemaps/status' ) {
				return sitemap;
			}
			const method = ( options.method ?? 'GET' ).toUpperCase();
			if (
				path === '/rankmyseo/v1/sitemaps/settings' &&
				method === 'POST'
			) {
				const data = ( options.data ?? {} ) as {
					indexnow_enabled?: boolean;
				};
				sitemap = createSitemapStatus( {
					indexnow: {
						...sitemap.indexnow,
						enabled:
							data.indexnow_enabled ?? sitemap.indexnow.enabled,
					},
				} );
				return { updated: true, settings: {}, status: sitemap };
			}
			if ( path === '/rankmyseo/v1/settings' ) {
				return fixtures.settingsResponse;
			}
			if ( path === '/rankmyseo/v1/status' ) {
				return fixtures.statusResponse;
			}
			if ( path === '/rankmyseo/v1/ui-preferences' ) {
				return { ...fixtures.uiPrefs, ui_mode: 'simple' as const };
			}
			throw new Error( `Unexpected API call: ${ method } ${ path }` );
		} );

		render( <App /> );

		expect(
			await screen.findByText( 'Sitemap is active' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', {
				name: 'https://example.com/sitemap_index.xml',
			} )
		).toHaveAttribute( 'href', 'https://example.com/sitemap_index.xml' );
		expect( screen.getByLabelText( 'Include Posts' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Include Pages' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Include Products' ) ).toBeChecked();
		expect(
			screen.getByText( /https:\/\/api\.indexnow\.org\/indexnow/ )
		).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Enable IndexNow instant indexing' )
		).not.toBeChecked();
		expect(
			screen.getByLabelText( 'Let search engines crawl my public pages' )
		).toBeChecked();
		expect(
			screen.getByLabelText( 'AI site guide off' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'List Posts' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Intro note' ) ).toBeInTheDocument();

		fireEvent.click(
			screen.getByLabelText( 'Enable IndexNow instant indexing' )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/rankmyseo/v1/sitemaps/settings',
					method: 'POST',
					data: { indexnow_enabled: true },
				} )
			);
		} );
	} );

	it( 'shows the advanced matrix, robots editor, and Disallow guardrail', async () => {
		installAdmin( 'advanced' );
		const fixtures = settingsFixture();
		const base = createSitemapStatus();
		const sitemap = createSitemapStatus( {
			robots: {
				...base.robots,
				physical_file: true,
				warnings: [
					{
						code: 'physical_file',
						message:
							'A physical robots.txt file is present at /var/www/html/robots.txt. WordPress will serve that file, so these virtual rules will not run.',
					},
				],
			},
			llms: {
				...base.llms,
				physical_file: true,
				warnings: [
					{
						code: 'physical_file',
						message:
							'A physical llms.txt file is present at /var/www/html/llms.txt. The web server will serve that file.',
					},
				],
			},
		} );

		mockedApiFetch.mockImplementation( async ( options ) => {
			const path = options.path ?? '';
			if ( path === '/rankmyseo/v1/sitemaps/status' ) {
				return sitemap;
			}
			if ( path === '/rankmyseo/v1/settings' ) {
				return fixtures.settingsResponse;
			}
			if ( path === '/rankmyseo/v1/status' ) {
				return fixtures.statusResponse;
			}
			if ( path === '/rankmyseo/v1/ui-preferences' ) {
				return { ...fixtures.uiPrefs, ui_mode: 'advanced' as const };
			}
			throw new Error(
				`Unexpected API call: ${ options.method } ${ path }`
			);
		} );

		render( <App /> );

		expect(
			await screen.findByLabelText( 'URLs per sitemap' )
		).toHaveValue( 1000 );
		expect( screen.getByLabelText( 'Include Posts' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Include Categories' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'IndexNow key' ) ).toHaveValue(
			'a'.repeat( 32 )
		);
		expect(
			screen.getByRole( 'button', { name: 'Rotate key' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Ping URL now' } )
		).toBeDisabled();
		expect(
			screen.getByText( /physical robots\.txt file is present/ )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Intro note' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Links to leave out, one address per line' )
		).toBeInTheDocument();
		expect( screen.getAllByText( 'Live preview' ).length ).toBeGreaterThan(
			1
		);
		expect(
			screen.getByText( /physical llms\.txt file is present/ )
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'radio', { name: 'Custom rules' } )
		);
		fireEvent.change( screen.getByLabelText( 'Custom robots.txt' ), {
			target: { value: 'User-agent: *\nDisallow: /\n' },
		} );

		expect(
			screen.getByText(
				'These rules include Disallow: /, which blocks crawlers from the entire site.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByText(
				/Sitemap: https:\/\/example.com\/sitemap_index.xml/
			)
		).toBeInTheDocument();
	} );
} );
