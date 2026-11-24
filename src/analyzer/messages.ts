import { __, sprintf } from '@wordpress/i18n';
import type { CheckResult, ScoreBand } from './types';

export function bandLabel( band: ScoreBand ): string {
	switch ( band ) {
		case 'good':
			return __( 'Good', 'rankmyseo' );
		case 'needs_work':
			return __( 'Needs work', 'rankmyseo' );
		case 'poor':
			return __( 'Poor', 'rankmyseo' );
		default:
			return __( 'No keyword', 'rankmyseo' );
	}
}

export function statusLabel( status: CheckResult[ 'status' ] ): string {
	switch ( status ) {
		case 'pass':
			return __( 'Pass', 'rankmyseo' );
		case 'improve':
			return __( 'Improve', 'rankmyseo' );
		default:
			return __( 'Critical', 'rankmyseo' );
	}
}

export function categoryLabel( category: CheckResult[ 'category' ] ): string {
	switch ( category ) {
		case 'keyword':
			return __( 'Keyword', 'rankmyseo' );
		case 'snippet':
			return __( 'Search snippet', 'rankmyseo' );
		case 'content':
			return __( 'Content', 'rankmyseo' );
		default:
			return __( 'Readability', 'rankmyseo' );
	}
}

function metricNumber( check: CheckResult, key: string ): number {
	const value = check.metrics[ key ];
	return typeof value === 'number' ? value : 0;
}

export function simpleTip( check: CheckResult ): string {
	switch ( check.id ) {
		case 'keyword_set':
			return __(
				'Choose the main phrase you want this page to be found for.',
				'rankmyseo'
			);
		case 'keyword_in_title':
			return check.metrics.nearStart
				? __(
						'The focus keyword is near the start of the search title.',
						'rankmyseo'
				  )
				: __(
						'Add the focus keyword near the start of the search title.',
						'rankmyseo'
				  );
		case 'keyword_in_description':
			return __(
				'Mention the focus keyword once in the search description.',
				'rankmyseo'
			);
		case 'keyword_in_slug':
			return __( 'Use the focus keyword in the page link.', 'rankmyseo' );
		case 'keyword_in_intro':
			return __(
				'Use the focus keyword in the first 100 words.',
				'rankmyseo'
			);
		case 'keyword_in_headings':
			return __( 'Use the focus keyword in a heading.', 'rankmyseo' );
		case 'keyword_in_alt':
			return __(
				'Describe one image with the focus keyword.',
				'rankmyseo'
			);
		case 'keyword_density':
			return check.metrics.stuffing
				? __(
						'The focus keyword appears too often. Use it more naturally.',
						'rankmyseo'
				  )
				: __(
						'Use the focus keyword a few times in the page text.',
						'rankmyseo'
				  );
		case 'title_length':
			return __(
				'Keep the search title around 30 to 60 characters.',
				'rankmyseo'
			);
		case 'description_length':
			return __(
				'Write a search description of about 120 to 155 characters.',
				'rankmyseo'
			);
		case 'indexable':
			return __(
				'This page is hidden from search. Show it if you want visitors to find it.',
				'rankmyseo'
			);
		case 'word_count':
			return sprintf(
				/* translators: %d: recommended word count. */
				__(
					'Add more useful detail. Aim for at least %d words.',
					'rankmyseo'
				),
				metricNumber( check, 'target' ) || 300
			);
		case 'heading_hierarchy':
			return __(
				'Use one main heading, then smaller headings in order.',
				'rankmyseo'
			);
		case 'image_alts':
			return __( 'Add a short description to every image.', 'rankmyseo' );
		case 'internal_links':
			return check.metrics.cornerstone &&
				metricNumber( check, 'count' ) < 3
				? __(
						'Cornerstone pages should link to at least 3 other pages on this site.',
						'rankmyseo'
				  )
				: __( 'Link to another page on this site.', 'rankmyseo' );
		case 'secondary_in_content':
			return sprintf(
				/* translators: %s: secondary keyword. */
				__( 'Use “%s” in the page text.', 'rankmyseo' ),
				String( check.metrics.keyword ?? '' )
			);
		case 'secondary_in_title':
			return sprintf(
				/* translators: %s: secondary keyword. */
				__( 'Use “%s” in the search title.', 'rankmyseo' ),
				String( check.metrics.keyword ?? '' )
			);
		case 'secondary_in_description':
			return sprintf(
				/* translators: %s: secondary keyword. */
				__( 'Use “%s” in the search description.', 'rankmyseo' ),
				String( check.metrics.keyword ?? '' )
			);
		case 'secondary_in_headings':
			return sprintf(
				/* translators: %s: secondary keyword. */
				__(
					'Use “%s” in a heading or the opening paragraph.',
					'rankmyseo'
				),
				String( check.metrics.keyword ?? '' )
			);
		case 'secondary_density':
			return check.metrics.stuffing
				? sprintf(
						/* translators: %s: secondary keyword. */
						__(
							'“%s” appears too often. Use it more naturally.',
							'rankmyseo'
						),
						String( check.metrics.keyword ?? '' )
				  )
				: sprintf(
						/* translators: %s: secondary keyword. */
						__(
							'Mention “%s” a few times in the page text.',
							'rankmyseo'
						),
						String( check.metrics.keyword ?? '' )
				  );
		case 'external_links':
			return __(
				'Link to a useful page on another site when it helps the reader.',
				'rankmyseo'
			);
		case 'paragraph_length':
			return __(
				'Break up long paragraphs so the page is easier to scan.',
				'rankmyseo'
			);
		case 'sentence_length':
			return __(
				'Shorten long sentences so the page is easier to read.',
				'rankmyseo'
			);
		case 'subheading_distribution':
			return __( 'Add a heading about every 300 words.', 'rankmyseo' );
		default:
			return __(
				'Use shorter sentences so the page is easy to read.',
				'rankmyseo'
			);
	}
}

