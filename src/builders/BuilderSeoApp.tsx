import apiFetch from '@wordpress/api-fetch';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { summaryPayload, type AnalysisResult } from '../analyzer';
import type { UiMode } from '../shared/types';
import { EditorPanel } from '../editor/components/EditorPanel';
import {
	fieldsFromRecord,
	type SeoFields,
	type TemplateVariable,
} from '../editor/fields';
import '../editor/editor.css';
import '../shared/tokens.css';
import {
	installBuilderApi,
	loadPostSeo,
	savePostSeo,
	type ScoreSave,
} from './api';
import { emptyDocument, type LiveDocument } from './content';
import './builders.css';

export interface BuilderEditorConfig {
	uiMode?: UiMode;
	siteName?: string;
	siteUrl?: string;
	separator?: string;
	tagline?: string;
	variables?: TemplateVariable[];
	restRoot?: string;
	nonce?: string;
	locale?: string;
	postId?: number;
	postType?: string;
	postTitle?: string;
	permalink?: string;
	fields?: Record< string, unknown >;
}

type SaveState = 'idle' | 'saving' | 'saved' | 'error';

function signature( fields: SeoFields, score: ScoreSave | null ): string {
	return JSON.stringify( { fields, score } );
}

export function BuilderSeoApp( {
	settings,
	readDocument,
}: {
	settings: BuilderEditorConfig;
	readDocument: () => LiveDocument;
} ): JSX.Element {
	const [ mode, setMode ] = useState< UiMode >(
		settings.uiMode === 'advanced' ? 'advanced' : 'simple'
	);
	const [ fields, setFields ] = useState< SeoFields >( () =>
		fieldsFromRecord( settings.fields ?? {} )
	);
	const [ live, setLive ] = useState< LiveDocument >( emptyDocument );
	const [ ready, setReady ] = useState( false );
	const [ saveState, setSaveState ] = useState< SaveState >( 'idle' );
	const [ scoreVersion, setScoreVersion ] = useState( 0 );
	const scoreRef = useRef< ScoreSave | null >( null );
	const baselineRef = useRef( '' );
	const sawAnalysis = useRef( false );

	useEffect( () => {
		installBuilderApi( settings.nonce || '', settings.restRoot || '' );
	}, [ settings.nonce, settings.restRoot ] );

	useEffect( () => {
		let cancelled = false;
		const load = async (): Promise< void > => {
			if ( ! settings.postId ) {
				if ( ! cancelled ) {
					setReady( true );
				}
				return;
			}
			try {
				const next = await loadPostSeo( settings.postId );
				if ( ! cancelled ) {
					setFields( next );
				}
			} catch {
				// Localized fields stay on screen. The next edit tries the save again.
			} finally {
				if ( ! cancelled ) {
					setReady( true );
				}
			}
		};
		void load();
		return () => {
			cancelled = true;
		};
	}, [ settings.postId ] );

	useEffect( () => {
		const pull = (): void => {
			let next = emptyDocument();
			try {
				next = readDocument();
			} catch {
				next = emptyDocument();
			}
			setLive( ( current ) =>
				current.html === next.html &&
				current.title === next.title &&
				current.slug === next.slug &&
				current.postId === next.postId
					? current
					: next
			);
		};
		pull();
		const timer = window.setInterval( pull, 800 );
		return () => window.clearInterval( timer );
	}, [ readDocument ] );

	useEffect( () => {
		if ( ! ready || ! settings.postId ) {
			return undefined;
		}
		const currentScore = scoreVersion > 0 ? scoreRef.current : null;
		const nextSignature = signature( fields, currentScore );
		if ( '' === baselineRef.current ) {
			baselineRef.current = nextSignature;
			return undefined;
		}
		if ( nextSignature === baselineRef.current ) {
			return undefined;
		}
		const timer = window.setTimeout( () => {
			setSaveState( 'saving' );
			void savePostSeo( settings.postId || 0, fields, currentScore )
				.then( () => {
					baselineRef.current = nextSignature;
					setSaveState( 'saved' );
				} )
				.catch( () => {
					setSaveState( 'error' );
				} );
		}, 400 );
		return () => window.clearTimeout( timer );
	}, [ fields, ready, scoreVersion, settings.postId ] );

	const onAnalysis = ( result: AnalysisResult ): void => {
		const next = {
			score: result.score,
			summary: JSON.stringify( summaryPayload( result ) ),
		};
		if ( ! sawAnalysis.current ) {
			sawAnalysis.current = true;
			scoreRef.current = next;
			return;
		}
		if (
			scoreRef.current?.score === next.score &&
			scoreRef.current?.summary === next.summary
		) {
			return;
		}
		scoreRef.current = next;
		setScoreVersion( ( version ) => version + 1 );
	};

	if ( ! settings.postId ) {
		return (
			<p className="rms-editor-note" role="status">
				{ __(
					'Open a page to edit its search settings.',
					'rankmyseo'
				) }
			</p>
		);
	}

	return (
		<div className="rankmyseo-builder-app">
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
				onChange={ setFields }
				siteName={ settings.siteName || '' }
				siteUrl={ settings.siteUrl || '' }
				separator={ settings.separator || '|' }
				permalink={ settings.permalink || '' }
				postTitle={ live.title || settings.postTitle || '' }
				tagline={ settings.tagline || '' }
				variables={ settings.variables || [] }
				onAskAi={ () => undefined }
				contentHtml={ live.html }
				slug={ live.slug }
				postId={ settings.postId }
				postType={ settings.postType || 'post' }
				locale={ settings.locale || 'en' }
				onAnalysis={ onAnalysis }
			/>
			<p className="rankmyseo-save-status" role="status">
				{ 'saving' === saveState &&
					__( 'Saving search settings…', 'rankmyseo' ) }
				{ 'saved' === saveState &&
					__( 'Search settings saved.', 'rankmyseo' ) }
				{ 'error' === saveState &&
					__(
						'Could not save search settings. Try again.',
						'rankmyseo'
					) }
			</p>
		</div>
	);
}
