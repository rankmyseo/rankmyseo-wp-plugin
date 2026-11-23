import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl } from '@wordpress/components';
import { useEffect, useId, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ModeToggle } from '../../shared/components/ModeToggle';
import { SerpPreview } from '../../editor/components/SerpPreview';
import { SocialPreview } from '../../editor/components/SocialPreview';
import { VariablePicker } from '../../editor/components/VariablePicker';
import type { TemplateVariable } from '../../editor/fields';
import { replaceTemplate } from '../../editor/template';
import '../../editor/editor.css';
import { useAdmin } from '../context/AdminContext';

interface TypeRow {
	title: string;
	description: string;
	noindex: boolean;
	label: string;
}

interface TitleDocument {
	separator: string;
	siteName: string;
	tagline: string;
	homepageTitle: string;
	homepageDescription: string;
	postTypes: Record< string, TypeRow >;
	taxonomies: Record< string, TypeRow >;
	archives: Record< string, TypeRow >;
	defaultImage: string;
	showOnFront: string;
}

interface MetaSettingsResponse {
	settings?: Record< string, unknown >;
	variables?: TemplateVariable[];
	separators?: string[];
	conflict?: boolean;
	head_paused?: boolean;
	site_name?: string;
}

const ARCHIVE_LABELS: Record< string, string > = {
	author: __( 'Author archive', 'rankmyseo' ),
	date: __( 'Date archive', 'rankmyseo' ),
	search: __( 'Search results', 'rankmyseo' ),
	not_found: __( 'Missing page', 'rankmyseo' ),
};

function asRecord( value: unknown ): Record< string, unknown > {
	return value && typeof value === 'object'
		? ( value as Record< string, unknown > )
		: {};
}

function typeRow( value: unknown ): TypeRow {
	const source = asRecord( value );
	return {
		title: typeof source.title === 'string' ? source.title : '',
		description:
			typeof source.description === 'string' ? source.description : '',
		noindex: source.noindex === true,
		label: typeof source.label === 'string' ? source.label : '',
	};
}

function mapRows( value: unknown ): Record< string, TypeRow > {
	const source = asRecord( value );
	return Object.fromEntries(
		Object.entries( source ).map( ( [ key, row ] ) => [
			key,
			typeRow( row ),
		] )
	);
}

function documentFromResponse( payload: MetaSettingsResponse ): TitleDocument {
	const settings = asRecord( payload.settings );
	const homepage = asRecord( settings.homepage );
	const social = asRecord( settings.social );
	const reading = asRecord( settings.reading );
	return {
		separator:
			typeof settings.separator === 'string' ? settings.separator : '|',
		siteName:
			payload.site_name ||
			( typeof settings.site_name === 'string'
				? settings.site_name
				: '' ),
		tagline: typeof settings.tagline === 'string' ? settings.tagline : '',
		homepageTitle: typeof homepage.title === 'string' ? homepage.title : '',
		homepageDescription:
			typeof homepage.description === 'string'
				? homepage.description
				: '',
		postTypes: mapRows( settings.post_types ),
		taxonomies: mapRows( settings.taxonomies ),
		archives: mapRows( settings.archives ),
		defaultImage:
			typeof social.default_image === 'string'
				? social.default_image
				: '',
		showOnFront:
			typeof reading.show_on_front === 'string'
				? reading.show_on_front
				: 'posts',
	};
}

function rowsPayload(
	rows: Record< string, TypeRow >
): Record< string, { title: string; description: string; noindex: boolean } > {
	return Object.fromEntries(
		Object.entries( rows ).map( ( [ key, row ] ) => [
			key,
			{
				title: row.title,
				description: row.description,
				noindex: row.noindex,
			},
		] )
	);
}

