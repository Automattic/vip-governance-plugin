const defaultScriptsConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultScriptsConfig,
	entry: {
		index: './src/index.ts',
		settings: './src/settings/index.ts',
	},
};
