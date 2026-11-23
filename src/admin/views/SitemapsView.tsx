import {
	Button,
	CheckboxControl,
	RadioControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	fetchSitemapStatus,
	flushSitemapCache,
	pingIndexNow,
	rotateIndexNowKey,
	saveSitemapSettings,
} from '../../shared/api';
import { Term } from '../../shared/components/Term';
import {
	ErrorBanner,
	SkeletonCard,
	StatusBadge,
} from '../../shared/components/StatePrimitives';
import {
	blocksEntireSite,
	flagMap,
	previewRobots,
	type SitemapCrawlFlags,
	type SitemapStatus,
	type SitemapTypeRow,
} from '../../shared/sitemaps';
import { useAdmin } from '../context/AdminContext';
import '../sitemaps.css';

const CRAWL_LABELS: Record< keyof SitemapCrawlFlags, string > = {
	redirect_attachments: __(
		'Redirect attachment pages to the parent or file',
		'rankmyseo'
	),
	noindex_attachments: __( 'Hide attachment pages from search', 'rankmyseo' ),
	noindex_search: __(
		'Hide internal search results from search',
		'rankmyseo'
	),
	noindex_comment_pages: __(
		'Hide extra comment pages from search',
		'rankmyseo'
	),
	strip_replytocom: __( 'Remove replytocom links', 'rankmyseo' ),
	remove_rsd: __( 'Remove the Really Simple Discovery link', 'rankmyseo' ),
	remove_wlw: __( 'Remove the Windows Live Writer link', 'rankmyseo' ),
	remove_shortlink: __( 'Remove the shortlink tag', 'rankmyseo' ),
};

function rowIncluded( rows: SitemapTypeRow[], name: string ): boolean {
	return rows.find( ( row ) => row.name === name )?.included ?? false;
}

function rowAvailable( rows: SitemapTypeRow[], name: string ): boolean {
	return rows.find( ( row ) => row.name === name )?.available ?? false;
}

