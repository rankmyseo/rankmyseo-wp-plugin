import { __ } from '@wordpress/i18n';

export interface SchemaChoice {
	value: string;
	label: string;
}

export const SCHEMA_SETTINGS_HREF = 'admin.php?page=rankmyseo-schema';

export function schemaChoices(): SchemaChoice[] {
	return [
		{ value: 'Article', label: __( 'Article', 'rankmyseo' ) },
		{ value: 'BlogPosting', label: __( 'Blog post', 'rankmyseo' ) },
		{ value: 'NewsArticle', label: __( 'News article', 'rankmyseo' ) },
		{ value: 'WebPage', label: __( 'Web page', 'rankmyseo' ) },
		{ value: 'AboutPage', label: __( 'About page', 'rankmyseo' ) },
		{ value: 'ContactPage', label: __( 'Contact page', 'rankmyseo' ) },
		{
			value: 'CollectionPage',
			label: __( 'Collection page', 'rankmyseo' ),
		},
		{ value: 'ProfilePage', label: __( 'Profile page', 'rankmyseo' ) },
		{
			value: 'SearchResultsPage',
			label: __( 'Search results page', 'rankmyseo' ),
		},
		{ value: 'Product', label: __( 'Product', 'rankmyseo' ) },
		{ value: 'LocalBusiness', label: __( 'Local business', 'rankmyseo' ) },
		{ value: 'FAQPage', label: __( 'FAQ page', 'rankmyseo' ) },
		{ value: 'HowTo', label: __( 'How-to', 'rankmyseo' ) },
		{ value: 'none', label: __( 'None', 'rankmyseo' ) },
	];
}

export function builtinSchemaType( postType: string ): string {
	if ( postType === 'post' ) {
		return 'BlogPosting';
	}
	if ( postType === 'product' ) {
		return 'Product';
	}
	return 'WebPage';
}

export function effectiveSchemaType(
	override: string,
	postType: string,
	defaults: Record< string, string > = {},
	productSchemaEnabled = true
): string {
	if ( override ) {
		return override;
	}
	const fromSite = defaults[ postType ];
	let type = fromSite || builtinSchemaType( postType );
	if (
		postType === 'product' &&
		type === 'Product' &&
		! productSchemaEnabled
	) {
		type = 'WebPage';
	}
	return type;
}

export function schemaTypeLabel( value: string ): string {
	if ( value === 'none' ) {
		return __( 'None', 'rankmyseo' );
	}
	return (
		schemaChoices().find( ( choice ) => choice.value === value )?.label ??
		value
	);
}
