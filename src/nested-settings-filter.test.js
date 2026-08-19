import { createNestedSettingPathMaps, resolveNestedSetting } from './nested-settings-filter';

describe( 'nested settings filter', () => {
	const namesById = {
		root: 'core/group',
		current: 'core/heading',
	};
	const getBlockParents = jest.fn( () => [ 'root' ] );
	const getBlockName = jest.fn( id => namesById[ id ] );

	it( 'prefers an exact block rule over a matching wildcard rule', () => {
		const nestedSettings = {
			'core/*': { color: { text: false } },
			'core/heading': { color: { text: true } },
		};
		const pathMaps = createNestedSettingPathMaps( nestedSettings );

		expect(
			resolveNestedSetting( {
				defaultValue: 'original',
				path: 'color.text',
				clientId: 'current',
				blockName: 'core/heading',
				nestedSettings,
				...pathMaps,
				getBlockParents,
				getBlockName,
			} )
		).toBe( true );
	} );

	it( 'applies a wildcard block setting when no exact rule exists', () => {
		const nestedSettings = {
			'core/*': { color: { text: false } },
		};
		const pathMaps = createNestedSettingPathMaps( nestedSettings );

		expect(
			resolveNestedSetting( {
				defaultValue: 'original',
				path: 'color.text',
				clientId: 'current',
				blockName: 'core/heading',
				nestedSettings,
				...pathMaps,
				getBlockParents,
				getBlockName,
			} )
		).toBe( false );
	} );

	it( 'returns the original value when no governed path applies', () => {
		const nestedSettings = { 'core/*': { color: { text: false } } };
		const pathMaps = createNestedSettingPathMaps( nestedSettings );

		expect(
			resolveNestedSetting( {
				defaultValue: 'original',
				path: 'typography.dropCap',
				clientId: 'current',
				blockName: 'core/heading',
				nestedSettings,
				...pathMaps,
				getBlockParents,
				getBlockName,
			} )
		).toBe( 'original' );
	} );

	it( 'uses the deepest matching setting in the current hierarchy', () => {
		const nestedSettings = {
			'core/paragraph': { color: { text: true } },
			'core/group': {
				'core/quote': {
					'core/paragraph': { color: { text: false } },
				},
			},
		};
		const pathMaps = createNestedSettingPathMaps( nestedSettings );
		const hierarchyNames = {
			root: 'core/group',
			parent: 'core/quote',
			current: 'core/paragraph',
		};

		expect(
			resolveNestedSetting( {
				defaultValue: 'original',
				path: 'color.text',
				clientId: 'current',
				blockName: 'core/paragraph',
				nestedSettings,
				...pathMaps,
				getBlockParents: () => [ 'parent', 'root' ],
				getBlockName: id => hierarchyNames[ id ],
			} )
		).toBe( false );
	} );

	it( 'unwraps the theme value expected by the WordPress settings API', () => {
		const palette = [ { color: '#fff', name: 'White', slug: 'white' } ];
		const nestedSettings = {
			'core/heading': { color: { palette: { theme: palette } } },
		};
		const pathMaps = createNestedSettingPathMaps( nestedSettings );

		expect(
			resolveNestedSetting( {
				defaultValue: 'original',
				path: 'color.palette',
				clientId: 'current',
				blockName: 'core/heading',
				nestedSettings,
				...pathMaps,
				getBlockParents,
				getBlockName,
			} )
		).toBe( palette );
	} );
} );
