import apiFetch from '@wordpress/api-fetch';
import { fieldsFromRecord, type SeoFields } from '../editor/fields';

export interface ScoreSave {
	score: number;
	summary: string;
}

interface ApiFetchMiddleware {
	use?: ( middleware: unknown ) => void;
	createNonceMiddleware?: ( nonce: string ) => unknown;
	createRootURLMiddleware?: ( root: string ) => unknown;
}

let apiReady = false;

export function installBuilderApi( nonce: string, restRoot: string ): void {
	if ( apiReady || '' === nonce ) {
		return;
	}
	const client = apiFetch as unknown as ApiFetchMiddleware;
	if ( typeof client.use !== 'function' ) {
		return;
	}
	if ( typeof client.createNonceMiddleware === 'function' ) {
		client.use( client.createNonceMiddleware( nonce ) );
	}
	if (
		typeof client.createRootURLMiddleware === 'function' &&
		'' !== restRoot
	) {
		const root = restRoot.replace( /rankmyseo\/v1\/?$/, '' );
		client.use( client.createRootURLMiddleware( root ) );
	}
	apiReady = true;
}

export function restPayloadFromFields(
	fields: SeoFields,
	score?: ScoreSave | null
): Record< string, unknown > {
	const payload: Record< string, unknown > = {
		title: fields.title,
		description: fields.description,
		canonical: fields.canonical,
		focus_keyword: fields.focusKeyword,
		secondary_keywords: fields.secondaryKeywords,
		robots: fields.robots,
		og_title: fields.ogTitle,
		og_description: fields.ogDescription,
		og_image: fields.ogImage,
		twitter_title: fields.twitterTitle,
		twitter_description: fields.twitterDescription,
		twitter_image: fields.twitterImage,
		twitter_card: fields.twitterCard,
	};
	if ( score ) {
		payload.seo_score = score.score;
		payload.seo_summary = score.summary;
	}
	return payload;
}

export async function loadPostSeo( postId: number ): Promise< SeoFields > {
	const response = await apiFetch< { meta?: Record< string, unknown > } >( {
		path: `/rankmyseo/v1/meta/posts/${ postId }`,
	} );
	return fieldsFromRecord( response?.meta ?? {} );
}

export async function savePostSeo(
	postId: number,
	fields: SeoFields,
	score?: ScoreSave | null
): Promise< void > {
	await apiFetch( {
		path: `/rankmyseo/v1/meta/posts/${ postId }`,
		method: 'POST',
		data: restPayloadFromFields( fields, score ),
	} );
}
