export type UiMode = 'simple' | 'advanced';

export type SiteType = 'organization' | 'person';

export type NavPageId =
	| 'dashboard'
	| 'meta'
	| 'missing'
	| 'schema'
	| 'store'
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
	| 'ai_queue'
	| 'woocommerce';

export interface PluginSettings {
	default_ui_mode: UiMode;
	site_type: SiteType;
	site_name: string;
	title_separator: string;
	keep_data_on_uninstall: boolean;
	delete_data_on_uninstall?: boolean;
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
	builders?: {
		elementor: BuilderIntegrationStatus;
		divi: BuilderIntegrationStatus;
	};
}

export interface BuilderIntegrationStatus {
	id: string;
	label: string;
	detected: boolean;
	active: boolean;
	version: string;
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
	items?: LocalChatItem[];
	followUps?: LocalChatFollowUp[];
	cloudHandoff?: LocalChatHandoff | null;
	operatorEnabled?: boolean;
	aiActions?: AiAction[];
}

export interface LocalChatItem {
	id: number;
	title: string;
	slug: string;
	type: string;
	edit_link: string;
	page: string;
	tip: string;
	seo_score: number | null;
	word_count: number | null;
	hits: number | null;
}

export interface LocalChatFollowUp {
	id: string;
	label: string;
	prompt: string;
}

export interface LocalChatHandoff {
	title: string;
	message: string;
	page?: NavPageId;
}

export interface LocalChatResponse {
	tier: 'local';
	offline: boolean;
	intent: string;
	explanation: string;
	items: LocalChatItem[];
	follow_ups: LocalChatFollowUp[];
	cloud_handoff: LocalChatHandoff | null;
	action_cards: ChatActionCardData[];
	operator_slot: {
		enabled: boolean;
		mode: string;
	};
}

export interface OnboardingCompletePayload {
	site_type: SiteType;
	site_name: string;
	title_separator: string;
	logo_id: number;
	logo_url: string;
	modules: {
		meta: boolean;
		schema: boolean;
		sitemaps: boolean;
		redirects: boolean;
	};
	skip_cloud: boolean;
}

export interface SiteIdentity {
	site_type: SiteType;
	site_name: string;
	logo_id: number;
	logo_url: string;
	title_separator: string;
	skipped_cloud: boolean;
}

export interface OnboardingStateResponse {
	completed: boolean;
	site_identity: SiteIdentity;
	modules: Record< string, boolean >;
	blog_public: boolean;
	cloud_connected: boolean;
	skipped_cloud: boolean;
}

export interface OnboardingCompleteResponse extends OnboardingStateResponse {
	onboarding_completed: boolean;
	settings: PluginSettings;
	module_catalog?: ModuleDescriptor[];
}

export interface DashboardFix {
	id: string;
	title: string;
	detail: string;
	count: number;
	prompt: string;
	page: NavPageId;
}

export interface DashboardSummary {
	health: {
		score: number;
		label: string;
		band: string;
		summary: string;
	};
	blog_public: boolean;
	onboarding_completed: boolean;
	counts: {
		missing_titles: number;
		missing_descriptions: number;
		thin_posts: number;
		low_scores: number;
		open_404s: number;
		orphans?: number;
		cornerstone?: number;
		redirects_total: number;
		redirects_active: number;
	};
	orphans?: Array< {
		id: number;
		title: string;
		edit_link?: string;
		url?: string;
		type?: string;
	} >;
	fixes: DashboardFix[];
	modules: ModuleDescriptor[];
	audit: {
		tables_healthy: boolean;
		tables: Record< string, { name?: string; exists?: boolean } >;
		sitemaps_enabled: boolean;
	};
	cloud: {
		connected: boolean;
		tier: string;
	};
}

declare global {
	interface Window {
		rankmyseoAdmin?: BootstrapConfig;
		rankmyseoEditor?: {
			uiMode?: UiMode;
			siteName?: string;
			siteUrl?: string;
			separator?: string;
			tagline?: string;
			variables?: Array< {
				token: string;
				label: string;
				description?: string;
			} >;
			restRoot?: string;
			nonce?: string;
			askAiEvent?: string;
			locale?: string;
			schemaDefaults?: Record< string, string >;
			schemaSettingsUrl?: string;
			productSchemaEnabled?: boolean;
			product?: {
				sku: string;
				gtin: string;
				shortDescription: string;
				galleryAlts: string[];
			};
		};
		rankmyseoClassic?: Window[ 'rankmyseoEditor' ] & {
			postId?: number;
			postType?: string;
			postTitle?: string;
			permalink?: string;
			fields?: Record< string, unknown >;
		};
		rankmyseoElementor?: Window[ 'rankmyseoClassic' ];
		rankmyseoDivi?: Window[ 'rankmyseoClassic' ];
		wp?: {
			editor?: Record< string, unknown >;
			editPost?: Record< string, unknown >;
			media?: ( options: Record< string, unknown > ) => {
				on: ( event: string, callback: () => void ) => void;
				open: () => void;
				state: () => {
					get: ( key: string ) => {
						first: () => {
							toJSON: () => { id?: number; url?: string };
						};
					};
				};
			};
		};
	}
}
