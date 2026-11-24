import {
	readDiviDocument,
	readElementorDocument,
} from '../../src/builders/content';
import { restPayloadFromFields } from '../../src/builders/api';
import { emptyFields } from '../../src/editor/fields';

describe( 'builder document readers', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'reads Elementor preview HTML, title, and post id', () => {
		document.body.innerHTML =
			'<iframe id="elementor-preview-iframe"></iframe>';
		const iframe = document.getElementById(
			'elementor-preview-iframe'
		) as HTMLIFrameElement;
		const frame = iframe.contentDocument;
		expect( frame ).not.toBeNull();
		if ( ! frame ) {
			return;
		}
		frame.body.innerHTML =
			'<div class="elementor"><h1>Garden tools</h1><p>garden tools</p></div>';

		const live = readElementorDocument( document, {
			config: { document: { id: 42 } },
			settings: {
				page: {
					model: {
						get: ( key ) =>
							'post_title' === key
								? 'Garden tools'
								: 'garden-tools',
					},
				},
			},
		} );

		expect( live.postId ).toBe( 42 );
		expect( live.title ).toBe( 'Garden tools' );
		expect( live.slug ).toBe( 'garden-tools' );
		expect( live.html ).toContain( 'Garden tools' );
	} );

	it( 'returns an empty Elementor document when the preview is missing', () => {
		document.body.innerHTML = '<div class="unrelated"></div>';
		expect( () =>
			readElementorDocument( document, undefined )
		).not.toThrow();
		expect( readElementorDocument( document, undefined ) ).toEqual( {
			html: '',
			title: '',
			slug: '',
			postId: 0,
		} );
	} );

	it( 'reads Divi content and degrades when the builder DOM is absent', () => {
		document.body.innerHTML =
			'<input id="title" value="Pricing" /><input id="post_name" value="pricing" /><div class="et-fb-post-content"><p>Plans for teams</p></div>';
		const live = readDiviDocument( document );
		expect( live.title ).toBe( 'Pricing' );
		expect( live.slug ).toBe( 'pricing' );
		expect( live.html ).toContain( 'Plans for teams' );

		document.body.innerHTML = '<div class="something-else"></div>';
		expect( () => readDiviDocument( document ) ).not.toThrow();
		expect( readDiviDocument( document ).html ).toBe( '' );
	} );
} );

describe( 'builder save payload', () => {
	it( 'uses the same public field names as the REST meta endpoint', () => {
		const payload = restPayloadFromFields(
			{
				...emptyFields(),
				title: 'Builder title',
				description: 'Builder description',
				focusKeyword: 'garden tools',
				secondaryKeywords: [ 'spades' ],
				canonical: 'https://example.com/garden/',
				ogTitle: 'Share title',
				twitterTitle: 'X title',
			},
			{ score: 84, summary: '{"score":84}' }
		);

		expect( payload ).toEqual(
			expect.objectContaining( {
				title: 'Builder title',
				description: 'Builder description',
				focus_keyword: 'garden tools',
				secondary_keywords: [ 'spades' ],
				canonical: 'https://example.com/garden/',
				og_title: 'Share title',
				twitter_title: 'X title',
				seo_score: 84,
				seo_summary: '{"score":84}',
			} )
		);
		expect( payload ).not.toHaveProperty( '_rankmyseo_title' );
	} );
} );
