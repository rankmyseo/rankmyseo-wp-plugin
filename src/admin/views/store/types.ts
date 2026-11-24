export interface WooSettings {
	variation_offers: boolean;
	product_open_graph: boolean;
	canonicalize_shop_filters: boolean;
	noindex_shop_filters: boolean;
	noindex_out_of_stock: boolean;
	remove_product_base: boolean;
	remove_category_base: boolean;
	legacy_product_base: string;
	legacy_category_base: string;
	return_policy_enabled: boolean;
	return_country: string;
	return_category: string;
	return_days: number;
	return_method: string;
	return_fees: string;
	return_policy_url: string;
	shipping_enabled: boolean;
	shipping_rate: string;
	shipping_currency: string;
	shipping_country: string;
	shipping_handling_min: number;
	shipping_handling_max: number;
	shipping_transit_min: number;
	shipping_transit_max: number;
}

export interface WooSettingsResponse {
	settings: WooSettings;
	woocommerceActive: boolean;
	moduleEnabled: boolean;
	updated: boolean;
}

export function defaultWooSettings(): WooSettings {
	return {
		variation_offers: true,
		product_open_graph: true,
		canonicalize_shop_filters: false,
		noindex_shop_filters: false,
		noindex_out_of_stock: false,
		remove_product_base: false,
		remove_category_base: false,
		legacy_product_base: 'product',
		legacy_category_base: 'product-category',
		return_policy_enabled: false,
		return_country: '',
		return_category: 'MerchantReturnFiniteReturnWindow',
		return_days: 30,
		return_method: 'ReturnByMail',
		return_fees: 'FreeReturn',
		return_policy_url: '',
		shipping_enabled: false,
		shipping_rate: '',
		shipping_currency: '',
		shipping_country: '',
		shipping_handling_min: 0,
		shipping_handling_max: 0,
		shipping_transit_min: 0,
		shipping_transit_max: 0,
	};
}

export function hasCustomAdvancedRules( settings: WooSettings ): boolean {
	return (
		settings.return_method !== 'ReturnByMail' ||
		( settings.return_fees !== 'FreeReturn' &&
			settings.return_fees !== 'ReturnFeesCustomerResponsibility' ) ||
		settings.return_category !== 'MerchantReturnFiniteReturnWindow' ||
		settings.return_policy_url !== '' ||
		settings.shipping_currency !== '' ||
		settings.shipping_handling_min > 0 ||
		settings.shipping_handling_max > 0 ||
		settings.shipping_transit_min > 0 ||
		settings.shipping_transit_max > 0 ||
		settings.legacy_product_base !== 'product' ||
		settings.legacy_category_base !== 'product-category'
	);
}
