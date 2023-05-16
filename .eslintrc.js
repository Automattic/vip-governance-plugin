require( '@automattic/eslint-plugin-wpvip/init' );

module.exports = {
	extends: [
		'plugin:@automattic/wpvip/javascript',
		'plugin:@automattic/wpvip/formatting',
		'plugin:@automattic/wpvip/prettier',
	],
};
