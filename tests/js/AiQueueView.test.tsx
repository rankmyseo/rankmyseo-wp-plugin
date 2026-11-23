import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import {
	AiQueueScreen,
	AiQueueView,
	DEFAULT_POLICY,
} from '../../src/admin/views/AiQueueView';
import type { AiAction, AiPolicy, AuditEntry } from '../../src/shared/types';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

jest.mock( '../../src/admin/context/AdminContext', () => ( {
	useAdmin: () => ( {
		uiMode: 'advanced',
		status: {
			cloud: { connected: true, tier: 'cloud' },
			site: {
				url: 'https://example.com/',
				name: 'Example',
				blog_public: true,
			},
		},
	} ),
} ) );

function sample( overrides: Partial< AiAction > = {} ): AiAction {
	return {
		id: 3,
		cloud_action_id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
		action_type: 'update_title',
		risk_tier: 'low',
		status: 'pending',
		wp_post_id: 9,
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

const audit: AuditEntry[] = [
	{
		id: 1,
		event_type: 'ai_action_applied',
		actor_type: 'user',
		summary: 'Update the search title',
		created_at: '2026-11-23 12:00:00',
	},
];

describe( 'AiQueueScreen', () => {
	const policy: AiPolicy = {
		...DEFAULT_POLICY,
		enabled: false,
	};

	function renderScreen(
		uiMode: 'simple' | 'advanced',
		actions: AiAction[] = [ sample() ]
	) {
		const onApprove = jest.fn();
		const onSavePolicy = jest.fn();
		const onPolicyChange = jest.fn();
		render(
			<AiQueueScreen
				uiMode={ uiMode }
				canApprove
				connected
				actions={ actions }
				policy={ policy }
				audit={ audit }
				statusFilter=""
				typeFilter=""
				showJson={ false }
				onStatusFilter={ jest.fn() }
				onTypeFilter={ jest.fn() }
				onToggleJson={ jest.fn() }
				onApprove={ onApprove }
				onReject={ jest.fn() }
				onRollback={ jest.fn() }
				onPolicyChange={ onPolicyChange }
				onSavePolicy={ onSavePolicy }
				onSync={ jest.fn() }
			/>
		);
		return { onApprove, onSavePolicy, onPolicyChange };
	}

	it( 'keeps Simple mode to plain cards with undo and hides the policy table', () => {
		renderScreen( 'simple', [
			sample(),
			sample( {
				id: 4,
				status: 'applied',
				title: 'Applied change',
			} ),
			sample( { id: 5, status: 'rejected', title: 'Skipped change' } ),
		] );

		expect(
			screen.getByText(
				'Nothing changes until you approve it. Undo puts the previous version back.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Approve & Apply' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Undo / Rollback' } )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Skipped change' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Audit log' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'content_patch · Approval only' )
		).not.toBeInTheDocument();
	} );

	it( 'shows filters, a locked content change, and the audit log in Advanced mode', () => {
		const { onSavePolicy, onPolicyChange } = renderScreen( 'advanced' );

		expect(
			screen.getByRole( 'combobox', { name: 'Status' } )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'content_patch · Approval only' )
		).toBeInTheDocument();
		expect( screen.getByText( 'ai_action_applied' ) ).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'checkbox', {
				name: 'Allow selected changes to apply on their own',
			} )
		);
		expect( onPolicyChange ).toHaveBeenCalledWith(
			expect.objectContaining( { enabled: true } )
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save auto-apply settings' } )
		);
		expect( onSavePolicy ).toHaveBeenCalled();
	} );
} );

describe( 'AiQueueView', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		window.rankmyseoAdmin = {
			version: '0.1.0',
			restUrl: '/wp-json/rankmyseo/v1',
			nonce: 'test-nonce',
			adminUrl: '/wp-admin/admin.php',
			siteName: 'Example',
			siteUrl: 'https://example.com/',
			blogPublic: true,
			initialPage: 'ai-queue',
			uiMode: 'advanced',
			defaultUiMode: 'advanced',
			wizardCompleted: true,
			wizardDismissed: true,
			canManage: true,
			canApproveAi: true,
		};
		mockedApiFetch.mockImplementation( async ( options ) => {
			const path =
				typeof options === 'string' ? options : options.path ?? '';
			if ( path.startsWith( '/rankmyseo/v1/ai-actions/policy' ) ) {
				return { policy: DEFAULT_POLICY };
			}
			if ( path.startsWith( '/rankmyseo/v1/ai-actions/audit-log' ) ) {
				return { entries: audit };
			}
			if ( path.startsWith( '/rankmyseo/v1/ai-actions' ) ) {
				return { actions: [ sample() ] };
			}
			throw new Error( `Unexpected API call: ${ path }` );
		} );
	} );

	it( 'loads the queue and approves a card', async () => {
		render( <AiQueueView /> );
		expect(
			await screen.findByRole( 'article', {
				name: 'Update the search title',
			} )
		).toBeInTheDocument();

		mockedApiFetch.mockImplementation( async ( options ) => {
			const path =
				typeof options === 'string' ? options : options.path ?? '';
			if ( path.endsWith( '/approve' ) ) {
				return {
					message: 'Applied. You can undo this change.',
					action: sample( { status: 'applied' } ),
				};
			}
			return { actions: [], policy: DEFAULT_POLICY, entries: [] };
		} );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Approve & Apply' } )
		);
		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/rankmyseo/v1/ai-actions/3/approve',
					method: 'POST',
				} )
			);
		} );
		expect(
			await screen.findByRole( 'button', { name: 'Undo / Rollback' } )
		).toBeInTheDocument();
	} );
} );
