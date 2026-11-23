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

export interface ChatMessage {
	id: string;
	role: 'user' | 'assistant';
	content: string;
	createdAt: string;
	quickLinks?: ChatQuickLink[];
	actionCards?: ChatActionCardData[];
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
		};
		rankmyseoClassic?: Window[ 'rankmyseoEditor' ] & {
			postId?: number;
			postType?: string;
			postTitle?: string;
			permalink?: string;
			fields?: Record< string, unknown >;
		};
		wp?: {
			editor?: Record< string, unknown >;
			editPost?: Record< string, unknown >;
			media?: ( options: Record< string, unknown > ) => {
				on: ( event: string, callback: () => void ) => void;
				open: () => void;
				state: () => {
					get: ( key: string ) => {
						first: () => { toJSON: () => { url?: string } };
					};
				};
			};
		};
	}
}
