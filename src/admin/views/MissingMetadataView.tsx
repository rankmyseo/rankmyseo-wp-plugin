import apiFetch from '@wordpress/api-fetch';
import { Button, CheckboxControl } from '@wordpress/components';
import { useEffect, useId, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ErrorBanner } from '../../shared/components/StatePrimitives';
import type { UiMode } from '../../shared/types';
import { diffWords } from '../utils/wordDiff';
import { useAdmin } from '../context/AdminContext';

export interface MissingRow {
	id: number;
	post_type: string;
	type_label: string;
	title: string;
	permalink: string;
	edit_link: string;
	seo_title: string;
	description: string;
	focus_keyword: string;
	image_id: number | null;
	image_alt: string;
	missing: string[];
}

export interface MissingChange {
	field: string;
	before: string;
	after: string;
	pattern: string;
}

export interface MissingPreviewItem {
	id: number;
	title: string;
	changes: MissingChange[];
}

export interface MissingFilters {
	missing: string;
	postType: string;
	search: string;
}

export interface PostTypeChoice {
	name: string;
	label: string;
}

const FILL_FIELDS = [ 'title', 'description', 'image_alt' ] as const;

interface MissingMetadataScreenProps {
	uiMode: UiMode;
	items: MissingRow[];
	total: number;
	page: number;
	filters: MissingFilters;
	postTypes: PostTypeChoice[];
	connected: boolean;
	preview: MissingPreviewItem[] | null;
	notice: string;
	error: string;
	busy: boolean;
	onFilters: ( next: MissingFilters ) => void;
	onPage: ( page: number ) => void;
	onPreview: ( ids: number[], fields: string[] ) => void;
	onApply: ( ids: number[], fields: string[] ) => void;
	onDismissPreview: () => void;
	onQueue: ( ids: number[] ) => void;
	onSave: (
		id: number,
		fields: {
			title: string;
			description: string;
			focus_keyword: string;
			image_alt: string;
		}
	) => void;
}

function fieldLabel( field: string, uiMode: UiMode ): string {
	if ( field === 'title' ) {
		return uiMode === 'simple'
			? __( 'Search title', 'rankmyseo' )
			: __( 'SEO title', 'rankmyseo' );
	}
	if ( field === 'description' ) {
		return uiMode === 'simple'
			? __( 'Search description', 'rankmyseo' )
			: __( 'Meta description', 'rankmyseo' );
	}
	if ( field === 'focus_keyword' ) {
		return uiMode === 'simple'
			? __( 'Main phrase', 'rankmyseo' )
			: __( 'Focus keyword', 'rankmyseo' );
	}
	return uiMode === 'simple'
		? __( 'Image description', 'rankmyseo' )
		: __( 'Image alt text', 'rankmyseo' );
}

function missingText( row: MissingRow, uiMode: UiMode ): string {
	return row.missing
		.map( ( field ) => fieldLabel( field, uiMode ) )
		.join( ', ' );
}

function diffClass( kind: string ): string | undefined {
	if ( kind === 'del' ) {
		return 'rms-diff-token-del';
	}
	if ( kind === 'add' ) {
		return 'rms-diff-token-add';
	}
	return undefined;
}

function WordDiff( {
	before,
	after,
}: {
	before: string;
	after: string;
} ): JSX.Element {
	const parts = diffWords( before, after );
	return (
		<span className="rms-diff-block">
			{ parts.map( ( part, index ) => (
				<span
					key={ `${ part.kind }-${ index }` }
					className={ diffClass( part.kind ) }
				>
					{ part.text }
				</span>
			) ) }
		</span>
	);
}

