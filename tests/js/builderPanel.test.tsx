import { act, fireEvent, render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { BuilderSeoApp } from '../../src/builders/BuilderSeoApp';
import type { LiveDocument } from '../../src/builders/content';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedFetch = apiFetch as unknown as jest.Mock;

const settings = {
	uiMode: 'advanced' as const,
	siteName: 'Acme',
	siteUrl: 'https://example.com/',
	separator: '|',
	tagline: 'Tools',
	variables: [ { token: '%title%', label: 'Title' } ],
	restRoot: 'https://example.com/wp-json/rankmyseo/v1',
	nonce: 'nonce-wp_rest',
	locale: 'en',
	postId: 42,
	postType: 'page',
	postTitle: 'Pricing',
	permalink: 'https://example.com/pricing/',
	fields: {
		title: 'Pricing',
		description: 'Plans for teams',
		focus_keyword: 'garden tools',
	},
};

function live( html: string ): LiveDocument {
	return {
		html,
		title: 'Pricing',
		slug: 'pricing',
		postId: 42,
	};
}

describe( 'builder SEO panel', () => {
	beforeEach( () => {
		mockedFetch.mockReset();
		mockedFetch.mockImplementation(
			async ( options: { path?: string; method?: string } ) => {
				const path = options?.path ?? '';
				if (
					path.includes( '/meta/posts/42' ) &&
					'POST' !== options?.method
				) {
					return {
						meta: settings.fields,
					};
				}
				if ( path.includes( 'cannibalization' ) ) {
					return { matches: [] };
				}
				return { meta: settings.fields };
			}
		);
	} );

	it( 'saves edited fields through the shared post meta endpoint', async () => {
		jest.useFakeTimers();
		const readDocument = jest.fn( () =>
			live( '<h1>Garden tools</h1><p>garden tools for the yard</p>' )
		);
		render(
			<BuilderSeoApp
				settings={ settings }
				readDocument={ readDocument }
			/>
		);

		await act( async () => {
			await Promise.resolve();
		} );

		fireEvent.change( screen.getByLabelText( 'Search title' ), {
			target: { value: 'Garden tools for yards' },
		} );

		await act( async () => {
			jest.advanceTimersByTime( 500 );
			await Promise.resolve();
		} );

		const post = mockedFetch.mock.calls
			.map( ( call ) => call[ 0 ] )
			.find(
				( options ) =>
					options?.method === 'POST' &&
					String( options.path ).includes( '/meta/posts/42' )
			);
		expect( post?.data.title ).toBe( 'Garden tools for yards' );
		expect( post?.data.focus_keyword ).toBe( 'garden tools' );
		expect( post?.data.og_title ).toBe( '' );
		expect(
			screen.getByRole( 'article', { name: 'Desktop Google preview' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'region', { name: 'SEO score' } )
		).toBeInTheDocument();
		jest.useRealTimers();
	} );

	it( 'scores the live builder HTML', async () => {
		const readDocument = () =>
			live(
				'<h1>Garden tools</h1><p>Garden tools help a small yard. Garden tools stay sharp.</p>'
			);
		render(
			<BuilderSeoApp
				settings={ settings }
				readDocument={ readDocument }
			/>
		);
		await act( async () => {
			await Promise.resolve();
		} );
		expect(
			screen.getByRole( 'region', { name: 'SEO score' } )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Focus keyword' ) ).toHaveValue(
			'garden tools'
		);
	} );
} );
