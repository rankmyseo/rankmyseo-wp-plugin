import { Button, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { fetchDashboardSummary } from '../../shared/api';
import {
	SkeletonCard,
	StatusBadge,
} from '../../shared/components/StatePrimitives';
import { Term } from '../../shared/components/Term';
import type { GlossaryKey } from '../../shared/glossary';
import type {
	DashboardFix,
	DashboardSummary,
	NavPageId,
} from '../../shared/types';
import { useAdmin } from '../context/AdminContext';

function termForFix( id: string ): GlossaryKey | null {
	if ( id === 'missing_descriptions' ) {
		return 'meta_description';
	}
	if ( id === 'missing_titles' ) {
		return 'seo_title';
	}
	if ( id === 'open_404s' ) {
		return 'redirect';
	}
	return null;
}

function scoreBandClass( band: string ): string {
	if ( band === 'good' ) {
		return 'is-good';
	}
	if ( band === 'poor' ) {
		return 'is-poor';
	}
	return 'is-needs-work';
}

export function DashboardView(): JSX.Element {
	const {
		uiMode,
		status,
		settings,
		modules,
		loading,
		saving,
		toggleModule,
		navigateTo,
		openChat,
		reopenWizard,
	} = useAdmin();
	const [ summary, setSummary ] = useState< DashboardSummary | null >( null );

	useEffect( () => {
		let active = true;
		void fetchDashboardSummary()
			.then( ( next ) => {
				if ( active ) {
					setSummary( next );
				}
			} )
			.catch( () => {
				if ( active ) {
					setSummary( null );
				}
			} );
		return () => {
			active = false;
		};
	}, [] );

	if ( loading && ! status ) {
		return (
			<div className="rms-stack">
				<SkeletonCard lines={ 2 } />
				<SkeletonCard lines={ 4 } />
			</div>
		);
	}

	const siteName =
		settings?.site_name ||
		status?.site.name ||
		__( 'Your WordPress Site', 'rankmyseo' );
	const blogPublic = summary?.blog_public ?? status?.site.blog_public ?? true;
	const enabledCount = status?.modules.enabled_count ?? 0;
	const totalCount = status?.modules.total_count ?? modules.length;
	const tablesHealthy =
		summary?.audit.tables_healthy ??
		status?.database.tables_healthy ??
		true;
	const tableCount = Object.keys( status?.database.tables ?? {} ).length;

	const openFix = ( fix: DashboardFix ): void => {
		if ( fix.id === 'onboarding' ) {
			reopenWizard();
			return;
		}
		navigateTo( fix.page );
	};

	if ( uiMode === 'simple' ) {
		return (
			<div className="rms-stack">
				{ ! blogPublic && (
					<div className="rms-alert rms-alert-warn" role="status">
						<strong>
							{ __(
								'Search engines are currently blocked',
								'rankmyseo'
							) }
						</strong>
						<p>
							{ __(
								'WordPress has "Discourage search engines from indexing this site" enabled under Settings → Reading. Uncheck it when you are ready to appear in Google.',
								'rankmyseo'
							) }
						</p>
					</div>
				) }

				<section className="rms-card rms-hero-card">
					<div className="rms-hero-main">
						{ summary ? (
							<div
								className={ `rms-score-ring ${ scoreBandClass(
									summary.health.band
								) }` }
								role="img"
								aria-label={ sprintf(
									/* translators: %d: site health score from 0 to 100 */
									__(
										'Site health %d out of 100',
										'rankmyseo'
									),
									summary.health.score
								) }
							>
								<span className="rms-score-value rms-tabular">
									{ summary.health.score }
								</span>
							</div>
						) : (
							<div className="rms-score-ring" aria-hidden="true">
								<span className="rms-score-value rms-tabular">
									{ blogPublic && tablesHealthy ? 'OK' : '!' }
								</span>
							</div>
						) }
						<div>
							<h1 className="rms-page-title">
								{ sprintf(
									/* translators: %s: site name */
									__( 'SEO overview for %s', 'rankmyseo' ),
									siteName
								) }
							</h1>
							<p className="rms-muted">
								{ summary
									? summary.health.summary
									: sprintf(
											/* translators: 1: active module count, 2: total module count */
											__(
												'%1$d of %2$d SEO tools are turned on and running locally with zero external calls.',
												'rankmyseo'
											),
											enabledCount,
											totalCount
									  ) }
							</p>
						</div>
					</div>
					<div className="rms-hero-actions">
						<Button
							variant="secondary"
							onClick={ () => reopenWizard() }
						>
							{ __( 'Run Setup Wizard', 'rankmyseo' ) }
						</Button>
						<Button
							variant="primary"
							className="rms-btn-primary"
							onClick={ () =>
								openChat(
									__( "How is my site's SEO?", 'rankmyseo' )
								)
							}
						>
							{ __( 'Ask AI for guidance', 'rankmyseo' ) }
						</Button>
					</div>
				</section>

				<section className="rms-section">
					<div className="rms-section-header">
						<h2 className="rms-section-title">
							{ __( 'Next best actions', 'rankmyseo' ) }
						</h2>
						<span className="rms-muted">
							{ __(
								'Plain-English steps to get your site ready for search.',
								'rankmyseo'
							) }
						</span>
					</div>

					<div className="rms-action-list">
						{ summary ? (
							summary.fixes.map( ( fix ) => {
								const term = termForFix( fix.id );
								return (
									<article
										key={ fix.id }
										className="rms-card rms-next-action-card"
									>
										<div>
											<h3 className="rms-card-title">
												{ fix.title }
											</h3>
											<p className="rms-muted">
												{ fix.detail }
												{ term && (
													<>
														{ ' ' }
														<Term term={ term } />
													</>
												) }
											</p>
										</div>
										<div className="rms-next-action-cta">
											<Button
												variant="secondary"
												onClick={ () => openFix( fix ) }
											>
												{ fix.id === 'onboarding'
													? __(
															'Continue setup',
															'rankmyseo'
													  )
													: __(
															'Review',
															'rankmyseo'
													  ) }
											</Button>
											<button
												type="button"
												className="rms-link-btn"
												aria-label={ sprintf(
													/* translators: %s: fix title */
													__(
														'Ask AI: %s',
														'rankmyseo'
													),
													fix.title
												) }
												onClick={ () =>
													openChat( fix.prompt )
												}
											>
												{ __( 'Ask AI', 'rankmyseo' ) }
											</button>
										</div>
									</article>
								);
							} )
						) : (
							<FallbackActions
								navigateTo={ navigateTo }
								openChat={ openChat }
							/>
						) }
					</div>
				</section>

				<section className="rms-card rms-cloud-banner">
					<div>
						<StatusBadge tone="flat">
							{ __( 'Optional', 'rankmyseo' ) }
						</StatusBadge>
						<h2 className="rms-section-title">
							{ __(
								'Pair with RankMySEO Cloud when ready',
								'rankmyseo'
							) }
						</h2>
						<p className="rms-muted">
							{ __(
								'All standalone on-page SEO tools stay free and local. Connect RankMySEO Cloud anytime for live keyword tracking and approval-first recommendations.',
								'rankmyseo'
							) }
						</p>
					</div>
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'cloud' ) }
					>
						{ status?.cloud.connected
							? __( 'View insights', 'rankmyseo' )
							: __( 'Connect to RankMySEO', 'rankmyseo' ) }
					</Button>
				</section>
			</div>
		);
	}

	return (
		<div className="rms-stack">
			<div className="rms-page-header">
				<div>
					<h1 className="rms-page-title">
						{ __( 'Dashboard', 'rankmyseo' ) }
					</h1>
					<p className="rms-muted">
						{ __(
							'Operational status, module toggles, and system health for this WordPress site.',
							'rankmyseo'
						) }
					</p>
				</div>
				<Button
					variant="secondary"
					onClick={ () => navigateTo( 'settings' ) }
				>
					{ __( 'All Settings', 'rankmyseo' ) }
				</Button>
			</div>

			<section
				className="rms-metric-strip"
				aria-label={ __( 'Site SEO metrics', 'rankmyseo' ) }
			>
				<div className="rms-card rms-metric-card">
					<span className="rms-metric-label">
						{ __( 'Active Modules', 'rankmyseo' ) }
					</span>
					<strong className="rms-metric-value rms-tabular">
						{ `${ enabledCount } / ${ totalCount }` }
					</strong>
					<span className="rms-metric-sub">
						{ __( 'Local standalone core', 'rankmyseo' ) }
					</span>
				</div>

				<div className="rms-card rms-metric-card">
					<span className="rms-metric-label">
						{ __( 'Search Indexing', 'rankmyseo' ) }
					</span>
					<strong className="rms-metric-value">
						{ blogPublic
							? __( 'Allowed', 'rankmyseo' )
							: __( 'Discouraged', 'rankmyseo' ) }
					</strong>
					<span className="rms-metric-sub rms-mono">
						{ `blog_public = ${ blogPublic ? '1' : '0' }` }
					</span>
				</div>

				<div className="rms-card rms-metric-card">
					<span className="rms-metric-label">
						{ __( 'Database Schema', 'rankmyseo' ) }
					</span>
					<strong className="rms-metric-value rms-tabular">
						{ sprintf(
							/* translators: %d: installed schema version */
							__( 'v%d', 'rankmyseo' ),
							status?.database.installed_version ?? 1
						) }
					</strong>
					<span className="rms-metric-sub">
						{ tablesHealthy
							? sprintf(
									/* translators: %d: table count */
									__(
										'%d custom tables healthy',
										'rankmyseo'
									),
									tableCount
							  )
							: __( 'Migration check needed', 'rankmyseo' ) }
					</span>
				</div>

				<div className="rms-card rms-metric-card">
					<span className="rms-metric-label">
						{ __( 'RankMySEO Cloud', 'rankmyseo' ) }
					</span>
					<strong className="rms-metric-value">
						{ status?.cloud.connected
							? __( 'Connected', 'rankmyseo' )
							: __( 'Local Only', 'rankmyseo' ) }
					</strong>
					<span className="rms-metric-sub">
						{ status?.cloud.connected
							? __( 'Rankings available', 'rankmyseo' )
							: __(
									'Stays on this site until you connect',
									'rankmyseo'
							  ) }
					</span>
				</div>
			</section>

			{ summary && (
				<section
					className="rms-section"
					aria-label={ __( 'Site audit counts', 'rankmyseo' ) }
				>
					<div className="rms-section-header">
						<h2 className="rms-section-title">
							{ __( 'Site audit', 'rankmyseo' ) }
						</h2>
						<span className="rms-muted">
							{ sprintf(
								/* translators: %d: site health score */
								__( 'Local score %d / 100', 'rankmyseo' ),
								summary.health.score
							) }
						</span>
					</div>
					<ul className="rms-audit-grid">
						<li>
							{ sprintf(
								/* translators: %d: count of published items missing a description */
								__( '%d missing descriptions', 'rankmyseo' ),
								summary.counts.missing_descriptions
							) }
						</li>
						<li>
							{ sprintf(
								/* translators: %d: count of published items missing an SEO title */
								__( '%d missing SEO titles', 'rankmyseo' ),
								summary.counts.missing_titles
							) }
						</li>
						<li>
							{ sprintf(
								/* translators: %d: count of thin published items */
								__( '%d thin posts', 'rankmyseo' ),
								summary.counts.thin_posts
							) }
						</li>
						<li>
							{ sprintf(
								/* translators: %d: count of unresolved 404s */
								__( '%d open 404s', 'rankmyseo' ),
								summary.counts.open_404s
							) }
						</li>
						<li>
							{ sprintf(
								/* translators: 1: active redirects, 2: total redirects */
								__(
									'%1$d active redirects of %2$d',
									'rankmyseo'
								),
								summary.counts.redirects_active,
								summary.counts.redirects_total
							) }
						</li>
					</ul>
				</section>
			) }

			<section className="rms-section">
				<div className="rms-section-header">
					<h2 className="rms-section-title">
						{ __( 'Quick links', 'rankmyseo' ) }
					</h2>
				</div>
				<div className="rms-quick-links">
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'schema' ) }
					>
						{ __( 'Open Schema', 'rankmyseo' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'sitemaps' ) }
					>
						{ __( 'Open Sitemaps', 'rankmyseo' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'redirects' ) }
					>
						{ __( 'Open Redirects', 'rankmyseo' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'meta' ) }
					>
						{ __( 'Open Search Previews', 'rankmyseo' ) }
					</Button>
				</div>
			</section>

			<section className="rms-section">
				<div className="rms-section-header">
					<h2 className="rms-section-title">
						{ __( 'SEO Modules', 'rankmyseo' ) }
					</h2>
					<span className="rms-muted">
						{ __(
							'Enable or disable modules independently. Disabled modules register zero hooks.',
							'rankmyseo'
						) }
					</span>
				</div>

				<div className="rms-module-grid">
					{ modules.map( ( mod ) => {
						let badgeTone: 'accent' | 'up' | 'flat' = 'flat';
						let badgeLabel = __( 'Disabled', 'rankmyseo' );
						if ( mod.requires_cloud ) {
							badgeTone = 'accent';
							badgeLabel = __( 'Cloud optional', 'rankmyseo' );
						} else if ( mod.enabled ) {
							badgeTone = 'up';
							badgeLabel = __( 'Enabled', 'rankmyseo' );
						}
						return (
							<article
								key={ mod.id }
								className="rms-card rms-module-card"
							>
								<div className="rms-module-card-top">
									<h3 className="rms-card-title">
										{ mod.name }
									</h3>
									<StatusBadge tone={ badgeTone }>
										{ badgeLabel }
									</StatusBadge>
								</div>
								<p className="rms-module-desc">
									{ mod.description }
								</p>
								<div className="rms-module-card-footer">
									<ToggleControl
										__nextHasNoMarginBottom
										label={
											mod.enabled
												? __( 'Active', 'rankmyseo' )
												: __( 'Inactive', 'rankmyseo' )
										}
										checked={ mod.enabled }
										disabled={ saving }
										onChange={ ( next ) => {
											void toggleModule( mod.id, next );
										} }
									/>
								</div>
							</article>
						);
					} ) }
				</div>
			</section>
		</div>
	);
}

