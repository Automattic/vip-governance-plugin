import { isBlockAllowedInHierarchy } from './block-utils';

describe( 'blockUtils', () => {
	describe( 'isBlockAllowedInHierarchy', () => {
		it( 'should return true if the block is allowed in the hierarchy for cascading mode', () => {
			const blockName = 'core/heading';
			const parentBlockNames = [ 'core/media-text' ];
			const governanceRules = {
				allowedBlocks: [ 'core/group', 'core/paragraph' ],
				blockSettings: {
					'core/media-text': {
						allowedBlocks: [ 'core/heading' ],
					},
				},
			};

			const result = isBlockAllowedInHierarchy( blockName, parentBlockNames, governanceRules );

			expect( result ).toBe( true );
		} );
	} );
} );