export function MissingMetadataScreen( {
	uiMode,
	items,
	total,
	page,
	filters,
	postTypes,
	connected,
	preview,
	notice,
	error,
	busy,
	onFilters,
	onPage,
	onPreview,
	onApply,
	onDismissPreview,
	onQueue,
	onSave,
}: MissingMetadataScreenProps ): JSX.Element {
	const formId = useId();
	const titleId = useId();
	const [ selected, setSelected ] = useState< number[] >( [] );
	const [ search, setSearch ] = useState( filters.search );
	const [ fields, setFields ] = useState< string[] >( [ ...FILL_FIELDS ] );
	const [ editing, setEditing ] = useState< number | null >( null );
	const [ draft, setDraft ] = useState( {
		title: '',
		description: '',
		focus_keyword: '',
		image_alt: '',
	} );

	useEffect( () => {
		setSearch( filters.search );
	}, [ filters.search ] );

	useEffect( () => {
		setSelected( ( current ) =>
			current.filter( ( id ) => items.some( ( item ) => item.id === id ) )
		);
	}, [ items ] );

	const heading =
		uiMode === 'simple'
			? __( 'Missing details', 'rankmyseo' )
			: __( 'Missing metadata', 'rankmyseo' );
	const allChecked = items.length > 0 && selected.length === items.length;

	function toggle( id: number ): void {
		setSelected( ( current ) =>
			current.includes( id )
				? current.filter( ( item ) => item !== id )
				: [ ...current, id ]
		);
	}

	function toggleField( field: string ): void {
		setFields( ( current ) =>
			current.includes( field )
				? current.filter( ( item ) => item !== field )
				: [ ...current, field ]
		);
	}

	function startEdit( row: MissingRow ): void {
		setEditing( row.id );
		setDraft( {
			title: row.seo_title,
			description: row.description,
			focus_keyword: row.focus_keyword,
			image_alt: row.image_alt,
		} );
	}

	return (
		<div className="rms-stack">
			<header className="rms-page-header">
				<h1>{ heading }</h1>
				<p className="rms-muted">
					{ __(
						'Fill empty search details from this site’s templates, or draft a suggestion to review before anything changes.',
						'rankmyseo'
					) }
				</p>
			</header>
			{ '' !== error && <ErrorBanner message={ error } /> }
			{ '' !== notice && (
				<p className="rms-notice" role="status">
					{ notice }
				</p>
			) }

			<form
				className="rms-missing-toolbar"
				onSubmit={ ( event ) => {
					event.preventDefault();
					onFilters( { ...filters, search } );
				} }
			>
				<label htmlFor={ `${ formId }-missing` }>
					{ __( 'Show', 'rankmyseo' ) }
					<select
						id={ `${ formId }-missing` }
						value={ filters.missing }
						onChange={ ( event ) =>
							onFilters( {
								...filters,
								missing: event.target.value,
							} )
						}
					>
						<option value="">
							{ __( 'Anything missing', 'rankmyseo' ) }
						</option>
						<option value="title">
							{ fieldLabel( 'title', uiMode ) }
						</option>
						<option value="description">
							{ fieldLabel( 'description', uiMode ) }
						</option>
						<option value="focus_keyword">
							{ fieldLabel( 'focus_keyword', uiMode ) }
						</option>
						<option value="image_alt">
							{ fieldLabel( 'image_alt', uiMode ) }
						</option>
					</select>
				</label>
				<label htmlFor={ `${ formId }-type` }>
					{ __( 'Type', 'rankmyseo' ) }
					<select
						id={ `${ formId }-type` }
						value={ filters.postType }
						onChange={ ( event ) =>
							onFilters( {
								...filters,
								postType: event.target.value,
							} )
						}
					>
						<option value="">
							{ __( 'All types', 'rankmyseo' ) }
						</option>
						{ postTypes.map( ( type ) => (
							<option key={ type.name } value={ type.name }>
								{ type.label }
							</option>
						) ) }
					</select>
				</label>
				<label htmlFor={ `${ formId }-search` }>
					{ __( 'Search', 'rankmyseo' ) }
					<input
						id={ `${ formId }-search` }
						type="search"
						value={ search }
						onChange={ ( event ) =>
							setSearch( event.target.value )
						}
					/>
				</label>
				<Button variant="secondary" type="submit" disabled={ busy }>
					{ __( 'Filter', 'rankmyseo' ) }
				</Button>
			</form>

			<fieldset className="rms-missing-fields">
				<legend>{ __( 'Local template fields', 'rankmyseo' ) }</legend>
				{ FILL_FIELDS.map( ( field ) => (
					<CheckboxControl
						key={ field }
						label={ fieldLabel( field, uiMode ) }
						checked={ fields.includes( field ) }
						onChange={ () => toggleField( field ) }
						__nextHasNoMarginBottom
					/>
				) ) }
			</fieldset>

			<div className="rms-missing-actions">
				<Button
					variant="primary"
					disabled={
						busy || selected.length === 0 || fields.length === 0
					}
					onClick={ () => onPreview( selected, fields ) }
				>
					{ __( 'Fill with local templates', 'rankmyseo' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ busy || ! connected || selected.length === 0 }
					onClick={ () => onQueue( selected ) }
				>
					{ __( 'Draft with RankMySEO AI', 'rankmyseo' ) }
				</Button>
				{ ! connected && (
					<p className="rms-muted">
						{ __(
							'Connect RankMySEO to draft suggestions. Local templates work without a connection.',
							'rankmyseo'
						) }
					</p>
				) }
			</div>

			<div className="rms-table-wrap">
				<table className="rms-table">
					<caption className="rms-sr-only">{ heading }</caption>
					<thead>
						<tr>
							<th scope="col">
								<input
									type="checkbox"
									aria-label={ __(
										'Select all on this page',
										'rankmyseo'
									) }
									checked={ allChecked }
									onChange={ () =>
										setSelected(
											allChecked
												? []
												: items.map(
														( item ) => item.id
												  )
										)
									}
								/>
							</th>
							<th scope="col">{ __( 'Title', 'rankmyseo' ) }</th>
							<th scope="col">{ __( 'Type', 'rankmyseo' ) }</th>
							<th scope="col">
								{ __( 'Missing', 'rankmyseo' ) }
							</th>
							<th scope="col">{ __( 'Edit', 'rankmyseo' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.length === 0 && (
							<tr>
								<td colSpan={ 5 }>
									{ __(
										'Nothing is missing for this filter.',
										'rankmyseo'
									) }
								</td>
							</tr>
						) }
						{ items.map( ( row ) => (
							<tr key={ row.id }>
								<td>
									<input
										type="checkbox"
										aria-label={ sprintf(
											/* translators: %s: page title. */
											__( 'Select %s', 'rankmyseo' ),
											row.title
										) }
										checked={ selected.includes( row.id ) }
										onChange={ () => toggle( row.id ) }
									/>
								</td>
								<td>
									<strong>{ row.title }</strong>
									{ editing === row.id && (
										<div className="rms-missing-inline">
											<label
												htmlFor={ `${ formId }-title-${ row.id }` }
											>
												{ fieldLabel(
													'title',
													uiMode
												) }
												<input
													id={ `${ formId }-title-${ row.id }` }
													aria-label={ sprintf(
														/* translators: %s: page title. */
														__(
															'Search title for %s',
															'rankmyseo'
														),
														row.title
													) }
													value={ draft.title }
													onChange={ ( event ) =>
														setDraft( {
															...draft,
															title: event.target
																.value,
														} )
													}
												/>
											</label>
											<label
												htmlFor={ `${ formId }-description-${ row.id }` }
											>
												{ fieldLabel(
													'description',
													uiMode
												) }
												<textarea
													id={ `${ formId }-description-${ row.id }` }
													value={ draft.description }
													onChange={ ( event ) =>
														setDraft( {
															...draft,
															description:
																event.target
																	.value,
														} )
													}
												/>
											</label>
											<label
												htmlFor={ `${ formId }-keyword-${ row.id }` }
											>
												{ fieldLabel(
													'focus_keyword',
													uiMode
												) }
												<input
													id={ `${ formId }-keyword-${ row.id }` }
													value={
														draft.focus_keyword
													}
													onChange={ ( event ) =>
														setDraft( {
															...draft,
															focus_keyword:
																event.target
																	.value,
														} )
													}
												/>
											</label>
											{ row.image_id && (
												<label
													htmlFor={ `${ formId }-alt-${ row.id }` }
												>
													{ fieldLabel(
														'image_alt',
														uiMode
													) }
													<input
														id={ `${ formId }-alt-${ row.id }` }
														value={
															draft.image_alt
														}
														onChange={ ( event ) =>
															setDraft( {
																...draft,
																image_alt:
																	event.target
																		.value,
															} )
														}
													/>
												</label>
											) }
											<Button
												variant="primary"
												onClick={ () =>
													onSave( row.id, draft )
												}
											>
												{ __( 'Save', 'rankmyseo' ) }
											</Button>
											<Button
												variant="tertiary"
												onClick={ () =>
													setEditing( null )
												}
											>
												{ __( 'Cancel', 'rankmyseo' ) }
											</Button>
										</div>
									) }
								</td>
								<td>{ row.type_label }</td>
								<td>{ missingText( row, uiMode ) }</td>
								<td>
									<Button
										variant="secondary"
										onClick={ () => startEdit( row ) }
									>
										{ __( 'Quick edit', 'rankmyseo' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
			<p className="rms-muted">
				{ sprintf(
					/* translators: 1: current page, 2: number of matching pages. */
					__( 'Page %1$d. %2$d matching items.', 'rankmyseo' ),
					page,
					total
				) }
			</p>
			<div className="rms-missing-actions">
				<Button
					variant="secondary"
					disabled={ page <= 1 || busy }
					onClick={ () => onPage( page - 1 ) }
				>
					{ __( 'Previous', 'rankmyseo' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ busy || page * 20 >= total }
					onClick={ () => onPage( page + 1 ) }
				>
					{ __( 'Next', 'rankmyseo' ) }
				</Button>
			</div>

			{ preview && (
				<div
					className="rms-preview-dialog"
					role="dialog"
					aria-modal="true"
					aria-labelledby={ titleId }
				>
					<h2 id={ titleId }>
						{ __( 'Review the local fill', 'rankmyseo' ) }
					</h2>
					{ preview.length === 0 && (
						<p>
							{ __(
								'Nothing to fill. Those fields already have text.',
								'rankmyseo'
							) }
						</p>
					) }
					{ preview.map( ( item ) => (
						<section key={ item.id }>
							<h3>{ item.title }</h3>
							{ item.changes.map( ( change ) => (
								<div key={ change.field }>
									<p>
										<strong>
											{ fieldLabel(
												change.field,
												uiMode
											) }
										</strong>
									</p>
									<WordDiff
										before={ change.before }
										after={ change.after }
									/>
								</div>
							) ) }
						</section>
					) ) }
					<div className="rms-missing-actions">
						<Button
							variant="primary"
							disabled={ busy || preview.length === 0 }
							onClick={ () =>
								onApply(
									preview.map( ( item ) => item.id ),
									fields
								)
							}
						>
							{ __( 'Apply these details', 'rankmyseo' ) }
						</Button>
						<Button variant="tertiary" onClick={ onDismissPreview }>
							{ __( 'Cancel', 'rankmyseo' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}

interface ListResponse {
	items?: MissingRow[];
	total?: number;
	page?: number;
	post_types?: PostTypeChoice[];
	connected?: boolean;
}

function messageFrom( error: unknown ): string {
	if ( error && typeof error === 'object' && 'message' in error ) {
		const text = ( error as { message?: unknown } ).message;
		if ( typeof text === 'string' && text !== '' ) {
			return text;
		}
	}
	return __( 'That request could not be completed.', 'rankmyseo' );
}

export function MissingMetadataView(): JSX.Element {
	const { uiMode } = useAdmin();
	const [ items, setItems ] = useState< MissingRow[] >( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ filters, setFilters ] = useState< MissingFilters >( {
		missing: '',
		postType: '',
		search: '',
	} );
	const [ postTypes, setPostTypes ] = useState< PostTypeChoice[] >( [] );
	const [ connected, setConnected ] = useState( false );
	const [ preview, setPreview ] = useState< MissingPreviewItem[] | null >(
		null
	);
	const [ notice, setNotice ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		let active = true;
		const params = new URLSearchParams( {
			missing: filters.missing,
			post_type: filters.postType,
			search: filters.search,
			page: String( page ),
		} );
		setBusy( true );
		void apiFetch( {
			path: `/rankmyseo/v1/missing-metadata?${ params.toString() }`,
		} )
			.then( ( result ) => {
				if ( ! active ) {
					return;
				}
				const body = result as ListResponse;
				setItems( Array.isArray( body.items ) ? body.items : [] );
				setTotal( typeof body.total === 'number' ? body.total : 0 );
				setPostTypes(
					Array.isArray( body.post_types ) ? body.post_types : []
				);
				setConnected( body.connected === true );
				setError( '' );
			} )
			.catch( ( caught ) => {
				if ( active ) {
					setError( messageFrom( caught ) );
				}
			} )
			.finally( () => {
				if ( active ) {
					setBusy( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ filters, page ] );

	async function reload(): Promise< void > {
		const params = new URLSearchParams( {
			missing: filters.missing,
			post_type: filters.postType,
			search: filters.search,
			page: String( page ),
		} );
		const body = ( await apiFetch( {
			path: `/rankmyseo/v1/missing-metadata?${ params.toString() }`,
		} ) ) as ListResponse;
		setItems( Array.isArray( body.items ) ? body.items : [] );
		setTotal( typeof body.total === 'number' ? body.total : 0 );
		setConnected( body.connected === true );
	}

	return (
		<MissingMetadataScreen
			uiMode={ uiMode }
			items={ items }
			total={ total }
			page={ page }
			filters={ filters }
			postTypes={ postTypes }
			connected={ connected }
			preview={ preview }
			notice={ notice }
			error={ error }
			busy={ busy }
			onFilters={ ( next ) => {
				setPage( 1 );
				setFilters( next );
			} }
			onPage={ setPage }
			onPreview={ ( ids, nextFields ) => {
				setBusy( true );
				setError( '' );
				void apiFetch( {
					path: '/rankmyseo/v1/missing-metadata/preview',
					method: 'POST',
					data: { ids, fields: nextFields },
				} )
					.then( ( result ) => {
						const body = result as { items?: MissingPreviewItem[] };
						setPreview(
							Array.isArray( body.items ) ? body.items : []
						);
					} )
					.catch( ( caught ) => setError( messageFrom( caught ) ) )
					.finally( () => setBusy( false ) );
			} }
			onApply={ ( ids, nextFields ) => {
				setBusy( true );
				setError( '' );
				void apiFetch( {
					path: '/rankmyseo/v1/missing-metadata/apply',
					method: 'POST',
					data: { ids, fields: nextFields },
				} )
					.then( async ( result ) => {
						const body = result as { applied?: number };
						setPreview( null );
						setNotice(
							sprintf(
								/* translators: %d: number of pages updated. */
								__(
									'Updated %d items from the local templates. Nothing was sent off this site.',
									'rankmyseo'
								),
								typeof body.applied === 'number'
									? body.applied
									: ids.length
							)
						);
						await reload();
					} )
					.catch( ( caught ) => setError( messageFrom( caught ) ) )
					.finally( () => setBusy( false ) );
			} }
			onDismissPreview={ () => setPreview( null ) }
			onQueue={ ( ids ) => {
				setBusy( true );
				setError( '' );
				void apiFetch( {
					path: '/rankmyseo/v1/missing-metadata/queue',
					method: 'POST',
					data: { ids },
				} )
					.then( ( result ) => {
						const body = result as { message?: string };
						setNotice(
							typeof body.message === 'string' ? body.message : ''
						);
					} )
					.catch( ( caught ) => setError( messageFrom( caught ) ) )
					.finally( () => setBusy( false ) );
			} }
			onSave={ ( id, values ) => {
				setBusy( true );
				setError( '' );
				void apiFetch( {
					path: `/rankmyseo/v1/missing-metadata/${ id }`,
					method: 'POST',
					data: values,
				} )
					.then( async () => {
						setNotice( __( 'Saved.', 'rankmyseo' ) );
						await reload();
					} )
					.catch( ( caught ) => setError( messageFrom( caught ) ) )
					.finally( () => setBusy( false ) );
			} }
		/>
	);
}
