import { __ } from '@wordpress/i18n';

export type GlossaryKey =
	| 'meta_description'
	| 'canonical'
	| 'noindex'
	| 'schema'
	| 'sitemap'
	| 'redirect'
	| 'focus_keyword'
	| 'robots_txt'
	| 'organization'
	| 'json_ld'
	| 'breadcrumb'
	| 'local_business'
	| 'faq_page';

export interface GlossaryEntry {
	label: string;
	definition: string;
}

export function getGlossaryTerms(): Record< GlossaryKey, GlossaryEntry > {
	return {
		meta_description: {
			label: __( 'Meta description', 'rankmyseo' ),
			definition: __(
				'The short summary text shown under your page title in Google search results to help searchers decide whether to click.',
				'rankmyseo'
			),
		},
		canonical: {
			label: __( 'Canonical URL', 'rankmyseo' ),
			definition: __(
				'The main web address you want search engines to index when similar content can be reached from more than one link.',
				'rankmyseo'
			),
		},
		noindex: {
			label: __( 'Noindex', 'rankmyseo' ),
			definition: __(
				'A search engine instruction telling Google not to show a specific page in search results.',
				'rankmyseo'
			),
		},
		schema: {
			label: __( 'Structured data (Schema)', 'rankmyseo' ),
			definition: __(
				'Hidden structured labels that explain to search engines what a page represents, such as a business, article, or product.',
				'rankmyseo'
			),
		},
		sitemap: {
			label: __( 'XML sitemap', 'rankmyseo' ),
			definition: __(
				'An automatically updated directory of your public pages that helps search engines find new and updated content faster.',
				'rankmyseo'
			),
		},
		redirect: {
			label: __( '301 redirect', 'rankmyseo' ),
			definition: __(
				'A permanent forward that sends visitors and search engines from an old or broken link to the right live page.',
				'rankmyseo'
			),
		},
		focus_keyword: {
			label: __( 'Focus keyword', 'rankmyseo' ),
			definition: __(
				'The main search phrase you want a page to rank for in Google.',
				'rankmyseo'
			),
		},
		robots_txt: {
			label: __( 'robots.txt', 'rankmyseo' ),
			definition: __(
				'A small text file at the root of your site that tells search engine crawlers which folders they should or should not crawl.',
				'rankmyseo'
			),
		},
		organization: {
			label: __( 'Organization', 'rankmyseo' ),
			definition: __(
				'A business, brand, or group that publishes this site. Search engines use it as the publisher of your pages.',
				'rankmyseo'
			),
		},
		json_ld: {
			label: __( 'JSON-LD', 'rankmyseo' ),
			definition: __(
				'A hidden block of structured data in the page code that describes the page in a format search engines can read.',
				'rankmyseo'
			),
		},
		breadcrumb: {
			label: __( 'Breadcrumb', 'rankmyseo' ),
			definition: __(
				'The path of links that shows where a page sits on your site, such as Home, then a category, then the page.',
				'rankmyseo'
			),
		},
		local_business: {
			label: __( 'Local business', 'rankmyseo' ),
			definition: __(
				'A business customers can visit in person, described with an address, phone number, and opening hours.',
				'rankmyseo'
			),
		},
		faq_page: {
			label: __( 'FAQ', 'rankmyseo' ),
			definition: __(
				'Questions and answers on a page. The markup stays useful for answer engines. Google stopped showing FAQ rich results in May 2026.',
				'rankmyseo'
			),
		},
	};
}
