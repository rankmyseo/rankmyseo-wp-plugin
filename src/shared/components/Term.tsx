import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { getGlossaryTerms, type GlossaryKey } from '../glossary';

interface TermProps {
	term: GlossaryKey;
	children?: string;
}

export function Term( { term, children }: TermProps ): JSX.Element {
	const [ open, setOpen ] = useState( false );
	const wrapperRef = useRef< HTMLSpanElement | null >( null );
	const popoverId = useId();
	const entry = getGlossaryTerms()[ term ];

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		const handleKeyDown = ( event: KeyboardEvent ): void => {
			if ( event.key === 'Escape' ) {
				setOpen( false );
			}
		};

		const handlePointerDown = ( event: MouseEvent ): void => {
			if (
				wrapperRef.current &&
				! wrapperRef.current.contains( event.target as Node )
			) {
				setOpen( false );
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		document.addEventListener( 'mousedown', handlePointerDown );
		return () => {
			document.removeEventListener( 'keydown', handleKeyDown );
			document.removeEventListener( 'mousedown', handlePointerDown );
		};
	}, [ open ] );

	return (
		<span className="rms-term-wrapper" ref={ wrapperRef }>
			<button
				type="button"
				className="rms-term-trigger"
				aria-expanded={ open }
				aria-controls={ open ? popoverId : undefined }
				onClick={ () => setOpen( ( prev ) => ! prev ) }
			>
				{ children ?? entry.label }
			</button>
			{ open && (
				<span
					id={ popoverId }
					role="tooltip"
					className="rms-term-popover"
				>
					<strong className="rms-term-popover-title">
						{ entry.label }
					</strong>
					<span className="rms-term-popover-body">
						{ entry.definition }
					</span>
				</span>
			) }
		</span>
	);
}
