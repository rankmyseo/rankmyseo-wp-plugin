import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	EmptyState,
	SkeletonCard,
	StatusBadge,
} from '../../../shared/components/StatePrimitives';
import { useAdmin } from '../../context/AdminContext';
import {
	bulkRedirects,
	convertLogToRedirect,
	createRedirect,
	deleteRedirect,
	exportRedirects,
	fetchNotFoundLogs,
	fetchRedirectSettings,
	fetchRedirects,
	flattenRedirect,
	ignoreLog,
	importRedirects,
	saveRedirectSettings,
	testRedirectUrl,
} from './api';
import type {
	NotFoundLog,
	RedirectMatchType,
	RedirectRule,
	RedirectSettingsPayload,
	RedirectStatusCode,
	RedirectTestResult,
} from './types';

const STATUS_OPTIONS: Array< { label: string; value: string } > = [
	{ label: '301 Permanent', value: '301' },
	{ label: '302 Found', value: '302' },
	{ label: '307 Temporary', value: '307' },
	{ label: '410 Gone', value: '410' },
	{ label: '451 Unavailable for Legal Reasons', value: '451' },
];

function errorText( err: unknown, fallback: string ): string {
	if ( err instanceof Error && err.message ) {
		return err.message;
	}
	if (
		typeof err === 'object' &&
		err !== null &&
		'message' in err &&
		typeof err.message === 'string' &&
		err.message
	) {
		return err.message;
	}
	return fallback;
}

function matchLabel( type: RedirectMatchType ): string {
	if ( type === 'exact' ) {
		return __( 'Exact', 'rankmyseo' );
	}
	if ( type === 'prefix' ) {
		return __( 'Prefix', 'rankmyseo' );
	}
	return __( 'Regex', 'rankmyseo' );
}

function testMessage( result: RedirectTestResult ): string {
	if ( ! result.matched ) {
		return __( 'No redirect matches that URL.', 'rankmyseo' );
	}
	if ( result.target_url ) {
		return `${ __( 'Sends visitors to', 'rankmyseo' ) } ${
			result.target_url
		}`;
	}
	return `${ __( 'Responds with', 'rankmyseo' ) } ${ result.status_code }`;
}

function isTerminal( code: number ): boolean {
	return code === 410 || code === 451;
}

function chainFor(
	rule: RedirectRule,
	rules: RedirectRule[]
): string[] | null {
	if (
		rule.match_type !== 'exact' ||
		! rule.target_url ||
		isTerminal( rule.status_code )
	) {
		return null;
	}

	const map = new Map< string, string >();
	rules.forEach( ( item ) => {
		if (
			item.is_active &&
			item.match_type === 'exact' &&
			item.target_url &&
			! isTerminal( item.status_code )
		) {
			map.set( item.source_path, item.target_url );
		}
	} );

	const chain = [ rule.source_path, rule.target_url ];
	let cursor = rule.target_url;
	while ( map.has( cursor ) && chain.length < 8 ) {
		const next = map.get( cursor );
		if ( ! next || chain.includes( next ) ) {
			return next ? [ ...chain, next ] : chain;
		}
		chain.push( next );
		cursor = next;
	}

	return chain.length > 2 ? chain : null;
}

