import { getNestedSettingPaths, getNestedSetting } from './nested-governance-loader';

describe( 'getNestedSettingPaths', () => {
	describe( 'getNestedSettingPaths', () => {
		it( 'should return the nested setting paths given the nested settings', () => {
			const result = getNestedSettingPaths( getTestNestedSettings() );
			expect( result ).toEqual( {
				'core/heading': {
					'color.palette': true,
					'color.palette.theme': true,
				},
				'core/paragraph': {
					'color.palette': true,
					'color.palette.theme': true,
				},
			} );
		} );

		it( 'should collect wildcard setting paths', () => {
			const result = getNestedSettingPaths( {
				'core/*': {
					color: { text: false },
				},
				'*': {
					typography: { customFontSize: false },
				},
			} );

			expect( result ).toEqual( {
				'core/*': { 'color.text': true },
				'*': { 'typography.customFontSize': true },
			} );
		} );

		it( 'should merge paths for the same block nested under different parents', () => {
			const result = getNestedSettingPaths( {
				'core/group': {
					'core/paragraph': { color: { text: false } },
				},
				'core/quote': {
					'core/paragraph': { typography: { dropCap: false } },
				},
			} );

			expect( result ).toEqual( {
				'core/paragraph': {
					'color.text': true,
					'typography.dropCap': true,
				},
			} );
		} );

		it( 'should include top-level primitive and array setting paths', () => {
			const result = getNestedSettingPaths( {
				'core/group': {
					useRootPaddingAwareAlignments: true,
					spacingUnits: [ 'px', 'rem' ],
				},
			} );

			expect( result ).toEqual( {
				'core/group': {
					useRootPaddingAwareAlignments: true,
					spacingUnits: true,
				},
			} );
		} );
	} );

	describe( 'getNestedSetting', () => {
		it( 'should return the nested setting found at the top level', () => {
			const result = getNestedSetting(
				[ 'core/heading' ],
				'color.palette.theme',
				getTestNestedSettings()
			);
			expect( result ).toEqual( {
				depth: 1,
				value: [
					{
						color: '#FFFFF',
						name: 'Primary',
						slug: 'primary',
					},
				],
			} );
		} );

		it( 'should return the nested setting found deep inside a nested block', () => {
			const result = getNestedSetting(
				[ 'core/media-text', 'core/quote', 'core/paragraph' ],
				'color.palette.theme',
				getTestNestedSettings()
			);
			expect( result ).toEqual( {
				depth: 3,
				value: [
					{
						color: '#F00001',
						name: 'Tertiary',
						slug: 'tertiary',
					},
				],
			} );
		} );

		it( 'should return an empty result for an empty block path', () => {
			expect( getNestedSetting( [], 'color.text', getTestNestedSettings() ) ).toEqual( {
				depth: 0,
				value: undefined,
			} );
		} );
	} );
} );

function getTestNestedSettings() {
	return {
		'core/heading': {
			color: {
				palette: {
					theme: [
						{
							color: '#FFFFF',
							name: 'Primary',
							slug: 'primary',
						},
					],
				},
			},
		},
		'core/quote': {
			allowedBlocks: [ 'core/paragraph' ],
			'core/paragraph': {
				color: {
					palette: {
						theme: [
							{
								color: '#F00000',
								name: 'Secondary',
								slug: 'secondary',
							},
						],
					},
				},
			},
		},
		'core/media-text': {
			allowedBlocks: [ 'core/heading', 'core/paragraph' ],
			'core/heading': {
				color: {
					palette: {
						theme: [
							{
								color: '#FFFFF',
								name: 'Primary',
								slug: 'primary',
							},
						],
					},
				},
			},
			'core/quote': {
				allowedBlocks: [ 'core/paragraph' ],
				'core/paragraph': {
					color: {
						palette: {
							theme: [
								{
									color: '#F00001',
									name: 'Tertiary',
									slug: 'tertiary',
								},
							],
						},
					},
				},
			},
		},
	};
}
