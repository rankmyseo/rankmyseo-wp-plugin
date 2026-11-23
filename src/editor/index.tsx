import apiFetch from '@wordpress/api-fetch';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import {
	createElement,
	useCallback,
	useRef,
	useState,
	type ComponentType,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import type { ReactNode } from 'react';
import { summaryPayload, type AnalysisResult } from '../analyzer';
import type { UiMode } from '../shared/types';
import { EditorPanel } from './components/EditorPanel';
import {
	emptyFields,
	fieldsFromRecord,
	metaPatchFromFields,
	type SeoFields,
	type TemplateVariable,
} from './fields';
import './editor.css';
import '../shared/tokens.css';

interface EditorConfig {
	uiMode?: UiMode;
	siteName?: string;
	siteUrl?: string;
	separator?: string;
	tagline?: string;
	variables?: TemplateVariable[];
	askAiEvent?: string;
	locale?: string;
}

interface EditorStores {
	getCurrentPostType?: () => string;
	getCurrentPostId?: () => number;
	getEditedPostAttribute?: ( key: string ) => unknown;
	getEditedPostContent?: () => string;
	getPermalink?: () => string;
}

function config(): EditorConfig {
	return window.rankmyseoEditor ?? {};
}

function useEditorState(): {
	postType: string;
	postId: number;
	postTitle: string;
	postExcerpt: string;
	permalink: string;
	content: string;
	slug: string;
} {
	return useSelect( ( select ) => {
		const store = select( 'core/editor' ) as EditorStores;
		return {
			postType: store?.getCurrentPostType?.() || 'post',
			postId: store?.getCurrentPostId?.() || 0,
			postTitle: String(
				store?.getEditedPostAttribute?.( 'title' ) ?? ''
			),
			postExcerpt: String(
				store?.getEditedPostAttribute?.( 'excerpt' ) ?? ''
			),
			permalink: String( store?.getPermalink?.() ?? '' ),
			content: String( store?.getEditedPostContent?.() ?? '' ),
			slug: String( store?.getEditedPostAttribute?.( 'slug' ) ?? '' ),
		};
	}, [] );
}

function EditorBody(): JSX.Element {
	const editor = useEditorState();
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		editor.postType,
		'meta'
	) as [
		Record< string, unknown > | undefined,
		( next: Record< string, unknown > ) => void,
		unknown,
	];
	const settings = config();
	const [ mode, setMode ] = useState< UiMode >(
		settings.uiMode === 'advanced' ? 'advanced' : 'simple'
	);
	const fields = fieldsFromRecord( meta ?? {} );
	const metaRef = useRef( meta );
	metaRef.current = meta;
	const initialSignature = useRef< string | null >( null );
	const persistAnalysis = useCallback(
		( result: AnalysisResult, signature: string ) => {
			if ( initialSignature.current === null ) {
				initialSignature.current = signature;
				return;
			}
			if ( signature === initialSignature.current ) {
				return;
			}
			const summary = JSON.stringify( summaryPayload( result ) );
			const current = metaRef.current ?? {};
			if (
				current._rankmyseo_seo_score === result.score &&
				current._rankmyseo_seo_summary === summary
			) {
				return;
			}
			setMeta( {
				...current,
				_rankmyseo_seo_score: result.score,
				_rankmyseo_seo_summary: summary,
			} );
		},
		[ setMeta ]
	);

	const ask = (): void => {
		const eventName = settings.askAiEvent || 'rankmyseo:ask-ai';
		document.dispatchEvent(
			new CustomEvent( eventName, {
				detail: {
					wpPostId: editor.postId,
					postType: editor.postType,
					title: editor.postTitle,
					seoTitle: fields.title,
					seoDescription: fields.description,
					focusKeyword: fields.focusKeyword,
					permalink: editor.permalink,
				},
			} )
		);
	};

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
			onChange={ ( next: SeoFields ) => {
				setMeta( {
					...( meta ?? {} ),
					...metaPatchFromFields( next ),
				} );
			} }
			siteName={ settings.siteName || '' }
			siteUrl={ settings.siteUrl || '' }
			separator={ settings.separator || '|' }
			permalink={ editor.permalink }
			postTitle={ editor.postTitle }
			postExcerpt={ editor.postExcerpt }
			tagline={ settings.tagline || '' }
			variables={ settings.variables || [] }
			onAskAi={ ask }
			contentHtml={ editor.content }
			slug={ editor.slug }
			postId={ editor.postId }
			postType={ editor.postType }
			locale={ settings.locale || 'en' }
			onAnalysis={ persistAnalysis }
		/>
	);
}

function DocumentSummary(): JSX.Element {
	const editor = useEditorState();
	const [ meta ] = useEntityProp( 'postType', editor.postType, 'meta' ) as [
		Record< string, unknown > | undefined,
		( next: Record< string, unknown > ) => void,
		unknown,
	];
	const fields = meta ? fieldsFromRecord( meta ) : emptyFields();

	return (
		<p className="rms-doc-summary">
			{ fields.title ||
				editor.postTitle ||
				__( 'Search preview', 'rankmyseo' ) }
		</p>
	);
}

type SlotProps = {
	name?: string;
	title?: string;
	icon?: string;
	target?: string;
	children?: ReactNode;
};

function slot(
	source: Record< string, unknown > | undefined,
	key: string
): ComponentType< SlotProps > | null {
	const value = source?.[ key ];
	return typeof value === 'function'
		? ( value as ComponentType< SlotProps > )
		: null;
}

function RankMySeoPlugin(): JSX.Element {
	const wpWindow = window.wp;
	const source = {
		...( wpWindow?.editPost ?? {} ),
		...( wpWindow?.editor ?? {} ),
	};
	const Sidebar = slot( source, 'PluginSidebar' );
	const More = slot( source, 'PluginSidebarMoreMenuItem' );
	const Panel = slot( source, 'PluginDocumentSettingPanel' );
	const body = createElement( EditorBody );
	const summary = createElement( DocumentSummary );

	return (
		<>
			{ More
				? createElement(
						More,
						{ target: 'rankmyseo-sidebar' },
						__( 'RankMySEO', 'rankmyseo' )
				  )
				: null }
			{ Sidebar
				? createElement(
						Sidebar,
						{
							name: 'rankmyseo-sidebar',
							title: __( 'RankMySEO', 'rankmyseo' ),
							icon: 'search',
						},
						body
				  )
				: body }
			{ Panel
				? createElement(
						Panel,
						{
							name: 'rankmyseo-document',
							title: __( 'Search preview', 'rankmyseo' ),
						},
						summary
				  )
				: null }
		</>
	);
}

registerPlugin( 'rankmyseo-meta', {
	render: RankMySeoPlugin,
} );
