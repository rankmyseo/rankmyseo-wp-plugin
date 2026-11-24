export type CheckStatus = 'pass' | 'improve' | 'critical';

export type ScoreBand = 'good' | 'needs_work' | 'poor' | 'no_keyword';

export type CheckCategory = 'keyword' | 'snippet' | 'content' | 'readability';

export interface AnalyzerInput {
	focusKeyword: string;
	secondaryKeywords?: string[];
	seoTitle: string;
	metaDescription: string;
	slug: string;
	contentHtml: string;
	postType?: string;
	locale?: string;
	noindex?: boolean;
	siteUrl?: string;
	cornerstone?: boolean;
}

export interface CheckMetrics {
	[ key: string ]: number | string | boolean;
}

export interface CheckResult {
	id: string;
	category: CheckCategory;
	status: CheckStatus;
	score: number;
	maxScore: number;
	metrics: CheckMetrics;
}

export interface AnalysisSummary {
	wordCount: number;
	keywordCount: number;
	keywordDensity: number;
	fleschReadingEase: number | null;
	fleschKincaidGrade: number | null;
	averageSentenceLength: number;
	averageParagraphLength: number;
	internalLinks: number;
	externalLinks: number;
	images: number;
	imagesWithAlt: number;
	h1Count: number;
	headingCount: number;
}

export interface KeywordAssessment {
	keyword: string;
	role: 'primary' | 'secondary';
	status: CheckStatus;
	score: number;
	checks: CheckResult[];
}

export interface AnalysisResult {
	score: number;
	primaryScore: number;
	band: ScoreBand;
	hasFocusKeyword: boolean;
	checks: CheckResult[];
	keywords: KeywordAssessment[];
	summary: AnalysisSummary;
}

export interface ContentFacts {
	plain: string;
	words: string[];
	sentences: string[];
	paragraphs: string[];
	headings: Array< { level: number; text: string } >;
	images: Array< { alt: string } >;
	internalLinks: number;
	externalLinks: number;
	syllables: number;
}
