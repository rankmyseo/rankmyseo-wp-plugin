import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent } from 'react';
import type { NavPageId } from '../../shared/types';
import { useAdmin } from '../context/AdminContext';

interface CommandItem {
	id: string;
	label: string;
	run: () => void;
}

const PAGE_COMMANDS: Array< { id: NavPageId; label: string } > = [
	{ id: 'dashboard', label: __( 'Go to Dashboard', 'rankmyseo' ) },
	{ id: 'meta', label: __( 'Go to Search Previews', 'rankmyseo' ) },
	{ id: 'missing', label: __( 'Go to Missing details', 'rankmyseo' ) },
	{ id: 'schema', label: __( 'Go to Schema', 'rankmyseo' ) },
	{ id: 'sitemaps', label: __( 'Go to Sitemaps', 'rankmyseo' ) },
	{ id: 'redirects', label: __( 'Go to Redirects', 'rankmyseo' ) },
	{ id: 'import', label: __( 'Go to Import', 'rankmyseo' ) },
	{ id: 'cloud', label: __( 'Go to RankMySEO Cloud', 'rankmyseo' ) },
	{ id: 'ai-queue', label: __( 'Go to AI Queue', 'rankmyseo' ) },
	{ id: 'settings', label: __( 'Go to Settings', 'rankmyseo' ) },
];

export function CommandBar(): JSX.Element | null {
	const {
		modules,
		toggleModule,
		navigateTo,
		openChat,
		setUiMode,
		uiMode,
		saving,
	} = useAdmin();
	const [ open, setOpen ] = useState( false );
	const [ query, setQuery ] = useState( '' );
	const inputRef = useRef< HTMLInputElement | null >( null );

	useEffect( () => {
		const onKeyDown = ( event: KeyboardEvent ): void => {
			if (
				( event.metaKey || event.ctrlKey ) &&
				event.key.toLowerCase() === 'k'
			) {
				event.preventDefault();
				setOpen( true );
			}
		};

		const onOpen = (): void => {
			setOpen( true );
		};

		window.addEventListener( 'keydown', onKeyDown );
		window.addEventListener( 'rankmyseo-open-command', onOpen );
		return () => {
			window.removeEventListener( 'keydown', onKeyDown );
			window.removeEventListener( 'rankmyseo-open-command', onOpen );
		};
	}, [] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		setQuery( '' );
		const active = inputRef.current?.ownerDocument.activeElement;
		const returnFocus = active instanceof HTMLElement ? active : null;
		inputRef.current?.focus();

		const onKeyDown = ( event: KeyboardEvent ): void => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				event.stopPropagation();
				setOpen( false );
			}
		};

		document.addEventListener( 'keydown', onKeyDown, true );
		return () => {
			document.removeEventListener( 'keydown', onKeyDown, true );
			returnFocus?.focus();
		};
	}, [ open ] );

	const commands = useMemo< CommandItem[] >( () => {
		const items: CommandItem[] = PAGE_COMMANDS.map( ( page ) => ( {
			id: `page-${ page.id }`,
			label: page.label,
			run: () => navigateTo( page.id ),
		} ) );

		items.push( {
			id: 'mode',
			label:
				uiMode === 'simple'
					? __( 'Switch to Advanced mode', 'rankmyseo' )
					: __( 'Switch to Simple mode', 'rankmyseo' ),
			run: () => {
				void setUiMode( uiMode === 'simple' ? 'advanced' : 'simple' );
			},
		} );

		items.push( {
			id: 'ask-ai',
			label: __( 'Open Ask AI', 'rankmyseo' ),
			run: () => openChat(),
		} );

		modules.forEach( ( mod ) => {
			const next = ! mod.enabled;
			items.push( {
				id: `module-${ mod.id }`,
				label: next
					? `${ __( 'Turn on', 'rankmyseo' ) } ${ mod.name }`
					: `${ __( 'Turn off', 'rankmyseo' ) } ${ mod.name }`,
				run: () => {
					if ( ! saving ) {
						void toggleModule( mod.id, next );
					}
				},
			} );
		} );

		return items;
	}, [
		modules,
		navigateTo,
		openChat,
		saving,
		setUiMode,
		toggleModule,
		uiMode,
	] );

	if ( ! open ) {
		return null;
	}

	const needle = query.trim().toLowerCase();
	const visible = needle
		? commands.filter( ( item ) =>
				item.label.toLowerCase().includes( needle )
		  )
		: commands;

	const closeAndRun = ( item: CommandItem ): void => {
		setOpen( false );
		item.run();
	};

	const handleSubmit = ( event: FormEvent ): void => {
		event.preventDefault();
		if ( visible[ 0 ] ) {
			closeAndRun( visible[ 0 ] );
		}
	};

	return (
		<div className="rms-command-backdrop">
			<div
				className="rms-command-dialog"
				role="dialog"
				aria-modal="true"
				aria-label={ __( 'Command bar', 'rankmyseo' ) }
			>
				<form onSubmit={ handleSubmit }>
					<label htmlFor="rms-command-input" className="rms-sr-only">
						{ __( 'Search commands', 'rankmyseo' ) }
					</label>
					<input
						id="rms-command-input"
						ref={ inputRef }
						className="rms-command-input"
						value={ query }
						placeholder={ __(
							'Jump to a screen, toggle a module, or ask AI',
							'rankmyseo'
						) }
						onChange={ ( event ) => setQuery( event.target.value ) }
					/>
				</form>
				<ul className="rms-command-list">
					{ visible.length === 0 && (
						<li className="rms-command-empty">
							{ __( 'No matching commands', 'rankmyseo' ) }
						</li>
					) }
					{ visible.map( ( item ) => (
						<li key={ item.id }>
							<button
								type="button"
								className="rms-command-item"
								onClick={ () => closeAndRun( item ) }
							>
								{ item.label }
							</button>
						</li>
					) ) }
				</ul>
			</div>
		</div>
	);
}
