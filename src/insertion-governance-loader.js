export const isBlockAllowed = (
	canInsert,
	blockType,
	rootClientId,
	governanceRule,
	{ getBlock }
) => {
	// Returns the default value if no rules can be found
	if ( ! governanceRule || governanceRule.length === 0 ) {
		return canInsert;
	}

	// if there's no parent just go by the root level block names in the rules
	if ( ! rootClientId ) {
		return isRootBlockAllowed( blockType.name, governanceRule.allowedBlocks );
	}

	// ToDo: Use the allowedChildren property under blockSettings to guard against nested blocks
	return isParentBlockAllowed(
		rootClientId,
		blockType,
		getBlock,
		canInsert,
		governanceRule.allowedBlocks
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
	return rules.some( rule => matchBlockToRule( rule, blockName ) );
}

/**
 * Matches a rule to a block name, with the following cases being possible:
 *
 * 1. ['*'] - matches all blocks
 * 2. '*' can be located somewhere else alongside a string, e.g. 'core/*' - matches all core blocks
 * 3. ['core/paragraph'] - matches only the core/paragraph block
 *
 * @param {*} rule
 * @param {*} blockName
 * @returns true, if the block name matches the rule or false otherwise
 */
function matchBlockToRule( rule, blockName ) {
	if ( rule === '*' ) {
		return true;
	} else if ( rule.includes( '*' ) ) {
		const [ stringToMatch ] = rule.split( '*' );
		return blockName.startsWith( stringToMatch );
	}

	return rule === blockName;
}
