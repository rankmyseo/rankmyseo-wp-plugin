import { __, sprintf } from '@wordpress/i18n';
import type {
	ChatMessage,
	ChatQuickLink,
	PluginSettings,
	StatusResponse,
	UiMode,
} from '../../shared/types';

export interface LocalStarterPrompt {
	id: string;
	label: string;
	prompt: string;
}

export function getStarterPrompts(): LocalStarterPrompt[] {
	return [
		{
			id: 'meta-descriptions',
			label: __( 'Fix missing meta descriptions', 'rankmyseo' ),
			prompt: __( 'Fix missing meta descriptions', 'rankmyseo' ),
		},
		{
			id: 'site-health',
			label: __( "How is my site's SEO?", 'rankmyseo' ),
			prompt: __( "How is my site's SEO?", 'rankmyseo' ),
		},
		{
			id: 'import-seo',
			label: __( 'Import from Yoast or Rank Math', 'rankmyseo' ),
			prompt: __( 'Import from Yoast or Rank Math', 'rankmyseo' ),
		},
		{
			id: 'connect-cloud',
			label: __( 'Connect RankMySEO Cloud', 'rankmyseo' ),
			prompt: __( 'Connect RankMySEO Cloud', 'rankmyseo' ),
		},
	];
}

export function buildLocalAssistantReply(
	prompt: string,
	status: StatusResponse | null,
	settings: PluginSettings | null,
	uiMode: UiMode
): ChatMessage {
	const normalized = prompt.trim().toLowerCase();
	const siteName =
		settings?.site_name ||
		status?.site.name ||
		__( 'your site', 'rankmyseo' );
	const enabledCount = status?.modules.enabled_count ?? 0;
	const totalCount = status?.modules.total_count ?? 0;
	const tablesHealthy = status?.database.tables_healthy ?? true;
	const blogPublic = status?.site.blog_public ?? true;
	const metaEnabled = settings?.modules.meta ?? true;
	const redirectsEnabled = settings?.modules.redirects ?? true;
	const sitemapsEnabled = settings?.modules.sitemaps ?? true;

	let content = '';
	const quickLinks: ChatQuickLink[] = [];

	if (
		normalized.includes( 'meta description' ) ||
		normalized.includes( 'title' ) ||
		normalized.includes( 'snippet' )
	) {
		content = metaEnabled
			? sprintf(
					/* translators: 1: site name, 2: title separator */
					__(
						'On-Page & Titles is enabled for %1$s (using "%2$s" as your title separator). Keep titles around 50–60 characters with your main keyword near the front, and write plain 120–155 character meta descriptions so search engines do not pull random page text. You can configure defaults under On-Page & Titles.',
						'rankmyseo'
					),
					siteName,
					settings?.title_separator ?? '|'
			  )
			: __(
					'Your On-Page & Titles module is currently turned off. Turn it back on in Settings or On-Page & Titles to manage search titles and meta descriptions.',
					'rankmyseo'
			  );

		quickLinks.push(
			{
				label: __( 'Open On-Page & Titles', 'rankmyseo' ),
				pageId: 'meta',
			},
			{ label: __( 'Manage Modules', 'rankmyseo' ), pageId: 'settings' }
		);
	} else if (
		normalized.includes( 'yoast' ) ||
		normalized.includes( 'rank math' ) ||
		normalized.includes( 'aioseo' ) ||
		normalized.includes( 'import' ) ||
		normalized.includes( 'migrat' )
	) {
		content = __(
			'RankMySEO supports non-destructive migration from Yoast SEO, Rank Math, and AIOSEO. Your original plugin data is never deleted, and every import writes rollback snapshots to the local import backups table so you can undo in one click.',
			'rankmyseo'
		);
		quickLinks.push( {
			label: __( 'Open Migration Importer', 'rankmyseo' ),
			pageId: 'import',
		} );
	} else if (
		normalized.includes( 'cloud' ) ||
		normalized.includes( 'connect' ) ||
		normalized.includes( 'rank' ) ||
		normalized.includes( 'keyword' )
	) {
		content = __(
			'Your site is currently running 100% locally with zero external calls. Connecting RankMySEO Cloud is optional and adds tracked Google keyword rankings, Search Console insights, and one-click AI approval cards with diff previews and rollback.',
			'rankmyseo'
		);
		quickLinks.push(
			{
				label: __( 'View RankMySEO Cloud', 'rankmyseo' ),
				pageId: 'cloud',
			},
			{
				label: __( 'Inspect AI Queue', 'rankmyseo' ),
				pageId: 'ai-queue',
				switchMode: uiMode === 'simple' ? 'advanced' : undefined,
			}
		);
	} else if (
		normalized.includes( 'redirect' ) ||
		normalized.includes( '404' ) ||
		normalized.includes( 'broken' )
	) {
		content = redirectsEnabled
			? __(
					'Redirects & 404s is enabled and backed by dedicated custom database tables for fast lookups and deduplicated 404 logs. You can review broken links and create 301 redirects from the Redirects & 404s screen.',
					'rankmyseo'
			  )
			: __(
					'Redirects & 404s is currently turned off. Enable it in Settings to track broken links and protect visitors when page URLs change.',
					'rankmyseo'
			  );
		quickLinks.push( {
			label: __( 'Open Redirects & 404s', 'rankmyseo' ),
			pageId: 'redirects',
		} );
	} else if (
		normalized.includes( 'sitemap' ) ||
		normalized.includes( 'robots' ) ||
		normalized.includes( 'crawl' )
	) {
		content = sitemapsEnabled
			? __(
					'Sitemaps & Crawl is enabled. Search engine visibility is currently set to allow indexing, and your XML sitemap settings live under Sitemaps.',
					'rankmyseo'
			  )
			: __(
					'Sitemaps & Crawl is turned off, so WordPress continues using its default sitemap behavior. Turn it on in Settings whenever you are ready.',
					'rankmyseo'
			  );
		quickLinks.push( {
			label: __( 'Open Sitemaps', 'rankmyseo' ),
			pageId: 'sitemaps',
			switchMode: uiMode === 'simple' ? 'advanced' : undefined,
		} );
	} else if (
		normalized.includes( 'schema' ) ||
		normalized.includes( 'structured data' )
	) {
		content = sprintf(
			/* translators: %s: site identity type */
			__(
				'Your site identity is currently set as "%s" for structured data. Schema helps search engines understand your business details, articles, and products.',
				'rankmyseo'
			),
			settings?.site_type === 'person'
				? __( 'Person', 'rankmyseo' )
				: __( 'Business / Organization', 'rankmyseo' )
		);
		quickLinks.push( {
			label: __( 'Open Schema', 'rankmyseo' ),
			pageId: 'schema',
			switchMode: uiMode === 'simple' ? 'advanced' : undefined,
		} );
	} else {
		const visibilitySummary = blogPublic
			? __(
					'Search engines are allowed to index this site.',
					'rankmyseo'
			  )
			: __(
					'Warning: WordPress is currently set to discourage search engines from indexing this site.',
					'rankmyseo'
			  );
		const dbSummary = tablesHealthy
			? __(
					'All custom SEO tables are installed and healthy.',
					'rankmyseo'
			  )
			: __(
					'Some plugin tables need attention in Settings.',
					'rankmyseo'
			  );

		content = sprintf(
			/* translators: 1: site name, 2: enabled module count, 3: total module count, 4: visibility summary, 5: db summary */
			__(
				'Local check for %1$s: %2$d of %3$d SEO modules are enabled. %4$s %5$s You are currently running locally with zero external network calls.',
				'rankmyseo'
			),
			siteName,
			enabledCount,
			totalCount,
			visibilitySummary,
			dbSummary
		);

		quickLinks.push(
			{
				label: __( 'Review Dashboard', 'rankmyseo' ),
				pageId: 'dashboard',
			},
			{ label: __( 'Open Settings', 'rankmyseo' ), pageId: 'settings' }
		);
	}

	return {
		id: `rms-assistant-${ Date.now() }-${ Math.random()
			.toString( 36 )
			.slice( 2, 7 ) }`,
		role: 'assistant',
		content,
		createdAt: new Date().toISOString(),
		quickLinks,
	};
}
