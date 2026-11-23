export type UiMode = 'simple' | 'advanced';

export type SiteType = 'organization' | 'person';

export type NavPageId =
	| 'dashboard'
	| 'meta'
	| 'schema'
	| 'sitemaps'
	| 'redirects'
	| 'import'
	| 'cloud'
	| 'ai-queue'
	| 'settings';

export type ModuleId =
	| 'meta'
	| 'analyzer'
	| 'schema'
	| 'sitemaps'
	| 'redirects'
	| 'importer'
	| 'cloud'
	| 'ai_queue';

export interface PluginSettings {
	default_ui_mode: UiMode;
	site_type: SiteType;
	site_name: string;
	title_separator: string;
	keep_data_on_uninstall: boolean;
	telemetry_opt_in: boolean;
	wizard_completed: boolean;
	wizard_dismissed: boolean;
	modules: Record< ModuleId, boolean >;
}

export interface ModuleDescriptor {
	id: ModuleId;
	name: string;
	description: string;
	simple_description: string;
	enabled: boolean;
	enabled_by_default: boolean;
	ready: boolean;
	requires_cloud: boolean;
	booted: boolean;
}

export interface SettingsResponse {
	settings: PluginSettings;
	modules: ModuleDescriptor[];
	defaults?: PluginSettings;
	updated?: boolean;
}

export interface TableStatus {
	name: string;
	exists: boolean;
}

export interface StatusResponse {
	plugin_version: string;
	php_version: string;
	wp_version: string;
	site: {
		name: string;
		url: string;
		blog_public: boolean;
	};
	database: {
		installed_version: number;
		target_version: number;
		needs_migration: boolean;
		tables_healthy: boolean;
		tables: Record< string, TableStatus >;
	};
	modules: {
		enabled_count: number;
		total_count: number;
		items: ModuleDescriptor[];
	};
	cloud: {
		connected: boolean;
		tier: 'local' | 'cloud';
	};
	onboarding: {
		wizard_completed: boolean;
		wizard_dismissed: boolean;
	};
	capabilities: {
		can_manage: boolean;
		can_approve_ai: boolean;
	};
}

export interface UiPreferencesResponse {
	ui_mode: UiMode;
	default_ui_mode: UiMode;
	wizard_completed: boolean;
	wizard_dismissed: boolean;
	can_manage: boolean;
	can_approve_ai: boolean;
}

export interface BootstrapConfig {
	version: string;
	restUrl: string;
	nonce: string;
	adminUrl: string;
	siteName: string;
	siteUrl: string;
	blogPublic: boolean;
	initialPage: NavPageId;
	uiMode: UiMode;
	defaultUiMode: UiMode;
	wizardCompleted: boolean;
	wizardDismissed: boolean;
	canManage: boolean;
	canApproveAi: boolean;
}

export type RiskTier = 'low' | 'medium' | 'high';

export type ActionCardStatus =
	| 'pending'
	| 'applied'
	| 'rejected'
	| 'rolled_back';

export interface ChatQuickLink {
	label: string;
	pageId: NavPageId;
	switchMode?: UiMode;
}

/**
 * Ready data contract for inline Approval Action Cards (diff preview + Approve/Reject/Undo)
 * populated by upcoming local & RankMySEO Cloud AI PRs.
 */
export interface ChatActionCardData {
	id: string;
	title: string;
	targetLabel: string;
	riskTier: RiskTier;
	beforeText: string;
	proposedText: string;
	rationale: string;
	status: ActionCardStatus;
}

export type CloudHealth =
	| 'disconnected'
	| 'connected'
	| 'reconnect'
	| 'degraded';

export interface CloudPairing {
	code: string;
	pair_url: string;
	expires_at: string;
}

export interface CloudPublicState {
	connected: boolean;
	reconnect_required: boolean;
	health: CloudHealth;
	site_name: string;
	site_host: string;
	site_url: string;
	domain: string;
	project_id: string;
	connection_id: string;
	plan_id: string;
	plan_label: string;
	token_prefix: string;
	last_sync_at: string;
	last_error: string;
	pairing: CloudPairing | null;
}

export interface CloudActionResponse {
	state: CloudPublicState;
	pair_status?: string;
	processed?: number;
	message?: string;
}

export interface CloudKeywordRow {
	keyword: string;
	position: number | null;
	url: string;
}

export interface CloudInsights {
	connected: boolean;
	stale: boolean;
	cached: boolean;
	fetched_at: string;
	message: string;
	project: { id: string; name: string; domain: string } | null;
	keywords: {
		tracked: number;
		rows: CloudKeywordRow[];
	};
	issues: Array< {
		id: string;
		category: string;
		count: number;
		impact: string;
	} >;
	page_updates: Array< {
		url: string;
		action: string;
		summary: string;
	} >;
}

export interface AiAction {
	id: number;
	cloud_action_id: string;
	action_type: string;
	risk_tier: RiskTier;
	status: string;
	wp_post_id: number | null;
	target_url: string;
	before: Record< string, unknown >;
	after: Record< string, unknown >;
	rationale: string;
	title: string;
	before_text: string;
	after_text: string;
	source?: string;
	applied_by?: string;
}

export interface AiPolicy {
	enabled: boolean;
	actionTypes: string[];
	postTypes: string[];
	excludedUrls: string[];
	dailyCap: number;
	articles: 'draft';
}

export interface AuditEntry {
	id: number;
	event_type: string;
	actor_type: string;
	summary: string;
	created_at: string;
}

export interface CloudChatTurn {
	state?: 'running' | 'done' | 'failed';
	turn_id?: string;
	thread_id?: string;
	reply?: string;
	phase?: string;
	actions?: AiAction[];
	message?: string;
	messages?: Array< {
		id: string;
		role: 'user' | 'assistant';
		content: string;
	} >;
}

export interface ChatMessage {
	id: string;
	role: 'user' | 'assistant';
	content: string;
	createdAt: string;
	quickLinks?: ChatQuickLink[];
	actionCards?: ChatActionCardData[];
	aiActions?: AiAction[];
}

declare global {
	interface Window {
		rankmyseoAdmin?: BootstrapConfig;
	}
}
