import { Button, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	EmptyState,
	StatusBadge,
} from '../../shared/components/StatePrimitives';
import type { ModuleId, NavPageId } from '../../shared/types';
import { useAdmin } from '../context/AdminContext';

interface ModuleScreenMeta {
	moduleId: ModuleId;
	title: string;
	simpleTitle: string;
	simpleSummary: string;
	advancedSummary: string;
	aiPrompt: string;
	highlights: string[];
}

const SCREEN_META: Record<
	Exclude< NavPageId, 'dashboard' | 'settings' | 'sitemaps' | 'meta' >,
	ModuleScreenMeta
> = {
	schema: {
		moduleId: 'schema',
		title: __( 'Structured Data (Schema)', 'rankmyseo' ),
		simpleTitle: __( 'Site Details for Search Engines', 'rankmyseo' ),
		simpleSummary: __(
			'Give search engines structured details about your business, articles, breadcrumbs, and WooCommerce products automatically.',
			'rankmyseo'
		),
		advancedSummary: __(
			'Unified JSON-LD @graph builder for WebSite, Organization/Person, Article, BreadcrumbList, LocalBusiness, FAQPage, and WooCommerce Product with duplicate suppression.',
			'rankmyseo'
		),
		aiPrompt: __(
			'Explain how structured data schema helps my site',
			'rankmyseo'
		),
		highlights: [
			__( 'Unified JSON-LD @graph output', 'rankmyseo' ),
			__( 'Automatic WooCommerce Product schema adapter', 'rankmyseo' ),
			__( 'Per-post schema type overrides & filters', 'rankmyseo' ),
		],
	},
	redirects: {
		moduleId: 'redirects',
		title: __( 'Redirects & 404s', 'rankmyseo' ),
		simpleTitle: __( 'Broken Links & Redirects', 'rankmyseo' ),
		simpleSummary: __(
			'Spot missing pages visitors hit and forward them to the right page in one click, with automatic protection when a page link changes.',
			'rankmyseo'
		),
		advancedSummary: __(
			'O(1) hashed exact redirects, prefix & bounded regex rules, loop/chain guard, slug-change auto-redirects, and deduplicated 404 monitor backed by custom tables.',
			'rankmyseo'
		),
		aiPrompt: __( 'How do redirects and 404 tracking work?', 'rankmyseo' ),
		highlights: [
			__( '301, 302, 307, 410, and 451 redirect rules', 'rankmyseo' ),
			__( 'Automatic 301 on published slug changes', 'rankmyseo' ),
			__( 'Deduplicated 404 monitor with noise filtering', 'rankmyseo' ),
		],
	},
	import: {
		moduleId: 'importer',
		title: __( 'Migration Importer', 'rankmyseo' ),
		simpleTitle: __( 'Import from Another SEO Plugin', 'rankmyseo' ),
		simpleSummary: __(
			'Move your titles, descriptions, and redirects safely from Yoast SEO, Rank Math, or AIOSEO without deleting your old data.',
			'rankmyseo'
		),
		advancedSummary: __(
			'Auto-detection, template variable translation, dry-run diff preview, chunked batch import, and batch_id rollback snapshots stored in rankmyseo_import_backups.',
			'rankmyseo'
		),
		aiPrompt: __( 'Import from Yoast or Rank Math', 'rankmyseo' ),
		highlights: [
			__( 'Supports Yoast SEO, Rank Math, and AIOSEO', 'rankmyseo' ),
			__(
				'Non-destructive dry-run preview before importing',
				'rankmyseo'
			),
			__( 'One-click rollback snapshots per batch', 'rankmyseo' ),
		],
	},
	cloud: {
		moduleId: 'cloud',
		title: __( 'RankMySEO Cloud', 'rankmyseo' ),
		simpleTitle: __( 'RankMySEO Cloud (Optional)', 'rankmyseo' ),
		simpleSummary: __(
			'Keep using local SEO for free, or pair your site with RankMySEO Cloud to see live Google rankings and Search Console clicks inside WordPress.',
			'rankmyseo'
		),
		advancedSummary: __(
			'Site-scoped pairing tokens encrypted at rest, incremental post inventory sync, cached keyword/issue/page-update reads, and server-side API proxy.',
			'rankmyseo'
		),
		aiPrompt: __( 'Connect RankMySEO Cloud', 'rankmyseo' ),
		highlights: [
			__(
				'Strictly optional — zero external calls until connected',
				'rankmyseo'
			),
			__(
				'Site-scoped credentials never exposed to browser JS',
				'rankmyseo'
			),
			__(
				'Tracked keyword rankings & Search Console clicks',
				'rankmyseo'
			),
		],
	},
	'ai-queue': {
		moduleId: 'ai_queue',
		title: __( 'AI Approval Queue', 'rankmyseo' ),
		simpleTitle: __( 'AI Suggestions & Undo', 'rankmyseo' ),
		simpleSummary: __(
			'Review every suggested SEO improvement before anything changes on your site, with clear before-and-after previews and one-click undo.',
			'rankmyseo'
		),
		advancedSummary: __(
			'Risk-tiered AI action queue backed by rankmyseo_ai_actions, immutable rankmyseo_ai_snapshots, WP Revisions, audit logs, and opt-in granular auto-apply rules.',
			'rankmyseo'
		),
		aiPrompt: __( 'Connect RankMySEO Cloud', 'rankmyseo' ),
		highlights: [
			__( 'Human approval required by default', 'rankmyseo' ),
			__( 'Immutable pre-change snapshots & WP revisions', 'rankmyseo' ),
			__( 'Capability-gated via rankmyseo_approve_ai', 'rankmyseo' ),
		],
	},
};

