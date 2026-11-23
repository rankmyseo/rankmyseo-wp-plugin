import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { App } from '../../src/admin/App';
import {
	installAdminBootstrap,
	mockedApiFetch,
	setupApiMock,
} from './adminApiMock';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

describe( 'Command bar', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		installAdminBootstrap();
		window.rankmyseoAdmin = {
			...window.rankmyseoAdmin!,
			uiMode: 'advanced',
			wizardDismissed: true,
		};
	} );

	it( 'opens with Ctrl+K, jumps to a screen, and toggles a module', async () => {
		setupApiMock( { wizard_dismissed: true, ui_mode: 'advanced' } );
		render( <App /> );

		expect(
			await screen.findByRole( 'button', { name: 'Commands' } )
		).toBeInTheDocument();

		fireEvent.keyDown( window, { key: 'k', ctrlKey: true } );

		expect(
			screen.getByRole( 'dialog', { name: 'Command bar' } )
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Go to Settings' } )
		);

		expect(
			await screen.findByRole( 'button', { name: 'Save Settings' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'dialog', { name: 'Command bar' } )
		).not.toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Commands' } ) );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Turn off On-Page & Titles' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/rankmyseo/v1/settings',
					method: 'POST',
					data: { modules: { meta: false } },
				} )
			);
		} );

		fireEvent.click( screen.getByRole( 'button', { name: 'Commands' } ) );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Open Ask AI' } )
		);
		expect(
			screen.getByRole( 'dialog', { name: 'RankMySEO AI Assistant' } )
		).toBeInTheDocument();
	} );
} );
