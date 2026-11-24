import { fireEvent, render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { EditorPanel } from '../../src/editor/components/EditorPanel';
import { CloudInsights } from '../../src/editor/components/CloudInsights';
import { emptyFields } from '../../src/editor/fields';
import {
	deltaLabel,
	insightMatchesUrl,
	positionLabel,
	type EditorInsight,
} from '../../src/editor/insights';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const variables = [
	{ token: '%title%', label: 'Title' },
	{ token: '%sep%', label: 'Separator' },
	{ token: '%sitename%', label: 'Site name' },
];

function insight( overrides: Partial< EditorInsight > = {} ): EditorInsight {
	return {
		connected: true,
		matched: true,
		keyword: {
			keyword: 'front door',
			position: 4,
			outside_top_20: false,
			positions_gained: 3,
		},
		page_action: {
			action: 'tune',
			label: 'Tune',
			summary: 'Small edits can help this page.',
		},
		pending_count: 1,
		issue_count: 0,
		queue_url:
			'https://example.com/wp-admin/admin.php?page=rankmyseo-ai-queue',
		...overrides,
	};
}

describe( 'connected editor insights', () => {
	it( 'names a rank, an outside-top-20 result, and a movement', () => {
		expect(
			positionLabel( {
				keyword: 'front door',
				position: 4,
				outside_top_20: false,
				positions_gained: 3,
			} )
		).toBe( '#4' );
		expect(
			positionLabel( {
				keyword: 'front door',
				position: null,
				outside_top_20: true,
				positions_gained: null,
			} )
		).toBe( 'Outside top 20' );
		expect(
			positionLabel( {
				keyword: 'front door',
				position: null,
				outside_top_20: false,
				positions_gained: null,
			} )
		).toBe( '' );
		expect( deltaLabel( 3 ) ).toBe( 'Up 3' );
		expect( deltaLabel( -2 ) ).toBe( 'Down 2' );
		expect( deltaLabel( 0 ) ).toBe( 'No change' );
		expect( deltaLabel( null ) ).toBe( '' );
		expect(
			insightMatchesUrl(
				'https://example.com/door',
				'https://example.com/door/',
				null,
				9
			)
		).toBe( true );
		expect(
			insightMatchesUrl(
				'https://other.example/door',
				'https://example.com/door/',
				null,
				9
			)
		).toBe( false );
	} );

	it( 'shows the position and verdict beside the score without calling the network', () => {
		const onAskAi = jest.fn();
		render(
			<EditorPanel
				mode="simple"
				onModeChange={ jest.fn() }
				fields={ emptyFields() }
				onChange={ jest.fn() }
				siteName="Example"
				siteUrl="https://example.com/"
				separator="|"
				permalink="https://example.com/door"
				postTitle="Front door"
				variables={ variables }
				onAskAi={ onAskAi }
				insight={ insight() }
			/>
		);
		expect( screen.getByText( '#4' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Up 3' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Tune' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Small edits can help this page.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Review pending suggestions' } )
		).toHaveAttribute(
			'href',
			'https://example.com/wp-admin/admin.php?page=rankmyseo-ai-queue'
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Ask about this result' } )
		);
		expect( onAskAi ).toHaveBeenCalled();
		expect( apiFetch ).not.toHaveBeenCalled();
		expect( screen.queryByText( /traffic/i ) ).not.toBeInTheDocument();
		expect( screen.queryByText( /volume/i ) ).not.toBeInTheDocument();
	} );

	it( 'says outside top 20 only when the cache says so, and hides an unmatched page', () => {
		const { rerender } = render(
			<CloudInsights
				insight={ insight( {
					keyword: {
						keyword: 'front door',
						position: null,
						outside_top_20: true,
						positions_gained: null,
					},
					page_action: null,
					pending_count: 0,
				} ) }
				onAskAi={ jest.fn() }
			/>
		);
		expect( screen.getByText( 'Outside top 20' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Open approval queue' } )
		).toBeInTheDocument();

		rerender(
			<CloudInsights
				insight={ insight( { connected: true, matched: false } ) }
				onAskAi={ jest.fn() }
			/>
		);
		expect( screen.queryByText( '#4' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Outside top 20' )
		).not.toBeInTheDocument();
	} );
} );
