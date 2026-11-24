import apiFetch from '@wordpress/api-fetch';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import {
	createElement,
	useCallback,
	useEffect,
	useRef,
	useState,
	type ComponentType,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import type { ReactNode } from 'react';
import {
	summaryPayload,
	type AnalysisResult,
	type ProductSignals,
} from '../analyzer';
import { registerRankMySEOBlocks } from '../blocks/register';
import type { UiMode } from '../shared/types';
import type { EditorInsight } from './insights';
import { EditorPanel } from './components/EditorPanel';
import { mergeProductSignals } from './productSignals';
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
	product?: ProductSignals;
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
	const product = useSelect(
		( select ) => {
			if ( editor.postType !== 'product' ) {
				return undefined;
			}
			const galleryRaw =
				meta &&
				Object.prototype.hasOwnProperty.call(
					meta,
					'_product_image_gallery'
				)
					? String( meta._product_image_gallery ?? '' )
					: null;
			let galleryAlts: string[] | undefined;
			if ( galleryRaw && galleryRaw.trim() !== '' ) {
				const core = select( 'core' ) as {
					getEntityRecord?: (
						kind: string,
						name: string,
						id: number
					) => { alt_text?: string } | undefined;
				};
				const ids = galleryRaw
					.split( ',' )
					.map( ( id ) => Number( id.trim() ) )
					.filter( ( id ) => id > 0 );
				const records = ids.map(
					( id ) =>
						core?.getEntityRecord?.( 'postType', 'attachment', id )
				);
				if (
					records.length > 0 &&
					records.every( ( record ) => record )
				) {
					galleryAlts = records.map( ( record ) =>
						String( record?.alt_text ?? '' )
					);
				}
			}
			return mergeProductSignals( {
				postType: editor.postType,
				meta,
				initial: settings.product,
				excerpt: editor.postExcerpt,
				excerptFromEditor: true,
				galleryAlts,
			} );
		},
		[ editor.postType, editor.postExcerpt, meta, settings.product ]
	);
	const metaRef = useRef( meta );
	metaRef.current = meta;
	const initialSignature = useRef< string | null >( null );
	const [ insight, setInsight ] = useState< EditorInsight | null >( null );
	useEffect( () => {
		if ( ! editor.postId ) {
			setInsight( null );
			return undefined;
		}
		let active = true;
		void apiFetch( {
			path: `/rankmyseo/v1/editor/insights?post_id=${ editor.postId }`,
		} )
			.then( ( result ) => {
				if ( active ) {
					setInsight( result as EditorInsight );
				}
			} )
			.catch( () => {
				if ( active ) {
					setInsight( null );
				}
			} );
		return () => {
			active = false;
		};
	}, [ editor.postId ] );
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

	const insertLink = ( html: string ): void => {
		const wp = window as Window & {
			wp?: {
				blocks?: {
					createBlock?: (
						name: string,
						attrs: { content: string }
					) => unknown;
				};
				data?: {
					dispatch?: ( store: string ) => {
						insertBlocks?: ( blocks: unknown ) => void;
					};
				};
			};
		};
		const block = wp.wp?.blocks?.createBlock?.( 'core/paragraph', {
			content: html,
		} );
		wp.wp?.data?.dispatch?.( 'core/block-editor' )?.insertBlocks?.( block );
	};

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
			product={ product }
			onAnalysis={ persistAnalysis }
			onInsertLink={ insertLink }
			insight={ insight }
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

registerRankMySEOBlocks();

registerPlugin( 'rankmyseo-meta', {
	render: RankMySeoPlugin,
} );
