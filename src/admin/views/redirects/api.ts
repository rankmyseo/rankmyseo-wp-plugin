import apiFetch from '@wordpress/api-fetch';
import type {
	ConvertLogResult,
	NotFoundListResponse,
	RedirectExportFile,
	RedirectListResponse,
	RedirectMatchType,
	RedirectSettingsPayload,
	RedirectStatusCode,
	RedirectTestResult,
	RedirectWriteResult,
} from './types';

export interface RedirectQuery {
	page?: number;
	perPage?: number;
	search?: string;
	statusCode?: number;
	matchType?: string;
	origin?: string;
}

export interface NotFoundQuery {
	page?: number;
	perPage?: number;
	status?: string;
	search?: string;
	orderby?: 'hits' | 'last_seen_at';
}

export interface RedirectInput {
	source: string;
	target?: string;
	status_code?: RedirectStatusCode;
	match_type?: RedirectMatchType;
	is_active?: boolean;
}

function redirectQuery( query: RedirectQuery ): string {
	const params = new URLSearchParams();
	params.set( 'page', String( query.page ?? 1 ) );
	params.set( 'per_page', String( query.perPage ?? 20 ) );
	if ( query.search ) {
		params.set( 'search', query.search );
	}
	if ( query.statusCode ) {
		params.set( 'status_code', String( query.statusCode ) );
	}
	if ( query.matchType ) {
		params.set( 'match_type', query.matchType );
	}
	if ( query.origin ) {
		params.set( 'origin', query.origin );
	}
	return `/rankmyseo/v1/redirects?${ params.toString() }`;
}

export function fetchRedirects(
	query: RedirectQuery = {}
): Promise< RedirectListResponse > {
	return apiFetch< RedirectListResponse >( {
		path: redirectQuery( query ),
		method: 'GET',
	} );
}

export function createRedirect(
	input: RedirectInput
): Promise< RedirectWriteResult > {
	return apiFetch< RedirectWriteResult >( {
		path: '/rankmyseo/v1/redirects',
		method: 'POST',
		data: input,
	} );
}

export function deleteRedirect( id: number ): Promise< { deleted: boolean } > {
	return apiFetch< { deleted: boolean } >( {
		path: `/rankmyseo/v1/redirects/${ id }`,
		method: 'DELETE',
	} );
}

export function bulkRedirects(
	action: 'delete' | 'activate' | 'deactivate',
	ids: number[]
): Promise< { updated: number } > {
	return apiFetch< { updated: number } >( {
		path: '/rankmyseo/v1/redirects/bulk',
		method: 'POST',
		data: { action, ids },
	} );
}

export function flattenRedirect( id: number ): Promise< RedirectWriteResult > {
	return apiFetch< RedirectWriteResult >( {
		path: `/rankmyseo/v1/redirects/${ id }/flatten`,
		method: 'POST',
	} );
}

export function testRedirectUrl( url: string ): Promise< RedirectTestResult > {
	return apiFetch< RedirectTestResult >( {
		path: '/rankmyseo/v1/redirects/test-url',
		method: 'POST',
		data: { url },
	} );
}

export function exportRedirects(
	format: 'csv' | 'htaccess' | 'nginx'
): Promise< RedirectExportFile > {
	return apiFetch< RedirectExportFile >( {
		path: `/rankmyseo/v1/redirects/export?format=${ format }`,
		method: 'GET',
	} );
}

export function importRedirects( csv: string ): Promise< {
	created: number;
	updated: number;
	skipped: number;
	errors: Array< { line: number; message: string } >;
} > {
	return apiFetch( {
		path: '/rankmyseo/v1/redirects/import',
		method: 'POST',
		data: { csv },
	} );
}

export function fetchRedirectSettings(): Promise< {
	settings: RedirectSettingsPayload;
} > {
	return apiFetch( {
		path: '/rankmyseo/v1/redirects/settings',
		method: 'GET',
	} );
}

export function saveRedirectSettings(
	settings: Partial< RedirectSettingsPayload >
): Promise< { settings: RedirectSettingsPayload } > {
	return apiFetch( {
		path: '/rankmyseo/v1/redirects/settings',
		method: 'POST',
		data: settings,
	} );
}

export function fetchNotFoundLogs(
	query: NotFoundQuery = {}
): Promise< NotFoundListResponse > {
	const params = new URLSearchParams();
	params.set( 'page', String( query.page ?? 1 ) );
	params.set( 'per_page', String( query.perPage ?? 20 ) );
	params.set( 'orderby', query.orderby ?? 'hits' );
	params.set( 'order', 'desc' );
	if ( query.status ) {
		params.set( 'status', query.status );
	}
	if ( query.search ) {
		params.set( 'search', query.search );
	}
	return apiFetch< NotFoundListResponse >( {
		path: `/rankmyseo/v1/404-logs?${ params.toString() }`,
		method: 'GET',
	} );
}

export function convertLogToRedirect(
	id: number,
	targetUrl: string
): Promise< ConvertLogResult > {
	return apiFetch< ConvertLogResult >( {
		path: `/rankmyseo/v1/404-logs/${ id }/redirect`,
		method: 'POST',
		data: {
			target_url: targetUrl,
			status_code: 301,
		},
	} );
}

export function ignoreLog( id: number ): Promise< { ignored: boolean } > {
	return apiFetch< { ignored: boolean } >( {
		path: `/rankmyseo/v1/404-logs/${ id }/ignore`,
		method: 'POST',
	} );
}

export function deleteLog( id: number ): Promise< { deleted: boolean } > {
	return apiFetch< { deleted: boolean } >( {
		path: `/rankmyseo/v1/404-logs/${ id }`,
		method: 'DELETE',
	} );
}
