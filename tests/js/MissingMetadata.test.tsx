import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import {
	AiQueueScreen,
	DEFAULT_POLICY,
} from '../../src/admin/views/AiQueueView';
import {
	MissingMetadataScreen,
	type MissingFilters,
	type MissingPreviewItem,
	type MissingRow,
} from '../../src/admin/views/MissingMetadataView';
import type { AiAction, AuditEntry } from '../../src/shared/types';

const filters: MissingFilters = {
	missing: '',
	postType: '',
	search: '',
};

function row( overrides: Partial< MissingRow > = {} ): MissingRow {
	return {
		id: 10,
		post_type: 'post',
		type_label: 'Posts',
		title: 'Front door',
		permalink: 'https://example.com/door/',
		edit_link: 'https://example.com/wp-admin/post.php?post=10&action=edit',
		seo_title: '',
		description: '',
		focus_keyword: '',
		image_id: 11,
		image_alt: '',
		missing: [ 'title', 'description', 'image_alt' ],
		...overrides,
	};
}

function preview(): MissingPreviewItem[] {
	return [
		{
			id: 10,
			title: 'Front door',
			changes: [
				{
					field: 'title',
					before: '',
					after: 'Front door | Example',
					pattern: '%title% %sep% %sitename%',
				},
			],
		},
	];
}

describe( 'Missing metadata screen', () => {
	function renderScreen(
		extra: Partial< Parameters< typeof MissingMetadataScreen >[ 0 ] > = {}
	) {
		const onFilters = jest.fn();
		const onPreview = jest.fn();
		const onApply = jest.fn();
		const onQueue = jest.fn();
		const onSave = jest.fn();
		render(
			<MissingMetadataScreen
				uiMode="advanced"
				items={ [
					row(),
					row( {
						id: 12,
						title: 'About',
						post_type: 'page',
						type_label: 'Pages',
						image_id: null,
						missing: [ 'description' ],
					} ),
				] }
				total={ 2 }
				page={ 1 }
				filters={ filters }
				postTypes={ [
					{ name: 'post', label: 'Posts' },
					{ name: 'page', label: 'Pages' },
				] }
				connected
				preview={ null }
				notice=""
				error=""
				busy={ false }
				onFilters={ onFilters }
				onPage={ jest.fn() }
				onPreview={ onPreview }
				onApply={ onApply }
				onDismissPreview={ jest.fn() }
				onQueue={ onQueue }
				onSave={ onSave }
				{ ...extra }
			/>
		);
		return { onFilters, onPreview, onApply, onQueue, onSave };
	}

	it( 'filters by the missing field', () => {
		const { onFilters } = renderScreen();
		fireEvent.change( screen.getByLabelText( 'Show', { exact: false } ), {
			target: { value: 'image_alt' },
		} );
		expect( onFilters ).toHaveBeenCalledWith( {
			missing: 'image_alt',
			postType: '',
			search: '',
		} );
	} );

	it( 'previews a local template fill and applies the shown change', () => {
		const { onPreview } = renderScreen();
		fireEvent.click(
			screen.getByRole( 'checkbox', { name: 'Select Front door' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Fill with local templates' } )
		);
		expect( onPreview ).toHaveBeenCalledWith(
			[ 10 ],
			[ 'title', 'description', 'image_alt' ]
		);

		const { onApply } = renderScreen( { preview: preview() } );
		expect(
			screen.getByRole( 'dialog', { name: 'Review the local fill' } )
		).toHaveTextContent( 'Front door | Example' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply these details' } )
		);
		expect( onApply ).toHaveBeenCalledWith(
			[ 10 ],
			[ 'title', 'description', 'image_alt' ]
		);
	} );

	it( 'queues a draft only when RankMySEO is connected', () => {
		const { onQueue } = renderScreen();
		fireEvent.click(
			screen.getByRole( 'checkbox', { name: 'Select Front door' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Draft with RankMySEO AI' } )
		);
		expect( onQueue ).toHaveBeenCalledWith( [ 10 ] );
		cleanup();

		renderScreen( { connected: false } );
		expect(
			screen.getByText( /Local templates work without a connection/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Draft with RankMySEO AI' } )
		).toBeDisabled();
	} );

	it( 'saves an inline edit for one row', () => {
		const { onSave } = renderScreen();
		fireEvent.click(
			screen.getAllByRole( 'button', { name: 'Quick edit' } )[ 0 ]
		);
		fireEvent.change(
			screen.getByLabelText( 'Search title for Front door' ),
			{
				target: { value: 'Custom title' },
			}
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Save' } ) );
		expect( onSave ).toHaveBeenCalledWith(
			10,
			expect.objectContaining( { title: 'Custom title' } )
		);
	} );
} );

describe( 'image description undo', () => {
	it( 'rolls back an applied image description', () => {
		const onRollback = jest.fn();
		const action: AiAction = {
			id: 8,
			cloud_action_id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
			action_type: 'image_alt',
			risk_tier: 'low',
			status: 'applied',
			wp_post_id: 11,
			target_url: 'https://example.com/door/',
			before: { imageAlt: 'Old alt' },
			after: { imageAlt: 'Front door of the shop' },
			rationale: 'Describe the photo.',
			title: 'Update the image description',
			before_text: 'Old alt',
			after_text: 'Front door of the shop',
		};
		const audit: AuditEntry[] = [];
		render(
			<AiQueueScreen
				uiMode="simple"
				canApprove
				connected
				actions={ [ action ] }
				policy={ DEFAULT_POLICY }
				audit={ audit }
				statusFilter=""
				typeFilter=""
				showJson={ false }
				onStatusFilter={ jest.fn() }
				onTypeFilter={ jest.fn() }
				onToggleJson={ jest.fn() }
				onApprove={ jest.fn() }
				onReject={ jest.fn() }
				onRollback={ onRollback }
				onPolicyChange={ jest.fn() }
				onSavePolicy={ jest.fn() }
				onSync={ jest.fn() }
			/>
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Undo / Rollback' } )
		);
		expect( onRollback ).toHaveBeenCalledWith( 8 );
	} );
} );
