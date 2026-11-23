import { inspectContent, skippedHeadingLevels } from './content';
import type {
	AnalysisResult,
	AnalyzerInput,
	CheckCategory,
	CheckMetrics,
	CheckResult,
	CheckStatus,
	ScoreBand,
} from './types';
import {
	containsPhrase,
	countPhrase,
	estimatePixelWidth,
	isEnglishLocale,
	keywordNearStart,
	roundDensity,
	normalizeWords,
	slugContains,
	wordTargetFor,
} from './text';

const TITLE_FONT = 20;
const DESCRIPTION_FONT = 14;
const TITLE_PIXEL_MAX = 580;
const DESCRIPTION_PIXEL_MAX = 920;

function statusFor(
	score: number,
	maxScore: number,
	emptyStatus: CheckStatus
): CheckStatus {
	if ( maxScore > 0 && score >= maxScore ) {
		return 'pass';
	}
	if ( score <= 0 ) {
		return emptyStatus;
	}
	return 'improve';
}

function check(
	id: string,
	category: CheckCategory,
	score: number,
	maxScore: number,
	emptyStatus: CheckStatus,
	metrics: CheckMetrics
): CheckResult {
	return {
		id,
		category,
		status: statusFor( score, maxScore, emptyStatus ),
		score,
		maxScore,
		metrics,
	};
}

export function scoreBand( score: number, hasKeyword: boolean ): ScoreBand {
	if ( ! hasKeyword ) {
		return 'no_keyword';
	}
	if ( score >= 80 ) {
		return 'good';
	}
	if ( score >= 50 ) {
		return 'needs_work';
	}
	return 'poor';
}

function titlePoints( title: string ): { score: number; pixels: number } {
	const length = title.length;
	const pixels = estimatePixelWidth( title, TITLE_FONT );
	if ( length >= 30 && length <= 60 && pixels < TITLE_PIXEL_MAX ) {
		return { score: 12, pixels };
	}
	if ( length >= 20 && length <= 70 && pixels < 700 ) {
		return { score: 6, pixels };
	}
	return { score: 0, pixels };
}

function descriptionPoints( description: string ): {
	score: number;
	pixels: number;
} {
	const length = description.length;
	const pixels = estimatePixelWidth( description, DESCRIPTION_FONT );
	if ( length >= 120 && length <= 155 && pixels < DESCRIPTION_PIXEL_MAX ) {
		return { score: 10, pixels };
	}
	if ( length >= 70 && length <= 170 && pixels < 1100 ) {
		return { score: 5, pixels };
	}
	return { score: 0, pixels };
}

function flesch(
	wordCount: number,
	sentenceCount: number,
	syllables: number
): { ease: number | null; grade: number | null } {
	if ( wordCount === 0 || sentenceCount === 0 ) {
		return { ease: null, grade: null };
	}
	const wordsPerSentence = wordCount / sentenceCount;
	const syllablesPerWord = syllables / wordCount;
	const ease = 206.835 - 1.015 * wordsPerSentence - 84.6 * syllablesPerWord;
	const grade = 0.39 * wordsPerSentence + 11.8 * syllablesPerWord - 15.59;
	return {
		ease: Math.round( ease * 10 ) / 10,
		grade: Math.round( grade * 10 ) / 10,
	};
}

