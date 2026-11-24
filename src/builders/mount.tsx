import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactElement } from 'react';
import { BuilderSeoApp, type BuilderEditorConfig } from './BuilderSeoApp';
import {
	DIVI_CHROME_SELECTORS,
	readDiviDocument,
	readElementorDocument,
	type ElementorLike,
} from './content';

interface MountedRoot {
	render: ( node: ReactElement ) => void;
	unmount: () => void;
}

function mountReact( host: HTMLElement, node: ReactElement ): MountedRoot {
	const root = createRoot( host );
	root.render( node );
	return root;
}

const ELEMENTOR_ANCHORS = [
	'#elementor-panel-footer-tools',
	'#elementor-panel-header-wrapper',
	'#elementor-panel-header',
	'.elementor-panel-header',
	'.elementor-panel-navigation',
	'.elementor-panel-header-buttons',
];

interface ElementorHookBag {
	addAction?: ( name: string, callback: () => void ) => void;
	removeAction?: ( name: string, callback: () => void ) => void;
}

interface ElementorEventBus {
	on?: ( event: string, callback: () => void ) => void;
	off?: ( event: string, callback: () => void ) => void;
	hooks?: ElementorHookBag;
}

interface JqueryEvented {
	on?: ( event: string, callback: () => void ) => void;
	off?: ( event: string, callback: () => void ) => void;
}

function findElementorAnchor( doc: Document, panel: HTMLElement ): HTMLElement {
	for ( const selector of ELEMENTOR_ANCHORS ) {
		const inPanel = panel.querySelector( selector );
		if ( inPanel instanceof HTMLElement ) {
			return inPanel;
		}
		const anywhere = doc.querySelector( selector );
		if ( anywhere instanceof HTMLElement ) {
			return anywhere;
		}
	}
	return panel;
}

function listenForElementorReady( onReady: () => void ): () => void {
	const cleanups: Array< () => void > = [];
	const win = window as Window & {
		elementor?: ElementorEventBus;
		$e?: { hooks?: ElementorHookBag };
		jQuery?: ( target: Window ) => JqueryEvented;
	};

	const bindHook = (
		hooks: ElementorHookBag | undefined,
		name: string
	): void => {
		if ( ! hooks || 'function' !== typeof hooks.addAction ) {
			return;
		}
		const callback = (): void => {
			onReady();
		};
		try {
			hooks.addAction( name, callback );
			if ( 'function' === typeof hooks.removeAction ) {
				cleanups.push( () => {
					hooks.removeAction?.( name, callback );
				} );
			}
		} catch {
			// Elementor hook signatures differ between major versions.
		}
	};

	bindHook( win.elementor?.hooks, 'elementor/panel/init' );
	bindHook( win.$e?.hooks, 'elementor/panel/init' );

	const bus = win.elementor;
	if ( bus && 'function' === typeof bus.on ) {
		const callback = (): void => {
			onReady();
		};
		try {
			bus.on( 'elementor:init', callback );
			bus.on( 'panel:init', callback );
			cleanups.push( () => {
				bus.off?.( 'elementor:init', callback );
				bus.off?.( 'panel:init', callback );
			} );
		} catch {
			// The editor event bus is optional.
		}
	}

	if ( 'function' === typeof win.jQuery ) {
		try {
			const target = win.jQuery( window );
			const callback = (): void => {
				onReady();
			};
			target.on?.( 'elementor:init', callback );
			cleanups.push( () => {
				target.off?.( 'elementor:init', callback );
			} );
		} catch {
			// jQuery is not required for the panel observer.
		}
	}

	return () => {
		cleanups.forEach( ( cleanup ) => {
			try {
				cleanup();
			} catch {
				// The editor may already have torn the bus down.
			}
		} );
	};
}

function elementorGlobal(): ElementorLike | null {
	const candidate = ( window as Window & { elementor?: ElementorLike } )
		.elementor;
	return candidate && typeof candidate === 'object' ? candidate : null;
}

