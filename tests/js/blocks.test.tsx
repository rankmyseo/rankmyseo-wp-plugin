import { fireEvent, render, screen } from '@testing-library/react';
import { BreadcrumbEdit } from '../../src/blocks/breadcrumbs/edit';
import { FaqEdit } from '../../src/blocks/faq/edit';
import { registerRankMySEOBlocks } from '../../src/blocks/register';

describe( 'visible breadcrumb and FAQ blocks', () => {
	it( 'edits the breadcrumb separator and visibility', () => {
		const setAttributes = jest.fn();
		render(
			<BreadcrumbEdit
				attributes={ {
					separator: '/',
					showHome: true,
					showCurrent: true,
				} }
				setAttributes={ setAttributes }
				isSelected
			/>
		);

		expect(
			screen.getByRole( 'navigation', { name: 'Breadcrumb' } )
		).toBeInTheDocument();
		fireEvent.change( screen.getByLabelText( 'Separator' ), {
			target: { value: '>' },
		} );
		expect( setAttributes ).toHaveBeenCalledWith( { separator: '>' } );

		fireEvent.click( screen.getByLabelText( 'Show home' ) );
		expect( setAttributes ).toHaveBeenCalledWith( { showHome: false } );
		fireEvent.click( screen.getByLabelText( 'Show current page' ) );
		expect( setAttributes ).toHaveBeenCalledWith( { showCurrent: false } );
	} );

	it( 'adds, edits, and reorders FAQ pairs', () => {
		const setAttributes = jest.fn();
		const questions = [
			{ question: 'First?', answer: 'One.' },
			{ question: 'Second?', answer: 'Two.' },
		];
		render(
			<FaqEdit
				attributes={ { questions, layout: 'details' } }
				setAttributes={ setAttributes }
				isSelected
			/>
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Add question' } )
		);
		expect( setAttributes ).toHaveBeenCalledWith( {
			questions: [ ...questions, { question: '', answer: '' } ],
		} );

		fireEvent.change( screen.getAllByLabelText( 'Question' )[ 0 ], {
			target: { value: 'Updated?' },
		} );
		expect( setAttributes ).toHaveBeenCalledWith( {
			questions: [
				{ question: 'Updated?', answer: 'One.' },
				questions[ 1 ],
			],
		} );

		fireEvent.click(
			screen.getAllByRole( 'button', { name: 'Move down' } )[ 0 ]
		);
		expect( setAttributes ).toHaveBeenCalledWith( {
			questions: [ questions[ 1 ], questions[ 0 ] ],
		} );
	} );

	it( 'registers both blocks on the editor global', () => {
		const registerBlockType = jest.fn();
		(
			globalThis as {
				wp?: {
					blocks?: { registerBlockType: typeof registerBlockType };
				};
			}
		 ).wp = { blocks: { registerBlockType } };

		registerRankMySEOBlocks();

		expect( registerBlockType ).toHaveBeenCalledWith(
			'rankmyseo/breadcrumbs',
			expect.objectContaining( { save: expect.any( Function ) } )
		);
		expect( registerBlockType ).toHaveBeenCalledWith(
			'rankmyseo/faq',
			expect.objectContaining( { edit: expect.any( Function ) } )
		);
	} );
} );
