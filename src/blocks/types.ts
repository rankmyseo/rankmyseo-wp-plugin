import type { ComponentType } from 'react';

export interface BlockEditProps< T > {
	attributes: T;
	setAttributes: ( next: Partial< T > ) => void;
	isSelected: boolean;
}

export interface FaqPair {
	question: string;
	answer: string;
}

export interface FaqAttributes {
	questions: FaqPair[];
	layout: 'details' | 'headings';
}

export interface BreadcrumbAttributes {
	separator: string;
	showHome: boolean;
	showCurrent: boolean;
}

interface BlockSettings< T > {
	apiVersion: number;
	title: string;
	description: string;
	category: string;
	icon: string;
	attributes: Record< string, unknown >;
	edit: ComponentType< BlockEditProps< T > >;
	save: () => null;
}

interface BlocksApi {
	registerBlockType: (
		name: string,
		settings: BlockSettings< never >
	) => void;
}

function blocksApi(): BlocksApi | null {
	const wp = (
		globalThis as {
			wp?: { blocks?: BlocksApi };
		}
	 ).wp;
	return wp?.blocks ?? null;
}

export function registerBlock< T >(
	name: string,
	settings: BlockSettings< T >
): void {
	blocksApi()?.registerBlockType(
		name,
		settings as unknown as BlockSettings< never >
	);
}
