import { doesBlockNameMatchBlockRegex, doesBlockMatchDefaultBlockRules } from './block-utils';

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

	if ( doesBlockMatchDefaultBlockRules( parentBlock, blockType ) ) {
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
