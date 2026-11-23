import { fireEvent, render, screen } from '@testing-library/react';
import { InlineActionCard } from '../../src/admin/components/InlineActionCard';
import type { AiAction } from '../../src/shared/types';

function action( overrides: Partial< AiAction > = {} ): AiAction {
	return {
		id: 7,
		cloud_action_id: '11111111-1111-4111-8111-111111111111',
		action_type: 'update_title',
		risk_tier: 'low',
		status: 'pending',
		wp_post_id: 4,
		target_url: 'https://example.com/about/',
		before: { current: 'Old title' },
		after: { suggested: 'New title' },
		rationale: 'Clearer in search results.',
		title: 'Update the search title',
		before_text: 'Old title',
		after_text: 'New title',
		...overrides,
	};
}

describe( 'InlineActionCard', () => {
	it( 'shows a word diff and approves, edits, or rejects a pending change', () => {
		const onApprove = jest.fn();
		const onReject = jest.fn();
		render(
			<InlineActionCard
				action={ action() }
				onApprove={ onApprove }
				onReject={ onReject }
			/>
		);

		expect( screen.getByText( 'Low' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Old' ) ).toBeInTheDocument();
		expect( screen.getByText( 'New' ) ).toBeInTheDocument();
		expect( document.querySelector( 'del' ) ).toHaveTextContent( 'Old' );
		expect( document.querySelector( 'ins' ) ).toHaveTextContent( 'New' );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Approve & Apply' } )
		);
		expect( onApprove ).toHaveBeenCalledWith( 7, undefined );

		fireEvent.click( screen.getByRole( 'button', { name: 'Edit' } ) );
		fireEvent.change( screen.getByRole( 'textbox' ), {
			target: { value: 'Edited title' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Approve & Apply' } )
		);
		expect( onApprove ).toHaveBeenLastCalledWith( 7, {
			suggested: 'Edited title',
		} );

		fireEvent.click( screen.getByRole( 'button', { name: 'Reject' } ) );
		expect( onReject ).toHaveBeenCalledWith( 7 );
	} );

	it( 'asks for a second click on a high-risk change and offers rollback after apply', () => {
		const onApprove = jest.fn();
		const onRollback = jest.fn();
		const { rerender } = render(
			<InlineActionCard
				action={ action( {
					action_type: 'content_patch',
					risk_tier: 'high',
					title: 'Update the page content',
					before_text: 'Old body',
					after_text: 'New body',
				} ) }
				onApprove={ onApprove }
				onRollback={ onRollback }
			/>
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Approve & Apply' } )
		);
		expect( onApprove ).not.toHaveBeenCalled();
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Confirm apply' } )
		);
		expect( onApprove ).toHaveBeenCalledWith( 7, undefined );

		rerender(
			<InlineActionCard
				action={ action( { status: 'applied', risk_tier: 'high' } ) }
				onRollback={ onRollback }
			/>
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Undo / Rollback' } )
		);
		expect( onRollback ).toHaveBeenCalledWith( 7 );
	} );
} );
