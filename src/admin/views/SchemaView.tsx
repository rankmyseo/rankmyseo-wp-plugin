import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent } from 'react';
import { Term } from '../../shared/components/Term';
import {
	ErrorBanner,
	SkeletonCard,
	StatusBadge,
} from '../../shared/components/StatePrimitives';
import { useAdmin } from '../context/AdminContext';
import {
	fetchSchemaPreview,
	fetchSchemaSettings,
	saveSchemaSettings,
	validateSchemaJson,
} from './schema/api';
import {
	defaultSchemaSettings,
	type OpeningHoursRow,
	type SchemaChoice,
	type SchemaConflict,
	type SchemaGraphDocument,
	type SchemaPostType,
	type SchemaSettings,
} from './schema/types';

const WEEKDAYS = [
	'Monday',
	'Tuesday',
	'Wednesday',
	'Thursday',
	'Friday',
	'Saturday',
	'Sunday',
];

const FAQ_NOTE = __(
	'Google stopped showing FAQ rich results on May 7, 2026. FAQ markup is still valid structured data for answer engines, and it does not promise a FAQ snippet in Google.',
	'rankmyseo'
);

function choiceOptions( choices: SchemaChoice[] ): Array< {
	label: string;
	value: string;
} > {
	if ( choices.length > 0 ) {
		return choices;
	}
	return [
		{ label: __( 'Blog post', 'rankmyseo' ), value: 'BlogPosting' },
		{ label: __( 'Article', 'rankmyseo' ), value: 'Article' },
		{ label: __( 'Web page', 'rankmyseo' ), value: 'WebPage' },
		{ label: __( 'Product', 'rankmyseo' ), value: 'Product' },
		{ label: __( 'None', 'rankmyseo' ), value: 'none' },
	];
}