function downloadText( filename: string, content: string, mime: string ): void {
	if ( typeof URL.createObjectURL !== 'function' ) {
		return;
	}
	const blob = new Blob( [ content ], { type: mime } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}

export function RedirectsView(): JSX.Element {
	const {
		uiMode,
		modules,
		loading: adminLoading,
		toggleModule,
		saving,
	} = useAdmin();
	const moduleInfo = modules.find( ( item ) => item.id === 'redirects' );
	const enabled = moduleInfo?.enabled ?? true;

	const [ settings, setSettings ] =
		useState< RedirectSettingsPayload | null >( null );
	const [ rules, setRules ] = useState< RedirectRule[] >( [] );
	const [ logs, setLogs ] = useState< NotFoundLog[] >( [] );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ notice, setNotice ] = useState< string | null >( null );
	const [ chain, setChain ] = useState< string[] | null >( null );
	const [ chainId, setChainId ] = useState< number | null >( null );

	const load = useCallback( async (): Promise< void > => {
		setLoading( true );
		setError( null );
		try {
			const [ settingsRes, redirectRes, logRes ] = await Promise.all( [
				fetchRedirectSettings(),
				fetchRedirects( { page: 1, perPage: 20 } ),
				fetchNotFoundLogs( {
					status: 'open',
					page: 1,
					perPage: 20,
					orderby: 'hits',
				} ),
			] );
			setSettings( settingsRes.settings );
			setRules( redirectRes.items );
			setLogs( logRes.items );
		} catch ( err ) {
			setError(
				errorText(
					err,
					__(
						'Could not load redirects. Please try again.',
						'rankmyseo'
					)
				)
			);
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		if ( adminLoading || ! enabled ) {
			return;
		}
		void load();
	}, [ adminLoading, enabled, load ] );

	const rememberChain = (
		warning: string,
		nextChain: string[],
		id: number
	): void => {
		if ( warning === 'redirect_chain' && nextChain.length > 2 ) {
			setChain( nextChain );
			setChainId( id );
			return;
		}
		setChain( null );
		setChainId( null );
	};

	if ( adminLoading && modules.length === 0 ) {
		return (
			<div className="rms-stack">
				<SkeletonCard lines={ 2 } />
				<SkeletonCard lines={ 4 } />
			</div>
		);
	}

	if ( ! enabled ) {
		return (
			<div className="rms-stack">
				<EmptyState
					title={ __( 'Redirects are turned off', 'rankmyseo' ) }
					description={ __(
						'Turn this module on to forward old addresses and keep a list of broken pages.',
						'rankmyseo'
					) }
					actionLabel={ __( 'Enable redirects', 'rankmyseo' ) }
					onAction={ () => {
						void toggleModule( 'redirects', true );
					} }
				/>
			</div>
		);
	}

	const hasAdvancedRules = rules.some(
		( rule ) => rule.match_type !== 'exact' || rule.status_code !== 301
	);

	return (
		<div className="rms-stack">
			<div className="rms-page-header">
				<div>
					<h1 className="rms-page-title">
						{ uiMode === 'simple'
							? __(
									'Fix broken links & forward old pages',
									'rankmyseo'
							  )
							: __( 'Redirects & 404s', 'rankmyseo' ) }
					</h1>
					<p className="rms-muted">
						{ uiMode === 'simple'
							? __(
									'Send visitors from an old address to the right page. Permanent redirects are used unless you switch to Advanced.',
									'rankmyseo'
							  )
							: __(
									'Exact lookups are indexed by path hash. Regex rules run only after an exact miss, with a backtrack limit and a save-time safety check.',
									'rankmyseo'
							  ) }
					</p>
				</div>
				<ToggleControl
					__nextHasNoMarginBottom
					label={
						enabled
							? __( 'Enabled', 'rankmyseo' )
							: __( 'Disabled', 'rankmyseo' )
					}
					checked={ enabled }
					disabled={ saving }
					onChange={ ( next ) => {
						void toggleModule( 'redirects', next );
					} }
				/>
			</div>

			{ error && (
				<div className="rms-error-banner" role="alert">
					<span>{ error }</span>
					<Button
						variant="secondary"
						onClick={ () => {
							void load();
						} }
					>
						{ __( 'Try again', 'rankmyseo' ) }
					</Button>
				</div>
			) }

			{ notice && (
				<div className="rms-alert" role="status">
					{ notice }
				</div>
			) }

			{ chain && chainId && (
				<div className="rms-alert rms-alert-warn" role="status">
					<p>
						{ __(
							'This redirect continues through another rule:',
							'rankmyseo'
						) }{ ' ' }
						<span className="rms-tabular">
							{ chain.join( ' → ' ) }
						</span>
					</p>
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () => {
							void ( async () => {
								setBusy( true );
								try {
									await flattenRedirect( chainId );
									setChain( null );
									setNotice(
										__(
											'The chain now points straight at the final page.',
											'rankmyseo'
										)
									);
									await load();
								} catch ( err ) {
									setError(
										errorText(
											err,
											__(
												'Could not flatten that chain.',
												'rankmyseo'
											)
										)
									);
								} finally {
									setBusy( false );
								}
							} )();
						} }
					>
						{ __( 'Flatten chain', 'rankmyseo' ) }
					</Button>
				</div>
			) }

			{ loading && <SkeletonCard lines={ 5 } /> }
			{ ! loading && settings && uiMode === 'simple' && (
				<SimpleRedirects
					settings={ settings }
					rules={ rules }
					logs={ logs }
					busy={ busy }
					hasAdvancedRules={ hasAdvancedRules }
					onSettings={ setSettings }
					onNotice={ setNotice }
					onError={ setError }
					onBusy={ setBusy }
					onChain={ rememberChain }
					onReload={ load }
				/>
			) }
			{ ! loading && settings && uiMode === 'advanced' && (
				<AdvancedRedirects
					settings={ settings }
					rules={ rules }
					logs={ logs }
					busy={ busy }
					onSettings={ setSettings }
					onNotice={ setNotice }
					onError={ setError }
					onBusy={ setBusy }
					onChain={ rememberChain }
					onReload={ load }
				/>
			) }
		</div>
	);
}

