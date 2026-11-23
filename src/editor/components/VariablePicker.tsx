import { __ } from '@wordpress/i18n';
import { useId, useState } from '@wordpress/element';
import type { TemplateVariable } from '../fields';

interface VariablePickerProps {
	variables: TemplateVariable[];
	onInsert: ( token: string ) => void;
}

export function VariablePicker( {
	variables,
	onInsert,
}: VariablePickerProps ): JSX.Element {
	const [ token, setToken ] = useState( variables[ 0 ]?.token ?? '%title%' );
	const selectId = useId();

	return (
		<div className="rms-variable-picker">
			<label htmlFor={ selectId }>
				<span>{ __( 'Insert variable', 'rankmyseo' ) }</span>
				<select
					id={ selectId }
					value={ token }
					onChange={ ( event ) => setToken( event.target.value ) }
				>
					{ variables.map( ( variable ) => (
						<option key={ variable.token } value={ variable.token }>
							{ variable.label }
						</option>
					) ) }
				</select>
			</label>
			<button
				type="button"
				className="rms-insert-token"
				onClick={ () => onInsert( token ) }
			>
				{ __( 'Insert', 'rankmyseo' ) }
			</button>
		</div>
	);
}