export function SchemaView(): JSX.Element {
	const { uiMode, modules, saving, toggleModule } = useAdmin();
	const descriptor = modules.find( ( module ) => module.id === 'schema' );
	const moduleEnabled = descriptor?.enabled ?? true;

	const [ settings, setSettings ] = useState< SchemaSettings | null >( null );
	const [ postTypes, setPostTypes ] = useState< SchemaPostType[] >( [] );
	const [ schemaTypes, setSchemaTypes ] = useState< SchemaChoice[] >( [] );
	const [ localTypes, setLocalTypes ] = useState< SchemaChoice[] >( [] );
	const [ filters, setFilters ] = useState< string[] >( [] );
	const [ preview, setPreview ] = useState< SchemaGraphDocument | null >(
		null
	);
	const [ previewUrl, setPreviewUrl ] = useState< string >( '' );
	const [ conflicts, setConflicts ] = useState< SchemaConflict[] >( [] );
	const [ postId, setPostId ] = useState< string >( '0' );
	const [ customJson, setCustomJson ] = useState< string >( '' );
	const [ validationMessage, setValidationMessage ] =
		useState< string >( '' );
	const [ validationOk, setValidationOk ] = useState< boolean | null >(
		null
	);
	const [ loading, setLoading ] = useState< boolean >( true );
	const [ localSaving, setLocalSaving ] = useState< boolean >( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ copied, setCopied ] = useState< boolean >( false );

	const loadPreview = useCallback( async ( nextPostId: number ) => {
		const response = await fetchSchemaPreview( nextPostId );
		setPreview( response.graph );
		setPreviewUrl( response.url );
		setConflicts( response.conflicts );
	}, [] );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const response = await fetchSchemaSettings();
			setSettings( response.settings );
			setPostTypes( response.postTypes );
			setSchemaTypes( response.schemaTypes );
			setLocalTypes( response.localBusinessTypes );
			setFilters( response.filters );
			await loadPreview( 0 );
		} catch ( err ) {
			setError(
				err instanceof Error
					? err.message
					: __(
							'Could not load structured data settings.',
							'rankmyseo'
					  )
			);
		} finally {
			setLoading( false );
		}
	}, [ loadPreview ] );

	useEffect( () => {
		void load();
	}, [ load ] );

	const update = ( patch: Partial< SchemaSettings > ): void => {
		setSettings( ( current ) =>
			current ? { ...current, ...patch } : current
		);
	};

	const save = async ( event?: FormEvent ): Promise< void > => {
		event?.preventDefault();
		if ( ! settings ) {
			return;
		}
		setLocalSaving( true );
		setError( null );
		try {
			const response = await saveSchemaSettings( settings );
			setSettings( response.settings );
			const numericId = Number.parseInt( postId, 10 );
			await loadPreview( Number.isNaN( numericId ) ? 0 : numericId );
		} catch ( err ) {
			setError(
				err instanceof Error
					? err.message
					: __(
							'Could not save structured data settings.',
							'rankmyseo'
					  )
			);
		} finally {
			setLocalSaving( false );
		}
	};

	const copyPreview = async (): Promise< void > => {
		if ( ! preview || ! navigator.clipboard?.writeText ) {
			setCopied( false );
			return;
		}
		const text = JSON.stringify( preview, null, 2 );
		await navigator.clipboard.writeText( text );
		setCopied( true );
	};

	const checkJson = async (): Promise< void > => {
		const result = await validateSchemaJson( customJson );
		setValidationOk( result.valid );
		setValidationMessage( result.message );
	};

	if ( loading && ! settings ) {
		return (
			<div className="rms-stack">
				<SkeletonCard lines={ 4 } />
				<SkeletonCard lines={ 3 } />
			</div>
		);
	}

	const formSettings = settings ?? defaultSchemaSettings();
	const schemaOptions = choiceOptions( schemaTypes );
	const busy = saving || localSaving;
	const advancedCustomized =
		formSettings.local_business_enabled ||
		( formSettings.post_type_schema.page &&
			formSettings.post_type_schema.page !== 'WebPage' ) ||
		( formSettings.post_type_schema.product &&
			formSettings.post_type_schema.product !== 'Product' );

	return (
		<div className="rms-stack">
			<div className="rms-page-header">
				<div>
					<div className="rms-title-row">
						<h1 className="rms-page-title">
							{ uiMode === 'simple'
								? __(
										'Site Details for Search Engines',
										'rankmyseo'
								  )
								: __(
										'Structured Data (Schema)',
										'rankmyseo'
								  ) }
						</h1>
						<StatusBadge tone={ moduleEnabled ? 'up' : 'flat' }>
							{ moduleEnabled
								? __( 'Module enabled', 'rankmyseo' )
								: __( 'Module disabled', 'rankmyseo' ) }
						</StatusBadge>
					</div>
					<p className="rms-muted">
						{ uiMode === 'simple'
							? __(
									'Tell search engines who publishes this site, then let articles, products, and breadcrumbs be described automatically.',
									'rankmyseo'
							  )
							: __(
									'One JSON-LD @graph per page, with stable @id links, per-post overrides, and PHP filters.',
									'rankmyseo'
							  ) }
					</p>
				</div>
				<ToggleControl
					__nextHasNoMarginBottom
					label={
						moduleEnabled
							? __( 'Enabled', 'rankmyseo' )
							: __( 'Disabled', 'rankmyseo' )
					}
					checked={ moduleEnabled }
					disabled={ saving }
					onChange={ ( next ) => {
						void toggleModule( 'schema', next );
					} }
				/>
			</div>

			{ error && (
				<ErrorBanner
					message={ error }
					onRetry={ () => {
						void load();
					} }
				/>
			) }

			<div className="rms-notice rms-notice-info" role="note">
				<p>
					<Term term="faq_page">FAQ</Term>
					{ ' — ' }
					{ FAQ_NOTE }
				</p>
			</div>

			{ uiMode === 'simple' && advancedCustomized && (
				<p className="rms-muted">
					{ __(
						'Custom advanced rules active — switch to Advanced to edit',
						'rankmyseo'
					) }
				</p>
			) }

			<form
				className="rms-stack"
				onSubmit={ ( event ) => void save( event ) }
			>
				{ uiMode === 'simple' ? (
					<SimpleIdentity
						settings={ formSettings }
						schemaOptions={ schemaOptions }
						disabled={ busy }
						onChange={ update }
					/>
				) : (
					<AdvancedSchema
						settings={ formSettings }
						postTypes={ postTypes }
						schemaOptions={ schemaOptions }
						localTypes={ localTypes }
						filters={ filters }
						preview={ preview }
						previewUrl={ previewUrl }
						conflicts={ conflicts }
						postId={ postId }
						customJson={ customJson }
						validationMessage={ validationMessage }
						validationOk={ validationOk }
						copied={ copied }
						disabled={ busy }
						onChange={ update }
						onPostId={ setPostId }
						onRefreshPreview={ () => {
							const numericId = Number.parseInt( postId, 10 );
							void loadPreview(
								Number.isNaN( numericId ) ? 0 : numericId
							);
						} }
						onCopy={ () => {
							void copyPreview();
						} }
						onCustomJson={ setCustomJson }
						onCheckJson={ () => {
							void checkJson();
						} }
					/>
				) }

				<div className="rms-page-header-actions">
					<Button
						variant="primary"
						type="submit"
						disabled={ busy }
						isBusy={ localSaving }
					>
						{ uiMode === 'simple'
							? __( 'Save site details', 'rankmyseo' )
							: __( 'Save schema settings', 'rankmyseo' ) }
					</Button>
				</div>
			</form>
		</div>
	);
}