interface PanelProps {
	settings: RedirectSettingsPayload;
	rules: RedirectRule[];
	logs: NotFoundLog[];
	busy: boolean;
	onSettings: ( settings: RedirectSettingsPayload ) => void;
	onNotice: ( message: string | null ) => void;
	onError: ( message: string | null ) => void;
	onBusy: ( busy: boolean ) => void;
	onChain: ( warning: string, chain: string[], id: number ) => void;
	onReload: () => Promise< void >;
}

function SimpleRedirects( {
	settings,
	rules,
	logs,
	busy,
	hasAdvancedRules,
	onSettings,
	onNotice,
	onError,
	onBusy,
	onChain,
	onReload,
}: PanelProps & { hasAdvancedRules: boolean } ): JSX.Element {
	const [ source, setSource ] = useState( '' );
	const [ target, setTarget ] = useState( '' );
	const [ fixingId, setFixingId ] = useState< number | null >( null );
	const [ fixTarget, setFixTarget ] = useState( '' );

	return (
		<>
			{ hasAdvancedRules && (
				<p className="rms-note" role="status">
					{ __(
						'Custom advanced rules active — switch to Advanced to edit',
						'rankmyseo'
					) }
				</p>
			) }

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ __( 'Add a redirect', 'rankmyseo' ) }
				</h2>
				<div className="rms-redirect-form">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Old link', 'rankmyseo' ) }
						value={ source }
						onChange={ setSource }
						placeholder="/old-page"
						disabled={ busy }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'New link', 'rankmyseo' ) }
						value={ target }
						onChange={ setTarget }
						placeholder="/new-page"
						disabled={ busy }
					/>
					<Button
						variant="primary"
						className="rms-btn-primary"
						disabled={ busy || ! source.trim() || ! target.trim() }
						onClick={ () => {
							void ( async () => {
								onBusy( true );
								onError( null );
								try {
									const result = await createRedirect( {
										source: source.trim(),
										target: target.trim(),
										status_code: 301,
										match_type: 'exact',
									} );
									onChain(
										result.warning,
										result.chain,
										result.redirect.id
									);
									setSource( '' );
									setTarget( '' );
									onNotice(
										__(
											'Visitors to the old link are now sent to the new page.',
											'rankmyseo'
										)
									);
									await onReload();
								} catch ( err ) {
									onError(
										errorText(
											err,
											__(
												'Could not add that redirect.',
												'rankmyseo'
											)
										)
									);
								} finally {
									onBusy( false );
								}
							} )();
						} }
					>
						{ __( 'Add redirect', 'rankmyseo' ) }
					</Button>
				</div>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Automatically forward visitors when a page address changes',
						'rankmyseo'
					) }
					checked={ settings.auto_slug }
					disabled={ busy }
					help={ __(
						'When you rename a published page, visitors to the old address are sent to the new one.',
						'rankmyseo'
					) }
					onChange={ ( next ) => {
						void ( async () => {
							onBusy( true );
							try {
								const saved = await saveRedirectSettings( {
									auto_slug: next,
								} );
								onSettings( saved.settings );
							} catch ( err ) {
								onError(
									errorText(
										err,
										__(
											'Could not save that setting.',
											'rankmyseo'
										)
									)
								);
							} finally {
								onBusy( false );
							}
						} )();
					} }
				/>
			</section>

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ __( 'Broken pages visitors hit (404s)', 'rankmyseo' ) }
				</h2>
				{ logs.length === 0 ? (
					<EmptyState
						title={ __( 'No broken pages yet', 'rankmyseo' ) }
						description={ __(
							'When a visitor opens a missing page, it will show up here so you can send them somewhere useful.',
							'rankmyseo'
						) }
					/>
				) : (
					<ul className="rms-log-list">
						{ logs.map( ( log ) => (
							<li key={ log.id } className="rms-log-row">
								<div>
									<strong className="rms-tabular">
										{ log.request_path }
									</strong>
									<p className="rms-muted">
										{ log.hits === 1
											? __( '1 visit', 'rankmyseo' )
											: `${ log.hits } visits` }
									</p>
								</div>
								{ fixingId === log.id ? (
									<div className="rms-redirect-form">
										<TextControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __(
												'Send visitors to',
												'rankmyseo'
											) }
											value={ fixTarget }
											onChange={ setFixTarget }
											placeholder="/the-right-page"
										/>
										<Button
											variant="primary"
											className="rms-btn-primary"
											disabled={
												busy || ! fixTarget.trim()
											}
											onClick={ () => {
												void ( async () => {
													onBusy( true );
													try {
														await convertLogToRedirect(
															log.id,
															fixTarget.trim()
														);
														setFixingId( null );
														setFixTarget( '' );
														onNotice(
															__(
																'That broken page now forwards visitors.',
																'rankmyseo'
															)
														);
														await onReload();
													} catch ( err ) {
														onError(
															errorText(
																err,
																__(
																	'Could not create that redirect.',
																	'rankmyseo'
																)
															)
														);
													} finally {
														onBusy( false );
													}
												} )();
											} }
										>
											{ __(
												'Create redirect',
												'rankmyseo'
											) }
										</Button>
									</div>
								) : (
									<Button
										variant="secondary"
										onClick={ () => {
											setFixingId( log.id );
											setFixTarget( '' );
										} }
									>
										{ __(
											'Fix with redirect',
											'rankmyseo'
										) }
									</Button>
								) }
							</li>
						) ) }
					</ul>
				) }
				<p className="rms-muted">
					{ rules.length === 1
						? __( '1 redirect is active.', 'rankmyseo' )
						: `${ rules.length } redirects are saved.` }
				</p>
			</section>
		</>
	);
}

