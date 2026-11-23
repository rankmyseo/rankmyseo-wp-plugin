import apiFetch from '@wordpress/api-fetch';
import type {
	CloudActionResponse,
	CloudInsights,
	CloudPublicState,
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

export function fetchCloudStatus(): Promise< CloudPublicState > {
	return apiFetch< CloudPublicState >( {
		path: '/rankmyseo/v1/cloud/status',
		method: 'GET',
	} );
}

export function startCloudPairing(): Promise< CloudActionResponse > {
	return apiFetch< CloudActionResponse >( {
		path: '/rankmyseo/v1/cloud/pair/start',
		method: 'POST',
	} );
}

export function pollCloudPairing(): Promise< CloudActionResponse > {
	return apiFetch< CloudActionResponse >( {
		path: '/rankmyseo/v1/cloud/pair/poll',
		method: 'POST',
	} );
}

export function disconnectCloud(): Promise< CloudActionResponse > {
	return apiFetch< CloudActionResponse >( {
		path: '/rankmyseo/v1/cloud/disconnect',
		method: 'POST',
	} );
}

export function rotateCloudToken(): Promise< CloudActionResponse > {
	return apiFetch< CloudActionResponse >( {
		path: '/rankmyseo/v1/cloud/rotate-token',
		method: 'POST',
	} );
}

export function syncCloudNow(): Promise< CloudActionResponse > {
	return apiFetch< CloudActionResponse >( {
		path: '/rankmyseo/v1/cloud/sync',
		method: 'POST',
	} );
}

export function fetchCloudInsights(
	refresh = false
): Promise< CloudInsights > {
	const path = refresh
		? '/rankmyseo/v1/cloud/insights?refresh=1'
		: '/rankmyseo/v1/cloud/insights';
	return apiFetch< CloudInsights >( {
		path,
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
