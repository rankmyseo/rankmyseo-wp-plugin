export interface SitemapTypeRow {
	name: string;
	label: string;
	included: boolean;
	available: boolean;
}

export interface SitemapWarning {
	code: string;
	message: string;
}

export interface IndexNowLogEntry {
	time: string;
	ok: boolean;
	code: number;
	message: string;
	count: number;
	urls?: string[];
}

export interface SitemapCrawlFlags {
	redirect_attachments: boolean;
	noindex_attachments: boolean;
	noindex_search: boolean;
	noindex_comment_pages: boolean;
	strip_replytocom: boolean;
	remove_rsd: boolean;
	remove_wlw: boolean;
	remove_shortlink: boolean;
}

export interface SitemapStatus {
	module_enabled: boolean;
	blog_public: boolean;
	sitemap_url: string;
	xsl_url: string;
	max_urls: number;
	include_images: boolean;
	include_authors: boolean;
	allow_crawl: boolean;
	hide_utility_pages: boolean;
	post_types: SitemapTypeRow[];
	taxonomies: SitemapTypeRow[];
	excluded_post_ids: number[];
	excluded_term_ids: number[];
	crawl: SitemapCrawlFlags;
	indexnow: {
		enabled: boolean;
		key: string;
		key_url: string;
		endpoint: string;
		log: IndexNowLogEntry[];
	};
	robots: {
		mode: 'preset' | 'custom';
		preset: 'default' | 'open';
		custom: string;
		preview: string;
		physical_file: boolean;
		physical_path: string;
		warnings: SitemapWarning[];
	};
}

export function blocksEntireSite( rules: string ): boolean {
	return /^\s*disallow\s*:\s*\/\s*(?:#.*)?$/im.test( rules );
}

export function previewRobots( rules: string, sitemapUrl: string ): string {
	const trimmed = rules.replace( /\r\n/g, '\n' ).trim();
	const body = trimmed === '' ? 'User-agent: *\nAllow: /' : trimmed;
	if ( /^\s*sitemap\s*:/im.test( body ) ) {
		return `${ body }\n`;
	}
	return `${ body }\n\nSitemap: ${ sitemapUrl }\n`;
}

export function flagMap(
	rows: SitemapTypeRow[],
	name: string,
	included: boolean
): Record< string, boolean > {
	const map: Record< string, boolean > = {};
	rows.forEach( ( row ) => {
		map[ row.name ] = row.name === name ? included : row.included;
	} );
	return map;
}
