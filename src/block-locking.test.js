import { applyFilters } from '@wordpress/hooks';

import { isBlockAllowedForEditing } from './block-locking';
import { isBlockAllowedInHierarchy } from './block-utils';

jest.mock( '@wordpress/block-editor', () => ( {
	store: 'core/block-editor',
	useBlockEditingMode: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Disabled: jest.fn(),
} ) );

jest.mock( '@wordpress/compose', () => ( {
	createHigherOrderComponent: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
	applyFilters: jest.fn(),
} ) );

jest.mock( './block-utils', () => ( {
	isBlockAllowedInHierarchy: jest.fn(),
} ) );

describe( 'isBlockAllowedForEditing', () => {
	const governanceRules = { allowedBlocks: [ 'core/paragraph' ] };

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'applies the public editing filter to the hierarchy decision', () => {
		isBlockAllowedInHierarchy.mockReturnValue( false );
		applyFilters.mockReturnValue( true );

		expect(
			isBlockAllowedForEditing( 'core/heading', [ 'core/group' ], governanceRules, false )
		).toBe( true );
		expect( applyFilters ).toHaveBeenCalledWith(
			'vip_governance__is_block_allowed_for_editing',
			false,
			'core/heading',
			[ 'core/group' ],
			governanceRules
		);
	} );

	it( 'allows a child to inherit an existing parent lock without reevaluating it', () => {
		expect(
			isBlockAllowedForEditing( 'core/heading', [ 'core/group' ], governanceRules, true )
		).toBe( true );
		expect( isBlockAllowedInHierarchy ).not.toHaveBeenCalled();
		expect( applyFilters ).not.toHaveBeenCalled();
	} );
} );
