import apiFetch from '@wordpress/api-fetch';
import { createRoot, render, useState } from '@wordpress/element';
import type { UiMode } from '../shared/types';
import { EditorPanel } from '../editor/components/EditorPanel';
import {
	fieldsFromRecord,
	type SeoFields,
	type TemplateVariable,
} from '../editor/fields';
import '../editor/editor.css';
import '../shared/tokens.css';

interface ClassicConfig {
	uiMode?: UiMode;
	siteName?: string;
	siteUrl?: string;
	separator?: string;
	tagline?: string;
	variables?: TemplateVariable[];
	postId?: number;
	postType?: string;
	postTitle?: string;
	permalink?: string;
	fields?: Record< string, unknown >;
	askAiEvent?: string;
}

const FORM_IDS: Record< string, keyof SeoFields | 'robots' | 'keywords' > = {
	rankmyseo_title: 'title',
	rankmyseo_description: 'description',
	rankmyseo_canonical: 'canonical',
	rankmyseo_focus_keyword: 'focusKeyword',
	rankmyseo_og_title: 'ogTitle',
	rankmyseo_og_description: 'ogDescription',
	rankmyseo_og_image: 'ogImage',
	rankmyseo_twitter_title: 'twitterTitle',
	rankmyseo_twitter_description: 'twitterDescription',
	rankmyseo_twitter_image: 'twitterImage',
	rankmyseo_twitter_card: 'twitterCard',
};

function writeHidden( fields: SeoFields ): void {
	Object.entries( FORM_IDS ).forEach( ( [ id, key ] ) => {
		const input = document.getElementById( id );
		if (
			input instanceof HTMLInputElement &&
			key !== 'robots' &&
			key !== 'keywords'
		) {
			input.value = String( fields[ key ] );
		}
	} );

	const keywords = document.getElementById( 'rankmyseo_secondary_keywords' );
	if ( keywords instanceof HTMLInputElement ) {
		keywords.value = JSON.stringify( fields.secondaryKeywords );
	}
	const robots = document.getElementById( 'rankmyseo_robots' );
	if ( robots instanceof HTMLInputElement ) {
		robots.value = JSON.stringify( fields.robots );
	}
}

function ClassicApp( { settings }: { settings: ClassicConfig } ): JSX.Element {
	const [ mode, setMode ] = useState< UiMode >(
		settings.uiMode === 'advanced' ? 'advanced' : 'simple'
	);
	const [ fields, setFields ] = useState< SeoFields >( () =>
		fieldsFromRecord( settings.fields ?? {} )
	);

	return (
		<EditorPanel
			mode={ mode }
			onModeChange={ ( next ) => {
				setMode( next );
				void apiFetch( {
					path: '/rankmyseo/v1/ui-preferences',
					method: 'POST',
					data: { ui_mode: next },
				} );
			} }
			fields={ fields }
			onChange={ ( next ) => {
				setFields( next );
				writeHidden( next );
			} }
			siteName={ settings.siteName || '' }
			siteUrl={ settings.siteUrl || '' }
			separator={ settings.separator || '|' }
			permalink={ settings.permalink || '' }
			postTitle={ settings.postTitle || '' }
			tagline={ settings.tagline || '' }
			variables={ settings.variables || [] }
			onAskAi={ () => {
				document.dispatchEvent(
					new CustomEvent(
						settings.askAiEvent || 'rankmyseo:ask-ai',
						{
							detail: {
								wpPostId: settings.postId || 0,
								postType: settings.postType || 'post',
								title: settings.postTitle || '',
								seoTitle: fields.title,
								seoDescription: fields.description,
								focusKeyword: fields.focusKeyword,
								permalink: settings.permalink || '',
							},
						}
					)
				);
			} }
		/>
	);
}

const mountNode = document.getElementById( 'rankmyseo-classic-root' );
const classicConfig = window.rankmyseoClassic;

if ( mountNode && classicConfig ) {
	const app = <ClassicApp settings={ classicConfig } />;
	if ( typeof createRoot === 'function' ) {
		createRoot( mountNode ).render( app );
	} else {
		render( app, mountNode );
	}
}
