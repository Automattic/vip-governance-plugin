import { addFilter } from '@wordpress/hooks';
import { select, dispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as noticeStore } from '@wordpress/notices';

import { getNestedSettingPaths, getNestedSetting } from './nested-governance-loader';
import { isBlockAllowed } from './insertion-governance-loader';
import { setupBlockLocking } from './block-locking';

function setup() {
	if ( VIP_GOVERNANCE.errors ) {
		dispatch( noticeStore ).createErrorNotice( VIP_GOVERNANCE.errors, {
			isDismissible: true,
		} );

		return;
	}

	const governanceRules = VIP_GOVERNANCE.governanceRules;

	addFilter(
		'blockEditor.__unstableCanInsertBlockType',
		`wpcomvip-governance/block-insertion`,
		( canInsert, blockType, rootClientId, { getBlock } ) => {
			return isBlockAllowed( canInsert, blockType, rootClientId, governanceRules, {
				getBlock,
			} );
		}
	);

	const nestedSettings = VIP_GOVERNANCE.nestedSettings;
	const nestedSettingPaths = getNestedSettingPaths( nestedSettings );

	addFilter(
		'blockEditor.useSetting.before',
		`wpcomvip-governance/nested-block-settings`,
		( result, path, clientId, blockName ) => {
			const hasCustomSetting =
				// eslint-disable-next-line security/detect-object-injection
				nestedSettingPaths[ blockName ] !== undefined &&
				// eslint-disable-next-line security/detect-object-injection
				nestedSettingPaths[ blockName ][ path ] === true;

			if ( result !== undefined || ! hasCustomSetting ) {
				return result;
			}

			const blockNamePath = [
				clientId,
				...select( blockEditorStore ).getBlockParents( clientId, /* ascending */ true ),
			]
				.map( candidateId => select( blockEditorStore ).getBlockName( candidateId ) )
				.reverse();

			( { value: result } = getNestedSetting( blockNamePath, path, nestedSettings ) );

			return result;
		}
	);

	// Block locking
	if ( governanceRules?.allowedBlocks ) {
		setupBlockLocking( governanceRules );
	}
}

setup();
