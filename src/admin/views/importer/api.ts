/**
 * REST helpers for the migration importer.
 */

import apiFetch from '@wordpress/api-fetch';
import type {
	DetectedSource,
	HistoryEntry,
	ImportOptions,
	PreviewResult,
	RunResult,
} from './types';

interface DetectResponse {
	sources: DetectedSource[];
	nothing_deleted: boolean;
}

interface HistoryResponse {
	imports: HistoryEntry[];
}

interface RollbackResponse {
	import_id: string;
	status: string;
	restored: number;
	nothing_deleted: boolean;
}

export function fetchDetectedSources(): Promise< DetectResponse > {
	return apiFetch< DetectResponse >( {
		path: '/rankmyseo/v1/importer/detect',
		method: 'GET',
	} );
}

export function fetchImportHistory(): Promise< HistoryResponse > {
	return apiFetch< HistoryResponse >( {
		path: '/rankmyseo/v1/importer/history',
		method: 'GET',
	} );
}

export function previewImport(
	options: ImportOptions
): Promise< PreviewResult > {
	return apiFetch< PreviewResult >( {
		path: '/rankmyseo/v1/importer/preview',
		method: 'POST',
		data: options,
	} );
}

export function runImportBatch(
	options: ImportOptions,
	cursor: string,
	importId: string
): Promise< RunResult > {
	return apiFetch< RunResult >( {
		path: '/rankmyseo/v1/importer/run',
		method: 'POST',
		data: {
			...options,
			cursor,
			import_id: importId,
		},
	} );
}

export function rollbackImport(
	importId: string
): Promise< RollbackResponse > {
	return apiFetch< RollbackResponse >( {
		path: '/rankmyseo/v1/importer/rollback',
		method: 'POST',
		data: { import_id: importId },
	} );
}
