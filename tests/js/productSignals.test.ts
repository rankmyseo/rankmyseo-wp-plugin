import { mergeProductSignals } from '../../src/editor/productSignals';

describe( 'product editor signals', () => {
	const initial = {
		sku: 'HAT-1',
		gtin: '12345678',
		shortDescription: 'A wool hat for cold mornings in the city.',
		galleryAlts: [ 'Red hat' ],
	};

	it( 'keeps initial values until the editor has the field', () => {
		expect(
			mergeProductSignals( {
				postType: 'product',
				initial,
			} )
		).toEqual( initial );
		expect(
			mergeProductSignals( {
				postType: 'post',
				initial,
			} )
		).toBeUndefined();
	} );

	it( 'uses a cleared live field instead of the saved value', () => {
		expect(
			mergeProductSignals( {
				postType: 'product',
				initial,
				meta: {
					_sku: '',
					_rankmyseo_gtin: '',
					_product_image_gallery: '',
				},
				excerpt: '',
				excerptFromEditor: true,
			} )
		).toEqual( {
			sku: '',
			gtin: '',
			shortDescription: '',
			galleryAlts: [],
		} );
	} );

	it( 'reads live gallery descriptions when the gallery still has photos', () => {
		expect(
			mergeProductSignals( {
				postType: 'product',
				initial,
				meta: { _product_image_gallery: '3,4' },
				galleryAlts: [ 'Front', '' ],
			} )?.galleryAlts
		).toEqual( [ 'Front', '' ] );
	} );
} );
