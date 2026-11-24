import { TextareaControl, TextControl } from '@wordpress/components';
import { useId, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { AnalysisResult, AnalyzerInput } from '../../analyzer';
import { ModeToggle } from '../../shared/components/ModeToggle';
import type { UiMode } from '../../shared/types';
import { useCannibalization } from '../useCannibalization';
import type { SeoFields, TemplateVariable } from '../fields';
import {
	DESCRIPTION_FONT,
	DESCRIPTION_MAX_CHARS,
	DESCRIPTION_MIN_CHARS,
	DESKTOP_DESCRIPTION_PX,
	DESKTOP_TITLE_PX,
	estimatePixelWidth,
	TITLE_FONT,
	TITLE_MAX_CHARS,
	TITLE_MIN_CHARS,
} from '../metrics';
import { replaceTemplate, type TemplateVars } from '../template';
import type { EditorInsight } from '../insights';
import { AnalyzerPanel, type CannibalizationMatch } from './AnalyzerPanel';
import { linkHtml, LinkSuggestions } from './LinkSuggestions';
import { AskAiButton } from './AskAiButton';
import { CloudInsights } from './CloudInsights';
import { LengthMeter } from './LengthMeter';
import { SerpPreview } from './SerpPreview';
import { SocialPreview } from './SocialPreview';
import { VariablePicker } from './VariablePicker';

export interface EditorPanelProps {
	mode: UiMode;
	onModeChange: ( mode: UiMode ) => void;
	fields: SeoFields;
	onChange: ( fields: SeoFields ) => void;
	siteName: string;
	siteUrl: string;
	separator: string;
	permalink: string;
	postTitle: string;
	postExcerpt?: string;
	tagline?: string;
	variables: TemplateVariable[];
	onAskAi: () => void;
	contentHtml?: string;
	slug?: string;
	postId?: number;
	postType?: string;
	locale?: string;
	cannibalizationMatches?: CannibalizationMatch[];
	onAnalysis?: ( result: AnalysisResult, signature: string ) => void;
	onInsertLink?: ( html: string ) => void;
	insight?: EditorInsight | null;
}

function slugFromPermalink( permalink: string ): string {
	try {
		const parts = new URL( permalink ).pathname
			.split( '/' )
			.filter( Boolean );
		return parts[ parts.length - 1 ] ?? '';
	} catch {
		return '';
	}
}

type AdvancedTab = 'general' | 'social' | 'schema' | 'advanced';

function displayHost( url: string ): string {
	return url.replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
}

export function EditorPanel( {
	mode,
	onModeChange,
	fields,
	onChange,
	siteName,
	siteUrl,
	separator,
	permalink,
	postTitle,
	postExcerpt = '',
	tagline = '',
	variables,
	onAskAi,
	contentHtml = '',
	slug = '',
	postId = 0,
	postType = 'post',
	locale = 'en',
	cannibalizationMatches,
	onAnalysis,
	onInsertLink,
	insight = null,
}: EditorPanelProps ): JSX.Element {
	const [ tab, setTab ] = useState< AdvancedTab >( 'general' );
	const fieldId = useId();
	const vars: TemplateVars = {
		title: postTitle,
		sep: separator,
		sitename: siteName,
		tagline,
		excerpt: postExcerpt,
		page: '',
		currentyear: String( new Date().getFullYear() ),
	};
	const titleTemplate = fields.title || '%title% %sep% %sitename%';
	const descriptionTemplate = fields.description || '%excerpt%';
	const resolvedTitle = replaceTemplate( titleTemplate, vars );
	const resolvedDescription = replaceTemplate( descriptionTemplate, vars );
	const socialTitle = fields.ogTitle || resolvedTitle;
	const socialDescription = fields.ogDescription || resolvedDescription;
	const socialImage = fields.ogImage;
	const urlLabel = displayHost( permalink || siteUrl );

	const patch = ( next: Partial< SeoFields > ): void => {
		onChange( { ...fields, ...next } );
	};
	const analyzerInput = useMemo< AnalyzerInput >(
		() => ( {
			focusKeyword: fields.focusKeyword,
			secondaryKeywords: fields.secondaryKeywords,
			cornerstone: fields.cornerstone,
			seoTitle: resolvedTitle,
			metaDescription: resolvedDescription,
			slug: slug || slugFromPermalink( permalink ),
			contentHtml,
			postType,
			locale,
			noindex: fields.robots.noindex,
			siteUrl,
		} ),
		[
			fields.focusKeyword,
			fields.secondaryKeywords,
			fields.cornerstone,
			fields.robots.noindex,
			resolvedTitle,
			resolvedDescription,
			slug,
			permalink,
			contentHtml,
			postType,
			locale,
			siteUrl,
		]
	);
	const fetchedMatches = useCannibalization(
		fields.focusKeyword,
		postId,
		cannibalizationMatches === undefined
	);
	const matches = cannibalizationMatches ?? fetchedMatches;

	const insertInto = (
		key: 'title' | 'description',
		token: string
	): void => {
		const current = fields[ key ];
		patch( { [ key ]: `${ current }${ current ? ' ' : '' }${ token }` } );
	};

	return (
		<div className="rms-root rms-editor">
			<header className="rms-editor-header">
				<h2>{ __( 'Search preview', 'rankmyseo' ) }</h2>
				<ModeToggle mode={ mode } onChange={ onModeChange } />
			</header>

			{ mode === 'simple' && fields.robots.noindex && (
				<p className="rms-editor-note" role="status">
					{ __(
						'This page is hidden from search. Switch to Advanced to change that.',
						'rankmyseo'
					) }
				</p>
			) }

			<div className="rms-serp-pair">
				<SerpPreview
					viewport="desktop"
					title={ resolvedTitle }
					description={ resolvedDescription }
					url={ urlLabel }
				/>
				<SerpPreview
					viewport="mobile"
					title={ resolvedTitle }
					description={ resolvedDescription }
					url={ urlLabel }
				/>
			</div>

			<TextControl
				label={ __( 'Search title', 'rankmyseo' ) }
				value={ fields.title }
				placeholder={ resolvedTitle }
				onChange={ ( value ) => patch( { title: value } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<LengthMeter
				label={ __( 'Search title length', 'rankmyseo' ) }
				length={ resolvedTitle.length }
				min={ TITLE_MIN_CHARS }
				max={ TITLE_MAX_CHARS }
				pixelWidth={ estimatePixelWidth( resolvedTitle, TITLE_FONT ) }
				pixelMax={ DESKTOP_TITLE_PX }
			/>

			<TextareaControl
				label={ __( 'Search description', 'rankmyseo' ) }
				value={ fields.description }
				placeholder={ resolvedDescription }
				onChange={ ( value ) => patch( { description: value } ) }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			<LengthMeter
				label={ __( 'Search description length', 'rankmyseo' ) }
				length={ resolvedDescription.length }
				min={ DESCRIPTION_MIN_CHARS }
				max={ DESCRIPTION_MAX_CHARS }
				pixelWidth={ estimatePixelWidth(
					resolvedDescription,
					DESCRIPTION_FONT
				) }
				pixelMax={ DESKTOP_DESCRIPTION_PX }
			/>

			<TextControl
				label={ __( 'Focus keyword', 'rankmyseo' ) }
				help={ __(
					'The main phrase you want this page to be found for.',
					'rankmyseo'
				) }
				value={ fields.focusKeyword }
				onChange={ ( value ) => patch( { focusKeyword: value } ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<label className="rms-check" htmlFor={ `${ fieldId }-cornerstone` }>
				<input
					id={ `${ fieldId }-cornerstone` }
					type="checkbox"
					checked={ fields.cornerstone }
					onChange={ ( event ) =>
						patch( { cornerstone: event.target.checked } )
					}
				/>
				<span>{ __( 'This is a cornerstone page', 'rankmyseo' ) }</span>
			</label>
			<p className="rms-muted">
				{ __(
					'Cornerstone pages should be longer and link to at least 3 other pages on this site.',
					'rankmyseo'
				) }
			</p>

			<div
				id="rankmyseo-analyzer-slot"
				data-rankmyseo-analyzer-slot="1"
				className="rms-slot"
			>
				<AnalyzerPanel
					input={ analyzerInput }
					mode={ mode }
					matches={ matches }
					onAnalysis={ onAnalysis }
					aside={
						<CloudInsights
							insight={ insight }
							onAskAi={ onAskAi }
						/>
					}
				/>
			</div>

			<LinkSuggestions
				postId={ postId }
				focusKeyword={ fields.focusKeyword }
				secondaryKeywords={ fields.secondaryKeywords }
				title={ postTitle }
				slug={ slug || slugFromPermalink( permalink ) }
				onInsert={
					onInsertLink
						? ( url, label ) =>
								onInsertLink( linkHtml( url, label ) )
						: undefined
				}
			/>

			{ mode === 'simple' ? (
				<details className="rms-social-fold">
					<summary>{ __( 'Social image', 'rankmyseo' ) }</summary>
					<TextControl
						label={ __( 'Image address', 'rankmyseo' ) }
						value={ fields.ogImage }
						onChange={ ( value ) => patch( { ogImage: value } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<SocialPreview
						network="facebook"
						title={ socialTitle }
						description={ socialDescription }
						image={ socialImage }
						url={ urlLabel }
					/>
				</details>
			) : (
				<div className="rms-editor-tabs">
					<div
						role="tablist"
						aria-label={ __( 'SEO details', 'rankmyseo' ) }
					>
						{ (
							[
								[ 'general', __( 'General', 'rankmyseo' ) ],
								[ 'social', __( 'Social', 'rankmyseo' ) ],
								[ 'schema', __( 'Schema', 'rankmyseo' ) ],
								[ 'advanced', __( 'Advanced', 'rankmyseo' ) ],
							] as Array< [ AdvancedTab, string ] >
						 ).map( ( [ id, label ] ) => (
							<button
								key={ id }
								type="button"
								role="tab"
								aria-selected={ tab === id }
								className={ tab === id ? 'is-active' : '' }
								onClick={ () => setTab( id ) }
							>
								{ label }
							</button>
						) ) }
					</div>

					{ tab === 'general' && (
						<div role="tabpanel">
							<VariablePicker
								variables={ variables }
								onInsert={ ( token ) =>
									insertInto( 'title', token )
								}
							/>
							<label
								className="rms-stack-label"
								htmlFor={ `${ fieldId }-secondary` }
							>
								<span>
									{ __( 'Secondary keywords', 'rankmyseo' ) }
								</span>
								<input
									id={ `${ fieldId }-secondary` }
									type="text"
									value={ fields.secondaryKeywords.join(
										', '
									) }
									onChange={ ( event ) =>
										patch( {
											secondaryKeywords:
												event.target.value
													.split( ',' )
													.map( ( item ) =>
														item.trim()
													)
													.filter( Boolean )
													.slice( 0, 5 ),
										} )
									}
								/>
							</label>
						</div>
					) }

					{ tab === 'social' && (
						<div role="tabpanel">
							<TextControl
								label={ __( 'Facebook title', 'rankmyseo' ) }
								value={ fields.ogTitle }
								onChange={ ( value ) =>
									patch( { ogTitle: value } )
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextareaControl
								label={ __(
									'Facebook description',
									'rankmyseo'
								) }
								value={ fields.ogDescription }
								onChange={ ( value ) =>
									patch( { ogDescription: value } )
								}
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Facebook image', 'rankmyseo' ) }
								value={ fields.ogImage }
								onChange={ ( value ) =>
									patch( { ogImage: value } )
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<SocialPreview
								network="facebook"
								title={ socialTitle }
								description={ socialDescription }
								image={ fields.ogImage }
								url={ urlLabel }
							/>
							<TextControl
								label={ __( 'X title', 'rankmyseo' ) }
								value={ fields.twitterTitle }
								onChange={ ( value ) =>
									patch( { twitterTitle: value } )
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextareaControl
								label={ __( 'X description', 'rankmyseo' ) }
								value={ fields.twitterDescription }
								onChange={ ( value ) =>
									patch( { twitterDescription: value } )
								}
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'X image', 'rankmyseo' ) }
								value={ fields.twitterImage }
								onChange={ ( value ) =>
									patch( { twitterImage: value } )
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<label
								className="rms-stack-label"
								htmlFor={ `${ fieldId }-card` }
							>
								<span>{ __( 'X card', 'rankmyseo' ) }</span>
								<select
									id={ `${ fieldId }-card` }
									value={ fields.twitterCard }
									onChange={ ( event ) =>
										patch( {
											twitterCard:
												event.target.value === 'summary'
													? 'summary'
													: 'summary_large_image',
										} )
									}
								>
									<option value="summary_large_image">
										{ __( 'Large image', 'rankmyseo' ) }
									</option>
									<option value="summary">
										{ __( 'Small image', 'rankmyseo' ) }
									</option>
								</select>
							</label>
							<SocialPreview
								network="x"
								title={ fields.twitterTitle || socialTitle }
								description={
									fields.twitterDescription ||
									socialDescription
								}
								image={ fields.twitterImage || fields.ogImage }
								url={ urlLabel }
							/>
						</div>
					) }

					{ tab === 'schema' && (
						<div role="tabpanel">
							<div
								id="rankmyseo-schema-slot"
								data-rankmyseo-schema-slot="1"
								className="rms-slot"
							>
								<p>
									{ __(
										'Structured data for this page will appear here.',
										'rankmyseo'
									) }
								</p>
							</div>
						</div>
					) }

					{ tab === 'advanced' && (
						<div role="tabpanel">
							<TextControl
								label={ __( 'Canonical URL', 'rankmyseo' ) }
								value={ fields.canonical }
								onChange={ ( value ) =>
									patch( { canonical: value } )
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<label
								className="rms-check"
								htmlFor={ `${ fieldId }-noindex` }
							>
								<input
									id={ `${ fieldId }-noindex` }
									type="checkbox"
									checked={ fields.robots.noindex }
									onChange={ ( event ) =>
										patch( {
											robots: {
												...fields.robots,
												noindex: event.target.checked,
											},
										} )
									}
								/>
								<span>
									{ __( 'Hide from search', 'rankmyseo' ) }
								</span>
							</label>
							<label
								className="rms-check"
								htmlFor={ `${ fieldId }-nofollow` }
							>
								<input
									id={ `${ fieldId }-nofollow` }
									type="checkbox"
									checked={ fields.robots.nofollow }
									onChange={ ( event ) =>
										patch( {
											robots: {
												...fields.robots,
												nofollow: event.target.checked,
											},
										} )
									}
								/>
								<span>
									{ __( 'Do not follow links', 'rankmyseo' ) }
								</span>
							</label>
							<VariablePicker
								variables={ variables }
								onInsert={ ( token ) =>
									insertInto( 'description', token )
								}
							/>
						</div>
					) }
				</div>
			) }

			<AskAiButton onAsk={ onAskAi } />
		</div>
	);
}