export function analyze( input: AnalyzerInput ): AnalysisResult {
	const keyword = input.focusKeyword.trim();
	const hasKeyword = keyword !== '';
	const title = input.seoTitle.trim();
	const description = input.metaDescription.trim();
	const facts = inspectContent(
		input.contentHtml || '',
		input.siteUrl || ''
	);
	const wordCount = facts.words.length;
	const sentenceCount = facts.sentences.length;
	const paragraphCount = facts.paragraphs.length;
	const keywordCount = hasKeyword ? countPhrase( facts.plain, keyword ) : 0;
	const keywordWords = hasKeyword
		? normalizeWords( keyword ).split( ' ' ).filter( Boolean ).length
		: 0;
	const density =
		hasKeyword && wordCount > 0
			? roundDensity(
					( ( keywordCount * Math.max( keywordWords, 1 ) ) /
						wordCount ) *
						100
			  )
			: 0;
	const headingLevels = facts.headings.map( ( heading ) => heading.level );
	const h1Count = headingLevels.filter( ( level ) => level === 1 ).length;
	const subheadCount = headingLevels.filter( ( level ) => level >= 2 ).length;
	const hasSubhead = headingLevels.some(
		( level ) => level === 2 || level === 3
	);
	const skipped = skippedHeadingLevels( headingLevels );
	const imagesWithAlt = facts.images.filter(
		( image ) => image.alt !== ''
	).length;
	const keywordInAlt =
		hasKeyword &&
		facts.images.some( ( image ) => containsPhrase( image.alt, keyword ) );
	const intro = facts.words.slice( 0, 100 ).join( ' ' );
	const headingText = facts.headings
		.filter( ( heading ) => heading.level <= 3 )
		.map( ( heading ) => heading.text )
		.join( ' ' );
	const english = isEnglishLocale( input.locale );
	const reading = flesch( wordCount, sentenceCount, facts.syllables );
	const target = wordTargetFor( input.postType );
	const averageSentence =
		sentenceCount > 0
			? Math.round( ( wordCount / sentenceCount ) * 10 ) / 10
			: 0;
	const paragraphWords = facts.paragraphs.reduce(
		( total, paragraph ) =>
			total + paragraph.split( /\s+/ ).filter( Boolean ).length,
		0
	);
	const averageParagraph =
		paragraphCount > 0
			? Math.round( ( paragraphWords / paragraphCount ) * 10 ) / 10
			: 0;
	const longParagraphs = facts.paragraphs.filter(
		( paragraph ) => paragraph.split( /\s+/ ).filter( Boolean ).length > 150
	).length;

	let titleScore = 0;
	if ( hasKeyword && containsPhrase( title, keyword ) ) {
		titleScore = keywordNearStart( title, keyword ) ? 6 : 4;
	}
	let densityScore = 0;
	let densityEmpty: CheckStatus = 'improve';
	if ( hasKeyword && density > 2.5 ) {
		densityScore = 0;
		densityEmpty = 'critical';
	} else if ( hasKeyword && density >= 0.5 ) {
		densityScore = 3;
	} else if ( hasKeyword && density > 0 ) {
		densityScore = 1;
	}

	const titleLength = titlePoints( title );
	const descriptionLength = descriptionPoints( description );
	let wordScore = 0;
	if ( wordCount >= target ) {
		wordScore = 8;
	} else if ( wordCount >= Math.ceil( target / 2 ) ) {
		wordScore = 4;
	}

	let headingScore = 0;
	if ( h1Count === 1 ) {
		headingScore += 3;
	}
	if ( hasSubhead ) {
		headingScore += 2;
	}
	if ( ! skipped ) {
		headingScore += 2;
	}

	let imageScore = 0;
	if ( facts.images.length > 0 && imagesWithAlt === facts.images.length ) {
		imageScore = 5;
	} else if ( imagesWithAlt > 0 ) {
		imageScore = 2;
	}

	const neededSubheads = wordCount < 300 ? 0 : Math.ceil( wordCount / 300 );
	let subheadScore = 0;
	if ( neededSubheads === 0 || subheadCount >= neededSubheads ) {
		subheadScore = 3;
	} else if ( subheadCount > 0 ) {
		subheadScore = 1;
	}

	let paragraphScore = 0;
	if ( paragraphCount > 0 && longParagraphs === 0 ) {
		paragraphScore = 5;
	} else if ( paragraphCount > longParagraphs ) {
		paragraphScore = 2;
	}

	let sentenceScore = 0;
	if ( sentenceCount > 0 && averageSentence <= 20 ) {
		sentenceScore = 4;
	} else if ( sentenceCount > 0 && averageSentence <= 25 ) {
		sentenceScore = 2;
	}

	let readingScore = 0;
	if ( ! english ) {
		readingScore = 3;
	} else if ( reading.ease !== null && reading.ease >= 60 ) {
		readingScore = 3;
	} else if ( reading.ease !== null && reading.ease >= 30 ) {
		readingScore = 2;
	}

	const checks: CheckResult[] = [
		check( 'keyword_set', 'keyword', hasKeyword ? 4 : 0, 4, 'critical', {
			present: hasKeyword,
		} ),
		check(
			'keyword_in_title',
			'keyword',
			titleScore,
			6,
			hasKeyword ? 'critical' : 'improve',
			{ present: titleScore > 0, nearStart: titleScore === 6 }
		),
		check(
			'keyword_in_description',
			'keyword',
			hasKeyword && containsPhrase( description, keyword ) ? 5 : 0,
			5,
			'improve',
			{
				present: hasKeyword && containsPhrase( description, keyword ),
			}
		),
		check(
			'keyword_in_slug',
			'keyword',
			hasKeyword && slugContains( input.slug, keyword ) ? 5 : 0,
			5,
			'improve',
			{
				present: hasKeyword && slugContains( input.slug, keyword ),
				slug: input.slug,
			}
		),
		check(
			'keyword_in_intro',
			'keyword',
			hasKeyword && containsPhrase( intro, keyword ) ? 5 : 0,
			5,
			'improve',
			{ present: hasKeyword && containsPhrase( intro, keyword ) }
		),
		check(
			'keyword_in_headings',
			'keyword',
			hasKeyword && containsPhrase( headingText, keyword ) ? 5 : 0,
			5,
			'improve',
			{
				present: hasKeyword && containsPhrase( headingText, keyword ),
			}
		),
		check(
			'keyword_in_alt',
			'keyword',
			keywordInAlt ? 2 : 0,
			2,
			'improve',
			{ present: keywordInAlt, images: facts.images.length }
		),
		check( 'keyword_density', 'keyword', densityScore, 3, densityEmpty, {
			density,
			count: keywordCount,
			stuffing: density > 2.5,
		} ),
		check(
			'title_length',
			'snippet',
			titleLength.score,
			12,
			title.length === 0 || title.length < 15 ? 'critical' : 'improve',
			{
				characters: title.length,
				pixels: titleLength.pixels,
				min: 30,
				max: 60,
			}
		),
		check(
			'description_length',
			'snippet',
			descriptionLength.score,
			10,
			description.length === 0 || description.length < 40
				? 'critical'
				: 'improve',
			{
				characters: description.length,
				pixels: descriptionLength.pixels,
				min: 120,
				max: 155,
			}
		),
		check( 'indexable', 'snippet', input.noindex ? 0 : 3, 3, 'critical', {
			noindex: Boolean( input.noindex ),
		} ),
		check(
			'word_count',
			'content',
			wordScore,
			8,
			wordCount < Math.ceil( target / 2 ) ? 'critical' : 'improve',
			{ words: wordCount, target }
		),
		check(
			'heading_hierarchy',
			'content',
			headingScore,
			7,
			headingScore === 0 ? 'critical' : 'improve',
			{
				h1: h1Count,
				subheads: subheadCount,
				skipped,
			}
		),
		check( 'image_alts', 'content', imageScore, 5, 'improve', {
			images: facts.images.length,
			withAlt: imagesWithAlt,
		} ),
		check(
			'internal_links',
			'content',
			facts.internalLinks > 0 ? 3 : 0,
			3,
			'improve',
			{ count: facts.internalLinks }
		),
		check(
			'external_links',
			'content',
			facts.externalLinks > 0 ? 2 : 0,
			2,
			'improve',
			{ count: facts.externalLinks }
		),
		check(
			'paragraph_length',
			'readability',
			paragraphScore,
			5,
			'improve',
			{
				paragraphs: paragraphCount,
				longParagraphs,
				average: averageParagraph,
			}
		),
		check( 'sentence_length', 'readability', sentenceScore, 4, 'improve', {
			sentences: sentenceCount,
			average: averageSentence,
		} ),
		check(
			'subheading_distribution',
			'readability',
			subheadScore,
			3,
			wordCount >= 300 && subheadCount === 0 ? 'critical' : 'improve',
			{ subheads: subheadCount, needed: neededSubheads }
		),
		check( 'readability', 'readability', readingScore, 3, 'improve', {
			english,
			flesch: reading.ease === null ? '' : reading.ease,
			grade: reading.grade === null ? '' : reading.grade,
		} ),
	];

	const score = Math.max(
		0,
		Math.min(
			100,
			checks.reduce( ( total, item ) => total + item.score, 0 )
		)
	);

	return {
		score,
		band: scoreBand( score, hasKeyword ),
		hasFocusKeyword: hasKeyword,
		checks,
		summary: {
			wordCount,
			keywordCount,
			keywordDensity: density,
			fleschReadingEase: english ? reading.ease : null,
			fleschKincaidGrade: english ? reading.grade : null,
			averageSentenceLength: averageSentence,
			averageParagraphLength: averageParagraph,
			internalLinks: facts.internalLinks,
			externalLinks: facts.externalLinks,
			images: facts.images.length,
			imagesWithAlt,
			h1Count,
			headingCount: facts.headings.length,
		},
	};
}

export function summaryPayload(
	result: AnalysisResult
): Record< string, unknown > {
	return {
		score: result.score,
		band: result.band,
		word_count: result.summary.wordCount,
		keyword_density: result.summary.keywordDensity,
		checks: result.checks.map( ( item ) => ( {
			id: item.id,
			status: item.status,
			score: item.score,
			max_score: item.maxScore,
		} ) ),
	};
}
