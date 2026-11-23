import { Button, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { SiteType } from '../../shared/types';
import { useAdmin } from '../context/AdminContext';

const SEPARATORS = [ '|', '-', '·', '—', '/', '•' ];

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

	const handleFinishLocal = async (): Promise< void > => {
		await completeWizard( {
			site_type: siteType,
			site_name: siteName,
			title_separator: separator,
		} );
	};

	const handleFinishAndOpenCloud = async (): Promise< void > => {
		await completeWizard( {
			site_type: siteType,
			site_name: siteName,
			title_separator: separator,
		} );
		navigateTo( 'cloud' );
	};

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
				</div>
			) }

			{ step === 2 && (
				<div className="rms-wizard-body">
					<h2 id="rms-wizard-heading" className="rms-wizard-title">
						{ __( '2. Import Existing SEO', 'rankmyseo' ) }
					</h2>
					<p className="rms-wizard-desc">
						{ __(
							'Switching from Yoast SEO, Rank Math, or AIOSEO? RankMySEO keeps your old plugin data untouched and creates rollback backups before importing.',
							'rankmyseo'
						) }
					</p>

					<div className="rms-surface-panel">
						<p className="rms-surface-panel-title">
							{ __( 'Ready for safe migration', 'rankmyseo' ) }
						</p>
						<p className="rms-surface-panel-text">
							{ __(
								'All standalone SEO modules (Titles, Analyzer, Schema, Sitemaps, Redirects & 404s, and Importer) are enabled with safe local defaults. You can run a dry-run preview anytime from the Import tab.',
								'rankmyseo'
							) }
						</p>
					</div>
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
								{ __( 'Start Free Locally', 'rankmyseo' ) }
							</strong>
							<p>
								{ __(
									'Use all standalone on-page SEO modules right inside WordPress with zero external account or network calls.',
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
									'Pair your site later to track Google keyword rankings, see Search Console clicks, and review AI proposals with one-click rollback.',
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
									void handleFinishAndOpenCloud();
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
									void handleFinishLocal();
								} }
							>
								{ __( 'Start Free Locally', 'rankmyseo' ) }
							</Button>
						</>
					) }
				</div>
			</div>
		</section>
	);
}
