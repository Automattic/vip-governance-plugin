import { useBlockEditingMode } from '@wordpress/block-editor';
import { select, useSelect } from '@wordpress/data';
import { useContext } from '@wordpress/element';
import { addFilter, applyFilters } from '@wordpress/hooks';

import { createGovernedBlockEdit } from './block-locking';
import { setupGovernance } from './editor';

import type { BlockRecord, GovernanceRules } from './types';

jest.mock( '@wordpress/block-editor', () => ( {
	store: 'core/block-editor',
	useBlockEditingMode: jest.fn(),
} ) );
jest.mock( '@wordpress/components', () => ( { Disabled: jest.fn() } ) );
jest.mock( '@wordpress/data', () => ( { select: jest.fn(), useSelect: jest.fn() } ) );
jest.mock( '@wordpress/element', () => ( {
	createContext: jest.fn( () => ( { Provider: jest.fn() } ) ),
	useContext: jest.fn(),
} ) );
jest.mock( '@wordpress/hooks', () => ( { addFilter: jest.fn(), applyFilters: jest.fn() } ) );
jest.mock( '@wordpress/notices', () => ( { store: 'core/notices' } ) );

const exemptions = [
	[ 'core/list', 'core/list-item' ],
	[ 'core/columns', 'core/column' ],
	[ 'core/page-list', 'core/page-list-item' ],
	[ 'core/navigation', 'core/navigation-link' ],
	[ 'core/navigation', 'core/navigation-submenu' ],
	[ 'core/navigation-link', 'core/navigation-link' ],
	[ 'core/navigation-link', 'core/navigation-submenu' ],
	[ 'core/navigation-link', 'core/page-list' ],
	[ 'core/quote', 'core/paragraph' ],
	[ 'core/media-text', 'core/paragraph' ],
	[ 'core/social-links', 'core/social-link' ],
	[ 'core/comments-pagination', 'core/comments-pagination-previous' ],
	[ 'core/comments-pagination', 'core/comments-pagination-numbers' ],
	[ 'core/comments-pagination', 'core/comments-pagination-next' ],
] as const;

const governed = [
	[ 'core/navigation', 'core/search' ],
	[ 'core/navigation', 'core/social-links' ],
	[ 'core/navigation', 'core/page-list' ],
	[ 'core/navigation', 'core/spacer' ],
	[ 'core/navigation', 'core/home-link' ],
	[ 'core/navigation', 'core/site-title' ],
	[ 'core/navigation', 'core/site-logo' ],
	[ 'core/navigation', 'core/loginout' ],
	[ 'core/navigation', 'core/buttons' ],
	[ 'core/navigation-submenu', 'core/navigation-link' ],
	[ 'core/buttons', 'core/button' ],
	[ 'core/query-pagination', 'core/query-pagination-previous' ],
	[ 'core/query-pagination', 'core/query-pagination-numbers' ],
	[ 'core/query-pagination', 'core/query-pagination-next' ],
	[ 'core/accordion', 'core/accordion-item' ],
	[ 'core/accordion-item', 'core/accordion-heading' ],
	[ 'core/accordion-item', 'core/accordion-panel' ],
	[ 'core/playlist', 'core/playlist-track' ],
	[ 'core/tab-panels', 'core/tab-panel' ],
	[ 'core/tabs', 'core/tab-list' ],
	[ 'core/tabs', 'core/tab-panels' ],
	[ 'example/container', 'example/child' ],
	[ 'example/list', 'core/list-item' ],
	[ 'core/list', 'example/list-item' ],
	[ 'core/group', 'core/paragraph' ],
] as const;

type InsertionFilter = (
	canInsert: boolean,
	block: { name: string },
	rootClientId: string,
	selectors: { getBlock: () => BlockRecord }
) => boolean;