export function ModulePlaceholderView( {
	pageId,
}: {
	pageId: Exclude< NavPageId, 'dashboard' | 'settings' | 'sitemaps' | 'meta' >;
} ): JSX.Element {
	const { uiMode, modules, saving, toggleModule, openChat, navigateTo } =
		useAdmin();
	const meta = SCREEN_META[ pageId ];
	const descriptor = modules.find( ( m ) => m.id === meta.moduleId );
	const isEnabled = descriptor?.enabled ?? true;

	return (
		<div className="rms-stack">
			<div className="rms-page-header">
				<div>
					<div className="rms-title-row">
						<h1 className="rms-page-title">
							{ uiMode === 'simple'
								? meta.simpleTitle
								: meta.title }
						</h1>
						<StatusBadge tone={ isEnabled ? 'up' : 'flat' }>
							{ isEnabled
								? __( 'Module enabled', 'rankmyseo' )
								: __( 'Module disabled', 'rankmyseo' ) }
						</StatusBadge>
					</div>
					<p className="rms-muted">
						{ uiMode === 'simple'
							? meta.simpleSummary
							: meta.advancedSummary }
					</p>
				</div>
				<div className="rms-page-header-actions">
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							isEnabled
								? __( 'Enabled', 'rankmyseo' )
								: __( 'Disabled', 'rankmyseo' )
						}
						checked={ isEnabled }
						disabled={ saving }
						onChange={ ( next ) => {
							void toggleModule( meta.moduleId, next );
						} }
					/>
				</div>
			</div>

			<EmptyState
				title={ __( 'Module foundation ready', 'rankmyseo' ) }
				description={
					uiMode === 'simple'
						? __(
								'Your database tables, permissions, and module switch are already set up. Full configuration tools for this section plug directly into this screen.',
								'rankmyseo'
						  )
						: __(
								'Core module registration, settings persistence, and custom schema tables are active. Feature controllers and UI panels mount here without core rewrites.',
								'rankmyseo'
						  )
				}
				actionLabel={ __( 'Ask AI about this section', 'rankmyseo' ) }
				onAction={ () => openChat( meta.aiPrompt ) }
				secondaryAction={
					<Button
						variant="secondary"
						onClick={ () => navigateTo( 'settings' ) }
					>
						{ __( 'Manage Settings', 'rankmyseo' ) }
					</Button>
				}
			/>

			<section className="rms-card">
				<h2 className="rms-section-title">
					{ __( 'Planned capabilities in this module', 'rankmyseo' ) }
				</h2>
				<ul className="rms-checklist">
					{ meta.highlights.map( ( item ) => (
						<li key={ item }>{ item }</li>
					) ) }
				</ul>
			</section>
		</div>
	);
}
