import { __ } from '@wordpress/i18n';
import { BreadcrumbEdit } from './breadcrumbs/edit';
import { FaqEdit } from './faq/edit';
import { registerBlock } from './types';

export function registerRankMySEOBlocks(): void {
	registerBlock( 'rankmyseo/breadcrumbs', {
		apiVersion: 3,
		title: __( 'Breadcrumbs', 'rankmyseo' ),
		description: __(
			'Show the path to this page. Uses the same trail as the structured data.',
			'rankmyseo'
		),
		category: 'rankmyseo',
		icon: 'ellipsis',
		attributes: {
			separator: { type: 'string', default: '/' },
			showHome: { type: 'boolean', default: true },
			showCurrent: { type: 'boolean', default: true },
		},
		edit: BreadcrumbEdit,
		save: () => null,
	} );

	registerBlock( 'rankmyseo/faq', {
		apiVersion: 3,
		title: __( 'FAQ', 'rankmyseo' ),
		description: __(
			'Questions and answers. The same pairs are added to the page structured data.',
			'rankmyseo'
		),
		category: 'rankmyseo',
		icon: 'editor-help',
		attributes: {
			questions: { type: 'array', default: [] },
			layout: { type: 'string', default: 'details' },
		},
		edit: FaqEdit,
		save: () => null,
	} );
}