function getDecisions(
	parentNames: string[],
	child: string,
	rules: GovernanceRules,
	canInsert = true,
	parentLocked = false
) {
	const [ parent, ...ancestors ] = parentNames;
	const getBlockName = ( id: string ) => ancestors[ Number( id ) ];
	const getBlockParents = () => ancestors.map( ( _ancestor, index ) => String( index ) );
	jest.mocked( select ).mockReturnValue( { getBlockName, getBlockParents } as never );
	jest.mocked( useSelect ).mockReturnValue( parentNames );
	jest.mocked( useContext ).mockReturnValue( parentLocked );

	setupGovernance( {
		error: false,
		governanceRules: rules,
		nestedSettings: {},
		urlSettingsPage: '',
	} );
	const callback = jest
		.mocked( addFilter )
		.mock.calls.find( ( [ hook ] ) => hook === 'blockEditor.__unstableCanInsertBlockType' )?.[ 2 ];
	if ( ! callback ) {
		throw new Error( 'Insertion filter was not registered.' );
	}

	const insert = ( callback as InsertionFilter )( canInsert, { name: child }, 'parent', {
		getBlock: () => ( { clientId: 'parent', name: parent } ),
	} );
	const GovernedBlockEdit = createGovernedBlockEdit( () => null, rules );
	( GovernedBlockEdit as ( props: { clientId: string; name: string } ) => unknown )( {
		clientId: 'child',
		name: child,
	} );

	return { insert, edit: jest.mocked( useBlockEditingMode ).mock.lastCall?.[ 0 ] !== 'disabled' };
}

describe.each( [
	[ 'cascading', true ],
	[ 'restrictive', false ],
] )( 'structural child compatibility in %s mode', ( _mode, cascading ) => {
	beforeEach( () => {
		jest.clearAllMocks();
		jest.mocked( applyFilters ).mockImplementation( ( hook, value ) => {
			return hook === 'vip_governance__is_block_allowed_in_hierarchy' ? cascading : value;
		} );
	} );

	it.each( exemptions )( 'allows %s → %s for insertion and editing', ( parent, child ) => {
		expect(
			getDecisions( [ parent, 'core/group' ], child, { allowedBlocks: [ parent ] } )
		).toEqual( { insert: true, edit: true } );
	} );

	it.each( governed )( 'checks governance for %s → %s after native approval', ( parent, child ) => {
		expect( getDecisions( [ parent ], child, { allowedBlocks: [ parent ] } ) ).toEqual( {
			insert: false,
			edit: false,
		} );
	} );

	it( 'honors a native veto for an exempt child while leaving existing content editable', () => {
		expect(
			getDecisions( [ 'core/list' ], 'core/list-item', { allowedBlocks: [ 'core/list' ] }, false )
		).toEqual( { insert: false, edit: true } );
		expect( applyFilters ).not.toHaveBeenCalledWith(
			'vip_governance__is_block_allowed_for_insertion',
			expect.anything(),
			expect.anything(),
			expect.anything(),
			expect.anything()
		);
	} );

	it( 'does not extend a direct exemption to an older ancestor', () => {
		expect(
			getDecisions( [ 'core/group', 'core/quote' ], 'core/paragraph', { allowedBlocks: [] } )
		).toEqual( { insert: false, edit: false } );
	} );

	it( 'still permits an ordinary nested block through the configured parent rule', () => {
		expect(
			getDecisions( [ 'core/navigation' ], 'core/search', {
				allowedBlocks: [ 'core/navigation' ],
				blockSettings: { 'core/navigation': { allowedBlocks: [ 'core/search' ] } },
			} )
		).toEqual( { insert: true, edit: true } );
	} );

	it( 'carries a root-only permission into a nest only in cascading mode', () => {
		expect(
			getDecisions( [ 'core/group' ], 'core/paragraph', { allowedBlocks: [ 'core/paragraph' ] } )
		).toEqual( { insert: cascading, edit: cascading } );
	} );

	it( 'leaves the wrapper to propagate an already inherited lock', () => {
		expect(
			getDecisions( [ 'core/list' ], 'core/list-item', { allowedBlocks: [] }, false, true )
		).toEqual( { insert: false, edit: true } );
		expect( applyFilters ).not.toHaveBeenCalled();
	} );
} );

