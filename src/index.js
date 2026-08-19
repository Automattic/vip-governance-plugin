import { store as blockEditorStore } from '@wordpress/block-editor';
import { dispatch, select } from '@wordpress/data';
import { addFilter, applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { store as noticeStore } from '@wordpress/notices';

import { setupBlockLocking } from './block-locking';
import { isBlockAllowedInHierarchy } from './block-utils';
import { createNestedSettingPathMaps, resolveNestedSetting } from './nested-settings-filter';

function setup() {
	if ( VIP_GOVERNANCE.error ) {
		dispatch( noticeStore ).createErrorNotice( VIP_GOVERNANCE.error, {
			id: 'wpcomvip-governance-error',
			isDismissible: true,
			actions: [
				{
					label: __( 'Open governance settings', 'vip-governance' ),
					url: VIP_GOVERNANCE.urlSettingsPage,
				},
			],
		} );

		return;
	}

	const governanceRules = VIP_GOVERNANCE.governanceRules;
	const { getBlockParents, getBlockName } = select( blockEditorStore );

	addFilter(
		'blockEditor.__unstableCanInsertBlockType',
		`wpcomvip-governance/block-insertion`,
		( canInsert, blockType, rootClientId, { getBlock } ) => {
			if ( canInsert === false ) {
				return canInsert;
			}

			let parentBlockNames = [];

			if ( rootClientId ) {
				// This block has parents. Build a list of parentBlockNames
				const parentBlock = getBlock( rootClientId );
				const ancestorClientIds = getBlockParents( rootClientId, true );

				parentBlockNames = [
					parentBlock?.name ?? getBlockName( rootClientId ),
					...ancestorClientIds.map( getBlockName ),
				];
			}

			const isAllowed = isBlockAllowedInHierarchy(
				blockType.name,
				parentBlockNames,
				governanceRules
			);

			/**
			 * Change what blocks are allowed to be inserted in the block editor.
			 *
			 * @param {bool}     isAllowed        Whether or not the block will be allowed.
			 * @param {string}   blockName        The name of the block to be inserted.
			 * @param {string[]} parentBlockNames An array of zero or more parent block names,
			 *                                    starting with the most recent parent ancestor.
			 * @param {Object}   governanceRules  An object containing the full set of governance
			 *                                    rules for the current user.
			 */
			return applyFilters(
				'vip_governance__is_block_allowed_for_insertion',
				isAllowed,
				blockType.name,
				parentBlockNames,
				governanceRules
			);
		}
	);

	const nestedSettings = VIP_GOVERNANCE.nestedSettings;
	const { exactPaths, wildcardPaths } = createNestedSettingPathMaps( nestedSettings );

	addFilter(
		'blockEditor.useSetting.before',
		`wpcomvip-governance/nested-block-settings`,
		( defaultValue, path, clientId, blockName ) => {
			if ( ! blockName ) {
				return defaultValue;
			}

			return resolveNestedSetting( {
				defaultValue,
				path,
				clientId,
				blockName,
				nestedSettings,
				exactPaths,
				wildcardPaths,
				getBlockParents,
				getBlockName,
			} );
		}
	);

	// Block locking
	if ( governanceRules?.allowedBlocks ) {
		setupBlockLocking( governanceRules );
	}
}

setup();
