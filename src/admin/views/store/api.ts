import apiFetch from '@wordpress/api-fetch';
import type { WooSettings, WooSettingsResponse } from './types';

export function fetchWooSettings(): Promise< WooSettingsResponse > {
	return apiFetch< WooSettingsResponse >( {
		path: '/rankmyseo/v1/woocommerce/settings',
		method: 'GET',
	} );
}

export function saveWooSettings(
	settings: WooSettings
): Promise< WooSettingsResponse > {
	return apiFetch< WooSettingsResponse >( {
		path: '/rankmyseo/v1/woocommerce/settings',
		method: 'POST',
		data: settings,
	} );
}