describe( 'editor wildcard compilation', () => {
	const validRule = 'core/.+*';
	const invalidRule = 'core/**';
	const governanceRules = { allowedBlocks: [ 'example/literal', validRule, invalidRule ] };
	const configuration = {
		error: false as const,
		governanceRules,
		nestedSettings: {
			'core/heading': { color: { text: true } },
			[ validRule ]: { color: { text: false } },
			[ invalidRule ]: { color: { text: 'unreachable' } },
		},
		urlSettingsPage: '',
	};

	function registerEditor() {
		jest.mocked( addFilter ).mockClear();
		setupGovernance( configuration );
		const getFilter = ( hook: string ): ( ( ...args: unknown[] ) => unknown ) => {
			const registration = jest.mocked( addFilter ).mock.calls.find( call => call[ 0 ] === hook );
			if ( ! registration ) {
				throw new Error( `Missing filter ${ hook }` );
			}
			return registration[ 2 ] as ( ...args: unknown[] ) => unknown;
		};
		const insert = getFilter( 'blockEditor.__unstableCanInsertBlockType' );
		const edit = getFilter( 'editor.BlockEdit' )( () => null ) as ( props: {
			name: string;
			clientId: string;
		} ) => unknown;
		const setting = getFilter( 'blockEditor.useSetting.before' );
		return {
			insert: ( name: string, canInsert = true ) => insert( canInsert, { name }, undefined, {} ),
			edit: ( name: string ) => edit( { name, clientId: 'child' } ),
			setting: ( name: string, path = 'color.text' ) => setting( 'native', path, 'child', name ),
		};
	}

	beforeEach( () => {
		jest.clearAllMocks();
		jest.mocked( select ).mockReturnValue( {
			getBlockParents: () => [],
			getBlockName: () => undefined,
		} as never );
		jest.mocked( useSelect ).mockReturnValue( [] );
		jest.mocked( useContext ).mockReturnValue( false );
		jest
			.mocked( applyFilters )
			.mockImplementation( ( hook, value ) =>
				hook === 'vip_governance__is_block_allowed_for_insertion' ? false : value
			);
	} );

	afterEach( () => jest.restoreAllMocks() );

	it( 'compiles only when reached and reuses the same successful expression across editor hooks', () => {
		const regexp = jest.spyOn( globalThis, 'RegExp' );
		const compilations = () => regexp.mock.calls.filter( ( [ input ] ) => input === 'core/.+.*' );
		const first = registerEditor();
		expect( first.insert( 'core/heading', false ) ).toBe( false );
		expect( first.insert( 'example/literal' ) ).toBe( false );
		expect( first.setting( 'core/heading' ) ).toBe( true );
		expect( first.setting( 'core/image', 'color.background' ) ).toBe( 'native' );
		expect( compilations() ).toHaveLength( 0 );

		for ( let call = 0; call < 10; call++ ) {
			expect( first.insert( 'core/image' ) ).toBe( false );
			first.edit( 'core/image' );
			expect( first.setting( 'core/image' ) ).toBe( false );
		}
		expect( compilations() ).toEqual( [ [ 'core/.+.*' ] ] );
		expect( useBlockEditingMode ).toHaveBeenLastCalledWith( undefined );
		expect( applyFilters ).toHaveBeenCalledTimes( 42 );
		expect( applyFilters ).toHaveBeenCalledWith(
			'vip_governance__is_block_allowed_for_insertion',
			true,
			'core/image',
			[],
			governanceRules
		);

		const second = registerEditor();
		second.insert( 'core/image' );
		first.insert( 'core/image' );
		expect( compilations() ).toEqual( [ [ 'core/.+.*' ], [ 'core/.+.*' ] ] );
	} );

	it( 'throws and retries every invalid pattern that is reached', () => {
		const regexp = jest.spyOn( globalThis, 'RegExp' );
		const editor = registerEditor();

		expect( () => editor.insert( 'example/missing' ) ).toThrow( SyntaxError );
		expect( () => editor.edit( 'example/missing' ) ).toThrow( SyntaxError );
		expect( () => editor.setting( 'example/missing' ) ).toThrow( SyntaxError );
		expect( regexp.mock.calls.filter( ( [ input ] ) => input === 'core/.**' ) ).toEqual( [
			[ 'core/.**' ],
			[ 'core/.**' ],
			[ 'core/.**' ],
		] );
	} );
} );
