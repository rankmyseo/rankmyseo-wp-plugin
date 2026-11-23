import { Button, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	SkeletonCard,
	StatusBadge,
} from '../../shared/components/StatePrimitives';
import { Term } from '../../shared/components/Term';
import { useAdmin } from '../context/AdminContext';

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
	const blogPublic = status?.site.blog_public ?? true;
	const enabledCount = status?.modules.enabled_count ?? 0;
	const totalCount = status?.modules.total_count ?? modules.length;
	const tablesHealthy = status?.database.tables_healthy ?? true;
	const tableCount = Object.keys( status?.database.tables ?? {} ).length;

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
						<div className="rms-score-ring" aria-hidden="true">
							<span className="rms-score-value rms-tabular">
								{ blogPublic && tablesHealthy ? 'OK' : '!' }
							</span>
						</div>
						<div>
							<h1 className="rms-page-title">
								{ sprintf(
									/* translators: %s: site name */
									__( 'SEO overview for %s', 'rankmyseo' ),
									siteName
								) }
							</h1>
							<p className="rms-muted">
								{ sprintf(
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
									{ __(
										'appears in search results.',
										'rankmyseo'
									) }
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
									onClick={ () =>
										openChat(
											__(
												'Fix missing meta descriptions',
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
									onClick={ () =>
										openChat(
											__(
												'How do redirects and 404 tracking work?',
												'rankmyseo'
											)
										)
									}
								>
									{ __( 'Ask AI', 'rankmyseo' ) }
								</button>
							</div>
						</article>
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
								'All standalone on-page SEO tools stay on this site. Connect RankMySEO when you want keyword rankings and suggested page edits.',
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
