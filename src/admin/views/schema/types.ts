export type SchemaEntityType = 'organization' | 'person';

export interface OpeningHoursRow {
	day: string;
	opens: string;
	closes: string;
}

export interface SchemaSettings {
	entity_type: SchemaEntityType;
	name: string;
	description: string;
	logo_url: string;
	telephone: string;
	same_as: string[];
	search_action: boolean;
	currency: string;
	post_type_schema: Record< string, string >;
	woocommerce_product: boolean;
	local_business_enabled: boolean;
	local_business_type: string;
	local_business_name: string;
	local_business_telephone: string;
	local_business_price_range: string;
	address_street: string;
	address_city: string;
	address_region: string;
	address_postal_code: string;
	address_country: string;
	geo_latitude: string;
	geo_longitude: string;
	opening_hours: OpeningHoursRow[];
}

export interface SchemaChoice {
	value: string;
	label: string;
}

export interface SchemaPostType {
	name: string;
	label: string;
}

export interface SchemaConflict {
	id: string;
	level: 'info' | 'warning';
	message: string;
}

export interface SchemaGraphDocument {
	'@context': string;
	'@graph': Array< Record< string, unknown > >;
}

export interface SchemaSettingsResponse {
	settings: SchemaSettings;
	postTypes: SchemaPostType[];
	schemaTypes: SchemaChoice[];
	localBusinessTypes: SchemaChoice[];
	woocommerceActive: boolean;
	moduleEnabled: boolean;
	updated?: boolean;
	filters: string[];
}

export interface SchemaPreviewResponse {
	postId: number;
	url: string;
	enabled: boolean;
	graph: SchemaGraphDocument;
	conflicts: SchemaConflict[];
}

export interface SchemaValidateResponse {
	valid: boolean;
	message: string;
	normalized?: Record< string, unknown > | unknown[];
}

export function defaultSchemaSettings(): SchemaSettings {
	return {
		entity_type: 'organization',
		name: '',
		description: '',
		logo_url: '',
		telephone: '',
		same_as: [],
		search_action: true,
		currency: 'USD',
		post_type_schema: {
			post: 'BlogPosting',
			page: 'WebPage',
			product: 'Product',
		},
		woocommerce_product: true,
		local_business_enabled: false,
		local_business_type: 'LocalBusiness',
		local_business_name: '',
		local_business_telephone: '',
		local_business_price_range: '',
		address_street: '',
		address_city: '',
		address_region: '',
		address_postal_code: '',
		address_country: '',
		geo_latitude: '',
		geo_longitude: '',
		opening_hours: [],
	};
}
