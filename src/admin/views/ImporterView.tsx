/**
 * Simple and advanced migration importer screen.
 */

import { Button, CheckboxControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useAdmin } from '../context/AdminContext';
import {
	fetchDetectedSources,
	fetchImportHistory,
	previewImport,
	rollbackImport,
	runImportBatch,
} from './importer/api';
import type {
	DetectedSource,
	HistoryEntry,
	ImportOptions,
	PreviewResult,
} from './importer/types';

const DEFAULT_OPTIONS: Omit< ImportOptions, 'source' > = {
	include_posts: true,
	include_terms: true,
	include_settings: true,
	include_redirects: true,
	overwrite: false,
	batch_size: 100,
};

function sourceSentence( source: DetectedSource ): string {
	return sprintf(
		/* translators: 1: SEO plugin name, 2: number of posts with saved settings */
		__(
			'We found %1$s settings on %2$d posts — nothing will be deleted',
			'rankmyseo'
		),
		source.label,
		source.posts
	);
}

function readyTotal( preview: PreviewResult ): number {
	return preview.posts + preview.terms + preview.redirects + preview.settings;
}

export function ImporterView(): JSX.Element {
	const { uiMode } = useAdmin();
	const advanced = uiMode === 'advanced';
	const [ sources, setSources ] = useState< DetectedSource[] >( [] );
	const [ history, setHistory ] = useState< HistoryEntry[] >( [] );
	const [ selected, setSelected ] = useState( '' );
	const [ options, setOptions ] = useState( DEFAULT_OPTIONS );
	const [ preview, setPreview ] = useState< PreviewResult | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ progress, setProgress ] = useState( 0 );
	const [ running, setRunning ] = useState( false );
	const [ lastImportId, setLastImportId ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		Promise.all( [ fetchDetectedSources(), fetchImportHistory() ] )
			.then( ( [ detected, past ] ) => {
				if ( cancelled ) {
					return;
				}
				const found = detected.sources.filter(
					( source ) => source.found
				);
				found.sort(
					( left, right ) =>
						right.posts + right.terms - ( left.posts + left.terms )
				);
				setSources( detected.sources );
				setHistory( past.imports );
				setSelected( found[ 0 ]?.id ?? '' );
				setLoading( false );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setError(
						__(
							'The importer could not read this site.',
							'rankmyseo'
						)
					);
					setLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const selectedSource = sources.find( ( source ) => source.id === selected );
	const foundSources = sources.filter( ( source ) => source.found );

	const currentOptions = (): ImportOptions => ( {
		source: selected,
		...options,
		include_posts: advanced ? options.include_posts : true,
		include_terms: advanced ? options.include_terms : true,
		include_settings: advanced ? options.include_settings : true,
		include_redirects: advanced ? options.include_redirects : true,
		overwrite: advanced ? options.overwrite : false,
	} );

	const refreshHistory = async (): Promise< void > => {
		const past = await fetchImportHistory();
		setHistory( past.imports );
	};

	const onPreview = async (): Promise< void > => {
		setBusy( true );
		setError( '' );
		setMessage( '' );
		try {
			const result = await previewImport( currentOptions() );
			setPreview( result );
		} catch {
			setError(
				__( 'The dry run could not be completed.', 'rankmyseo' )
			);
		} finally {
			setBusy( false );
		}
	};

	const onImport = async (): Promise< void > => {
		if ( ! preview ) {
			return;
		}
		setRunning( true );
		setBusy( true );
		setError( '' );
		setProgress( 0 );
		const total = Math.max( 1, readyTotal( preview ) );
		let cursor = '';
		let importId = '';
		let seen = 0;
		try {
			for ( let guard = 0; guard < 500; guard++ ) {
				const batch = await runImportBatch(
					currentOptions(),
					cursor,
					importId
				);
				importId = batch.import_id;
				seen += batch.processed;
				setProgress(
					Math.min( 100, Math.round( ( seen / total ) * 100 ) )
				);
				if ( batch.done ) {
					break;
				}
				cursor = batch.next_cursor;
			}
			setLastImportId( importId );
			setMessage(
				__(
					'Import finished. The original plugin data is still there.',
					'rankmyseo'
				)
			);
			await refreshHistory();
		} catch {
			setError(
				__( 'The import stopped before it finished.', 'rankmyseo' )
			);
		} finally {
			setRunning( false );
			setBusy( false );
		}
	};

	const onRollback = async ( importId: string ): Promise< void > => {
		setBusy( true );
		setError( '' );
		try {
			await rollbackImport( importId );
			if ( importId === lastImportId ) {
				setLastImportId( '' );
			}
			setMessage( __( 'That import was undone.', 'rankmyseo' ) );
			await refreshHistory();
		} catch {
			setError( __( 'The import could not be undone.', 'rankmyseo' ) );
		} finally {
			setBusy( false );
		}
	};

	const updateOption = (
		key: keyof typeof options,
		value: boolean
	): void => {
		setOptions( ( current ) => ( { ...current, [ key ]: value } ) );
		setPreview( null );
	};

	return (
		<section className="rms-import" aria-labelledby="rms-import-title">
			<header className="rms-import-header">
				<h2 id="rms-import-title">
					{ __( 'Import SEO settings', 'rankmyseo' ) }
				</h2>
				<p>
					{ __(
						'Copy titles, descriptions, and redirects into RankMySEO. Nothing from the other plugin is deleted.',
						'rankmyseo'
					) }
				</p>
			</header>

			{ error && (
				<p className="rms-import-error" role="alert">
					{ error }
				</p>
			) }
			{ message && (
				<p className="rms-import-message" role="status">
					{ message }
				</p>
			) }

			{ loading && (
				<p>{ __( 'Looking for saved SEO settings…', 'rankmyseo' ) }</p>
			) }

			{ ! loading && foundSources.length === 0 && (
				<div className="rms-card rms-import-empty">
					<p>
						{ __(
							'We didn’t find settings from another SEO plugin.',
							'rankmyseo'
						) }
					</p>
				</div>
			) }

			{ ! loading && foundSources.length > 0 && (
				<div
					className="rms-import-cards"
					role="group"
					aria-label={ __( 'Detected SEO plugins', 'rankmyseo' ) }
				>
					{ foundSources.map( ( source ) => (
						<button
							key={ source.id }
							type="button"
							className={ `rms-card rms-import-card${
								selected === source.id ? ' is-selected' : ''
							}` }
							aria-pressed={ selected === source.id }
							onClick={ () => {
								setSelected( source.id );
								setPreview( null );
							} }
						>
							<strong>{ source.label }</strong>
							<span>{ sourceSentence( source ) }</span>
							{ source.active && (
								<span className="rms-import-note">
									{ __(
										'The original plugin can stay active. Its data will not be deleted.',
										'rankmyseo'
									) }
								</span>
							) }
						</button>
					) ) }
				</div>
			) }

			{ advanced && selectedSource && (
				<fieldset className="rms-card rms-import-options">
					<legend>{ __( 'What to copy', 'rankmyseo' ) }</legend>
					<CheckboxControl
						label={ __( 'Post meta', 'rankmyseo' ) }
						checked={ options.include_posts }
						onChange={ ( checked ) =>
							updateOption( 'include_posts', checked )
						}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={ __( 'Term meta', 'rankmyseo' ) }
						checked={ options.include_terms }
						onChange={ ( checked ) =>
							updateOption( 'include_terms', checked )
						}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={ __( 'Global settings', 'rankmyseo' ) }
						checked={ options.include_settings }
						onChange={ ( checked ) =>
							updateOption( 'include_settings', checked )
						}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={ __( 'Redirects', 'rankmyseo' ) }
						checked={ options.include_redirects }
						onChange={ ( checked ) =>
							updateOption( 'include_redirects', checked )
						}
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={ __(
							'Overwrite existing RankMySEO values',
							'rankmyseo'
						) }
						checked={ options.overwrite }
						onChange={ ( checked ) =>
							updateOption( 'overwrite', checked )
						}
						__nextHasNoMarginBottom
					/>
					<div className="rms-import-batch">
						<label htmlFor="rms-import-batch-size">
							{ __( 'Batch size', 'rankmyseo' ) }
						</label>
						<input
							id="rms-import-batch-size"
							type="number"
							min={ 1 }
							max={ 200 }
							value={ options.batch_size }
							onChange={ ( event ) => {
								const next = Number( event.target.value );
								setOptions( ( current ) => ( {
									...current,
									batch_size: Number.isFinite( next )
										? Math.min( 200, Math.max( 1, next ) )
										: 100,
								} ) );
								setPreview( null );
							} }
						/>
					</div>
				</fieldset>
			) }

			{ selectedSource && (
				<div className="rms-import-actions">
					<Button
						variant="secondary"
						disabled={ busy || ! selected }
						onClick={ () => {
							void onPreview();
						} }
					>
						{ __( 'Dry-run preview', 'rankmyseo' ) }
					</Button>
					<Button
						variant="primary"
						className="rms-btn-primary"
						disabled={ busy || ! preview }
						onClick={ () => {
							void onImport();
						} }
					>
						{ __( 'Import these settings', 'rankmyseo' ) }
					</Button>
					{ lastImportId && (
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () => {
								void onRollback( lastImportId );
							} }
						>
							{ __( 'Undo import', 'rankmyseo' ) }
						</Button>
					) }
				</div>
			) }

			{ running && (
				<div
					className="rms-import-progress"
					role="progressbar"
					aria-valuemin={ 0 }
					aria-valuemax={ 100 }
					aria-valuenow={ progress }
					aria-label={ __( 'Import progress', 'rankmyseo' ) }
				>
					<span style={ { width: `${ progress }%` } } />
				</div>
			) }

			{ preview && (
				<div className="rms-card rms-import-preview">
					<p>
						{ sprintf(
							/* translators: 1: posts, 2: terms, 3: redirects, 4: settings records */
							__(
								'%1$d posts, %2$d terms, %3$d redirects, and %4$d site settings are ready to import.',
								'rankmyseo'
							),
							preview.posts,
							preview.terms,
							preview.redirects,
							preview.settings
						) }
					</p>
					{ advanced && preview.samples.length > 0 && (
						<table className="rms-import-diff">
							<caption>
								{ __(
									'Sample of what will change',
									'rankmyseo'
								) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Field', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'Before', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'After', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'Action', 'rankmyseo' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ preview.samples.map( ( sample ) => (
									<tr
										key={ `${ sample.object_type }-${ sample.object_id }-${ sample.field }` }
									>
										<th scope="row">
											{ sample.field_label }
										</th>
										<td>{ sample.before || '—' }</td>
										<td>{ sample.after || '—' }</td>
										<td>
											{ sample.action === 'skip'
												? __( 'Skip', 'rankmyseo' )
												: __( 'Write', 'rankmyseo' ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</div>
			) }

			{ advanced && (
				<div className="rms-card rms-import-history">
					<h3>{ __( 'Import history', 'rankmyseo' ) }</h3>
					{ history.length === 0 && (
						<p>{ __( 'No imports yet.', 'rankmyseo' ) }</p>
					) }
					{ history.length > 0 && (
						<ul>
							{ history.map( ( entry ) => (
								<li key={ entry.import_id }>
									<span>
										{ sprintf(
											/* translators: 1: source id, 2: status, 3: number of values written */
											__(
												'%1$s · %2$s · %3$d values written',
												'rankmyseo'
											),
											entry.source,
											entry.status,
											entry.written
										) }
									</span>
									{ entry.status !== 'rolled_back' && (
										<Button
											variant="secondary"
											disabled={ busy }
											onClick={ () => {
												void onRollback(
													entry.import_id
												);
											} }
										>
											{ __( 'Undo import', 'rankmyseo' ) }
										</Button>
									) }
								</li>
							) ) }
						</ul>
					) }
				</div>
			) }
		</section>
	);
}
