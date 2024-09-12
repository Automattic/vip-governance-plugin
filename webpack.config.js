const defaultScriptsConfig = require( '@wordpress/scripts/config/webpack.config' );

// This is the default webpack config used by `@wordpress/scripts`
// It's been exported so we can extend it in our custom webpack config
// to add our own customizations like HMR, etc.

module.exports = [ defaultScriptsConfig ];
