import {
	createNestedSettingRules,
	getNestedSetting,
	resolveNestedSetting,
} from './nested-settings-filter';

import type { BlockSettings } from './types';

describe( 'nested settings filter', () => {
	function resolve(
		nestedSettings: BlockSettings,
		{
			blockNames = [ 'core/heading', 'core/group' ],
			path = 'color.text',
		}: { blockNames?: ( string | undefined )[]; path?: string } = {}
	) {
		return resolveNestedSetting( {
			defaultValue: 'original',
			path,
			clientId: '0',
			rules: createNestedSettingRules( nestedSettings ),
			getBlockParents: () => blockNames.slice( 1 ).map( ( _, index ) => String( index + 1 ) ),
			getBlockName: id => blockNames[ Number( id ) ],
		} );
	}

	it( 'passes theme-defined duotone presets to the block editor picker', () => {
		const presets = [
			{ slug: 'review-mono', name: 'Review mono', colors: [ '#222222', '#eeeeee' ] },
		];
		expect(
			resolve(
				{ 'core/image': { color: { duotone: { theme: presets } } } },
				{ blockNames: [ 'core/image' ], path: 'color.duotone.theme' }
			)
		).toEqual( presets );
	} );

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
				{ blockNames: [ 'core/paragraph', 'core/quote', 'core/group' ] }
			)
		).toBe( false );
	} );

	it( 'uses the deepest setting when ancestor block names repeat', () => {
		expect(
			resolve(
				{
					'core/paragraph': { color: { text: true } },
					'core/group': {
						'core/group': {
							'core/paragraph': { color: { text: false } },
						},
					},
				},
				{ blockNames: [ 'core/paragraph', 'core/group', 'core/group' ] }
			)
		).toBe( false );
	} );

	it( 'uses the later setting when matching paths have equal depth', () => {
		expect(
			resolve(
				{
					'core/group': {
						'core/paragraph': { color: { text: true } },
					},
					'core/quote': {
						'core/paragraph': { color: { text: false } },
					},
				},
				{ blockNames: [ 'core/paragraph', 'core/quote', 'core/group' ] }
			)
		).toBe( false );
	} );

	it( 'ignores a missing ancestor name while resolving nested settings', () => {
		expect(
			resolve(
				{
					'core/group': {
						'core/heading': { color: { text: false } },
					},
				},
				{ blockNames: [ 'core/heading', undefined, 'core/group' ] }
			)
		).toBe( false );
	} );

	it( 'preserves a root exact rule instead of using a deeper wildcard rule', () => {
		expect(
			resolve(
				{
					'core/heading': { color: { text: true } },
					'core/quote': {
						'core/*': { color: { text: false } },
					},
				},
				{ blockNames: [ 'core/heading', 'core/quote' ] }
			)
		).toBe( true );
	} );

	it( 'preserves the legacy limitation on wildcard ancestors', () => {
		expect(
			resolve(
				{
					'core/*': {
						'core/paragraph': { color: { text: false } },
					},
				},
				{ blockNames: [ 'core/paragraph', 'core/group' ] }
			)
		).toBeUndefined();
	} );

	it( 'uses the first matching wildcard declaration', () => {
		expect(
			resolve( {
				'*': { color: { text: true } },
				'core/*': { color: { text: false } },
			} )
		).toBe( true );
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

	it( 'preserves the legacy limitation on top-level primitive settings', () => {
		expect(
			resolve(
				{
					'core/heading': { useRootPaddingAwareAlignments: true },
				},
				{ path: 'useRootPaddingAwareAlignments' }
			)
		).toBe( 'original' );
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

	it( 'preserves exact-name nested allowedBlocks traversal', () => {
		const settings = {
			'core/group': {
				allowedBlocks: [ 'core/heading' ],
				'core/quote': { allowedBlocks: [ 'core/paragraph' ] },
			},
		};

		expect( getNestedSetting( [ 'core/quote', 'core/group' ], 'allowedBlocks', settings ) ).toEqual(
			{ depth: 2, value: [ 'core/paragraph' ] }
		);
	} );

	it.each( [
		[ 'flags.disabled', false ],
		[ 'flags.zero', 0 ],
		[ 'flags.empty', '' ],
		[ 'flags.nullValue', null ],
		[ 'flags.missing', undefined ],
		[ 'items[0].enabled', true ],
		[ '.items[0].enabled', true ],
		[ 'items.10.enabled', true ],
		[ 'items[10].enabled', undefined ],
		[ 'items.length', 11 ],
		[ 'label.length', undefined ],
		[ '', undefined ],
	] )( 'preserves trunk path lookup for %s', ( path, expected ) => {
		const settings = {
			'core/group': {
				flags: { disabled: false, zero: 0, empty: '', nullValue: null },
				items: Array.from( { length: 11 }, () => ( { enabled: true } ) ),
				label: 'text',
			},
		};

		expect( getNestedSetting( [ 'core/group' ], path, settings ).value ).toBe( expected );
	} );

	it( 'preserves inherited setting lookup', () => {
		const color: unknown = Object.create( { text: false } );

		expect(
			getNestedSetting( [ 'core/group' ], 'color.text', { 'core/group': { color } } ).value
		).toBe( false );
	} );

	it( 'preserves precedence across repeated and mixed ancestor paths', () => {
		const settings = {
			'core/heading': { custom: { marker: 'root' } },
			'core/group': {
				'core/heading': { custom: { marker: 'group' } },
				'core/quote': { 'core/heading': { custom: { marker: 'group/quote' } } },
				'core/group': {
					'core/heading': { custom: { marker: 'group/group' } },
					'core/quote': {
						'core/heading': { custom: { marker: 'group/group/quote' } },
					},
				},
			},
			'core/quote': {
				'core/heading': { custom: { marker: 'quote' } },
				'core/group': { 'core/heading': { custom: { marker: 'quote/group' } } },
			},
		};
		const results: string[] = [];
		let ancestors: string[][] = [ [] ];
		for ( let depth = 0; depth <= 5; depth++ ) {
			for ( const names of ancestors ) {
				const result = getNestedSetting( [ 'core/heading', ...names ], 'custom.marker', settings );
				results.push(
					`${ names.join( ' > ' ) || 'root' }: ${ result.depth } ${ String( result.value ) }`
				);
			}
			ancestors = ancestors.flatMap( names => [
				[ ...names, 'core/group' ],
				[ ...names, 'core/quote' ],
			] );
		}
		expect( results ).toMatchSnapshot();
	} );

	it( 'does not repeatedly resolve the same rule through equivalent group ancestors', () => {
		let lookups = 0;
		let settings: BlockSettings = { 'core/heading': { color: { text: false } } };
		for ( let depth = 0; depth < 5; depth++ ) {
			const child = settings;
			settings = {
				get 'core/group'() {
					lookups++;
					return child;
				},
			};
		}

		expect(
			getNestedSetting(
				[ 'core/heading', ...Array< string >( 12 ).fill( 'core/group' ) ],
				'color.text',
				settings
			)
		).toEqual( { depth: 6, value: false } );
		expect( lookups ).toBeLessThanOrEqual( 100 );
	} );

	it( 'preserves the documented wildcard limitation for parent allowedBlocks', () => {
		expect(
			getNestedSetting( [ 'core/group' ], 'allowedBlocks', {
				'core/*': { allowedBlocks: [ 'core/paragraph' ] },
			} )
		).toEqual( { depth: 0, value: undefined } );
	} );
} );
