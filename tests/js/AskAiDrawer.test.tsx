import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { App } from '../../src/admin/App';
import {
	installAdminBootstrap,
	mockedApiFetch,
	setupApiMock,
} from './adminApiMock';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const localReply = {
	tier: 'local',
	offline: true,
	intent: 'missing_descriptions',
	explanation: 'Three posts are missing a search description.',
	items: [
		{
			id: 7,
			title: 'Pricing',
			slug: 'pricing',
			type: 'page',
			edit_link:
				'https://example.com/wp-admin/post.php?post=7&action=edit',
			page: 'meta',
			tip: 'Write a short search description.',
			seo_score: null,
			word_count: 120,
			hits: null,
		},
	],
	follow_ups: [
		{
			id: 'thin_posts',
			label: 'Thin posts',
			prompt: 'Show thin posts',
		},
	],
	cloud_handoff: null,
	action_cards: [],
	operator_slot: { enabled: false, mode: 'local' },
};

describe( 'Ask AI drawer', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		installAdminBootstrap();
		window.rankmyseoAdmin = {
			...window.rankmyseoAdmin!,
			wizardDismissed: true,
		};
	} );

	it( 'shows a structured local reply, follow-up, and no operator slot', async () => {
		setupApiMock( { wizard_dismissed: true }, ( path, method ) => {
			if ( path === '/rankmyseo/v1/chat/local' && method === 'POST' ) {
				return localReply;
			}
			return undefined;
		} );

		render( <App /> );

		fireEvent.keyDown( window, { key: 'j', ctrlKey: true } );

		expect(
			await screen.findByRole( 'dialog', {
				name: 'RankMySEO AI Assistant',
			} )
		).toBeInTheDocument();

		fireEvent.change(
			screen.getByLabelText( 'Ask a question about your site SEO' ),
			{ target: { value: 'Find posts missing meta descriptions' } }
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Send' } ) );

		expect(
			await screen.findByText(
				'Three posts are missing a search description.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Pricing' } )
		).toHaveAttribute(
			'href',
			'https://example.com/wp-admin/post.php?post=7&action=edit'
		);
		expect(
			screen.getByText( 'Write a short search description.' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Connected assistant replies will appear here.'
			)
		).not.toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Thin posts' } ) );

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/rankmyseo/v1/chat/local',
					method: 'POST',
					data: expect.objectContaining( {
						message: 'Show thin posts',
					} ),
				} )
			);
		} );
	} );
} );
