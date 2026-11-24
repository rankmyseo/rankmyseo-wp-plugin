import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import type { BuilderEditorConfig } from '../../src/builders/BuilderSeoApp';
import { mountDiviPanel, mountElementorPanel } from '../../src/builders/mount';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockedFetch = apiFetch as unknown as jest.Mock;

const settings: BuilderEditorConfig = {
	uiMode: 'simple',
	siteName: 'Acme',
	siteUrl: 'https://example.com/',
	separator: '|',
	nonce: 'nonce-wp_rest',
	restRoot: 'https://example.com/wp-json/rankmyseo/v1',
	postId: 42,
	postTitle: 'Pricing',
	permalink: 'https://example.com/pricing/',
	fields: { title: 'Pricing' },
};

describe( 'builder mounts', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		mockedFetch.mockReset();
		mockedFetch.mockResolvedValue( {
			meta: { title: 'Pricing' },
			matches: [],
		} );
	} );

	it( 'adds a RankMySEO control inside the Elementor panel', async () => {
		document.body.innerHTML =
			'<div id="elementor-panel"><div id="elementor-panel-footer-tools"></div><iframe id="elementor-preview-iframe"></iframe></div>';
		const iframe = document.getElementById(
			'elementor-preview-iframe'
		) as HTMLIFrameElement;
		const preview = iframe.contentDocument?.body;
		if ( preview ) {
			preview.innerHTML =
				'<div class="elementor"><p>Pricing plans</p></div>';
		}

		const cleanup = mountElementorPanel( document, settings );
		const button = screen.getByRole( 'button', { name: 'RankMySEO' } );
		expect(
			document.getElementById( 'elementor-panel-footer-tools' )
		).toContainElement( button );

		await act( async () => {
			fireEvent.click( button );
		} );
		await waitFor( () => {
			expect(
				screen.getByRole( 'heading', { name: 'Search preview' } )
			).toBeInTheDocument();
		} );
		await act( async () => {
			cleanup();
		} );
	} );

	it( 'mounts when the Elementor panel appears after the old 15 second cutoff', async () => {
		document.body.innerHTML = '<div id="elementor-shell"></div>';
		const cleanup = mountElementorPanel( document, settings );
		expect(
			document.getElementById( 'rankmyseo-elementor-tab' )
		).toBeNull();

		const shell = document.getElementById( 'elementor-shell' );
		const panel = document.createElement( 'div' );
		panel.id = 'elementor-panel';
		const header = document.createElement( 'div' );
		header.className = 'elementor-panel-navigation';
		panel.appendChild( header );
		shell?.appendChild( panel );

		await waitFor( () => {
			expect(
				document.getElementById( 'rankmyseo-elementor-tab' )
			).toBeTruthy();
		} );
		expect( header ).toContainElement(
			document.getElementById( 'rankmyseo-elementor-tab' )
		);

		cleanup();
		expect(
			document.getElementById( 'rankmyseo-elementor-tab' )
		).toBeNull();
	} );

	it( 'mounts from elementor/panel/init and removes the hook on cleanup', () => {
		const actions: Record< string, Array< () => void > > = {};
		const removed: string[] = [];
		const runtime = globalThis as typeof globalThis & {
			MutationObserver: typeof MutationObserver;
		};
		const OriginalObserver = runtime.MutationObserver;
		runtime.MutationObserver = class {
			observe(): void {
				return undefined;
			}
			disconnect(): void {
				return undefined;
			}
			takeRecords(): MutationRecord[] {
				return [];
			}
		} as unknown as typeof MutationObserver;

		(
			window as Window & {
				elementor?: {
					hooks: {
						addAction: (
							name: string,
							callback: () => void
						) => void;
						removeAction: (
							name: string,
							callback: () => void
						) => void;
					};
				};
			}
		 ).elementor = {
			hooks: {
				addAction( name: string, callback: () => void ) {
					actions[ name ] = actions[ name ] ?? [];
					actions[ name ].push( callback );
				},
				removeAction( name: string ) {
					removed.push( name );
					actions[ name ] = [];
				},
			},
		};

		document.body.innerHTML = '<div id="elementor-shell"></div>';
		const cleanup = mountElementorPanel( document, settings );
		expect(
			document.getElementById( 'rankmyseo-elementor-tab' )
		).toBeNull();

		const shell = document.getElementById( 'elementor-shell' );
		shell?.insertAdjacentHTML(
			'beforeend',
			'<div id="elementor-panel"><div id="elementor-panel-header"></div></div>'
		);
		actions[ 'elementor/panel/init' ]?.[ 0 ]?.();

		const button = document.getElementById( 'rankmyseo-elementor-tab' );
		expect( button ).toBeTruthy();
		expect(
			document.getElementById( 'elementor-panel-header' )
		).toContainElement( button );

		cleanup();
		expect( removed ).toContain( 'elementor/panel/init' );
		expect(
			document.getElementById( 'rankmyseo-elementor-tab' )
		).toBeNull();

		delete ( window as Window & { elementor?: unknown } ).elementor;
		runtime.MutationObserver = OriginalObserver;
	} );

	it( 'does not throw when the Elementor panel is missing', () => {
		document.body.innerHTML = '<div id="unrelated"></div>';
		let cleanup = (): void => undefined;
		expect( () => {
			cleanup = mountElementorPanel( document, settings );
		} ).not.toThrow();
		expect(
			screen.queryByRole( 'button', { name: 'RankMySEO' } )
		).not.toBeInTheDocument();
		cleanup();
	} );

	it( 'docks a Divi drawer on the builder chrome and falls back when it is missing', async () => {
		document.body.innerHTML =
			'<div id="et-fb-app"><div class="et-fb-page-settings-bar"></div><div class="et-fb-post-content"><p>Plans</p></div></div>';
		const cleanup = mountDiviPanel( document, settings );
		const button = screen.getByRole( 'button', { name: 'RankMySEO' } );
		expect(
			document.querySelector( '.et-fb-page-settings-bar' )
		).toContainElement( button );
		await act( async () => {
			fireEvent.click( button );
		} );
		await waitFor( () => {
			expect(
				screen.getByRole( 'dialog', {
					name: 'RankMySEO search settings',
				} )
			).toBeInTheDocument();
		} );
		fireEvent.keyDown( document, { key: 'Escape' } );
		expect(
			document.getElementById( 'rankmyseo-divi-drawer' )
		).toHaveAttribute( 'hidden' );
		await act( async () => {
			cleanup();
		} );

		document.body.innerHTML = '<main></main>';
		const fallback = mountDiviPanel( document, settings );
		expect( document.querySelector( '.rankmyseo-divi-slot' ) ).toHaveClass(
			'is-fallback'
		);
		fallback();
	} );
} );
