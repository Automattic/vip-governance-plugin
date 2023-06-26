import { doesBlockNameMatchBlockRegex } from './block-utils';

export const isBlockAllowed = (
	canInsert,
	blockType,
	rootClientId,
	governanceRules,
	{ getBlock }
) => {
	// Returns the default value if no rules can be found
	if ( ! governanceRules || governanceRules.length === 0 ) {
		return canInsert;
	}

	// if there's no parent just go by the root level block names in the rules
	if ( ! rootClientId ) {
		return isRootBlockAllowed( blockType.name, governanceRules.allowedBlocks );
	}

	// ToDo: Use the allowedChildren property under blockSettings to guard against nested blocks
	return isParentBlockAllowed(
		rootClientId,
		blockType,
		getBlock,
		canInsert,
		governanceRules.allowedBlocks
	);
};

function isParentBlockAllowed( rootClientId, blockType, getBlock, canInsert, rules ) {
	const parentBlock = getBlock( rootClientId );

	// Need a basic set of rules here for some blocks
	// 1 - for core/quote -> paragraph is always allowed
	// 2 - for media/text -> image and paragraph is always allowed
	if ( parentBlock.name === 'core/quote' && blockType.name === 'core/paragraph' ) {
		return canInsert;
	} else if (
		parentBlock.name === 'core/media-text' &&
		blockType.name in [ 'core/image', 'core/paragraph' ]
	) {
		return canInsert;
	}

	return isRootBlockAllowed( blockType.name, rules );
}

function isRootBlockAllowed( blockName, rules ) {
	return rules.some( rule => doesBlockNameMatchBlockRegex( blockName, rule ) );
}
