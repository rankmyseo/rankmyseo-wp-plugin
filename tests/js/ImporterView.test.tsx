/**
 * Tests for the migration importer screen.
 */

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { ImporterView } from '../../src/admin/views/ImporterView';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockMode: { value: 'simple' | 'advanced' } = { value: 'simple' };

jest.mock( '../../src/admin/context/AdminContext', () => ( {
	useAdmin: () => ( { uiMode: mockMode.value } ),
} ) );

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

function requestPath( options: unknown ): string {
	if ( typeof options === 'string' ) {
		return options;
	}
	if ( options && typeof options === 'object' && 'path' in options ) {
		return String( ( options as { path: string } ).path );
	}
	return '';
}

function requestData( options: unknown ): Record< string, unknown > {
	if ( options && typeof options === 'object' && 'data' in options ) {
		return ( options as { data: Record< string, unknown > } ).data;
	}
	return {};
}

describe( 'ImporterView', () => {
	beforeEach( () => {
		mockMode.value = 'simple';
		mockedApiFetch.mockReset();
	} );

	function mockReads( posts = 142 ): void {
		mockedApiFetch.mockImplementation( ( options ) => {
			const path = requestPath( options );
			if ( path.endsWith( '/importer/detect' ) ) {
				return Promise.resolve( {
					sources: [
						{
							id: 'yoast',
							label: 'Yoast SEO',
							active: true,
							found: true,
							posts,
							terms: 4,
							redirects: 2,
							settings: 1,
						},
						{
							id: 'rankmath',
							label: 'Rank Math',
							active: false,
							found: false,
							posts: 0,
							terms: 0,
							redirects: 0,
							settings: 0,
						},
					],
					nothing_deleted: true,
				} );
			}
			if ( path.endsWith( '/importer/history' ) ) {
				return Promise.resolve( {
					imports: [
						{
							import_id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
							source: 'yoast',
							status: 'completed',
							started_at: '2026-01-01 00:00:00',
							finished_at: '2026-01-01 00:01:00',
							posts: 2,
							terms: 0,
							redirects: 0,
							settings: 0,
							written: 3,
							skipped: 0,
							overwrite: false,
						},
					],
				} );
			}
			if ( path.endsWith( '/importer/preview' ) ) {
				return Promise.resolve( {
					source: 'yoast',
					label: 'Yoast SEO',
					active: true,
					found: true,
					posts,
					terms: 4,
					redirects: 2,
					settings: 1,
					conflicts: 0,
					samples: [
						{
							object_type: 'post',
							object_id: 1,
							label: 'Hello',
							field: '_rankmyseo_title',
							field_label: 'SEO title',
							before: '',
							after: '%title%',
							action: 'write',
							unsupported: [],
						},
					],
					nothing_deleted: true,
				} );
			}
			if ( path.endsWith( '/importer/run' ) ) {
				const data = requestData( options );
				const cursor = String( data.cursor ?? '' );
				if ( cursor === '' ) {
					return Promise.resolve( {
						import_id: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
						source: 'yoast',
						cursor: 'posts:2',
						next_cursor: 'posts:2',
						done: false,
						processed: 2,
						written: 2,
						skipped: 0,
						phase: 'posts',
						batch_size: 100,
						nothing_deleted: true,
					} );
				}
				return Promise.resolve( {
					import_id: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
					source: 'yoast',
					cursor: '',
					next_cursor: '',
					done: true,
					processed: 1,
					written: 1,
					skipped: 0,
					phase: '',
					batch_size: 100,
					nothing_deleted: true,
				} );
			}
			if ( path.endsWith( '/importer/rollback' ) ) {
				return Promise.resolve( {
					import_id: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
					status: 'rolled_back',
					restored: 1,
					nothing_deleted: true,
				} );
			}
			return Promise.reject( new Error( `Unexpected ${ path }` ) );
		} );
	}

	it( 'shows the detected plugin and keeps source data', async () => {
		mockReads();
		render( <ImporterView /> );

		expect(
			await screen.findByText(
				'We found Yoast SEO settings on 142 posts — nothing will be deleted'
			)
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'The original plugin can stay active. Its data will not be deleted.'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'checkbox', { name: 'Post meta' } )
		).not.toBeInTheDocument();
	} );

	it( 'previews, imports in chunks, and undoes the batch', async () => {
		mockReads();
		render( <ImporterView /> );
		await screen.findByRole( 'button', { name: /Yoast SEO/ } );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Dry-run preview' } )
		);
		expect(
			await screen.findByText(
				'142 posts, 4 terms, 2 redirects, and 1 site settings are ready to import.'
			)
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Import these settings' } )
		);
		expect(
			await screen.findByText(
				'Import finished. The original plugin data is still there.'
			)
		).toBeInTheDocument();

		const runCalls = mockedApiFetch.mock.calls.filter( ( call ) =>
			requestPath( call[ 0 ] ).endsWith( '/importer/run' )
		);
		expect( runCalls ).toHaveLength( 2 );
		expect( requestData( runCalls[ 1 ][ 0 ] ).cursor ).toBe( 'posts:2' );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Undo import' } )
		);
		await waitFor( () => {
			expect(
				mockedApiFetch.mock.calls.some( ( call ) =>
					requestPath( call[ 0 ] ).endsWith( '/importer/rollback' )
				)
			).toBe( true );
		} );
		expect(
			await screen.findByText( 'That import was undone.' )
		).toBeInTheDocument();
	} );

	it( 'shows advanced mapping, options, and history rollback', async () => {
		mockMode.value = 'advanced';
		mockReads( 3 );
		render( <ImporterView /> );

		expect(
			await screen.findByRole( 'checkbox', { name: 'Post meta' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', {
				name: 'Overwrite existing RankMySEO values',
			} )
		).not.toBeChecked();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Dry-run preview' } )
		);
		expect(
			await screen.findByRole( 'table', {
				name: 'Sample of what will change',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( 'SEO title' ) ).toBeInTheDocument();
		expect( screen.getByText( '%title%' ) ).toBeInTheDocument();

		const historyUndo = screen.getAllByRole( 'button', {
			name: 'Undo import',
		} );
		fireEvent.click( historyUndo[ 0 ] );
		await waitFor( () => {
			expect(
				mockedApiFetch.mock.calls.some(
					( call ) =>
						requestPath( call[ 0 ] ).endsWith(
							'/importer/rollback'
						) &&
						requestData( call[ 0 ] ).import_id ===
							'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'
				)
			).toBe( true );
		} );
	} );

	it( 'explains when no other plugin data is present', async () => {
		mockedApiFetch.mockImplementation( ( options ) => {
			const path = requestPath( options );
			if ( path.endsWith( '/importer/detect' ) ) {
				return Promise.resolve( {
					sources: [],
					nothing_deleted: true,
				} );
			}
			return Promise.resolve( { imports: [] } );
		} );
		render( <ImporterView /> );
		expect(
			await screen.findByText(
				'We didn’t find settings from another SEO plugin.'
			)
		).toBeInTheDocument();
	} );
} );