export function mountElementorPanel(
	doc: Document,
	settings: BuilderEditorConfig
): () => void {
	let observer: MutationObserver | null = null;
	let root: MountedRoot | null = null;
	let stopped = false;

	const mount = (): boolean => {
		if ( stopped || doc.getElementById( 'rankmyseo-elementor-tab' ) ) {
			return Boolean( doc.getElementById( 'rankmyseo-elementor-tab' ) );
		}
		const panel = doc.getElementById( 'elementor-panel' );
		if ( ! panel ) {
			return false;
		}
		const tools = findElementorAnchor( doc, panel );
		const slot = doc.createElement( 'div' );
		slot.className = 'rms-root rankmyseo-elementor-slot';
		const button = doc.createElement( 'button' );
		button.id = 'rankmyseo-elementor-tab';
		button.type = 'button';
		button.className = 'rankmyseo-builder-tab';
		button.textContent = __( 'RankMySEO', 'rankmyseo' );
		button.setAttribute( 'aria-pressed', 'false' );
		button.setAttribute( 'aria-controls', 'rankmyseo-elementor-panel' );
		const host = doc.createElement( 'div' );
		host.id = 'rankmyseo-elementor-panel';
		host.className =
			'rankmyseo-builder-panel rankmyseo-elementor-panel rms-root';
		host.hidden = true;
		panel.appendChild( host );
		slot.appendChild( button );
		tools.appendChild( slot );

		const readDocument = (): ReturnType< typeof readElementorDocument > =>
			readElementorDocument( doc, elementorGlobal() );

		button.addEventListener( 'click', () => {
			const opening = host.hidden;
			host.hidden = ! opening;
			button.setAttribute( 'aria-pressed', opening ? 'true' : 'false' );
			if ( opening && ! root ) {
				try {
					root = mountReact(
						host,
						<BuilderSeoApp
							settings={ settings }
							readDocument={ readDocument }
						/>
					);
				} catch {
					host.hidden = true;
					button.setAttribute( 'aria-pressed', 'false' );
					return;
				}
			}
			if ( opening ) {
				host
					.querySelector< HTMLElement >(
						'input, textarea, button, select'
					)
					?.focus();
			} else {
				button.focus();
			}
		} );
		return true;
	};

	const stopListening = listenForElementorReady( () => {
		if ( mount() ) {
			observer?.disconnect();
			observer = null;
		}
	} );

	try {
		if (
			! mount() &&
			typeof MutationObserver !== 'undefined' &&
			( doc.documentElement || doc.body )
		) {
			observer = new MutationObserver( () => {
				if ( mount() ) {
					observer?.disconnect();
					observer = null;
				}
			} );
			observer.observe( doc.documentElement || doc.body, {
				childList: true,
				subtree: true,
			} );
		}
	} catch {
		// Elementor's panel markup differs between versions.
	}

	return () => {
		stopped = true;
		stopListening();
		observer?.disconnect();
		try {
			root?.unmount();
		} catch {
			// The editor may already have removed the host.
		}
		doc.getElementById( 'rankmyseo-elementor-slot' )?.remove();
		doc
			.getElementById( 'rankmyseo-elementor-tab' )
			?.closest( '.rankmyseo-elementor-slot' )
			?.remove();
		doc.getElementById( 'rankmyseo-elementor-panel' )?.remove();
	};
}

export function mountDiviPanel(
	doc: Document,
	settings: BuilderEditorConfig
): () => void {
	if ( ! doc.body ) {
		return () => undefined;
	}

	let root: MountedRoot | null = null;
	let observer: MutationObserver | null = null;
	const drawer = doc.createElement( 'div' );
	drawer.id = 'rankmyseo-divi-drawer';
	drawer.className = 'rms-root rankmyseo-divi-drawer';
	drawer.hidden = true;
	drawer.setAttribute( 'role', 'dialog' );
	drawer.setAttribute( 'aria-modal', 'false' );
	drawer.setAttribute(
		'aria-label',
		__( 'RankMySEO search settings', 'rankmyseo' )
	);

	const slot = doc.createElement( 'div' );
	slot.className = 'rms-root rankmyseo-divi-slot';
	const button = doc.createElement( 'button' );
	button.id = 'rankmyseo-divi-tab';
	button.type = 'button';
	button.className = 'rankmyseo-builder-tab';
	button.textContent = __( 'RankMySEO', 'rankmyseo' );
	button.setAttribute( 'aria-expanded', 'false' );
	button.setAttribute( 'aria-controls', 'rankmyseo-divi-drawer' );
	slot.appendChild( button );

	const place = (): void => {
		let anchor: Element | null = null;
		try {
			for ( const selector of DIVI_CHROME_SELECTORS ) {
				anchor = doc.querySelector( selector );
				if ( anchor ) {
					break;
				}
			}
		} catch {
			anchor = null;
		}
		slot.classList.toggle( 'is-fallback', ! anchor );
		try {
			( anchor ?? doc.body ).appendChild( slot );
			if ( ! drawer.isConnected ) {
				doc.body.appendChild( drawer );
			}
		} catch {
			// Divi removed the node while we were moving it.
		}
	};

	const onKey = ( event: KeyboardEvent ): void => {
		if ( 'Escape' === event.key && ! drawer.hidden ) {
			drawer.hidden = true;
			button.setAttribute( 'aria-expanded', 'false' );
			button.focus();
		}
	};

	button.addEventListener( 'click', () => {
		const opening = drawer.hidden;
		drawer.hidden = ! opening;
		button.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
		if ( opening && ! root ) {
			try {
				root = mountReact(
					drawer,
					<BuilderSeoApp
						settings={ settings }
						readDocument={ () => readDiviDocument( doc ) }
					/>
				);
			} catch {
				drawer.hidden = true;
				button.setAttribute( 'aria-expanded', 'false' );
				return;
			}
		}
		if ( opening ) {
			drawer
				.querySelector< HTMLElement >(
					'input, textarea, button, select'
				)
				?.focus();
		}
	} );

	try {
		place();
		doc.addEventListener( 'keydown', onKey );
		if ( typeof MutationObserver !== 'undefined' ) {
			observer = new MutationObserver( () => {
				if (
					! slot.isConnected ||
					! slot.classList.contains( 'is-fallback' )
				) {
					return;
				}
				place();
			} );
			observer.observe( doc.body, { childList: true, subtree: true } );
		}
	} catch {
		return () => undefined;
	}

	return () => {
		doc.removeEventListener( 'keydown', onKey );
		observer?.disconnect();
		try {
			root?.unmount();
		} catch {
			// The builder iframe may already be gone.
		}
		slot.remove();
		drawer.remove();
	};
}
