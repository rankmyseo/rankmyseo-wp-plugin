import { analyze } from '../../src/analyzer/analyze';
import { checkDetail, simpleTip } from '../../src/analyzer/messages';
import type { AnalyzerInput } from '../../src/analyzer/types';

const description =
	'Garden tools that help small yards stay tidy. Compare pruners, gloves, and hand tools for everyday planting and weekend care today.';

function words( count: number, phrase: string ): string {
	const sentence = 'Keep beds tidy with sharp edges and steady hands.';
	const chunks = [ phrase ];
	while ( chunks.join( ' ' ).split( /\s+/ ).length < count ) {
		chunks.push( sentence );
	}
	return chunks.join( ' ' );
}

function strongInput(): AnalyzerInput {
	const body = words( 320, 'Garden tools make weekend planting easier.' );
	return {
		focusKeyword: 'garden tools',
		seoTitle: 'Garden tools for small city yards',
		metaDescription: description,
		slug: 'garden-tools',
		postType: 'post',
		locale: 'en_US',
		noindex: false,
		siteUrl: 'https://example.com',
		contentHtml: `
			<h1>Garden tools</h1>
			<p>${ body }</p>
			<h2>Garden tools for beds</h2>
			<p>Choose a hand fork before you buy a large set.</p>
			<h2>Care</h2>
			<p>Wipe blades after each job.</p>
			<img alt="Garden tools on a bench" src="/tools.jpg" />
			<a href="/gloves">Gloves</a>
			<a href="https://example.org/guide">Guide</a>
		`,
	};
}

