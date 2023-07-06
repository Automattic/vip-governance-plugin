import { applyFilters } from '@wordpress/hooks';
import { doesBlockNameMatchBlockRegex } from './block-utils';

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
		isAllowed = isRootBlockAllowed( blockType.name, governanceRules.allowedBlocks );
	} else if ( governanceRules && rootClientId ) {
		// if there's no parent just go by the root level block names in the rules
		isAllowed = isParentBlockAllowed(
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

function isParentBlockAllowed( rootClientId, blockType, getBlock, canInsert, rules ) {
	const parentBlock = getBlock( rootClientId );

	if (
		rules.blockSettings &&
		rules.blockSettings[ parentBlock.name ] &&
		rules.blockSettings[ parentBlock.name ].allowedChildren
	) {
		return isRootBlockAllowed(
			blockType.name,
			rules.blockSettings[ parentBlock.name ].allowedChildren
		);
	} else if ( rules.allowedBlocks.length === 1 && rules.allowedBlocks[ 0 ] === '*' ) {
		return canInsert;
	}

	return false;
}

function isRootBlockAllowed( blockName, rules ) {
	return rules.some( rule => doesBlockNameMatchBlockRegex( blockName, rule ) );
}
