import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import {
	buildBlockDenyMessage,
	buildWpInserterNoticeRegex,
	VIP_GOVERNANCE_DENY_NOTICE_ID,
	WP_INSERTER_NOTICE_ID,
} from './deny-message';

jest.mock( '@wordpress/hooks', () => ( {
	applyFilters: jest.fn(),
} ) );

jest.mock( '@wordpress/i18n', () => {
	const actual = jest.requireActual( '@wordpress/i18n' );

	return {
		...actual,
		__: jest.fn( actual.__ ),
	};
} );

describe( 'denyMessage', () => {
	describe( 'buildBlockDenyMessage', () => {
		beforeEach( () => {
			// applyFilters(hookName, value, ...args) — passthrough returns the value (2nd arg).
			applyFilters.mockImplementation( ( _hookName, value ) => value );
		} );

		it( 'uses the block title in the default message when available', () => {
			const result = buildBlockDenyMessage( {
				blockName: 'core/audio',
				blockTitle: 'Audio',
				governanceRules: {},
			} );

			expect( result ).toBe( "The 'Audio' block is restricted by your site's governance rules." );
		} );

		it( 'falls back to the block name when no title is provided', () => {
			const result = buildBlockDenyMessage( {
				blockName: 'core/audio',
				blockTitle: null,
				governanceRules: {},
			} );

			expect( result ).toBe(
				"The 'core/audio' block is restricted by your site's governance rules."
			);
		} );

		it( 'passes the default message through the vip_governance__deny_message filter', () => {
			const governanceRules = { allowedBlocks: [ 'core/paragraph' ] };

			buildBlockDenyMessage( {
				blockName: 'core/audio',
				blockTitle: 'Audio',
				governanceRules,
			} );

			expect( applyFilters ).toHaveBeenCalledWith(
				'vip_governance__deny_message',
				"The 'Audio' block is restricted by your site's governance rules.",
				'core/audio',
				'Audio',
				governanceRules
			);
		} );

		it( 'returns the filtered message when a filter overrides it', () => {
			applyFilters.mockImplementation(
				( _hookName, _defaultMessage, blockName ) => `nope: ${ blockName }`
			);

			const result = buildBlockDenyMessage( {
				blockName: 'core/audio',
				blockTitle: 'Audio',
				governanceRules: {},
			} );

			expect( result ).toBe( 'nope: core/audio' );
		} );
	} );

	describe( 'buildWpInserterNoticeRegex', () => {
		beforeEach( () => {
			__.mockImplementation( text => text );
		} );

		it( 'matches the default WordPress deny-snackbar message and captures the block title', () => {
			const regex = buildWpInserterNoticeRegex();
			const match = regex.exec( 'Block "Audio" can\'t be inserted.' );

			expect( match ).not.toBeNull();
			expect( match[ 1 ] ).toBe( 'Audio' );
		} );

		it( 'does not match unrelated snackbar copy', () => {
			const regex = buildWpInserterNoticeRegex();

			expect( regex.exec( 'Some other notice.' ) ).toBeNull();
			expect(
				regex.exec( "The 'Audio' block is restricted by your site's governance rules." )
			).toBeNull();
		} );

		it( 'matches when translation uses a numbered placeholder', () => {
			__.mockImplementation( text =>
				text === 'Block "%s" can\'t be inserted.' ? 'Block "%1$s" can\'t be inserted.' : text
			);

			const regex = buildWpInserterNoticeRegex();
			const match = regex.exec( 'Block "Audio" can\'t be inserted.' );

			expect( match ).not.toBeNull();
			expect( match[ 1 ] ).toBe( 'Audio' );
		} );
	} );

	describe( 'exported notice ids', () => {
		it( "matches WordPress' inserter-notice id", () => {
			expect( WP_INSERTER_NOTICE_ID ).toBe( 'inserter-notice' );
		} );

		it( 'is namespaced for the governance plugin', () => {
			expect( VIP_GOVERNANCE_DENY_NOTICE_ID ).toMatch( /^wpcomvip-governance-/ );
		} );
	} );
} );
