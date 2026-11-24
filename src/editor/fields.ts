export interface RobotsValue {
	noindex: boolean;
	nofollow: boolean;
	noarchive: boolean;
	nosnippet: boolean;
	noimageindex: boolean;
	max_snippet: number;
	max_image_preview: '' | 'none' | 'standard' | 'large';
	max_video_preview: number;
}

export interface SeoFields {
	title: string;
	description: string;
	canonical: string;
	focusKeyword: string;
	secondaryKeywords: string[];
	cornerstone: boolean;
	robots: RobotsValue;
	ogTitle: string;
	ogDescription: string;
	ogImage: string;
	twitterTitle: string;
	twitterDescription: string;
	twitterImage: string;
	twitterCard: 'summary' | 'summary_large_image';
}

export interface TemplateVariable {
	token: string;
	label: string;
	description?: string;
}

export function defaultRobots(): RobotsValue {
	return {
		noindex: false,
		nofollow: false,
		noarchive: false,
		nosnippet: false,
		noimageindex: false,
		max_snippet: -1,
		max_image_preview: '',
		max_video_preview: -1,
	};
}

export function emptyFields(): SeoFields {
	return {
		title: '',
		description: '',
		canonical: '',
		focusKeyword: '',
		secondaryKeywords: [],
		cornerstone: false,
		robots: defaultRobots(),
		ogTitle: '',
		ogDescription: '',
		ogImage: '',
		twitterTitle: '',
		twitterDescription: '',
		twitterImage: '',
		twitterCard: 'summary_large_image',
	};
}

function asString( value: unknown ): string {
	return typeof value === 'string' ? value : '';
}

function asBool( value: unknown ): boolean {
	return value === true || value === 1 || value === '1' || value === 'true';
}

export function robotsFromUnknown( value: unknown ): RobotsValue {
	const robots = defaultRobots();
	if ( ! value || typeof value !== 'object' ) {
		return robots;
	}
	const source = value as Record< string, unknown >;
	robots.noindex = asBool( source.noindex );
	robots.nofollow = asBool( source.nofollow );
	robots.noarchive = asBool( source.noarchive );
	robots.nosnippet = asBool( source.nosnippet );
	robots.noimageindex = asBool( source.noimageindex );
	if ( typeof source.max_snippet === 'number' ) {
		robots.max_snippet = source.max_snippet;
	}
	if (
		source.max_image_preview === 'none' ||
		source.max_image_preview === 'standard' ||
		source.max_image_preview === 'large' ||
		source.max_image_preview === ''
	) {
		robots.max_image_preview = source.max_image_preview;
	}
	if ( typeof source.max_video_preview === 'number' ) {
		robots.max_video_preview = source.max_video_preview;
	}
	return robots;
}

function pick(
	record: Record< string, unknown >,
	name: string,
	metaKey: string
): unknown {
	if ( Object.prototype.hasOwnProperty.call( record, name ) ) {
		return record[ name ];
	}
	return record[ metaKey ];
}

export function fieldsFromRecord(
	record: Record< string, unknown >
): SeoFields {
	const keywords = pick(
		record,
		'secondary_keywords',
		'_rankmyseo_secondary_keywords'
	);
	const list = Array.isArray( keywords )
		? keywords.filter(
				( item ): item is string => typeof item === 'string'
		  )
		: [];
	const card = asString(
		pick( record, 'twitter_card', '_rankmyseo_twitter_card' )
	);

	return {
		title: asString( pick( record, 'title', '_rankmyseo_title' ) ),
		description: asString(
			pick( record, 'description', '_rankmyseo_description' )
		),
		canonical: asString(
			pick( record, 'canonical', '_rankmyseo_canonical' )
		),
		focusKeyword: asString(
			pick( record, 'focus_keyword', '_rankmyseo_focus_keyword' )
		),
		secondaryKeywords: list.slice( 0, 5 ),
		cornerstone: asBool(
			pick( record, 'cornerstone', '_rankmyseo_cornerstone' )
		),
		robots: robotsFromUnknown(
			pick( record, 'robots', '_rankmyseo_robots' )
		),
		ogTitle: asString( pick( record, 'og_title', '_rankmyseo_og_title' ) ),
		ogDescription: asString(
			pick( record, 'og_description', '_rankmyseo_og_description' )
		),
		ogImage: asString( pick( record, 'og_image', '_rankmyseo_og_image' ) ),
		twitterTitle: asString(
			pick( record, 'twitter_title', '_rankmyseo_twitter_title' )
		),
		twitterDescription: asString(
			pick(
				record,
				'twitter_description',
				'_rankmyseo_twitter_description'
			)
		),
		twitterImage: asString(
			pick( record, 'twitter_image', '_rankmyseo_twitter_image' )
		),
		twitterCard: card === 'summary' ? 'summary' : 'summary_large_image',
	};
}

export function metaPatchFromFields(
	fields: SeoFields
): Record< string, unknown > {
	return {
		_rankmyseo_title: fields.title,
		_rankmyseo_description: fields.description,
		_rankmyseo_canonical: fields.canonical,
		_rankmyseo_focus_keyword: fields.focusKeyword,
		_rankmyseo_secondary_keywords: fields.secondaryKeywords,
		_rankmyseo_cornerstone: fields.cornerstone,
		_rankmyseo_robots: fields.robots,
		_rankmyseo_og_title: fields.ogTitle,
		_rankmyseo_og_description: fields.ogDescription,
		_rankmyseo_og_image: fields.ogImage,
		_rankmyseo_twitter_title: fields.twitterTitle,
		_rankmyseo_twitter_description: fields.twitterDescription,
		_rankmyseo_twitter_image: fields.twitterImage,
		_rankmyseo_twitter_card: fields.twitterCard,
	};
}
