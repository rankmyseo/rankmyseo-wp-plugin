import apiFetch from '@wordpress/api-fetch';
import type { SitemapStatus } from './sitemaps';
import type {
	AiAction,
	AiPolicy,
	AuditEntry,
	CloudActionResponse,
	CloudChatTurn,
	CloudInsights,
	CloudPublicState,
	DashboardSummary,
	LocalChatResponse,
	OnboardingCompletePayload,
	OnboardingCompleteResponse,
	OnboardingStateResponse,
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

export function fetchAiActions( filters?: {
	status?: string;
	type?: string;
} ): Promise< { actions: AiAction[] } > {
	const params = new URLSearchParams();
	if ( filters?.status ) {
		params.set( 'status', filters.status );
	}
	if ( filters?.type ) {
		params.set( 'type', filters.type );
	}
	const query = params.toString();
	return apiFetch< { actions: AiAction[] } >( {
		path: query
			? `/rankmyseo/v1/ai-actions?${ query }`
			: '/rankmyseo/v1/ai-actions',
		method: 'GET',
	} );
}

export function syncAiActions(): Promise< {
	imported: number;
	actions: AiAction[];
	message: string;
} > {
	return apiFetch( {
		path: '/rankmyseo/v1/ai-actions/sync',
		method: 'POST',
		data: { generate: true },
	} );
}

export function approveAiAction(
	id: number,
	after?: Record< string, unknown >
): Promise< { message: string; action: AiAction } > {
	return apiFetch( {
		path: `/rankmyseo/v1/ai-actions/${ id }/approve`,
		method: 'POST',
		data: after ? { after } : {},
	} );
}

export function rejectAiAction(
	id: number
): Promise< { message: string; action: AiAction } > {
	return apiFetch( {
		path: `/rankmyseo/v1/ai-actions/${ id }/reject`,
		method: 'POST',
		data: {},
	} );
}

export function rollbackAiAction(
	id: number
): Promise< { message: string; action: AiAction } > {
	return apiFetch( {
		path: `/rankmyseo/v1/ai-actions/${ id }/rollback`,
		method: 'POST',
		data: {},
	} );
}

export function fetchAuditLog(): Promise< { entries: AuditEntry[] } > {
	return apiFetch( {
		path: '/rankmyseo/v1/ai-actions/audit-log',
		method: 'GET',
	} );
}

export function fetchAiPolicy(): Promise< { policy: AiPolicy } > {
	return apiFetch( {
		path: '/rankmyseo/v1/ai-actions/policy',
		method: 'GET',
	} );
}

export function saveAiPolicy(
	policy: AiPolicy
): Promise< { policy: AiPolicy } > {
	return apiFetch( {
		path: '/rankmyseo/v1/ai-actions/policy',
		method: 'POST',
		data: policy,
	} );
}

export function sendCloudChat(
	content: string,
	pageContext?: Record< string, unknown >
): Promise< CloudChatTurn > {
	return apiFetch( {
		path: '/rankmyseo/v1/cloud/chat/send',
		method: 'POST',
		data: pageContext ? { content, pageContext } : { content },
	} );
}

export function fetchCloudChatTurn( turnId: string ): Promise< CloudChatTurn > {
	return apiFetch( {
		path: `/rankmyseo/v1/cloud/chat/turns/${ turnId }`,
		method: 'GET',
	} );
}

export function fetchCloudChatHistory(): Promise< CloudChatTurn > {
	return apiFetch( {
		path: '/rankmyseo/v1/cloud/chat/history',
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

export function fetchOnboardingState(): Promise< OnboardingStateResponse > {
	return apiFetch< OnboardingStateResponse >( {
		path: '/rankmyseo/v1/onboarding/state',
		method: 'GET',
	} );
}

export function completeOnboarding(
	payload: OnboardingCompletePayload
): Promise< OnboardingCompleteResponse > {
	return apiFetch< OnboardingCompleteResponse >( {
		path: '/rankmyseo/v1/onboarding/complete',
		method: 'POST',
		data: payload,
	} );
}

export function fetchDashboardSummary(): Promise< DashboardSummary > {
	return apiFetch< DashboardSummary >( {
		path: '/rankmyseo/v1/dashboard/summary',
		method: 'GET',
	} );
}

export interface LocalChatRequest {
	message: string;
	post_id?: number;
	screen?: string;
}

export function sendLocalChat(
	payload: LocalChatRequest
): Promise< LocalChatResponse > {
	return apiFetch< LocalChatResponse >( {
		path: '/rankmyseo/v1/chat/local',
		method: 'POST',
		data: payload,
	} );
}