function AdvancedRedirects( {
	settings,
	rules,
	logs,
	busy,
	onSettings,
	onNotice,
	onError,
	onBusy,
	onChain,
	onReload,
}: PanelProps ): JSX.Element {
	const [ source, setSource ] = useState( '' );
	const [ target, setTarget ] = useState( '' );
	const [ statusCode, setStatusCode ] = useState< RedirectStatusCode >( 301 );
	const [ matchType, setMatchType ] =
		useState< RedirectMatchType >( 'exact' );
	const [ testUrl, setTestUrl ] = useState( '' );
	const [ testResult, setTestResult ] = useState< RedirectTestResult | null >(
		null
	);
	const [ selected, setSelected ] = useState< number[] >( [] );
	const [ csv, setCsv ] = useState( '' );
	const [ ignoreText, setIgnoreText ] = useState(
		settings.ignore_patterns.join( '\n' )
	);

	const terminal = isTerminal( statusCode );

	return (
		<>
			<section className="rms-card">
				<h2 className="rms-section-title">
					{ __( 'Add a rule', 'rankmyseo' ) }
				</h2>
				<div className="rms-redirect-grid">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'From URL', 'rankmyseo' ) }
						value={ source }
						onChange={ setSource }
						disabled={ busy }
					/>
					{ ! terminal && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'To URL', 'rankmyseo' ) }
							value={ target }
							onChange={ setTarget }
							disabled={ busy }
						/>
					) }
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'HTTP status', 'rankmyseo' ) }
						value={ String( statusCode ) }
						options={ STATUS_OPTIONS }
						onChange={ ( value ) => {
							setStatusCode(
								Number( value ) as RedirectStatusCode
							);
						} }
						disabled={ busy }
					/>
					<div
						className="rms-match-toggle"
						role="radiogroup"
						aria-label={ __( 'Match type', 'rankmyseo' ) }
					>
						{ ( [ 'exact', 'prefix', 'regex' ] as const ).map(
							( type ) => (
								<button
									key={ type }
									type="button"
									role="radio"
									aria-checked={ matchType === type }
									className={ `rms-match-option${
										matchType === type ? ' is-active' : ''
									}` }
									onClick={ () => setMatchType( type ) }
								>
									{ matchLabel( type ) }
								</button>
							)
						) }
					</div>
					<Button
						variant="primary"
						className="rms-btn-primary"
						disabled={
							busy ||
							! source.trim() ||
							( ! terminal && ! target.trim() )
						}
						onClick={ () => {
							void ( async () => {
								onBusy( true );
								onError( null );
								try {
									const result = await createRedirect( {
										source: source.trim(),
										target: terminal
											? undefined
											: target.trim(),
										status_code: statusCode,
										match_type: matchType,
									} );
									onChain(
										result.warning,
										result.chain,
										result.redirect.id
									);
									setSource( '' );
									setTarget( '' );
									onNotice(
										__( 'Redirect saved.', 'rankmyseo' )
									);
									await onReload();
								} catch ( err ) {
									onError(
										errorText(
											err,
											__(
												'Could not save that redirect.',
												'rankmyseo'
											)
										)
									);
								} finally {
									onBusy( false );
								}
							} )();
						} }
					>
						{ __( 'Save redirect', 'rankmyseo' ) }
					</Button>
				</div>

				<div className="rms-redirect-form rms-tester">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Test a URL', 'rankmyseo' ) }
						value={ testUrl }
						onChange={ setTestUrl }
						placeholder="/old-page"
						disabled={ busy }
					/>
					<Button
						variant="secondary"
						disabled={ busy || ! testUrl.trim() }
						onClick={ () => {
							void ( async () => {
								onBusy( true );
								try {
									setTestResult(
										await testRedirectUrl( testUrl.trim() )
									);
								} catch ( err ) {
									onError(
										errorText(
											err,
											__(
												'Could not test that URL.',
												'rankmyseo'
											)
										)
									);
								} finally {
									onBusy( false );
								}
							} )();
						} }
					>
						{ __( 'Test URL', 'rankmyseo' ) }
					</Button>
				</div>
				{ testResult && (
					<p className="rms-note" role="status">
						{ testMessage( testResult ) }
					</p>
				) }
			</section>

			<section className="rms-card">
				<div className="rms-section-row">
					<h2 className="rms-section-title">
						{ __( 'Redirect rules', 'rankmyseo' ) }
					</h2>
					<div className="rms-inline-actions">
						<Button
							variant="secondary"
							disabled={ busy || selected.length === 0 }
							onClick={ () => {
								void ( async () => {
									onBusy( true );
									try {
										await bulkRedirects(
											'delete',
											selected
										);
										setSelected( [] );
										onNotice(
											__(
												'Selected redirects deleted.',
												'rankmyseo'
											)
										);
										await onReload();
									} catch ( err ) {
										onError(
											errorText(
												err,
												__(
													'Could not delete those redirects.',
													'rankmyseo'
												)
											)
										);
									} finally {
										onBusy( false );
									}
								} )();
							} }
						>
							{ __( 'Delete selected', 'rankmyseo' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () => {
								void ( async () => {
									try {
										const file =
											await exportRedirects( 'csv' );
										downloadText(
											file.filename,
											file.content,
											file.mime_type
										);
										onNotice(
											__(
												'CSV export is ready.',
												'rankmyseo'
											)
										);
									} catch ( err ) {
										onError(
											errorText(
												err,
												__(
													'Could not export redirects.',
													'rankmyseo'
												)
											)
										);
									}
								} )();
							} }
						>
							{ __( 'Export CSV', 'rankmyseo' ) }
						</Button>
					</div>
				</div>

				{ rules.length === 0 ? (
					<EmptyState
						title={ __( 'No redirects yet', 'rankmyseo' ) }
						description={ __(
							'Add an exact, prefix, or regex rule above.',
							'rankmyseo'
						) }
					/>
				) : (
					<div className="rms-table-wrap">
						<table className="rms-table">
							<caption className="rms-sr-only">
								{ __( 'Redirect rules', 'rankmyseo' ) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Select', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'From', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'To', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'Type', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'Code', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'Hits', 'rankmyseo' ) }
									</th>
									<th scope="col">
										{ __( 'Actions', 'rankmyseo' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ rules.map( ( rule ) => {
									const ruleChain = chainFor( rule, rules );
									return (
										<tr key={ rule.id }>
											<td>
												<input
													type="checkbox"
													aria-label={ `${ __(
														'Select',
														'rankmyseo'
													) } ${ rule.source_path }` }
													checked={ selected.includes(
														rule.id
													) }
													onChange={ () => {
														setSelected(
															( current ) =>
																current.includes(
																	rule.id
																)
																	? current.filter(
																			(
																				id
																			) =>
																				id !==
																				rule.id
																	  )
																	: [
																			...current,
																			rule.id,
																	  ]
														);
													} }
												/>
											</td>
											<td className="rms-tabular">
												{ rule.source_path }
											</td>
											<td className="rms-tabular">
												{ rule.target_url ?? '—' }
											</td>
											<td>{ rule.match_type }</td>
											<td className="rms-tabular">
												{ rule.status_code }
												{ ruleChain && (
													<StatusBadge tone="warn">
														{ __(
															'Chain',
															'rankmyseo'
														) }
													</StatusBadge>
												) }
											</td>
											<td className="rms-tabular">
												{ rule.hits }
											</td>
											<td>
												<Button
													variant="link"
													disabled={ busy }
													onClick={ () => {
														void ( async () => {
															onBusy( true );
															try {
																await deleteRedirect(
																	rule.id
																);
																await onReload();
															} catch ( err ) {
																onError(
																	errorText(
																		err,
																		__(
																			'Could not delete that redirect.',
																			'rankmyseo'
																		)
																	)
																);
															} finally {
																onBusy( false );
															}
														} )();
													} }
												>
													{ __(
														'Delete',
														'rankmyseo'
													) }
												</Button>
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					</div>
				) }

				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Import CSV', 'rankmyseo' ) }
					value={ csv }
					onChange={ setCsv }
					help={ __(
						'Columns: source_path, match_type, target_url, status_code, is_active. Formula-like cells are stored as text.',
						'rankmyseo'
					) }
					rows={ 4 }
				/>
				<Button
					variant="secondary"
					disabled={ busy || ! csv.trim() }
					onClick={ () => {
						void ( async () => {
							onBusy( true );
							try {
								const result = await importRedirects( csv );
								setCsv( '' );
								onNotice(
									`${ result.created } added, ${ result.updated } updated, ${ result.skipped } skipped.`
								);
								await onReload();
							} catch ( err ) {
								onError(
									errorText(
										err,
										__(
											'Could not import that CSV.',
											'rankmyseo'
										)
									)
								);
							} finally {
								onBusy( false );
							}
						} )();
					} }
				>
					{ __( 'Upload CSV', 'rankmyseo' ) }
				</Button>
			</section>

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ __( 'Broken pages (404s)', 'rankmyseo' ) }
				</h2>
				{ logs.length === 0 ? (
					<p className="rms-muted">
						{ __( 'No open 404s.', 'rankmyseo' ) }
					</p>
				) : (
					<ul className="rms-log-list">
						{ logs.map( ( log ) => (
							<li key={ log.id } className="rms-log-row">
								<span className="rms-tabular">
									{ log.request_path }
								</span>
								<span className="rms-tabular">
									{ log.hits }
								</span>
								<Button
									variant="link"
									disabled={ busy }
									onClick={ () => {
										void ( async () => {
											await ignoreLog( log.id );
											await onReload();
										} )();
									} }
								>
									{ __( 'Ignore', 'rankmyseo' ) }
								</Button>
							</li>
						) ) }
					</ul>
				) }
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( '404 ignore rules', 'rankmyseo' ) }
					help={ __(
						'One path pattern per line. Use * as a wildcard, or start a line with regex: for a checked regular expression.',
						'rankmyseo'
					) }
					value={ ignoreText }
					onChange={ setIgnoreText }
					rows={ 4 }
				/>
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ () => {
						void ( async () => {
							onBusy( true );
							try {
								const saved = await saveRedirectSettings( {
									ignore_patterns: ignoreText
										.split( '\n' )
										.map( ( line ) => line.trim() )
										.filter( Boolean ),
									auto_slug: settings.auto_slug,
									preserve_query: settings.preserve_query,
									ttl_days: settings.ttl_days,
									max_rows: settings.max_rows,
								} );
								onSettings( saved.settings );
								onNotice(
									__( 'Ignore rules saved.', 'rankmyseo' )
								);
							} catch ( err ) {
								onError(
									errorText(
										err,
										__(
											'Could not save ignore rules.',
											'rankmyseo'
										)
									)
								);
							} finally {
								onBusy( false );
							}
						} )();
					} }
				>
					{ __( 'Save ignore rules', 'rankmyseo' ) }
				</Button>
			</section>
		</>
	);
}