function FallbackActions( {
	navigateTo,
	openChat,
}: {
	navigateTo: ( pageId: NavPageId ) => void;
	openChat: ( prompt?: string ) => void;
} ): JSX.Element {
	return (
		<>
			<article className="rms-card rms-next-action-card">
				<div>
					<h3 className="rms-card-title">
						{ __(
							'Set up your search titles and meta descriptions',
							'rankmyseo'
						) }
					</h3>
					<p className="rms-muted">
						{ __(
							'Choose a clear title separator and check how your',
							'rankmyseo'
						) }{ ' ' }
						<Term term="meta_description" />{ ' ' }
						{ __( 'appears in search results.', 'rankmyseo' ) }
					</p>
				</div>
				<div className="rms-next-action-cta">
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'meta' ) }
					>
						{ __( 'Review', 'rankmyseo' ) }
					</Button>
					<button
						type="button"
						className="rms-link-btn"
						aria-label={ __(
							'Ask AI about search titles',
							'rankmyseo'
						) }
						onClick={ () =>
							openChat(
								__(
									'Find posts missing meta descriptions',
									'rankmyseo'
								)
							)
						}
					>
						{ __( 'Ask AI', 'rankmyseo' ) }
					</button>
				</div>
			</article>

			<article className="rms-card rms-next-action-card">
				<div>
					<h3 className="rms-card-title">
						{ __(
							'Switching from another SEO plugin?',
							'rankmyseo'
						) }
					</h3>
					<p className="rms-muted">
						{ __(
							'Copy existing titles, descriptions, and redirects safely from Yoast SEO, Rank Math, or AIOSEO with one-click undo.',
							'rankmyseo'
						) }
					</p>
				</div>
				<div className="rms-next-action-cta">
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'import' ) }
					>
						{ __( 'Open Import', 'rankmyseo' ) }
					</Button>
					<button
						type="button"
						className="rms-link-btn"
						aria-label={ __(
							'Ask AI about importing',
							'rankmyseo'
						) }
						onClick={ () =>
							openChat(
								__(
									'Import from Yoast or Rank Math',
									'rankmyseo'
								)
							)
						}
					>
						{ __( 'Ask AI', 'rankmyseo' ) }
					</button>
				</div>
			</article>

			<article className="rms-card rms-next-action-card">
				<div>
					<h3 className="rms-card-title">
						{ __(
							'Keep broken links from costing visitors',
							'rankmyseo'
						) }
					</h3>
					<p className="rms-muted">
						{ __( 'Use automatic', 'rankmyseo' ) }{ ' ' }
						<Term term="redirect" />{ ' ' }
						{ __(
							'rules whenever a page address changes so visitors always land on a live page.',
							'rankmyseo'
						) }
					</p>
				</div>
				<div className="rms-next-action-cta">
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'redirects' ) }
					>
						{ __( 'Broken Links', 'rankmyseo' ) }
					</Button>
					<button
						type="button"
						className="rms-link-btn"
						aria-label={ __(
							'Ask AI about redirects',
							'rankmyseo'
						) }
						onClick={ () =>
							openChat(
								__( 'Show recent unresolved 404s', 'rankmyseo' )
							)
						}
					>
						{ __( 'Ask AI', 'rankmyseo' ) }
					</button>
				</div>
			</article>
		</>
	);
}
