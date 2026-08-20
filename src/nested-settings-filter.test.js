import {
	createNestedSettingRules,
	doesBlockHierarchyMatch,
	resolveNestedSetting,
} from './nested-settings-filter';

describe( 'nested settings filter', () => {
	const namesById = {
		root: 'core/group',
		parent: 'core/quote',
		current: 'core/heading',
	};
	const getBlockName = jest.fn( id => namesById[ id ] );

	function resolve( nestedSettings, options = {} ) {
		return resolveNestedSetting( {
			defaultValue: 'original',
			path: 'color.text',
			clientId: 'current',
			rulesByPath: createNestedSettingRules( nestedSettings ),
			getBlockParents: () => [ 'root' ],
			getBlockName,
			...options,
		} );
	}

	it( 'prefers an exact block rule over a wildcard at the same depth', () => {
		expect(
			resolve( {
				'core/*': { color: { text: false } },
				'core/heading': { color: { text: true } },
			} )
		).toBe( true );
	} );

	it( 'applies a wildcard block setting when no exact rule exists', () => {
		expect(
			resolve( {
				'core/*': { color: { text: false } },
			} )
		).toBe( false );
	} );

	it( 'returns the original value when no governed path applies', () => {
		expect(
			resolve( { 'core/*': { color: { text: false } } }, { path: 'typography.dropCap' } )
		).toBe( 'original' );
	} );

	it( 'uses the deepest exact setting in the current hierarchy', () => {
		const hierarchyNames = {
			root: 'core/group',
			parent: 'core/quote',
			current: 'core/paragraph',
		};

		expect(
			resolve(
				{
					'core/paragraph': { color: { text: true } },
					'core/group': {
						'core/quote': {
							'core/paragraph': { color: { text: false } },
						},
					},
				},
				{
					getBlockParents: () => [ 'parent', 'root' ],
					getBlockName: id => hierarchyNames[ id ],
				}
			)
		).toBe( false );
	} );

	it( 'uses a deeper wildcard rule instead of a root exact rule', () => {
		expect(
			resolve(
				{
					'core/heading': { color: { text: true } },
					'core/quote': {
						'core/*': { color: { text: false } },
					},
				},
				{ getBlockParents: () => [ 'parent' ] }
			)
		).toBe( false );
	} );

	it( 'matches wildcard ancestors in a nested rule', () => {
		const hierarchyNames = {
			root: 'core/group',
			current: 'core/paragraph',
		};

		expect(
			resolve(
				{
					'core/*': {
						'core/paragraph': { color: { text: false } },
					},
				},
				{ getBlockName: id => hierarchyNames[ id ] }
			)
		).toBe( false );
	} );

	it( 'uses the later declaration when depth and exactness are equal', () => {
		expect(
			resolve( {
				'*': { color: { text: true } },
				'core/*': { color: { text: false } },
			} )
		).toBe( false );
	} );

	it( 'unwraps the theme value expected by the WordPress settings API', () => {
		const palette = [ { color: '#fff', name: 'White', slug: 'white' } ];

		expect(
			resolve(
				{
					'core/heading': { color: { palette: { theme: palette } } },
				},
				{ path: 'color.palette' }
			)
		).toBe( palette );
	} );

	it( 'applies a top-level primitive setting', () => {
		expect(
			resolve(
				{
					'core/heading': { useRootPaddingAwareAlignments: true },
				},
				{ path: 'useRootPaddingAwareAlignments' }
			)
		).toBe( true );
	} );

	it( 'returns an array setting without changing its value', () => {
		const units = [ 'px', 'rem', '%' ];

		expect(
			resolve(
				{
					'core/heading': { spacing: { units } },
				},
				{ path: 'spacing.units' }
			)
		).toBe( units );
	} );

	it( 'allows nested rules to match ordered non-direct ancestors', () => {
		expect(
			doesBlockHierarchyMatch(
				[ 'core/group', 'core/heading' ],
				[ 'core/group', 'core/quote', 'core/heading' ]
			)
		).toBe( true );
	} );
} );