export function TitlesMetaView(): JSX.Element {
	const { uiMode, setUiMode } = useAdmin();
	const [ doc, setDoc ] = useState< TitleDocument | null >( null );
	const [ variables, setVariables ] = useState< TemplateVariable[] >( [] );
	const [ separators, setSeparators ] = useState< string[] >( [
		'-',
		'|',
		'·',
	] );
	const [ conflict, setConflict ] = useState( false );
	const [ paused, setPaused ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ saved, setSaved ] = useState( false );
	const fieldId = useId();

	useEffect( () => {
		let active = true;
		void apiFetch< MetaSettingsResponse >( {
			path: '/rankmyseo/v1/meta/settings',
			method: 'GET',
		} )
			.then( ( payload ) => {
				if ( ! active ) {
					return;
				}
				setDoc( documentFromResponse( payload ) );
				setVariables( payload.variables ?? [] );
				if ( payload.separators?.length ) {
					setSeparators( payload.separators );
				}
				setConflict( payload.conflict === true );
				setPaused( payload.head_paused === true );
				setLoading( false );
			} )
			.catch( () => {
				if ( active ) {
					setError(
						__(
							'Search preview settings could not be loaded.',
							'rankmyseo'
						)
					);
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [] );

	if ( loading ) {
		return <p>{ __( 'Loading search previews', 'rankmyseo' ) }</p>;
	}

	if ( ! doc ) {
		return <p role="alert">{ error }</p>;
	}

	const previewVars = {
		title: __( 'Sample page', 'rankmyseo' ),
		sep: doc.separator,
		sitename: doc.siteName,
		tagline: doc.tagline,
		excerpt: __( 'A short sample excerpt.', 'rankmyseo' ),
		term_title: __( 'News', 'rankmyseo' ),
		term_description: __( 'Stories from the team.', 'rankmyseo' ),
		author: 'Alex',
		searchphrase: 'pricing',
		date: '2026',
		page: '',
		currentyear: String( new Date().getFullYear() ),
		pt_plural: __( 'Posts', 'rankmyseo' ),
	};
	const homeTitle = replaceTemplate( doc.homepageTitle, previewVars );
	const homeDescription = replaceTemplate(
		doc.homepageDescription,
		previewVars
	);

	const updateRow = (
		bucket: 'postTypes' | 'taxonomies' | 'archives',
		key: string,
		patch: Partial< TypeRow >
	): void => {
		setDoc( {
			...doc,
			[ bucket ]: {
				...doc[ bucket ],
				[ key ]: { ...doc[ bucket ][ key ], ...patch },
			},
		} );
	};

	const save = async (): Promise< void > => {
		setSaving( true );
		setSaved( false );
		try {
			await apiFetch( {
				path: '/rankmyseo/v1/settings',
				method: 'POST',
				data: {
					site_name: doc.siteName,
					title_separator: doc.separator,
				},
			} );
			await apiFetch( {
				path: '/rankmyseo/v1/meta/settings',
				method: 'POST',
				data: {
					separator: doc.separator,
					homepage: {
						title: doc.homepageTitle,
						description: doc.homepageDescription,
					},
					post_types: rowsPayload( doc.postTypes ),
					taxonomies: rowsPayload( doc.taxonomies ),
					archives: rowsPayload( doc.archives ),
					social: { default_image: doc.defaultImage },
				},
			} );
			setSaved( true );
		} catch ( saveError ) {
			setError(
				saveError instanceof Error
					? saveError.message
					: __( 'Could not save search previews.', 'rankmyseo' )
			);
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="rms-stack">
			<div className="rms-page-header">
				<div>
					<h1 className="rms-page-title">
						{ uiMode === 'simple'
							? __( 'Search previews', 'rankmyseo' )
							: __( 'Titles & Meta', 'rankmyseo' ) }
					</h1>
					<p className="rms-muted">
						{ __(
							'Choose the titles and descriptions people see in Google and when a page is shared.',
							'rankmyseo'
						) }
					</p>
				</div>
				<ModeToggle
					mode={ uiMode }
					onChange={ ( next ) => {
						void setUiMode( next );
					} }
				/>
			</div>

			{ conflict && paused && (
				<p className="rms-editor-note" role="status">
					{ __(
						'Another SEO plugin is active, so RankMySEO is not printing titles yet.',
						'rankmyseo'
					) }
				</p>
			) }
			{ error && <p role="alert">{ error }</p> }

			<section className="rms-card rms-stack">
				<TextControl
					label={ __( 'Site name', 'rankmyseo' ) }
					value={ doc.siteName }
					onChange={ ( value ) =>
						setDoc( { ...doc, siteName: value } )
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<label
					className="rms-stack-label"
					htmlFor={ `${ fieldId }-separator` }
				>
					<span>{ __( 'Separator', 'rankmyseo' ) }</span>
					<select
						id={ `${ fieldId }-separator` }
						value={ doc.separator }
						onChange={ ( event ) =>
							setDoc( { ...doc, separator: event.target.value } )
						}
					>
						{ separators.map( ( mark ) => (
							<option key={ mark } value={ mark }>
								{ mark }
							</option>
						) ) }
					</select>
				</label>
				<TextControl
					label={ __( 'Homepage title', 'rankmyseo' ) }
					value={ doc.homepageTitle }
					onChange={ ( value ) =>
						setDoc( { ...doc, homepageTitle: value } )
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( 'Homepage description', 'rankmyseo' ) }
					value={ doc.homepageDescription }
					onChange={ ( value ) =>
						setDoc( { ...doc, homepageDescription: value } )
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<div className="rms-serp-pair">
					<SerpPreview
						viewport="desktop"
						title={ homeTitle }
						description={ homeDescription }
						url={ doc.siteName || 'example.com' }
					/>
					<SerpPreview
						viewport="mobile"
						title={ homeTitle }
						description={ homeDescription }
						url={ doc.siteName || 'example.com' }
					/>
				</div>
				<TextControl
					label={ __( 'Default social image', 'rankmyseo' ) }
					value={ doc.defaultImage }
					onChange={ ( value ) =>
						setDoc( { ...doc, defaultImage: value } )
					}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<SocialPreview
					network="facebook"
					title={ homeTitle }
					description={ homeDescription }
					image={ doc.defaultImage }
					url={ doc.siteName || 'example.com' }
				/>

				{ [ 'post', 'page', 'product' ]
					.filter( ( key ) => doc.postTypes[ key ] )
					.map( ( key ) => (
						<label
							key={ key }
							className="rms-check"
							htmlFor={ `${ fieldId }-show-${ key }` }
						>
							<input
								id={ `${ fieldId }-show-${ key }` }
								type="checkbox"
								checked={ ! doc.postTypes[ key ].noindex }
								onChange={ ( event ) =>
									updateRow( 'postTypes', key, {
										noindex: ! event.target.checked,
									} )
								}
							/>
							<span>
								{ key === 'post' &&
									__( 'Show Posts in Google', 'rankmyseo' ) }
								{ key === 'page' &&
									__( 'Show Pages in Google', 'rankmyseo' ) }
								{ key === 'product' &&
									__(
										'Show Products in Google',
										'rankmyseo'
									) }
							</span>
						</label>
					) ) }
			</section>

			{ uiMode === 'advanced' && (
				<section className="rms-card rms-stack">
					<h2 className="rms-section-title">
						{ __( 'Templates', 'rankmyseo' ) }
					</h2>
					<p className="rms-muted">
						{ doc.showOnFront === 'page'
							? __(
									'The homepage is a static page. Its own search title is used when that page has one.',
									'rankmyseo'
							  )
							: __(
									'The homepage shows your latest posts.',
									'rankmyseo'
							  ) }
					</p>
					<VariablePicker
						variables={ variables }
						onInsert={ ( token ) =>
							setDoc( {
								...doc,
								homepageTitle:
									`${ doc.homepageTitle } ${ token }`.trim(),
							} )
						}
					/>
					{ Object.entries( doc.postTypes ).map( ( [ key, row ] ) => (
						<div key={ key }>
							<h3>{ row.label || key }</h3>
							<TextControl
								label={ __( 'Title template', 'rankmyseo' ) }
								value={ row.title }
								onChange={ ( value ) =>
									updateRow( 'postTypes', key, {
										title: value,
									} )
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<label
								className="rms-check"
								htmlFor={ `${ fieldId }-hide-${ key }` }
							>
								<input
									id={ `${ fieldId }-hide-${ key }` }
									type="checkbox"
									checked={ row.noindex }
									onChange={ ( event ) =>
										updateRow( 'postTypes', key, {
											noindex: event.target.checked,
										} )
									}
								/>
								<span>
									{ __(
										'Hide this type from search',
										'rankmyseo'
									) }
								</span>
							</label>
						</div>
					) ) }
					{ Object.entries( doc.archives ).map( ( [ key, row ] ) => (
						<TextControl
							key={ key }
							label={ ARCHIVE_LABELS[ key ] || key }
							value={ row.title }
							onChange={ ( value ) =>
								updateRow( 'archives', key, { title: value } )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					) ) }
					<ul className="rms-checklist">
						<li>
							{ __( 'Homepage preview:', 'rankmyseo' ) }{ ' ' }
							{ homeTitle }
						</li>
						<li>
							{ __( 'Post preview:', 'rankmyseo' ) }{ ' ' }
							{ replaceTemplate(
								doc.postTypes.post?.title || '',
								previewVars
							) }
						</li>
					</ul>
				</section>
			) }

			<Button
				variant="primary"
				className="rms-btn-primary"
				disabled={ saving }
				onClick={ () => {
					void save();
				} }
			>
				{ saving
					? __( 'Saving…', 'rankmyseo' )
					: __( 'Save search previews', 'rankmyseo' ) }
			</Button>
			{ saved && (
				<p role="status">
					{ __( 'Search previews saved.', 'rankmyseo' ) }
				</p>
			) }
		</div>
	);
}
