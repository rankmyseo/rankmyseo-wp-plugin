import { fireEvent, render, screen } from '@testing-library/react';
import { EditorPanel } from '../../src/editor/components/EditorPanel';
import { emptyFields } from '../../src/editor/fields';
import {
	estimatePixelWidth,
	meterStatus,
	truncateToPixels,
} from '../../src/editor/metrics';
import { replaceTemplate } from '../../src/editor/template';

const variables = [
	{ token: '%title%', label: 'Title' },
	{ token: '%sep%', label: 'Separator' },
	{ token: '%sitename%', label: 'Site name' },
];

function renderPanel( mode: 'simple' | 'advanced' = 'simple' ) {
	const onAskAi = jest.fn();
	const onModeChange = jest.fn();
	const onChange = jest.fn();
	const view = render(
		<EditorPanel
			mode={ mode }
			onModeChange={ onModeChange }
			fields={ {
				...emptyFields(),
				title: 'Pricing plans',
				description:
					'Compare plans for growing teams and agencies today.',
				ogTitle: 'Share pricing',
				ogDescription: 'A social description',
				ogImage: 'https://example.com/og.jpg',
			} }
			onChange={ onChange }
			siteName="Acme"
			siteUrl="https://example.com/"
			separator="|"
			permalink="https://example.com/pricing"
			postTitle="Pricing"
			postExcerpt="Plans for teams"
			variables={ variables }
			onAskAi={ onAskAi }
		/>
	);
	return { ...view, onAskAi, onModeChange, onChange };
}

describe( 'search preview metrics', () => {
	it( 'estimates width, status, and pixel truncation', () => {
		expect( estimatePixelWidth( '', 20 ) ).toBe( 0 );
		expect( meterStatus( 0, 30, 60, 0, 580 ) ).toBe( 'empty' );
		expect( meterStatus( 10, 30, 60, 80, 580 ) ).toBe( 'short' );
		expect( meterStatus( 40, 30, 60, 400, 580 ) ).toBe( 'good' );
		expect( meterStatus( 40, 30, 60, 700, 580 ) ).toBe( 'long' );
		expect( meterStatus( 80, 30, 60, 400, 580 ) ).toBe( 'long' );

		const truncated = truncateToPixels( 'A'.repeat( 80 ), 20, 100 );
		expect( truncated.endsWith( '…' ) ).toBe( true );
		expect( estimatePixelWidth( truncated, 20 ) ).toBeLessThanOrEqual(
			100
		);
	} );

	it( 'replaces tokens and drops an empty separator', () => {
		expect(
			replaceTemplate( '%title% %sep% %sitename%', {
				title: 'Pricing',
				sep: '|',
				sitename: 'Acme',
			} )
		).toBe( 'Pricing | Acme' );
		expect(
			replaceTemplate( '%title% %sep% %page%', {
				title: 'Pricing',
				sep: '|',
				page: '',
			} )
		).toBe( 'Pricing' );
	} );
} );

describe( 'editor search panel', () => {
	it( 'shows desktop and mobile previews plus a social card', () => {
		renderPanel();
		expect(
			screen.getByRole( 'article', { name: 'Desktop Google preview' } )
		).toHaveTextContent( 'Pricing plans' );
		expect(
			screen.getByRole( 'article', { name: 'Mobile Google preview' } )
		).toBeInTheDocument();
		fireEvent.click( screen.getByText( 'Social image' ) );
		expect(
			screen.getByRole( 'article', { name: 'Facebook preview' } )
		).toHaveTextContent( 'Share pricing' );
	} );

	it( 'keeps canonical and robots in Advanced and asks AI from either mode', () => {
		const simple = renderPanel( 'simple' );
		expect(
			screen.queryByLabelText( 'Canonical URL' )
		).not.toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'radio', { name: 'Advanced' } ) );
		expect( simple.onModeChange ).toHaveBeenCalledWith( 'advanced' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Ask AI about this page' } )
		);
		expect( simple.onAskAi ).toHaveBeenCalled();

		simple.unmount();
		renderPanel( 'advanced' );
		fireEvent.click( screen.getByRole( 'tab', { name: 'Advanced' } ) );
		expect( screen.getByLabelText( 'Canonical URL' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Hide from search' )
		).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'tab', { name: 'Social' } ) );
		expect(
			screen.getByRole( 'article', { name: 'X preview' } )
		).toBeInTheDocument();
	} );
} );
