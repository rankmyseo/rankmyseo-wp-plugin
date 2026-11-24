const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'src/admin/index.tsx' ),
		editor: path.resolve( __dirname, 'src/editor/index.tsx' ),
		classic: path.resolve( __dirname, 'src/classic/index.tsx' ),
		'quick-edit': path.resolve( __dirname, 'src/quick-edit/index.ts' ),
		elementor: path.resolve( __dirname, 'src/builders/elementor.tsx' ),
		divi: path.resolve( __dirname, 'src/builders/divi.tsx' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
