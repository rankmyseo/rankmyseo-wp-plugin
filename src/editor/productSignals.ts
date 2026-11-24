import type { ProductSignals } from '../analyzer';

export interface ProductSignalInput {
	postType: string;
	meta?: Record< string, unknown >;
	initial?: ProductSignals;
	excerpt?: string;
	excerptFromEditor?: boolean;
	galleryAlts?: string[];
}

const EMPTY: ProductSignals = {
	sku: '',
	gtin: '',
	shortDescription: '',
	galleryAlts: [],
};

function hasKey(
	meta: Record< string, unknown > | undefined,
	key: string
): boolean {
	return Boolean( meta && Object.prototype.hasOwnProperty.call( meta, key ) );
}

/**
 * Prefer live editor values when the field is present, including an emptied field.
 * A missing key keeps the value loaded with the page.
 * @param input
 */
export function mergeProductSignals(
	input: ProductSignalInput
): ProductSignals | undefined {
	if ( input.postType !== 'product' ) {
		return undefined;
	}

	const initial = input.initial ?? EMPTY;
	const meta = input.meta;
	const sku = hasKey( meta, '_sku' )
		? String( meta?._sku ?? '' ).trim()
		: initial.sku;

	let gtin = initial.gtin;
	if ( hasKey( meta, '_rankmyseo_gtin' ) ) {
		gtin = String( meta?._rankmyseo_gtin ?? '' ).trim();
	} else if ( hasKey( meta, '_global_unique_id' ) ) {
		gtin = String( meta?._global_unique_id ?? '' ).trim();
	}

	const shortDescription = input.excerptFromEditor
		? ( input.excerpt ?? '' ).trim()
		: ( input.excerpt ?? initial.shortDescription ).trim();

	let galleryAlts = initial.galleryAlts;
	if ( hasKey( meta, '_product_image_gallery' ) ) {
		const raw = String( meta?._product_image_gallery ?? '' ).trim();
		if ( raw === '' ) {
			galleryAlts = [];
		} else if ( input.galleryAlts ) {
			galleryAlts = input.galleryAlts;
		}
	}

	return { sku, gtin, shortDescription, galleryAlts };
}
