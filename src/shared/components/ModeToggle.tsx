import { __ } from '@wordpress/i18n';
import type { UiMode } from '../types';

interface ModeToggleProps {
	mode: UiMode;
	onChange: ( nextMode: UiMode ) => void;
	disabled?: boolean;
}

export function ModeToggle( {
	mode,
	onChange,
	disabled = false,
}: ModeToggleProps ): JSX.Element {
	return (
		<div
			className="rms-mode-toggle"
			role="radiogroup"
			aria-label={ __( 'Experience mode', 'rankmyseo' ) }
			title={ __(
				'Switch anytime — none of your settings change.',
				'rankmyseo'
			) }
		>
			<button
				type="button"
				role="radio"
				aria-checked={ mode === 'simple' }
				disabled={ disabled }
				className={ `rms-mode-toggle-btn${
					mode === 'simple' ? ' is-active' : ''
				}` }
				onClick={ () => onChange( 'simple' ) }
			>
				{ __( 'Simple', 'rankmyseo' ) }
			</button>
			<button
				type="button"
				role="radio"
				aria-checked={ mode === 'advanced' }
				disabled={ disabled }
				className={ `rms-mode-toggle-btn${
					mode === 'advanced' ? ' is-active' : ''
				}` }
				onClick={ () => onChange( 'advanced' ) }
			>
				{ __( 'Advanced', 'rankmyseo' ) }
			</button>
		</div>
	);
}