function SimpleIdentity( {
	settings,
	schemaOptions,
	disabled,
	onChange,
}: {
	settings: SchemaSettings;
	schemaOptions: Array< { label: string; value: string } >;
	disabled: boolean;
	onChange: ( patch: Partial< SchemaSettings > ) => void;
} ): JSX.Element {
	return (
		<section className="rms-card rms-stack">
			<div>
				<h2 className="rms-section-title">
					{ __( 'Who is this site for?', 'rankmyseo' ) }
				</h2>
				<p className="rms-muted">
					{ __( 'Choose a person or an', 'rankmyseo' ) }{ ' ' }
					<Term term="organization">organization</Term>{ ' ' }
					{ __(
						'so search engines know who publishes your pages. This becomes your',
						'rankmyseo'
					) }{ ' ' }
					<Term term="schema">structured data</Term>.
				</p>
			</div>

			<div
				className="rms-choice-grid"
				role="group"
				aria-label={ __( 'Site identity', 'rankmyseo' ) }
			>
				<button
					type="button"
					className={ `rms-choice-card${
						settings.entity_type === 'organization'
							? ' is-selected'
							: ''
					}` }
					aria-pressed={ settings.entity_type === 'organization' }
					onClick={ () =>
						onChange( { entity_type: 'organization' } )
					}
				>
					<strong>
						{ __( 'Business or organization', 'rankmyseo' ) }
					</strong>
					<span>
						{ __(
							'A company, shop, or group brand.',
							'rankmyseo'
						) }
					</span>
				</button>
				<button
					type="button"
					className={ `rms-choice-card${
						settings.entity_type === 'person' ? ' is-selected' : ''
					}` }
					aria-pressed={ settings.entity_type === 'person' }
					onClick={ () => onChange( { entity_type: 'person' } ) }
				>
					<strong>{ __( 'Person', 'rankmyseo' ) }</strong>
					<span>
						{ __(
							'A personal site or solo creator.',
							'rankmyseo'
						) }
					</span>
				</button>
			</div>

			<div className="rms-form-grid">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Name', 'rankmyseo' ) }
					value={ settings.name }
					disabled={ disabled }
					onChange={ ( value ) => onChange( { name: value } ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Logo image address', 'rankmyseo' ) }
					type="url"
					value={ settings.logo_url }
					disabled={ disabled }
					help={ __(
						'A square logo helps search engines recognize your brand.',
						'rankmyseo'
					) }
					onChange={ ( value ) => onChange( { logo_url: value } ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Short description', 'rankmyseo' ) }
					value={ settings.description }
					disabled={ disabled }
					onChange={ ( value ) => onChange( { description: value } ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Phone', 'rankmyseo' ) }
					value={ settings.telephone }
					disabled={ disabled }
					onChange={ ( value ) => onChange( { telephone: value } ) }
				/>
			</div>

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Other profile links', 'rankmyseo' ) }
				help={ __(
					'One public profile address per line.',
					'rankmyseo'
				) }
				value={ settings.same_as.join( '\n' ) }
				disabled={ disabled }
				onChange={ ( value: string ) =>
					onChange( {
						same_as: value
							.split( /\s+/ )
							.map( ( item ) => item.trim() )
							.filter( ( item ) => item !== '' ),
					} )
				}
			/>

			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Default type for posts', 'rankmyseo' ) }
				value={ settings.post_type_schema.post ?? 'BlogPosting' }
				options={ schemaOptions }
				disabled={ disabled }
				onChange={ ( value ) =>
					onChange( {
						post_type_schema: {
							...settings.post_type_schema,
							post: value,
						},
					} )
				}
			/>

			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Describe products automatically', 'rankmyseo' ) }
				help={ __(
					'Replaces the shop built-in product markup so each product page is described once.',
					'rankmyseo'
				) }
				checked={ settings.woocommerce_product }
				disabled={ disabled }
				onChange={ ( value ) =>
					onChange( { woocommerce_product: value } )
				}
			/>
		</section>
	);
}

function AdvancedSchema( {
	settings,
	postTypes,
	schemaOptions,
	localTypes,
	filters,
	preview,
	previewUrl,
	conflicts,
	postId,
	customJson,
	validationMessage,
	validationOk,
	copied,
	disabled,
	onChange,
	onPostId,
	onRefreshPreview,
	onCopy,
	onCustomJson,
	onCheckJson,
}: {
	settings: SchemaSettings;
	postTypes: SchemaPostType[];
	schemaOptions: Array< { label: string; value: string } >;
	localTypes: SchemaChoice[];
	filters: string[];
	preview: SchemaGraphDocument | null;
	previewUrl: string;
	conflicts: SchemaConflict[];
	postId: string;
	customJson: string;
	validationMessage: string;
	validationOk: boolean | null;
	copied: boolean;
	disabled: boolean;
	onChange: ( patch: Partial< SchemaSettings > ) => void;
	onPostId: ( value: string ) => void;
	onRefreshPreview: () => void;
	onCopy: () => void;
	onCustomJson: ( value: string ) => void;
	onCheckJson: () => void;
} ): JSX.Element {
	const rows =
		postTypes.length > 0
			? postTypes
			: [
					{ name: 'post', label: __( 'Post', 'rankmyseo' ) },
					{ name: 'page', label: __( 'Page', 'rankmyseo' ) },
			  ];
	const previewText = preview
		? JSON.stringify( preview, null, 2 )
		: __( 'Preview is not available yet.', 'rankmyseo' );

	const addHours = (): void => {
		const next: OpeningHoursRow = {
			day: 'Monday',
			opens: '09:00',
			closes: '17:00',
		};
		onChange( { opening_hours: [ ...settings.opening_hours, next ] } );
	};

	return (
		<>
			{ conflicts.map( ( conflict ) => (
				<div
					key={ conflict.id }
					className={ `rms-notice${
						conflict.level === 'warning'
							? ' rms-notice-warn'
							: ' rms-notice-info'
					}` }
					role="status"
				>
					<p>{ conflict.message }</p>
				</div>
			) ) }

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ __( 'Schema type by content type', 'rankmyseo' ) }
				</h2>
				<table className="rms-schema-table">
					<thead>
						<tr>
							<th>{ __( 'Content type', 'rankmyseo' ) }</th>
							<th>{ __( 'Schema type', 'rankmyseo' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row ) => (
							<tr key={ row.name }>
								<td>{ row.label }</td>
								<td>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ `${ row.label } schema` }
										hideLabelFromVision
										value={
											settings.post_type_schema[
												row.name
											] ?? 'WebPage'
										}
										options={ schemaOptions }
										disabled={ disabled }
										onChange={ ( value ) =>
											onChange( {
												post_type_schema: {
													...settings.post_type_schema,
													[ row.name ]: value,
												},
											} )
										}
									/>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</section>

			<section className="rms-card rms-stack">
				<h2 className="rms-section-title">
					<Term term="local_business">Local business</Term>
				</h2>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Publish local business details',
						'rankmyseo'
					) }
					checked={ settings.local_business_enabled }
					disabled={ disabled }
					onChange={ ( value ) =>
						onChange( { local_business_enabled: value } )
					}
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Business type', 'rankmyseo' ) }
					value={ settings.local_business_type }
					options={
						localTypes.length > 0
							? localTypes
							: [
									{
										label: __(
											'Local business',
											'rankmyseo'
										),
										value: 'LocalBusiness',
									},
							  ]
					}
					disabled={ disabled }
					onChange={ ( value ) =>
						onChange( { local_business_type: value } )
					}
				/>
				<div className="rms-form-grid">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Business name', 'rankmyseo' ) }
						value={ settings.local_business_name }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { local_business_name: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Street address', 'rankmyseo' ) }
						value={ settings.address_street }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { address_street: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'City', 'rankmyseo' ) }
						value={ settings.address_city }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { address_city: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Region', 'rankmyseo' ) }
						value={ settings.address_region }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { address_region: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Postal code', 'rankmyseo' ) }
						value={ settings.address_postal_code }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { address_postal_code: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Country', 'rankmyseo' ) }
						value={ settings.address_country }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { address_country: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Latitude', 'rankmyseo' ) }
						value={ settings.geo_latitude }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { geo_latitude: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Longitude', 'rankmyseo' ) }
						value={ settings.geo_longitude }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { geo_longitude: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Price range', 'rankmyseo' ) }
						value={ settings.local_business_price_range }
						disabled={ disabled }
						onChange={ ( value ) =>
							onChange( { local_business_price_range: value } )
						}
					/>
				</div>

				<h3 className="rms-card-title">
					{ __( 'Opening hours', 'rankmyseo' ) }
				</h3>
				{ settings.opening_hours.map( ( row, index ) => (
					<div
						className="rms-hours-row"
						key={ `${ row.day }-${ index }` }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Day', 'rankmyseo' ) }
							value={ row.day }
							options={ WEEKDAYS.map( ( day ) => ( {
								label: day,
								value: day,
							} ) ) }
							disabled={ disabled }
							onChange={ ( value ) => {
								const openingHours = settings.opening_hours.map(
									( item, itemIndex ) =>
										itemIndex === index
											? { ...item, day: value }
											: item
								);
								onChange( { opening_hours: openingHours } );
							} }
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Opens', 'rankmyseo' ) }
							value={ row.opens }
							disabled={ disabled }
							onChange={ ( value ) => {
								const openingHours = settings.opening_hours.map(
									( item, itemIndex ) =>
										itemIndex === index
											? { ...item, opens: value }
											: item
								);
								onChange( { opening_hours: openingHours } );
							} }
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Closes', 'rankmyseo' ) }
							value={ row.closes }
							disabled={ disabled }
							onChange={ ( value ) => {
								const openingHours = settings.opening_hours.map(
									( item, itemIndex ) =>
										itemIndex === index
											? { ...item, closes: value }
											: item
								);
								onChange( { opening_hours: openingHours } );
							} }
						/>
						<Button
							variant="secondary"
							disabled={ disabled }
							onClick={ () => {
								const openingHours =
									settings.opening_hours.slice();
								openingHours.splice( index, 1 );
								onChange( { opening_hours: openingHours } );
							} }
						>
							{ __( 'Remove', 'rankmyseo' ) }
						</Button>
					</div>
				) ) }
				<Button
					variant="secondary"
					disabled={ disabled }
					onClick={ addHours }
				>
					{ __( 'Add hours', 'rankmyseo' ) }
				</Button>
			</section>

			<section className="rms-card rms-stack">
				<div className="rms-section-header">
					<div>
						<h2 className="rms-section-title">
							<Term term="json_ld">JSON-LD</Term>{ ' ' }
							{ __( 'preview', 'rankmyseo' ) }
						</h2>
						{ previewUrl && (
							<p className="rms-muted rms-mono">{ previewUrl }</p>
						) }
					</div>
					<Button
						variant="secondary"
						onClick={ onCopy }
						disabled={ ! preview }
					>
						{ copied
							? __( 'Copied JSON-LD', 'rankmyseo' )
							: __( 'Copy JSON-LD', 'rankmyseo' ) }
					</Button>
				</div>
				<div className="rms-form-grid">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Preview page ID', 'rankmyseo' ) }
						type="number"
						value={ postId }
						help={ __( 'Use 0 for the homepage.', 'rankmyseo' ) }
						onChange={ onPostId }
					/>
					<Button variant="secondary" onClick={ onRefreshPreview }>
						{ __( 'Refresh preview', 'rankmyseo' ) }
					</Button>
				</div>
				<pre
					className="rms-schema-preview"
					aria-label={ __( 'JSON-LD preview', 'rankmyseo' ) }
				>
					{ previewText }
				</pre>
			</section>

			<section className="rms-card rms-stack">
				<h2 className="rms-section-title">
					{ __( 'Check custom JSON-LD', 'rankmyseo' ) }
				</h2>
				<p className="rms-muted">
					{ __(
						'Per-post overrides are stored in _rankmyseo_schema_type and _rankmyseo_schema_custom. This check uses the same validation before anything is saved.',
						'rankmyseo'
					) }
				</p>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Custom JSON-LD', 'rankmyseo' ) }
					value={ customJson }
					onChange={ onCustomJson }
				/>
				<Button variant="secondary" onClick={ onCheckJson }>
					{ __( 'Check JSON-LD', 'rankmyseo' ) }
				</Button>
				{ validationMessage && (
					<p role="status">
						{ validationOk
							? __( 'Valid', 'rankmyseo' )
							: __( 'Needs a fix', 'rankmyseo' ) }
						{ ': ' }
						{ validationMessage }
					</p>
				) }
			</section>

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ __( 'PHP filters', 'rankmyseo' ) }
				</h2>
				<ul className="rms-filter-list">
					{ ( filters.length > 0
						? filters
						: [ 'rankmyseo_schema_graph' ]
					).map( ( hook ) => (
						<li key={ hook }>
							<code>{ hook }</code>
						</li>
					) ) }
				</ul>
			</section>
		</>
	);
}