export function checkDetail( check: CheckResult ): string {
	switch ( check.id ) {
		case 'keyword_density':
			return sprintf(
				/* translators: 1: keyword density percentage, 2: number of mentions. */
				__( 'Density %1$s%% from %2$d mentions.', 'rankmyseo' ),
				String( check.metrics.density ?? 0 ),
				metricNumber( check, 'count' )
			);
		case 'title_length':
			return sprintf(
				/* translators: 1: character count, 2: estimated pixel width. */
				__( '%1$d characters, about %2$d pixels.', 'rankmyseo' ),
				metricNumber( check, 'characters' ),
				metricNumber( check, 'pixels' )
			);
		case 'description_length':
			return sprintf(
				/* translators: 1: character count, 2: estimated pixel width. */
				__( '%1$d characters, about %2$d pixels.', 'rankmyseo' ),
				metricNumber( check, 'characters' ),
				metricNumber( check, 'pixels' )
			);
		case 'word_count':
			return sprintf(
				/* translators: 1: current word count, 2: target word count. */
				__( '%1$d words. Target %2$d.', 'rankmyseo' ),
				metricNumber( check, 'words' ),
				metricNumber( check, 'target' )
			);
		case 'heading_hierarchy':
			return sprintf(
				/* translators: 1: H1 count, 2: subheading count. */
				__( '%1$d main headings, %2$d subheadings.', 'rankmyseo' ),
				metricNumber( check, 'h1' ),
				metricNumber( check, 'subheads' )
			);
		case 'image_alts':
			return sprintf(
				/* translators: 1: images with alt text, 2: total images. */
				__( '%1$d of %2$d images have a description.', 'rankmyseo' ),
				metricNumber( check, 'withAlt' ),
				metricNumber( check, 'images' )
			);
		case 'internal_links':
			return check.metrics.cornerstone
				? sprintf(
						/* translators: 1: number of internal links, 2: links a cornerstone page should have. */
						__(
							'%1$d links to this site. Cornerstone pages should have at least %2$d.',
							'rankmyseo'
						),
						metricNumber( check, 'count' ),
						metricNumber( check, 'minimum' ) || 3
				  )
				: sprintf(
						/* translators: %d: number of internal links. */
						__( '%d links to this site.', 'rankmyseo' ),
						metricNumber( check, 'count' )
				  );
		case 'secondary_density':
			return sprintf(
				/* translators: 1: keyword density percentage, 2: number of mentions. */
				__( 'Density %1$s%% from %2$d mentions.', 'rankmyseo' ),
				String( check.metrics.density ?? 0 ),
				metricNumber( check, 'count' )
			);
		case 'external_links':
			return sprintf(
				/* translators: %d: number of external links. */
				__( '%d links to other sites.', 'rankmyseo' ),
				metricNumber( check, 'count' )
			);
		case 'paragraph_length':
			return sprintf(
				/* translators: 1: average paragraph length in words, 2: paragraphs over 150 words. */
				__(
					'Average paragraph %1$s words. %2$d over 150 words.',
					'rankmyseo'
				),
				String( check.metrics.average ?? 0 ),
				metricNumber( check, 'longParagraphs' )
			);
		case 'sentence_length':
			return sprintf(
				/* translators: %s: average sentence length in words. */
				__( 'Average sentence %s words.', 'rankmyseo' ),
				String( check.metrics.average ?? 0 )
			);
		case 'subheading_distribution':
			return sprintf(
				/* translators: 1: subheading count, 2: subheadings needed. */
				__( '%1$d subheadings. About %2$d needed.', 'rankmyseo' ),
				metricNumber( check, 'subheads' ),
				metricNumber( check, 'needed' )
			);
		case 'readability':
			if ( ! check.metrics.english ) {
				return __(
					'Readability uses sentence length for this language.',
					'rankmyseo'
				);
			}
			return sprintf(
				/* translators: 1: Flesch reading ease, 2: Flesch-Kincaid grade. */
				__( 'Reading ease %1$s. Grade %2$s.', 'rankmyseo' ),
				String( check.metrics.flesch || '—' ),
				String( check.metrics.grade || '—' )
			);
		case 'keyword_set':
			return check.metrics.present
				? __( 'Focus keyword is set.', 'rankmyseo' )
				: __( 'Focus keyword is empty.', 'rankmyseo' );
		default:
			return check.metrics.present
				? __( 'Found.', 'rankmyseo' )
				: __( 'Not found.', 'rankmyseo' );
	}
}
