import apiFetch from '@wordpress/api-fetch';
import { createRoot, render, useEffect, useState } from '@wordpress/element';
import type { ProductSignals } from '../analyzer';
import type { UiMode } from '../shared/types';
import { mergeProductSignals } from '../editor/productSignals';
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
	locale?: string;
	product?: ProductSignals;
	schemaDefaults?: Record< string, string >;
	schemaSettingsUrl?: string;
	productSchemaEnabled?: boolean;
}

interface TinyEditor {
	getContent: () => string;
	insertContent: ( html: string ) => void;
	on: ( event: string, callback: () => void ) => void;
	off: ( event: string, callback: () => void ) => void;
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
	rankmyseo_schema_type: 'schemaType',
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
	const cornerstone = document.getElementById( 'rankmyseo_cornerstone' );
	if ( cornerstone instanceof HTMLInputElement ) {
		cornerstone.value = fields.cornerstone ? '1' : '0';
	}
}

function insertClassicLink( html: string ): void {
	const tiny = (
		window as Window & {
			tinymce?: { get?: ( id: string ) => TinyEditor | null };
		}
	 ).tinymce?.get?.( 'content' );
	if ( tiny ) {
		tiny.insertContent( html );
		return;
	}
	const contentField = document.getElementById( 'content' );
	if ( contentField instanceof HTMLTextAreaElement ) {
		contentField.value = `${ contentField.value }\n${ html }`;
	}
}

function readClassicDocument( fallbackTitle: string ): {
	content: string;
	slug: string;
	title: string;
} {
	const contentField = document.getElementById( 'content' );
	const titleField = document.getElementById( 'title' );
	const slugField = document.getElementById( 'post_name' );
	const tiny = (
		window as Window & {
			tinymce?: { get?: ( id: string ) => TinyEditor | null };
		}
	 ).tinymce?.get?.( 'content' );
	const content =
		tiny?.getContent() ||
		( contentField instanceof HTMLTextAreaElement
			? contentField.value
			: '' );
	return {
		content,
		slug: slugField instanceof HTMLInputElement ? slugField.value : '',
		title:
			titleField instanceof HTMLInputElement && titleField.value
				? titleField.value
				: fallbackTitle,
	};
}

function ClassicApp( { settings }: { settings: ClassicConfig } ): JSX.Element {
	const [ mode, setMode ] = useState< UiMode >(
		settings.uiMode === 'advanced' ? 'advanced' : 'simple'
	);
	const [ fields, setFields ] = useState< SeoFields >( () =>
		fieldsFromRecord( settings.fields ?? {} )
	);
	const [ documentState, setDocumentState ] = useState( () =>
		readClassicDocument( settings.postTitle || '' )
	);
	const [ product, setProduct ] = useState< ProductSignals | undefined >(
		() =>
			mergeProductSignals( {
				postType: settings.postType || 'post',
				initial: settings.product,
			} )
	);

	useEffect( () => {
		const update = (): void => {
			setDocumentState( readClassicDocument( settings.postTitle || '' ) );
			const skuField = document.getElementById( '_sku' );
			const gtinField = document.getElementById( '_rankmyseo_gtin' );
			const excerptField = document.getElementById( 'excerpt' );
			const liveMeta: Record< string, unknown > = {};
			if ( skuField instanceof HTMLInputElement ) {
				liveMeta._sku = skuField.value;
			}
			if ( gtinField instanceof HTMLInputElement ) {
				liveMeta._rankmyseo_gtin = gtinField.value;
			}
			setProduct(
				mergeProductSignals( {
					postType: settings.postType || 'post',
					meta: liveMeta,
					initial: settings.product,
					excerpt:
						excerptField instanceof HTMLTextAreaElement
							? excerptField.value
							: undefined,
					excerptFromEditor:
						excerptField instanceof HTMLTextAreaElement,
				} )
			);
		};
		update();
		const contentField = document.getElementById( 'content' );
		const titleField = document.getElementById( 'title' );
		const slugField = document.getElementById( 'post_name' );
		const skuField = document.getElementById( '_sku' );
		const gtinField = document.getElementById( '_rankmyseo_gtin' );
		const excerptField = document.getElementById( 'excerpt' );
		contentField?.addEventListener( 'input', update );
		titleField?.addEventListener( 'input', update );
		slugField?.addEventListener( 'input', update );
		skuField?.addEventListener( 'input', update );
		gtinField?.addEventListener( 'input', update );
		excerptField?.addEventListener( 'input', update );
		const tiny = (
			window as Window & {
				tinymce?: { get?: ( id: string ) => TinyEditor | null };
			}
		 ).tinymce?.get?.( 'content' );
		tiny?.on( 'keyup change input', update );
		return () => {
			contentField?.removeEventListener( 'input', update );
			titleField?.removeEventListener( 'input', update );
			slugField?.removeEventListener( 'input', update );
			skuField?.removeEventListener( 'input', update );
			gtinField?.removeEventListener( 'input', update );
			excerptField?.removeEventListener( 'input', update );
			tiny?.off( 'keyup change input', update );
		};
	}, [ settings.postTitle, settings.postType, settings.product ] );

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
			postTitle={ documentState.title || settings.postTitle || '' }
			tagline={ settings.tagline || '' }
			variables={ settings.variables || [] }
			contentHtml={ documentState.content }
			slug={ documentState.slug }
			postId={ settings.postId || 0 }
			postType={ settings.postType || 'post' }
			locale={ settings.locale || 'en' }
			schemaDefaults={ settings.schemaDefaults }
			schemaSettingsUrl={ settings.schemaSettingsUrl }
			productSchemaEnabled={ settings.productSchemaEnabled }
			onInsertLink={ insertClassicLink }
			product={ product }
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
