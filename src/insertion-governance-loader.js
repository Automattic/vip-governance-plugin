import { applyFilters } from '@wordpress/hooks';
import { isBlockAllowedByBlockRegexes } from './block-utils';

/**
 * @param {boolean} canInsert       The initial canInsert value returned by Gutenberg.
 * @param {Object}  blockType       The block type object for the block that may be inserted.
 * @param {?string} rootClientId    Parent client ID of the block that may be inserted.
 *                                  Null if the block is being inserted at the root level.
 * @param {Object}  governanceRules An object containing governance rules for the current
 *                                  user, with keys 'allowedBlocks' and 'blockSettings'.
 *
 * @return {boolean} Whether the given block type is allowed to be inserted.
 */
export const isBlockAllowed = (
	canInsert,
	blockType,
	rootClientId,
	governanceRules,
	{ getBlock }
) => {
	// Default value will be what Gutenberg has already determined
	let isAllowed = canInsert;

	if ( governanceRules && ! rootClientId ) {
		// When no rootClientId is present, the block is being inserted at the root level.
		// Use the root set of allowedBlocks.
		isAllowed = isBlockAllowedByBlockRegexes( blockType.name, governanceRules.allowedBlocks );
	} else if ( governanceRules && rootClientId ) {
		// If the block is being inserted into a parent, check this block against the parent type.
		isAllowed = isChildBlockAllowed(
			rootClientId,
			blockType,
			getBlock,
			isAllowed,
			governanceRules
		);
	}

	// Allow overriding the result using a filter
	return applyFilters(
		'vip_governance__is_block_allowed_for_insertion',
		isAllowed,
		blockType,
		governanceRules,
		rootClientId,
		getBlock
	);
};

function isChildBlockAllowed( rootClientId, blockType, getBlock, canInsert, rules ) {
	const parentBlock = getBlock( rootClientId );

	if (
		rules.blockSettings &&
		rules.blockSettings[ parentBlock.name ] &&
		rules.blockSettings[ parentBlock.name ].allowedChildren
	) {
		return isBlockAllowedByBlockRegexes(
			blockType.name,
			rules.blockSettings[ parentBlock.name ].allowedChildren
		);
	}

	return isBlockAllowedByBlockRegexes( blockType.name, rules.allowedBlocks );
}
