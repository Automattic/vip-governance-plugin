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

	return isParentBlockAllowed( rootClientId, blockType, getBlock, canInsert, governanceRules );
};

function isParentBlockAllowed( rootClientId, blockType, getBlock, canInsert, rules ) {
	const parentBlock = getBlock( rootClientId );

	// Need a basic set of rules here for some blocks
	// 1 - for quote -> paragraph is always allowed
	// 2 - for media/text -> image and paragraph is always allowed
	// 3 - for pullquote -> paragraph is always allowed
	if ( parentBlock.name === 'core/quote' && blockType.name === 'core/paragraph' ) {
		return canInsert;
	} else if (
		parentBlock.name === 'core/media-text' &&
		blockType.name in [ 'core/image', 'core/paragraph' ]
	) {
		return canInsert;
	} else if ( parentBlock.name === 'core/pullquote' && blockType.name === 'core/paragraph' ) {
		return canInsert;
	}

	// TODO: Allow adding to the default rules for both and root by the customer, in case they have some custom blocks that they want to take into account.

	if (
		rules.blockSettings &&
		rules.blockSettings[ parentBlock.name ] &&
		rules.blockSettings[ parentBlock.name ].allowedChildren
	) {
		return isRootBlockAllowed(
			blockType.name,
			rules.blockSettings[ parentBlock.name ].allowedChildren
		);
	}

	return false;
}

function isRootBlockAllowed( blockName, rules ) {
	return rules.some( rule => doesBlockNameMatchBlockRegex( blockName, rule ) );
}
