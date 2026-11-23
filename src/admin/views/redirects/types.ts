export type RedirectMatchType = 'exact' | 'prefix' | 'regex';

export type RedirectStatusCode = 301 | 302 | 307 | 410 | 451;

export type RedirectOrigin = 'manual' | 'auto_slug' | 'import' | 'ai';

export interface RedirectRule {
	id: number;
	source_path: string;
	source_hash: string;
	match_type: RedirectMatchType;
	target_url: string | null;
	status_code: RedirectStatusCode;
	is_active: boolean;
	hits: number;
	last_hit_at: string | null;
	source_origin: RedirectOrigin;
	created_at: string;
	updated_at: string;
}

export interface RedirectWriteResult {
	redirect: RedirectRule;
	warning: string;
	chain: string[];
	updated: boolean;
}

export interface RedirectListResponse {
	items: RedirectRule[];
	total: number;
	page: number;
	per_page: number;
}

export interface RedirectSettingsPayload {
	auto_slug: boolean;
	preserve_query: boolean;
	strip_trailing_slash: boolean;
	ignore_patterns: string[];
	bot_patterns: string[];
	max_rows: number;
	ttl_days: number;
}

export interface RedirectTestResult {
	matched: boolean;
	path: string;
	query: string;
	redirect_id?: number;
	match_type?: RedirectMatchType;
	status_code?: RedirectStatusCode;
	target_url?: string | null;
	source_path?: string;
}

export interface RedirectExportFile {
	filename: string;
	mime_type: string;
	content: string;
}

export interface NotFoundLog {
	id: number;
	request_path: string;
	path_hash: string;
	query_string: string;
	referer: string;
	user_agent: string;
	hits: number;
	status: 'open' | 'redirected' | 'ignored';
	first_seen_at: string;
	last_seen_at: string;
}

export interface NotFoundListResponse {
	items: NotFoundLog[];
	total: number;
	page: number;
	per_page: number;
}

export interface ConvertLogResult extends RedirectWriteResult {
	log: NotFoundLog;
}
