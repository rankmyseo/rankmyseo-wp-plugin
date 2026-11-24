import { act, fireEvent, render, screen } from '@testing-library/react';
import {
	linkHtml,
	LinkSuggestions,
} from '../../src/editor/components/LinkSuggestions';

const rows = [
	{
		id: 4,
		title: 'Pruning saw',
		url: 'https://example.com/pruning-saw/',
		score: 67,
		reasons: [ 'shared_keyword', 'cornerstone', 'orphan' ],
		cornerstone: true,
		orphan: true,
	},
];

describe( 'link suggestions', () => {
	it( 'copies and inserts a ranked local link', async () => {
		const writeText = jest.fn().mockResolvedValue( undefined );
		Object.assign( navigator, {
			clipboard: { writeText },
		} );
		const onInsert = jest.fn();
		render(
			<LinkSuggestions
				postId={ 1 }
				focusKeyword="garden tools"
				secondaryKeywords={ [] }
				title="Garden tools"
				slug="garden-tools"
				suggestions={ rows }
				onInsert={ onInsert }
			/>
		);

		expect( screen.getByText( 'Pruning saw' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Same keyword · Cornerstone · No incoming links' )
		).toBeInTheDocument();

		await act( async () => {
			fireEvent.click(
				screen.getByRole( 'button', { name: 'Copy link' } )
			);
		} );
		expect( writeText ).toHaveBeenCalledWith(
			'https://example.com/pruning-saw/'
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Insert link' } )
		);
		expect( onInsert ).toHaveBeenCalledWith(
			'https://example.com/pruning-saw/',
			'Pruning saw'
		);
		expect( linkHtml( rows[ 0 ].url, rows[ 0 ].title ) ).toBe(
			'<a href="https://example.com/pruning-saw/">Pruning saw</a>'
		);
	} );
} );
