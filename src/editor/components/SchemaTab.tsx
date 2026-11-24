import { __ } from '@wordpress/i18n';
import {
	SCHEMA_SETTINGS_HREF,
	effectiveSchemaType,
	schemaChoices,
	schemaTypeLabel,
} from '../schemaOptions';

export interface SchemaTabProps {
	schemaType: string;
	postType: string;
	onChange: ( schemaType: string ) => void;
	schemaDefaults?: Record< string, string >;
	schemaSettingsUrl?: string;
	productSchemaEnabled?: boolean;
	fieldId: string;
}

export function SchemaTab( {
	schemaType,
	postType,
	onChange,
	schemaDefaults = {},
	schemaSettingsUrl = SCHEMA_SETTINGS_HREF,
	productSchemaEnabled = true,
	fieldId,
}: SchemaTabProps ): JSX.Element {
	const effective = effectiveSchemaType(
		schemaType,
		postType,
		schemaDefaults,
		productSchemaEnabled
	);
	const effectiveLabel = schemaTypeLabel( effective );
	const automatic = ! schemaType;

	return (
		<div
			id="rankmyseo-schema-slot"
			className="rms-schema-panel"
			data-rankmyseo-schema-slot="1"
			role="tabpanel"
		>
			<p className="rms-schema-effective">
				{ automatic
					? __(
							'Automatic. This page uses the site default for its content type.',
							'rankmyseo'
					  )
					: __(
							'This page uses a schema type chosen here.',
							'rankmyseo'
					  ) }{ ' ' }
				<strong>{ effectiveLabel }</strong>
			</p>
			<label
				className="rms-stack-label"
				htmlFor={ `${ fieldId }-schema` }
			>
				<span>{ __( 'Schema type', 'rankmyseo' ) }</span>
				<select
					id={ `${ fieldId }-schema` }
					value={ schemaType }
					onChange={ ( event ) => onChange( event.target.value ) }
				>
					<option value="">{ __( 'Automatic', 'rankmyseo' ) }</option>
					{ schemaChoices().map( ( choice ) => (
						<option key={ choice.value } value={ choice.value }>
							{ choice.label }
						</option>
					) ) }
				</select>
			</label>
			<p className="rms-schema-note">
				{ __(
					'Automatic follows the site default. None leaves out article, product, FAQ, and how-to markup for this page.',
					'rankmyseo'
				) }
			</p>
			<p className="rms-schema-note">
				<a className="rms-schema-link" href={ schemaSettingsUrl }>
					{ __( 'Site-wide schema defaults', 'rankmyseo' ) }
				</a>
			</p>
		</div>
	);
}