describe( 'on-page analyzer', () => {
	it( 'weights every check to 100 points', () => {
		const result = analyze( {
			focusKeyword: '',
			seoTitle: '',
			metaDescription: '',
			slug: '',
			contentHtml: '',
		} );
		const max = result.checks.reduce(
			( total, check ) => total + check.maxScore,
			0
		);
		expect( max ).toBe( 100 );
		expect( result.score ).toBe(
			result.checks.reduce( ( total, check ) => total + check.score, 0 )
		);
		expect( result.band ).toBe( 'no_keyword' );
	} );

	it( 'scores a complete page as good', () => {
		const result = analyze( strongInput() );
		expect( result.score ).toBeGreaterThanOrEqual( 80 );
		expect( result.band ).toBe( 'good' );
		expect( result.summary.wordCount ).toBeGreaterThanOrEqual( 300 );
		expect( result.summary.h1Count ).toBe( 1 );
		expect( result.summary.internalLinks ).toBe( 1 );
		expect( result.summary.externalLinks ).toBe( 1 );
		expect( result.summary.imagesWithAlt ).toBe( 1 );
		expect(
			result.checks.find( ( check ) => check.id === 'keyword_in_title' )
				?.status
		).toBe( 'pass' );
		expect(
			result.checks.find( ( check ) => check.id === 'keyword_density' )
				?.metrics.stuffing
		).toBe( false );
		expect( result.summary.fleschReadingEase ).not.toBeNull();
	} );

	it( 'marks thin pages as poor and keyword stuffing as critical', () => {
		const thin = analyze( {
			focusKeyword: 'pricing',
			seoTitle: 'Hi',
			metaDescription: '',
			slug: 'hello',
			contentHtml: '<p>Hi.</p>',
			locale: 'en_US',
		} );
		expect( thin.score ).toBeLessThan( 50 );
		expect( thin.band ).toBe( 'poor' );
		expect(
			thin.checks.find( ( check ) => check.id === 'word_count' )?.status
		).toBe( 'critical' );

		const stuffed = analyze( {
			focusKeyword: 'tools',
			seoTitle: 'Tools tools tools for every tools shed today',
			metaDescription: description.replace( /Garden tools/g, 'Tools' ),
			slug: 'tools',
			locale: 'en_US',
			contentHtml: `<p>${ 'tools '.repeat( 40 ) }</p>`,
		} );
		const density = stuffed.checks.find(
			( check ) => check.id === 'keyword_density'
		);
		expect( density?.status ).toBe( 'critical' );
		expect( density?.metrics.stuffing ).toBe( true );
		expect( simpleTip( density! ) ).toMatch( /too often/i );
		expect( checkDetail( density! ) ).toMatch( /Density/ );
	} );

	it( 'does not penalize non-English text with an English reading-ease score', () => {
		const result = analyze( {
			...strongInput(),
			locale: 'id_ID',
		} );
		const reading = result.checks.find(
			( check ) => check.id === 'readability'
		);
		expect( reading?.score ).toBe( 3 );
		expect( reading?.status ).toBe( 'pass' );
		expect( result.summary.fleschReadingEase ).toBeNull();
		expect( checkDetail( reading! ) ).toMatch( /this language/i );
	} );

	it( 'flags skipped headings, missing alt text, and a hidden page', () => {
		const result = analyze( {
			focusKeyword: 'garden tools',
			seoTitle: 'Garden tools for small city yards',
			metaDescription: description,
			slug: 'garden-tools',
			noindex: true,
			siteUrl: 'https://example.com',
			contentHtml:
				'<h1>Garden tools</h1><h3>Skipped</h3><img src="/a.jpg" /><img alt="Garden tools" src="/b.jpg" /><a href="https://example.com/about">About</a>',
		} );
		const headings = result.checks.find(
			( check ) => check.id === 'heading_hierarchy'
		);
		const images = result.checks.find(
			( check ) => check.id === 'image_alts'
		);
		expect( headings?.metrics.skipped ).toBe( true );
		expect( headings?.status ).not.toBe( 'pass' );
		expect( images?.score ).toBe( 2 );
		expect( result.summary.images ).toBe( 2 );
		expect( result.summary.imagesWithAlt ).toBe( 1 );
		expect(
			result.checks.find( ( check ) => check.id === 'indexable' )?.status
		).toBe( 'critical' );
		expect( result.summary.internalLinks ).toBe( 1 );
		expect( result.summary.externalLinks ).toBe( 0 );
	} );

	it( 'treats a product page word target as 150', () => {
		const result = analyze( {
			focusKeyword: 'garden tools',
			seoTitle: 'Garden tools',
			metaDescription: '',
			slug: 'garden-tools',
			postType: 'product',
			contentHtml: `<p>${ words( 160, 'Garden tools' ) }</p>`,
		} );
		expect(
			result.checks.find( ( check ) => check.id === 'word_count' )
				?.metrics.target
		).toBe( 150 );
		expect(
			result.checks.find( ( check ) => check.id === 'word_count' )?.status
		).toBe( 'pass' );
	} );

	it( 'scores every secondary keyword and keeps the primary in charge', () => {
		const primary = analyze( strongInput() );
		const blended = analyze( {
			...strongInput(),
			secondaryKeywords: [ 'unrelated quartz mineral' ],
		} );
		expect( blended.primaryScore ).toBe( primary.score );
		expect( blended.keywords ).toHaveLength( 2 );
		expect( blended.keywords[ 0 ]?.role ).toBe( 'primary' );
		expect( blended.keywords[ 1 ]?.status ).toBe( 'critical' );
		expect( blended.score ).toBe(
			Math.round( primary.score * 0.8 + 0 * 0.2 )
		);
		expect( blended.checks ).toHaveLength( primary.checks.length );
	} );

	it( 'matches English word forms and leaves other languages exact', () => {
		const english = analyze( {
			...strongInput(),
			focusKeyword: 'running shoes',
			seoTitle: 'Run shoe guide for city trails today',
			slug: 'running-shoes',
			contentHtml: `<h1>Running shoes</h1><p>${ words(
				320,
				'Running shoes make weekend miles easier.'
			) }</p><h2>Care</h2><p>Buy a spare pair.</p>`,
		} );
		expect(
			english.checks.find( ( check ) => check.id === 'keyword_in_title' )
				?.status
		).toBe( 'pass' );

		const synonym = analyze( {
			focusKeyword: 'buy',
			seoTitle: 'Purchase garden tools today',
			metaDescription: description,
			slug: 'buy',
			locale: 'en_US',
			contentHtml: '<p>Purchase one set.</p>',
		} );
		expect(
			synonym.checks.find( ( check ) => check.id === 'keyword_in_title' )
				?.metrics.present
		).toBe( true );

		const indonesian = analyze( {
			focusKeyword: 'buy',
			seoTitle: 'Purchase garden tools today',
			metaDescription: '',
			slug: 'buy',
			locale: 'id_ID',
			contentHtml: '<p>Purchase one set. Buy stays exact.</p>',
		} );
		expect(
			indonesian.checks.find(
				( check ) => check.id === 'keyword_in_title'
			)?.metrics.present
		).toBe( false );
		expect(
			indonesian.checks.find(
				( check ) => check.id === 'keyword_in_intro'
			)?.metrics.present
		).toBe( true );
	} );

	it( 'asks cornerstone pages for more words and three internal links', () => {
		const base = {
			...strongInput(),
			contentHtml: `<h1>Garden tools</h1><p>${ words(
				320,
				'Garden tools make weekend planting easier.'
			) }</p>`,
		};
		const normal = analyze( base );
		const pillar = analyze( { ...base, cornerstone: true } );
		expect(
			normal.checks.find( ( check ) => check.id === 'word_count' )
				?.metrics.target
		).toBe( 300 );
		expect(
			pillar.checks.find( ( check ) => check.id === 'word_count' )
				?.metrics.target
		).toBe( 900 );
		expect(
			pillar.checks.find( ( check ) => check.id === 'internal_links' )
				?.status
		).toBe( 'critical' );
		expect(
			pillar.checks.find( ( check ) => check.id === 'internal_links' )
				?.score
		).toBe( 0 );

		const linked = analyze( {
			...base,
			cornerstone: true,
			contentHtml: `${ base.contentHtml }<p><a href="/a">A</a> <a href="/b">B</a> <a href="/c">C</a></p>`,
		} );
		expect(
			linked.checks.find( ( check ) => check.id === 'internal_links' )
		).toMatchObject( { score: 3, status: 'pass' } );
	} );

	it( 'adds product checks for sku, short description, and gallery alt text', () => {
		const missing = analyze( {
			focusKeyword: '',
			seoTitle: 'Hat',
			metaDescription: '',
			slug: 'hat',
			postType: 'product',
			contentHtml: '<p>Hat</p>',
			product: {
				sku: '',
				gtin: '',
				shortDescription: '',
				galleryAlts: [ '', 'Side view' ],
			},
		} );
		expect(
			missing.checks.find(
				( check ) => check.id === 'product_identifier'
			)?.status
		).toBe( 'critical' );
		expect(
			missing.checks.find(
				( check ) => check.id === 'product_short_description'
			)?.status
		).toBe( 'critical' );
		expect(
			missing.checks.find(
				( check ) => check.id === 'product_gallery_alt'
			)?.status
		).toBe( 'improve' );
		expect(
			missing.checks.some( ( check ) => check.category === 'product' )
		).toBe( true );
		expect( missing.score ).toBeLessThanOrEqual( 100 );

		const ready = analyze( {
			...strongInput(),
			postType: 'product',
			product: {
				sku: 'HAT-1',
				gtin: '',
				shortDescription:
					'A wool hat for cold mornings, with a folded brim and a soft lining.',
				galleryAlts: [ 'Red wool hat', 'Hat brim' ],
			},
		} );
		expect(
			ready.checks
				.filter( ( check ) => check.category === 'product' )
				.every( ( check ) => check.status === 'pass' )
		).toBe( true );
	} );
} );
