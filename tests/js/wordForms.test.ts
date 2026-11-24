import {
	limitSecondary,
	phraseMatch,
	samePhrase,
	tokensMatch,
} from '../../src/analyzer/wordForms';

describe( 'english word forms', () => {
	it( 'matches plurals, stems, and a small synonym list', () => {
		expect( tokensMatch( 'cities', 'city', true ) ).toBe( true );
		expect( tokensMatch( 'running', 'run', true ) ).toBe( true );
		expect( tokensMatch( 'shoes', 'shoe', true ) ).toBe( true );
		expect( samePhrase( 'running shoes', 'run shoe', true ) ).toBe( true );
		expect(
			phraseMatch( 'Please purchase a set', 'buy', true ).found
		).toBe( true );
		expect( phraseMatch( 'Visit the city', 'cities', true ).found ).toBe(
			true
		);
	} );

	it( 'keeps non-English matching exact', () => {
		expect( tokensMatch( 'cities', 'city', false ) ).toBe( false );
		expect(
			phraseMatch( 'Please purchase a set', 'buy', false ).found
		).toBe( false );
		expect( phraseMatch( 'Please buy a set', 'buy', false ).found ).toBe(
			true
		);
		expect( samePhrase( 'garden tools', 'garden tools', false ) ).toBe(
			true
		);
	} );

	it( 'keeps five secondary keywords and drops the focus phrase', () => {
		expect(
			limitSecondary( 'garden tools', [
				'garden tools',
				'pruners',
				'gloves',
				'forks',
				'shears',
				'rakes',
				'hoses',
			] )
		).toEqual( [ 'pruners', 'gloves', 'forks', 'shears', 'rakes' ] );
	} );
} );