export function SitemapsView(): JSX.Element {
	const { uiMode, saving, toggleModule } = useAdmin();
	const [ status, setStatus ] = useState< SitemapStatus | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ maxUrls, setMaxUrls ] = useState( 1000 );
	const [ includeImages, setIncludeImages ] = useState( true );
	const [ includeAuthors, setIncludeAuthors ] = useState( true );
	const [ robotsMode, setRobotsMode ] = useState< 'preset' | 'custom' >(
		'preset'
	);
	const [ robotsPreset, setRobotsPreset ] = useState< 'default' | 'open' >(
		'default'
	);
	const [ robotsDraft, setRobotsDraft ] = useState( '' );
	const [ excludedIds, setExcludedIds ] = useState( '' );
	const [ crawl, setCrawl ] = useState< SitemapCrawlFlags | null >( null );
	const [ pingUrl, setPingUrl ] = useState( '' );
	const [ pingMessage, setPingMessage ] = useState< string | null >( null );

	const applyStatus = ( next: SitemapStatus ): void => {
		setStatus( next );
		setMaxUrls( next.max_urls );
		setIncludeImages( next.include_images );
		setIncludeAuthors( next.include_authors );
		setRobotsMode( next.robots.mode );
		setRobotsPreset( next.robots.preset );
		setRobotsDraft( next.robots.custom );
		setExcludedIds( next.excluded_post_ids.join( ', ' ) );
		setCrawl( next.crawl );
	};

	const load = async (): Promise< void > => {
		setLoading( true );
		setError( null );
		try {
			applyStatus( await fetchSitemapStatus() );
		} catch ( err ) {
			setError(
				err instanceof Error
					? err.message
					: __(
							'Could not load sitemap settings. Please try again.',
							'rankmyseo'
					  )
			);
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		void load();
		// Load once when the screen opens.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const persist = async (
		payload: Record< string, unknown >
	): Promise< void > => {
		setBusy( true );
		setError( null );
		try {
			const response = await saveSitemapSettings( payload );
			applyStatus( response.status );
		} catch ( err ) {
			setError(
				err instanceof Error
					? err.message
					: __( 'Could not save sitemap settings.', 'rankmyseo' )
			);
		} finally {
			setBusy( false );
		}
	};

	if ( loading && ! status ) {
		return (
			<div className="rms-stack">
				<SkeletonCard lines={ 4 } />
				<SkeletonCard lines={ 3 } />
			</div>
		);
	}

	if ( ! status || ! crawl ) {
		return (
			<ErrorBanner
				message={
					error ??
					__( 'Sitemap settings are unavailable.', 'rankmyseo' )
				}
				onRetry={ () => {
					void load();
				} }
			/>
		);
	}

	const simple = uiMode === 'simple';
	const livePreview =
		robotsMode === 'custom'
			? previewRobots( robotsDraft, status.sitemap_url )
			: status.robots.preview;
	const draftBlocksSite =
		robotsMode === 'custom' && blocksEntireSite( robotsDraft );
	const simpleTypes: Array< { name: string; label: string } > = [
		{ name: 'post', label: __( 'Include Posts', 'rankmyseo' ) },
		{ name: 'page', label: __( 'Include Pages', 'rankmyseo' ) },
		{ name: 'product', label: __( 'Include Products', 'rankmyseo' ) },
	];

	return (
		<div className="rms-stack">
			<div className="rms-page-header">
				<div>
					<div className="rms-title-row">
						<h1 className="rms-page-title">
							{ simple
								? __(
										'Site Map & Search Engine Updates',
										'rankmyseo'
								  )
								: __( 'Sitemaps & Crawl', 'rankmyseo' ) }
						</h1>
						<StatusBadge
							tone={ status.module_enabled ? 'up' : 'flat' }
						>
							{ status.module_enabled
								? __( 'Module enabled', 'rankmyseo' )
								: __( 'Module disabled', 'rankmyseo' ) }
						</StatusBadge>
					</div>
					<p className="rms-muted">
						{ simple
							? __(
									'Keep an automatic map of your public pages and choose when search engines should be told about updates.',
									'rankmyseo'
							  )
							: __(
									'Paginated XML sitemaps, IndexNow submissions, and virtual robots.txt rules.',
									'rankmyseo'
							  ) }
					</p>
				</div>
				<div className="rms-page-header-actions">
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							status.module_enabled
								? __( 'Sitemaps on', 'rankmyseo' )
								: __( 'Sitemaps off', 'rankmyseo' )
						}
						checked={ status.module_enabled }
						disabled={ saving || busy }
						onChange={ ( next ) => {
							void toggleModule( 'sitemaps', next ).then( () =>
								load()
							);
						} }
					/>
				</div>
			</div>

			{ error && (
				<ErrorBanner
					message={ error }
					onRetry={ () => {
						void load();
					} }
				/>
			) }

			{ status.robots.warnings.map( ( warning ) => (
				<div
					key={ warning.code }
					className={ `rms-warning${
						warning.code === 'disallow_all'
							? ' rms-warning-danger'
							: ''
					}` }
					role="alert"
				>
					{ warning.message }
				</div>
			) ) }

			<section
				className={ `rms-card rms-sitemap-status${
					status.module_enabled ? ' is-active' : ''
				}` }
				aria-live="polite"
			>
				<h2 className="rms-section-title">
					<StatusBadge tone={ status.module_enabled ? 'up' : 'flat' }>
						{ status.module_enabled
							? __( 'Sitemap is active', 'rankmyseo' )
							: __( 'Sitemap is off', 'rankmyseo' ) }
					</StatusBadge>
				</h2>
				{ status.module_enabled ? (
					<p className="rms-sitemap-note">
						<a
							className="rms-sitemap-url"
							href={ status.sitemap_url }
							target="_blank"
							rel="noreferrer"
						>
							{ status.sitemap_url }
						</a>
					</p>
				) : (
					<p className="rms-muted rms-sitemap-note">
						{ __(
							'Turn sitemaps on to publish your XML sitemap. WordPress keeps its own sitemap until then.',
							'rankmyseo'
						) }
					</p>
				) }
			</section>

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ simple
						? __( 'Pages to include', 'rankmyseo' )
						: __( 'Sitemap contents', 'rankmyseo' ) }
				</h2>
				{ simple ? (
					<div className="rms-sitemap-grid">
						{ simpleTypes.map( ( type ) => (
							<ToggleControl
								key={ type.name }
								__nextHasNoMarginBottom
								label={ type.label }
								checked={ rowIncluded(
									status.post_types,
									type.name
								) }
								disabled={ busy }
								help={
									type.name === 'product' &&
									! rowAvailable(
										status.post_types,
										'product'
									)
										? __(
												'Products are included when a product post type exists.',
												'rankmyseo'
										  )
										: undefined
								}
								onChange={ ( next ) => {
									void persist( {
										post_types: flagMap(
											status.post_types,
											type.name,
											next
										),
									} );
								} }
							/>
						) ) }
					</div>
				) : (
					<table className="rms-sitemap-matrix">
						<thead>
							<tr>
								<th>{ __( 'Content type', 'rankmyseo' ) }</th>
								<th>{ __( 'In sitemap', 'rankmyseo' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ status.post_types.map( ( row ) => (
								<tr key={ `post-${ row.name }` }>
									<td>
										{ row.label }
										{ ! row.available && (
											<span className="rms-muted">
												{ ' ' }
												{ __(
													'(not installed)',
													'rankmyseo'
												) }
											</span>
										) }
									</td>
									<td>
										<CheckboxControl
											__nextHasNoMarginBottom
											label={ `${ __(
												'Include',
												'rankmyseo'
											) } ${ row.label }` }
											checked={ row.included }
											onChange={ ( next ) => {
												void persist( {
													post_types: flagMap(
														status.post_types,
														row.name,
														next
													),
												} );
											} }
										/>
									</td>
								</tr>
							) ) }
							{ status.taxonomies.map( ( row ) => (
								<tr key={ `tax-${ row.name }` }>
									<td>{ row.label }</td>
									<td>
										<CheckboxControl
											__nextHasNoMarginBottom
											label={ `${ __(
												'Include',
												'rankmyseo'
											) } ${ row.label }` }
											checked={ row.included }
											onChange={ ( next ) => {
												void persist( {
													taxonomies: flagMap(
														status.taxonomies,
														row.name,
														next
													),
												} );
											} }
										/>
									</td>
								</tr>
							) ) }
							<tr>
								<td>{ __( 'Authors', 'rankmyseo' ) }</td>
								<td>
									<CheckboxControl
										__nextHasNoMarginBottom
										label={ __(
											'Include Authors',
											'rankmyseo'
										) }
										checked={ includeAuthors }
										onChange={ ( next ) => {
											setIncludeAuthors( next );
											void persist( {
												include_authors: next,
											} );
										} }
									/>
								</td>
							</tr>
						</tbody>
					</table>
				) }
			</section>

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ __( 'Enable IndexNow instant indexing', 'rankmyseo' ) }
				</h2>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Enable IndexNow instant indexing',
						'rankmyseo'
					) }
					checked={ status.indexnow.enabled }
					disabled={ busy }
					onChange={ ( next ) => {
						void persist( { indexnow_enabled: next } );
					} }
				/>
				<p className="rms-muted rms-sitemap-note">
					{ __(
						'IndexNow stays off until you enable it. After you enable it, RankMySEO sends changed public page addresses to IndexNow at https://api.indexnow.org/indexnow when you publish, update, or delete them. The request includes your site host, your IndexNow key, and those addresses.',
						'rankmyseo'
					) }
				</p>
				{ ! simple && (
					<div className="rms-stack">
						<label
							className="rms-field-label"
							htmlFor="rms-indexnow-key"
						>
							{ __( 'IndexNow key', 'rankmyseo' ) }
						</label>
						<input
							id="rms-indexnow-key"
							className="rms-sitemap-key rms-sitemap-input"
							readOnly
							value={ status.indexnow.key }
						/>
						<p className="rms-muted">
							{ __( 'Key file:', 'rankmyseo' ) }{ ' ' }
							<a href={ status.indexnow.key_url }>
								{ status.indexnow.key_url }
							</a>
						</p>
						<div className="rms-sitemap-actions">
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => {
									setBusy( true );
									void rotateIndexNowKey()
										.then( ( response ) =>
											applyStatus( response.status )
										)
										.catch( () =>
											setError(
												__(
													'Could not rotate the IndexNow key.',
													'rankmyseo'
												)
											)
										)
										.finally( () => setBusy( false ) );
								} }
							>
								{ __( 'Rotate key', 'rankmyseo' ) }
							</Button>
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ () => {
									setBusy( true );
									void flushSitemapCache()
										.then( ( response ) =>
											applyStatus( response.status )
										)
										.catch( () =>
											setError(
												__(
													'Could not refresh the sitemap cache.',
													'rankmyseo'
												)
											)
										)
										.finally( () => setBusy( false ) );
								} }
							>
								{ __(
									'Regenerate sitemap cache',
									'rankmyseo'
								) }
							</Button>
						</div>
						<label
							className="rms-field-label"
							htmlFor="rms-ping-url"
						>
							{ __( 'Ping URL now', 'rankmyseo' ) }
						</label>
						<input
							id="rms-ping-url"
							className="rms-sitemap-input"
							type="url"
							value={ pingUrl }
							placeholder={ status.sitemap_url.replace(
								'/sitemap_index.xml',
								'/'
							) }
							onChange={ ( event ) =>
								setPingUrl( event.target.value )
							}
						/>
						<div className="rms-sitemap-actions">
							<Button
								variant="primary"
								className="rms-btn-primary"
								disabled={
									busy ||
									! status.indexnow.enabled ||
									pingUrl.trim() === ''
								}
								onClick={ () => {
									setBusy( true );
									setPingMessage( null );
									void pingIndexNow( pingUrl.trim() )
										.then( ( response ) => {
											setPingMessage(
												response.result.message
											);
											setStatus( ( current ) =>
												current
													? {
															...current,
															indexnow: {
																...current.indexnow,
																log: response.log,
															},
													  }
													: current
											);
										} )
										.catch( () =>
											setError(
												__(
													'IndexNow did not accept that URL. Turn IndexNow on and use a URL from this site.',
													'rankmyseo'
												)
											)
										)
										.finally( () => setBusy( false ) );
								} }
							>
								{ __( 'Ping URL now', 'rankmyseo' ) }
							</Button>
						</div>
						{ pingMessage && (
							<p role="status" className="rms-muted">
								{ pingMessage }
							</p>
						) }
						<h3 className="rms-card-title">
							{ __( 'Recent submissions', 'rankmyseo' ) }
						</h3>
						{ status.indexnow.log.length === 0 ? (
							<p className="rms-muted">
								{ __(
									'No IndexNow submissions yet.',
									'rankmyseo'
								) }
							</p>
						) : (
							<ul className="rms-indexnow-log">
								{ status.indexnow.log.map( ( entry ) => (
									<li
										key={ `${ entry.time }-${ entry.message }` }
									>
										<StatusBadge
											tone={ entry.ok ? 'up' : 'down' }
										>
											{ entry.ok
												? __( 'Sent', 'rankmyseo' )
												: __( 'Failed', 'rankmyseo' ) }
										</StatusBadge>
										<span>{ entry.message }</span>
										<span className="rms-muted">
											{ entry.count }{ ' ' }
											{ __( 'URLs', 'rankmyseo' ) }
										</span>
									</li>
								) ) }
							</ul>
						) }
					</div>
				) }
			</section>

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ simple ? (
						__( 'Crawl safety', 'rankmyseo' )
					) : (
						<>
							<Term term="robots_txt">robots.txt</Term>
						</>
					) }
				</h2>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Let search engines crawl my public pages',
						'rankmyseo'
					) }
					checked={ status.allow_crawl }
					disabled={ busy }
					help={ __(
						'Leave this on unless you intentionally want to block the whole site.',
						'rankmyseo'
					) }
					onChange={ ( next ) => {
						void persist( { allow_crawl: next } );
					} }
				/>
				{ ! simple && (
					<div className="rms-stack">
						<RadioControl
							label={ __( 'robots.txt source', 'rankmyseo' ) }
							selected={ robotsMode }
							options={ [
								{
									label: __( 'Safe preset', 'rankmyseo' ),
									value: 'preset',
								},
								{
									label: __( 'Custom rules', 'rankmyseo' ),
									value: 'custom',
								},
							] }
							onChange={ ( value ) =>
								setRobotsMode(
									value === 'custom' ? 'custom' : 'preset'
								)
							}
						/>
						{ robotsMode === 'preset' && (
							<RadioControl
								label={ __( 'Preset', 'rankmyseo' ) }
								selected={ robotsPreset }
								options={ [
									{
										label: __(
											'Default (block wp-admin only)',
											'rankmyseo'
										),
										value: 'default',
									},
									{
										label: __(
											'Open (allow the whole site)',
											'rankmyseo'
										),
										value: 'open',
									},
								] }
								onChange={ ( value ) =>
									setRobotsPreset(
										value === 'open' ? 'open' : 'default'
									)
								}
							/>
						) }
						{ robotsMode === 'custom' && (
							<>
								<label
									className="rms-field-label"
									htmlFor="rms-robots-custom"
								>
									{ __( 'Custom robots.txt', 'rankmyseo' ) }
								</label>
								<textarea
									id="rms-robots-custom"
									className="rms-robots-editor"
									value={ robotsDraft }
									onChange={ ( event ) =>
										setRobotsDraft( event.target.value )
									}
								/>
							</>
						) }
						{ draftBlocksSite && (
							<div
								className="rms-warning rms-warning-danger"
								role="alert"
							>
								{ __(
									'These rules include Disallow: /, which blocks crawlers from the entire site.',
									'rankmyseo'
								) }
							</div>
						) }
						<h3 className="rms-card-title">
							{ __( 'Live preview', 'rankmyseo' ) }
						</h3>
						<pre className="rms-robots-preview">
							{ livePreview }
						</pre>
						<label
							className="rms-field-label"
							htmlFor="rms-max-urls"
						>
							{ __( 'URLs per sitemap', 'rankmyseo' ) }
						</label>
						<input
							id="rms-max-urls"
							className="rms-sitemap-input"
							type="number"
							min={ 100 }
							max={ 2000 }
							value={ maxUrls }
							onChange={ ( event ) =>
								setMaxUrls( Number( event.target.value ) )
							}
						/>
						<p className="rms-muted">
							{ __(
								'Between 100 and 2000. The default is 1000.',
								'rankmyseo'
							) }
						</p>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __(
								'Include images in the sitemap',
								'rankmyseo'
							) }
							checked={ includeImages }
							onChange={ setIncludeImages }
						/>
						<label
							className="rms-field-label"
							htmlFor="rms-excluded-ids"
						>
							{ __( 'Excluded post IDs', 'rankmyseo' ) }
						</label>
						<input
							id="rms-excluded-ids"
							className="rms-sitemap-input"
							value={ excludedIds }
							onChange={ ( event ) =>
								setExcludedIds( event.target.value )
							}
						/>
						<h3 className="rms-card-title">
							{ __( 'Crawl hygiene', 'rankmyseo' ) }
						</h3>
						{ (
							Object.keys( CRAWL_LABELS ) as Array<
								keyof SitemapCrawlFlags
							>
						 ).map( ( key ) => (
							<CheckboxControl
								key={ key }
								__nextHasNoMarginBottom
								label={ CRAWL_LABELS[ key ] }
								checked={ crawl[ key ] }
								onChange={ ( next ) =>
									setCrawl( {
										...crawl,
										[ key ]: next,
									} )
								}
							/>
						) ) }
						<div className="rms-sitemap-actions">
							<Button
								variant="primary"
								className="rms-btn-primary"
								disabled={ busy }
								onClick={ () => {
									void persist( {
										max_urls: maxUrls,
										include_images: includeImages,
										include_authors: includeAuthors,
										robots_mode: robotsMode,
										robots_preset: robotsPreset,
										robots_custom: robotsDraft,
										excluded_post_ids: excludedIds,
										crawl,
									} );
								} }
							>
								{ __( 'Save crawl settings', 'rankmyseo' ) }
							</Button>
						</div>
					</div>
				) }
			</section>
		</div>
	);
}
