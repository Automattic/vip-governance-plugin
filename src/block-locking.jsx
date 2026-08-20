/**
 * WordPress dependencies
 */
import { store as blockEditorStore, useBlockEditingMode } from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { select } from '@wordpress/data';
import { createContext, useContext } from '@wordpress/element';
import { addFilter, applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { isBlockAllowedInHierarchy } from './block-utils';

const LOCKED_BLOCK_STYLES = {
	opacity: 0.6,
	backgroundColor: '#eee',
	border: '2px dashed #999',
};

const GovernanceLockContext = createContext( false );

export function setupBlockLocking( governanceRules ) {
	const withDisabledBlocks = createHigherOrderComponent( BlockEdit => {
		return function GovernedBlockEdit( props ) {
			const { name: blockName, clientId } = props;
			const isParentLocked = useContext( GovernanceLockContext );
			const { getBlockParents, getBlockName } = select( blockEditorStore );
			const parentBlockNames = getBlockParents( clientId, true ).map( getBlockName );

			/**
			 * Change what blocks are allowed to be edited in the block editor.
			 *
			 * @param {bool}     isAllowed        Whether or not the block will be allowed.
			 * @param {string}   blockName        The name of the block to be edited.
			 * @param {string[]} parentBlockNames An array of zero or more parent block names,
			 *                                    starting with the most recent parent ancestor.
			 * @param {Object}   governanceRules  An object containing the full set of governance
			 *                                    rules for the current user.
			 */
			const isAllowed = isBlockAllowedForEditing(
				blockName,
				parentBlockNames,
				governanceRules,
				isParentLocked
			);

			useBlockEditingMode( isAllowed ? undefined : 'disabled' );

			if ( isAllowed ) {
				return (
					<GovernanceLockContext.Provider value={ isParentLocked }>
						<BlockEdit { ...props } />
					</GovernanceLockContext.Provider>
				);
			}

			return (
				<GovernanceLockContext.Provider value>
					<Disabled>
						<div style={ LOCKED_BLOCK_STYLES }>
							<BlockEdit { ...props } />
						</div>
					</Disabled>
				</GovernanceLockContext.Provider>
			);
		};
	}, 'withDisabledBlocks' );

	addFilter( 'editor.BlockEdit', 'wpcomvip-governance/with-disabled-blocks', withDisabledBlocks );
}

/**
 * Determine whether a block should remain editable.
 *
 * Children inherit a locked parent's editing mode, so they do not need another
 * disabled wrapper or another invocation of the public editing filter.
 *
 * @param {string}   blockName        Current block name.
 * @param {string[]} parentBlockNames Parent names, nearest parent first.
 * @param {Object}   governanceRules  Effective governance rules.
 * @param {boolean}  isParentLocked   Whether governance locked an ancestor.
 * @return {boolean} Whether the block should remain editable.
 */
export function isBlockAllowedForEditing(
	blockName,
	parentBlockNames,
	governanceRules,
	isParentLocked
) {
	if ( isParentLocked ) {
		return true;
	}

	const isAllowed = isBlockAllowedInHierarchy( blockName, parentBlockNames, governanceRules );

	return applyFilters(
		'vip_governance__is_block_allowed_for_editing',
		isAllowed,
		blockName,
		parentBlockNames,
		governanceRules
	);
}
