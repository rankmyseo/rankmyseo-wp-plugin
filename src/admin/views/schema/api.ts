import apiFetch from '@wordpress/api-fetch';
import type {
	SchemaPreviewResponse,
	SchemaSettings,
	SchemaSettingsResponse,
	SchemaValidateResponse,
} from './types';

export function fetchSchemaSettings(): Promise< SchemaSettingsResponse > {
	return apiFetch< SchemaSettingsResponse >( {
		path: '/rankmyseo/v1/schema/settings',
		method: 'GET',
	} );
}

export function saveSchemaSettings(
	settings: SchemaSettings
): Promise< SchemaSettingsResponse > {
	return apiFetch< SchemaSettingsResponse >( {
		path: '/rankmyseo/v1/schema/settings',
		method: 'POST',
		data: settings,
	} );
}

export function fetchSchemaPreview(
	postId: number
): Promise< SchemaPreviewResponse > {
	return apiFetch< SchemaPreviewResponse >( {
		path: `/rankmyseo/v1/schema/preview?post_id=${ postId }`,
		method: 'GET',
	} );
}

export function validateSchemaJson(
	json: string
): Promise< SchemaValidateResponse > {
	return apiFetch< SchemaValidateResponse >( {
		path: '/rankmyseo/v1/schema/validate',
		method: 'POST',
		data: { json },
	} );
}
