const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	testEnvironment: 'jsdom',
	roots: [ '<rootDir>/tests/js' ],
	setupFilesAfterEnv: [ '<rootDir>/tests/js/setup.ts' ],
	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		'\\.css$': '<rootDir>/tests/js/__mocks__/styleMock.js',
		'^@wordpress/private-apis$': path.resolve(
			__dirname,
			'node_modules/@wordpress/private-apis'
		),
	},
};
