/**
 * Types for the migration importer admin screen.
 */

export type ImportSourceId = 'yoast' | 'rankmath' | 'aioseo';

export interface DetectedSource {
	id: ImportSourceId;
	label: string;
	active: boolean;
	found: boolean;
	posts: number;
	terms: number;
	redirects: number;
	settings: number;
}

export interface FieldSample {
	object_type: string;
	object_id: number;
	label: string;
	field: string;
	field_label: string;
	before: string;
	after: string;
	action: 'write' | 'skip';
	unsupported: string[];
}

export interface PreviewResult {
	source: string;
	label: string;
	active: boolean;
	found: boolean;
	posts: number;
	terms: number;
	redirects: number;
	settings: number;
	conflicts: number;
	samples: FieldSample[];
	nothing_deleted: boolean;
}

export interface RunResult {
	import_id: string;
	source: string;
	cursor: string;
	next_cursor: string;
	done: boolean;
	processed: number;
	written: number;
	skipped: number;
	phase: string;
	batch_size: number;
	nothing_deleted: boolean;
}

export interface HistoryEntry {
	import_id: string;
	source: string;
	status: 'running' | 'completed' | 'rolled_back';
	started_at: string;
	finished_at: string;
	posts: number;
	terms: number;
	redirects: number;
	settings: number;
	written: number;
	skipped: number;
	overwrite: boolean;
}

export interface ImportOptions {
	source: string;
	include_posts: boolean;
	include_terms: boolean;
	include_settings: boolean;
	include_redirects: boolean;
	overwrite: boolean;
	batch_size: number;
}
