import apiFetch from '@wordpress/api-fetch';
import type { SitemapStatus } from './sitemaps';
import type {
	PluginSettings,
	SettingsResponse,
	StatusResponse,
	UiMode,
	UiPreferencesResponse,
} from './types';

export type SettingsUpdatePayload = Partial<
	Omit< PluginSettings, 'modules' >
> & {
	modules?: Partial< PluginSettings[ 'modules' ] >;
};

export interface UiPreferencesUpdatePayload {
	ui_mode?: UiMode;
	wizard_dismissed?: boolean;
	wizard_completed?: boolean;
}

export function fetchSettings(): Promise< SettingsResponse > {
	return apiFetch< SettingsResponse >( {
		path: '/rankmyseo/v1/settings',
		method: 'GET',
	} );
}

export function saveSettingsRequest(
	payload: SettingsUpdatePayload
): Promise< SettingsResponse > {
	return apiFetch< SettingsResponse >( {
		path: '/rankmyseo/v1/settings',
		method: 'POST',
		data: payload,
	} );
}

export function fetchStatus(): Promise< StatusResponse > {
	return apiFetch< StatusResponse >( {
		path: '/rankmyseo/v1/status',
		method: 'GET',
	} );
}

export function fetchUiPreferences(): Promise< UiPreferencesResponse > {
	return apiFetch< UiPreferencesResponse >( {
		path: '/rankmyseo/v1/ui-preferences',
		method: 'GET',
	} );
}

export function saveUiPreferencesRequest(
	payload: UiPreferencesUpdatePayload
): Promise< UiPreferencesResponse > {
	return apiFetch< UiPreferencesResponse >( {
		path: '/rankmyseo/v1/ui-preferences',
		method: 'POST',
		data: payload,
	} );
}

export interface SitemapSettingsResponse {
	updated: boolean;
	settings: Record< string, unknown >;
	status: SitemapStatus;
}

export interface SitemapMutationResponse {
	status: SitemapStatus;
}

export function fetchSitemapStatus(): Promise< SitemapStatus > {
	return apiFetch< SitemapStatus >( {
		path: '/rankmyseo/v1/sitemaps/status',
		method: 'GET',
	} );
}

export function saveSitemapSettings(
	payload: Record< string, unknown >
): Promise< SitemapSettingsResponse > {
	return apiFetch< SitemapSettingsResponse >( {
		path: '/rankmyseo/v1/sitemaps/settings',
		method: 'POST',
		data: payload,
	} );
}

export function flushSitemapCache(): Promise<
	SitemapMutationResponse & { flushed: boolean; generation: number }
> {
	return apiFetch( {
		path: '/rankmyseo/v1/sitemaps/flush',
		method: 'POST',
	} );
}

export function rotateIndexNowKey(): Promise<
	SitemapMutationResponse & { key: string; key_url: string }
> {
	return apiFetch( {
		path: '/rankmyseo/v1/sitemaps/indexnow/rotate',
		method: 'POST',
	} );
}

export function pingIndexNow( url: string ): Promise< {
	result: { ok: boolean; code: number; message: string };
	log: SitemapStatus[ 'indexnow' ][ 'log' ];
} > {
	return apiFetch( {
		path: '/rankmyseo/v1/sitemaps/indexnow/ping',
		method: 'POST',
		data: { url },
	} );
}
