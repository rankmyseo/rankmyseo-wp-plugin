import { renderLlmsMarkdown } from '../../src/shared/llms';

describe( 'llms.txt preview', () => {
	it( 'renders grouped markdown and applies include and exclude rules', () => {
		const body = renderLlmsMarkdown( {
			siteName: 'Acme',
			summary: 'Shoes and care',
			intro: '# Hand-picked pages.',
			homeUrl: 'https://example.com/',
			groups: [
				{
					label: 'Site',
					links: [
						{
							title: 'Acme',
							url: 'https://example.com/',
							description: '',
						},
					],
				},
				{
					label: 'Pages',
					links: [
						{
							title: 'About',
							url: 'https://example.com/about/',
							description: 'Our story',
						},
					],
				},
				{
					label: 'Posts',
					links: [
						{
							title: 'Hello',
							url: 'https://example.com/hello/',
							description: 'A post',
						},
					],
				},
			],
			includeUrls: 'https://example.com/extra/',
			excludeUrls: 'https://example.com/hello/',
		} );

		expect( body ).toBe(
			[
				'# Acme',
				'',
				'> Shoes and care',
				'',
				'Hand-picked pages.',
				'',
				'## Site',
				'',
				'- [Acme](https://example.com/)',
				'',
				'## Pages',
				'',
				'- [About](https://example.com/about/): Our story',
				'',
				'## Included links',
				'',
				'- [extra](https://example.com/extra/)',
				'',
			].join( '\n' )
		);
	} );
} );
