import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { App } from '../../src/admin/App';
import {
	installAdminBootstrap,
	mockedApiFetch,
	setupApiMock,
} from './adminApiMock';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

describe( 'Setup wizard', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		installAdminBootstrap();
	} );

	it( 'saves site identity, module toggles, and a local skip', async () => {
		setupApiMock( undefined, ( path, method, data ) => {
			if (
				path === '/rankmyseo/v1/onboarding/complete' &&
				method === 'POST'
			) {
				const payload = data as {
					site_name: string;
					modules: { schema: boolean };
					skip_cloud: boolean;
				};
				return {
					completed: true,
					onboarding_completed: true,
					site_identity: {
						site_type: 'organization',
						site_name: payload.site_name,
						logo_id: 0,
						logo_url: '',
						title_separator: '|',
						skipped_cloud: payload.skip_cloud,
					},
					modules: payload.modules,
					blog_public: true,
					cloud_connected: false,
					skipped_cloud: true,
					settings: {
						default_ui_mode: 'simple',
						site_type: 'organization',
						site_name: payload.site_name,
						title_separator: '|',
						keep_data_on_uninstall: true,
						telemetry_opt_in: false,
						wizard_completed: true,
						wizard_dismissed: true,
						modules: {
							meta: true,
							analyzer: true,
							schema: payload.modules.schema,
							sitemaps: true,
							redirects: true,
							importer: true,
							cloud: false,
							ai_queue: false,
							woocommerce: true,
						},
					},
					module_catalog: [],
				};
			}
			return undefined;
		} );

		render( <App /> );

		expect(
			await screen.findByText( '1. Site Basics' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Logo URL' ) ).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Continue' } ) );

		expect(
			screen.getByRole( 'heading', {
				name: '2. Core modules and indexing',
			} )
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'checkbox', {
				name: 'Structured data for your business',
			} )
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Continue' } ) );

		fireEvent.click(
			screen.getByRole( 'button', {
				name: 'Skip for now — keep using locally',
			} )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/rankmyseo/v1/onboarding/complete',
				method: 'POST',
				data: expect.objectContaining( {
					site_type: 'organization',
					site_name: 'Acme Store',
					logo_url: '',
					skip_cloud: true,
					modules: expect.objectContaining( {
						meta: true,
						schema: false,
						sitemaps: true,
						redirects: true,
					} ),
				} ),
			} );
		} );

		expect(
			screen.queryByText( '1. Site Basics' )
		).not.toBeInTheDocument();
	} );
} );
