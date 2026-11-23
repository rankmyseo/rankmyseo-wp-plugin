import { act, fireEvent, render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { AnalyzerPanel } from '../../src/editor/components/AnalyzerPanel';
import { EditorPanel } from '../../src/editor/components/EditorPanel';
import { emptyFields } from '../../src/editor/fields';
import type { AnalyzerInput } from '../../src/analyzer/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const variables = [
	{ token: '%title%', label: 'Title' },
	{ token: '%sep%', label: 'Separator' },
	{ token: '%sitename%', label: 'Site name' },
];

const emptyInput: AnalyzerInput = {
	focusKeyword: '',
	seoTitle: '',
	metaDescription: '',
	slug: '',
	contentHtml: '',
};

function mockedFetch(): jest.Mock {
	return apiFetch as unknown as jest.Mock;
}

describe( 'analyzer panel', () => {
	beforeEach( () => {
		mockedFetch().mockReset();
		mockedFetch().mockResolvedValue( { matches: [] } );
	} );

	it( 'shows plain tips in simple mode and exact counts in advanced mode', () => {
		const { rerender } = render(
			<AnalyzerPanel
				input={ emptyInput }
				mode="simple"
				debounceMs={ 0 }
			/>
		);
		expect(
			screen.getByRole( 'img', { name: /no keyword/i } )
		).toBeInTheDocument();
		expect(
			screen.getByText( /choose the main phrase/i )
		).toBeInTheDocument();
		expect( screen.queryByText( /Density/ ) ).not.toBeInTheDocument();

		rerender(
			<AnalyzerPanel
				input={ emptyInput }
				mode="advanced"
				debounceMs={ 0 }
			/>
		);
		expect(
			screen.getByRole( 'heading', { name: 'Keyword' } )
		).toBeInTheDocument();
		expect(
			screen.getByText( /Focus keyword is empty/ )
		).toBeInTheDocument();
		expect( screen.getAllByText( 'Critical' ).length ).toBeGreaterThan( 0 );
		expect( screen.getAllByText( 'Improve' ).length ).toBeGreaterThan( 0 );
	} );

	it( 'debounces a new score until the editor pauses', () => {
		jest.useFakeTimers();
		const onAnalysis = jest.fn();
		const { rerender } = render(
			<AnalyzerPanel
				input={ emptyInput }
				mode="simple"
				debounceMs={ 300 }
				onAnalysis={ onAnalysis }
			/>
		);
		const first = screen.getByRole( 'img' ).getAttribute( 'aria-label' );
		rerender(
			<AnalyzerPanel
				input={ {
					...emptyInput,
					focusKeyword: 'garden tools',
					seoTitle: 'Garden tools for small city yards',
					contentHtml: '<p>Garden tools help.</p>',
				} }
				mode="simple"
				debounceMs={ 300 }
				onAnalysis={ onAnalysis }
			/>
		);
		expect( screen.getByRole( 'img' ).getAttribute( 'aria-label' ) ).toBe(
			first
		);
		act( () => {
			jest.advanceTimersByTime( 300 );
		} );
		expect(
			screen.getByRole( 'img' ).getAttribute( 'aria-label' )
		).not.toBe( first );
		expect( onAnalysis ).toHaveBeenCalled();
		jest.useRealTimers();
	} );

	it( 'warns when another page targets the same keyword', () => {
		render(
			<AnalyzerPanel
				input={ { ...emptyInput, focusKeyword: 'pricing' } }
				mode="simple"
				debounceMs={ 0 }
				matches={ [
					{
						id: 9,
						title: 'Other pricing',
						editLink:
							'https://example.com/wp-admin/post.php?post=9&action=edit',
						postType: 'page',
					},
				] }
			/>
		);
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			/already targets this focus keyword/i
		);
		expect(
			screen.getByRole( 'link', { name: 'Other pricing' } )
		).toHaveAttribute(
			'href',
			'https://example.com/wp-admin/post.php?post=9&action=edit'
		);
	} );
} );

describe( 'editor panel analyzer integration', () => {
	it( 'keeps the search preview and adds the score checklist', () => {
		const onChange = jest.fn();
		render(
			<EditorPanel
				mode="simple"
				onModeChange={ jest.fn() }
				fields={ {
					...emptyFields(),
					title: 'Pricing plans',
					description:
						'Compare plans for growing teams and agencies today.',
					focusKeyword: 'pricing plans',
				} }
				onChange={ onChange }
				siteName="Acme"
				siteUrl="https://example.com/"
				separator="|"
				permalink="https://example.com/pricing"
				postTitle="Pricing"
				variables={ variables }
				onAskAi={ jest.fn() }
				contentHtml="<h1>Pricing plans</h1><p>Pricing plans for teams.</p>"
				slug="pricing"
				postId={ 4 }
				cannibalizationMatches={ [
					{
						id: 8,
						title: 'Old pricing',
						editLink:
							'https://example.com/wp-admin/post.php?post=8&action=edit',
						postType: 'post',
					},
				] }
			/>
		);

		expect(
			screen.getByRole( 'article', { name: 'Desktop Google preview' } )
		).toHaveTextContent( 'Pricing plans' );
		expect(
			screen.getByRole( 'region', { name: 'SEO score' } )
		).toBeInTheDocument();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Old pricing'
		);
		expect( mockedFetch() ).not.toHaveBeenCalled();

		fireEvent.change( screen.getByLabelText( 'Focus keyword' ), {
			target: { value: 'team pricing' },
		} );
		expect( onChange ).toHaveBeenCalledWith(
			expect.objectContaining( { focusKeyword: 'team pricing' } )
		);
	} );

	it( 'shows the full breakdown in advanced mode without hiding social previews', () => {
		render(
			<EditorPanel
				mode="advanced"
				onModeChange={ jest.fn() }
				fields={ {
					...emptyFields(),
					title: 'Pricing plans',
					ogTitle: 'Share pricing',
					ogDescription: 'A social description',
					ogImage: 'https://example.com/og.jpg',
				} }
				onChange={ jest.fn() }
				siteName="Acme"
				siteUrl="https://example.com/"
				separator="|"
				permalink="https://example.com/pricing"
				postTitle="Pricing"
				variables={ variables }
				onAskAi={ jest.fn() }
				cannibalizationMatches={ [] }
			/>
		);
		expect(
			screen.getByRole( 'heading', { name: 'Readability' } )
		).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'tab', { name: 'Social' } ) );
		expect(
			screen.getByRole( 'article', { name: 'Facebook preview' } )
		).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'tab', { name: 'Advanced' } ) );
		expect( screen.getByLabelText( 'Canonical URL' ) ).toBeInTheDocument();
	} );
} );
