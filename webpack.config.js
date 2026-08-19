const defaultScriptsConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultScriptsConfig,
	entry: {
		index: './src/index.js',
		settings: './src/settings/index.js',
	},
};
