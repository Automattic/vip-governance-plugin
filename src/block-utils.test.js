import { isBlockAllowedInHierarchy } from './block-utils';

describe( 'blockUtils', () => {
	describe( 'isBlockAllowedInHierarchy', () => {
		it( 'should return true if the child block is allowed in the hierarchy for cascading mode', () => {
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

		it( 'should return true if the child block is allowed in the hierarchy for cascading mode with no blockSettings', () => {
			const blockName = 'core/heading';
			const parentBlockNames = [ 'core/media-text' ];
			const governanceRules = {
				allowedBlocks: [ 'core/heading', 'core/paragraph' ],
			};

			const result = isBlockAllowedInHierarchy( blockName, parentBlockNames, governanceRules );

			expect( result ).toBe( true );
		} );

		it( 'should return true if the root block is allowed in the hierarchy for cascading mode', () => {
			const blockName = 'core/heading';
			const parentBlockNames = [];
			const governanceRules = {
				allowedBlocks: [ 'core/heading', 'core/paragraph' ],
			};

			const result = isBlockAllowedInHierarchy( blockName, parentBlockNames, governanceRules );

			expect( result ).toBe( true );
		} );

		it( 'should return true if the child block is a critical core block', () => {
			const blockName = 'core/list-item';
			const parentBlockNames = [ 'core/list', 'core/quote' ];
			const governanceRules = {
				allowedBlocks: [ 'core/heading', 'core/paragraph' ],
			};

			const result = isBlockAllowedInHierarchy( blockName, parentBlockNames, governanceRules );

			expect( result ).toBe( true );
		} );
	} );
} );
