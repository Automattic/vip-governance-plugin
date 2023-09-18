require( '@automattic/eslint-plugin-wpvip/init' );

module.exports = {
	extends: [ 'plugin:@automattic/wpvip/recommended' ],
	globals: {
		VIP_GOVERNANCE: 'readonly',
	},
	root: true,
	env: {
		jest: true,
	},
};
