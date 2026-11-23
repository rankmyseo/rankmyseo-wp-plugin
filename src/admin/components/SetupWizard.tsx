import { Button, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { SiteType } from '../../shared/types';
import { useAdmin } from '../context/AdminContext';

const SEPARATORS = [ '|', '-', '·', '—', '/', '•' ];

interface WizardModules {
	meta: boolean;
	schema: boolean;
	sitemaps: boolean;
	redirects: boolean;
}

interface MediaAttachment {
	id?: number;
	url?: string;
}

interface MediaFrame {
	on: ( event: 'select', callback: () => void ) => void;
	open: () => void;
	state: () => {
		get: ( key: 'selection' ) => {
			first: () => { toJSON: () => MediaAttachment };
		};
	};
}

function openLogoPicker(
	onPick: ( logoId: number, logoUrl: string ) => void
): void {
	const media = (
		window as Window & {
			wp?: {
				media?: ( options: {
					title: string;
					button: { text: string };
					multiple: boolean;
				} ) => MediaFrame;
			};
		}
	 ).wp?.media;

	if ( ! media ) {
		return;
	}

	const frame = media( {
		title: __( 'Choose a logo', 'rankmyseo' ),
		button: { text: __( 'Use this logo', 'rankmyseo' ) },
		multiple: false,
	} );

	frame.on( 'select', () => {
		const attachment = frame.state().get( 'selection' ).first().toJSON();
		onPick( attachment.id ?? 0, attachment.url ?? '' );
	} );
	frame.open();
}

export function SetupWizard(): JSX.Element {
	const {
		bootstrap,
		settings,
		saving,
		completeWizard,
		dismissWizard,
		navigateTo,
	} = useAdmin();

	const [ step, setStep ] = useState< 1 | 2 | 3 >( 1 );
	const [ siteType, setSiteType ] = useState< SiteType >(
		settings?.site_type ?? 'organization'
	);
	const [ siteName, setSiteName ] = useState< string >(
		settings?.site_name || bootstrap.siteName || ''
	);
	const [ separator, setSeparator ] = useState< string >(
		settings?.title_separator ?? '|'
	);
	const [ logoUrl, setLogoUrl ] = useState< string >( '' );
	const [ logoId, setLogoId ] = useState< number >( 0 );
	const [ modulesOn, setModulesOn ] = useState< WizardModules >( {
		meta: settings?.modules.meta ?? true,
		schema: settings?.modules.schema ?? true,
		sitemaps: settings?.modules.sitemaps ?? true,
		redirects: settings?.modules.redirects ?? true,
	} );

	const finish = async ( skipCloud: boolean ): Promise< void > => {
		await completeWizard( {
			site_type: siteType,
			site_name: siteName,
			title_separator: separator,
			logo_id: logoId,
			logo_url: logoUrl,
			modules: modulesOn,
			skip_cloud: skipCloud,
		} );
		if ( ! skipCloud ) {
			navigateTo( 'cloud' );
		}
	};

	const moduleChoices: Array< {
		id: keyof WizardModules;
		label: string;
		help: string;
	} > = [
		{
			id: 'meta',
			label: __( 'Search titles and social previews', 'rankmyseo' ),
			help: __(
				'Set the headline and short description people see in search and when a page is shared.',
				'rankmyseo'
			),
		},
		{
			id: 'schema',
			label: __( 'Structured data for your business', 'rankmyseo' ),
			help: __(
				'Tell search engines whether this site represents an organization or a person.',
				'rankmyseo'
			),
		},
		{
			id: 'sitemaps',
			label: __( 'XML sitemap for search engines', 'rankmyseo' ),
			help: __(
				'Publish a list of public pages so new and updated content is easier to find.',
				'rankmyseo'
			),
		},
		{
			id: 'redirects',
			label: __( 'Redirects and broken-link monitor', 'rankmyseo' ),
			help: __(
				'Send visitors from old addresses to a live page and keep a log of missing pages.',
				'rankmyseo'
			),
		},
	];

	return (
		<section
			className="rms-card rms-wizard"
			aria-labelledby="rms-wizard-heading"
		>
			<div className="rms-wizard-topbar">
				<div className="rms-wizard-progress">
					<span className="rms-badge rms-badge-accent">
						{ sprintf(
							/* translators: %d: current setup step number */
							__( 'Step %d of 3', 'rankmyseo' ),
							step
						) }
					</span>
					<span className="rms-wizard-subtitle">
						{ __( 'Quick Guided Setup', 'rankmyseo' ) }
					</span>
				</div>
				<div className="rms-wizard-escape">
					<button
						type="button"
						className="rms-link-btn"
						onClick={ () => {
							void dismissWizard( true );
						} }
					>
						{ __( 'Skip to Advanced Setup', 'rankmyseo' ) }
					</button>
					<button
						type="button"
						className="rms-link-btn rms-link-muted"
						onClick={ () => {
							void dismissWizard( false );
						} }
					>
						{ __( 'Dismiss', 'rankmyseo' ) }
					</button>
				</div>
			</div>

			{ step === 1 && (
				<div className="rms-wizard-body">
					<h2 id="rms-wizard-heading" className="rms-wizard-title">
						{ __( '1. Site Basics', 'rankmyseo' ) }
					</h2>
					<p className="rms-wizard-desc">
						{ __(
							'Tell RankMySEO how to represent your site in search results. You can change these details anytime.',
							'rankmyseo'
						) }
					</p>

					<div className="rms-field-group">
						<span className="rms-field-label">
							{ __( 'Who is this site for?', 'rankmyseo' ) }
						</span>
						<div className="rms-choice-grid" role="radiogroup">
							<button
								type="button"
								role="radio"
								aria-checked={ siteType === 'organization' }
								className={ `rms-choice-card${
									siteType === 'organization'
										? ' is-selected'
										: ''
								}` }
								onClick={ () => setSiteType( 'organization' ) }
							>
								<strong>
									{ __(
										'Business or Organization',
										'rankmyseo'
									) }
								</strong>
								<span>
									{ __(
										'Company, online store, local business, or publication.',
										'rankmyseo'
									) }
								</span>
							</button>
							<button
								type="button"
								role="radio"
								aria-checked={ siteType === 'person' }
								className={ `rms-choice-card${
									siteType === 'person' ? ' is-selected' : ''
								}` }
								onClick={ () => setSiteType( 'person' ) }
							>
								<strong>{ __( 'Person', 'rankmyseo' ) }</strong>
								<span>
									{ __(
										'Personal blog, portfolio, creator, or freelancer.',
										'rankmyseo'
									) }
								</span>
							</button>
						</div>
					</div>

					<div className="rms-wizard-two-col">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Site or brand name', 'rankmyseo' ) }
							value={ siteName }
							onChange={ setSiteName }
							help={ __(
								'Shown at the end of your search titles.',
								'rankmyseo'
							) }
						/>

						<div className="rms-field-group">
							<span className="rms-field-label">
								{ __( 'Title separator', 'rankmyseo' ) }
							</span>
							<div className="rms-separator-row">
								{ SEPARATORS.map( ( sep ) => (
									<button
										key={ sep }
										type="button"
										aria-pressed={ separator === sep }
										className={ `rms-sep-btn${
											separator === sep
												? ' is-selected'
												: ''
										}` }
										onClick={ () => setSeparator( sep ) }
									>
										{ sep }
									</button>
								) ) }
							</div>
							<p className="rms-field-help rms-mono">
								{ `${ __(
									'Sample Page',
									'rankmyseo'
								) } ${ separator } ${
									siteName || __( 'Your Site', 'rankmyseo' )
								}` }
							</p>
						</div>
					</div>

					<div className="rms-wizard-logo">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Logo URL', 'rankmyseo' ) }
							value={ logoUrl }
							onChange={ ( next ) => {
								setLogoUrl( next );
								setLogoId( 0 );
							} }
							help={ __(
								'Optional. Used as the default brand image. Stored with site identity, separate from other SEO settings.',
								'rankmyseo'
							) }
							type="url"
						/>
						<Button
							variant="secondary"
							onClick={ () =>
								openLogoPicker( ( nextId, nextUrl ) => {
									setLogoId( nextId );
									if ( nextUrl ) {
										setLogoUrl( nextUrl );
									}
								} )
							}
						>
							{ __( 'Choose from Media Library', 'rankmyseo' ) }
						</Button>
					</div>
				</div>
			) }

			{ step === 2 && (
				<div className="rms-wizard-body">
					<h2 id="rms-wizard-heading" className="rms-wizard-title">
						{ __( '2. Core modules and indexing', 'rankmyseo' ) }
					</h2>
					<p className="rms-wizard-desc">
						{ __(
							'Turn on the local tools you want. Search indexing still follows the Reading setting in WordPress, and every tool here runs on this site.',
							'rankmyseo'
						) }
					</p>
					<ul className="rms-wizard-toggles">
						{ moduleChoices.map( ( choice ) => (
							<li key={ choice.id }>
								<label
									className="rms-wizard-toggle"
									htmlFor={ `rms-wizard-${ choice.id }` }
								>
									<input
										id={ `rms-wizard-${ choice.id }` }
										type="checkbox"
										checked={ modulesOn[ choice.id ] }
										aria-describedby={ `rms-wizard-${ choice.id }-help` }
										onChange={ ( event ) => {
											const checked =
												event.target.checked;
											setModulesOn( ( prev ) => ( {
												...prev,
												[ choice.id ]: checked,
											} ) );
										} }
									/>
									<strong>{ choice.label }</strong>
								</label>
								<span
									id={ `rms-wizard-${ choice.id }-help` }
									className="rms-muted"
								>
									{ choice.help }
								</span>
							</li>
						) ) }
					</ul>
				</div>
			) }

			{ step === 3 && (
				<div className="rms-wizard-body">
					<h2 id="rms-wizard-heading" className="rms-wizard-title">
						{ __( '3. Connect RankMySEO Cloud', 'rankmyseo' ) }
					</h2>
					<p className="rms-wizard-desc">
						{ __(
							'Every core on-page SEO feature runs locally for free. Connecting RankMySEO Cloud is strictly optional.',
							'rankmyseo'
						) }
					</p>

					<div className="rms-choice-grid">
						<div className="rms-info-box">
							<strong>
								{ __(
									'Keep using this site locally',
									'rankmyseo'
								) }
							</strong>
							<p>
								{ __(
									'Use search titles, structured data, sitemaps, and redirects inside WordPress. Nothing is sent off this site.',
									'rankmyseo'
								) }
							</p>
						</div>
						<div className="rms-info-box rms-info-box-accent">
							<strong>
								{ __(
									'Optional: RankMySEO Cloud',
									'rankmyseo'
								) }
							</strong>
							<p>
								{ __(
									'Pair this site later to track keyword rankings and review suggested edits before anything changes.',
									'rankmyseo'
								) }
							</p>
						</div>
					</div>
				</div>
			) }

			<div className="rms-wizard-footer">
				<div>
					{ step > 1 && (
						<Button
							variant="secondary"
							onClick={ () =>
								setStep( ( prev ) => ( prev === 3 ? 2 : 1 ) )
							}
						>
							{ __( 'Back', 'rankmyseo' ) }
						</Button>
					) }
				</div>
				<div className="rms-wizard-footer-actions">
					{ step < 3 ? (
						<Button
							variant="primary"
							className="rms-btn-primary"
							onClick={ () =>
								setStep( ( prev ) => ( prev === 1 ? 2 : 3 ) )
							}
						>
							{ __( 'Continue', 'rankmyseo' ) }
						</Button>
					) : (
						<>
							<Button
								variant="secondary"
								disabled={ saving }
								onClick={ () => {
									void finish( false );
								} }
							>
								{ __( 'Review Cloud Setup', 'rankmyseo' ) }
							</Button>
							<Button
								variant="primary"
								className="rms-btn-primary"
								isBusy={ saving }
								disabled={ saving }
								onClick={ () => {
									void finish( true );
								} }
							>
								{ __(
									'Skip for now — keep using locally',
									'rankmyseo'
								) }
							</Button>
						</>
					) }
				</div>
			</div>
		</section>
	);
}
