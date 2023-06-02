export const isBlockAllowed = (
	canInsert,
	blockType,
	rootClientId,
	insertionRules,
	{ getBlock },
) => {
	// Returns the default value if no rules can be found
	if ( ! insertionRules || insertionRules.length === 0 ) {
		return canInsert;
	}

	// assume that either you will have allowed or blocked in the rules
	// both cannot exist at the same time
	const isInAllowedMode = insertionRules.allowed ? true : false;

	// if there's no parent just go by the root level block names in the rules
	if ( ! rootClientId ) {
		return isRootBlockAllowed(
			blockType.name,
			insertionRules[ isInAllowedMode ? 'allowed' : 'blocked' ],
			isInAllowedMode,
		);
	}

	// if there is a parent, allow the default set otherwise do the root check again
	return isParentBlockAllowed(
		rootClientId,
		blockType,
		getBlock,
		canInsert,
		isInAllowedMode,
		insertionRules[ isInAllowedMode ? 'allowed' : 'blocked' ],
	);
};

function isParentBlockAllowed(
	rootClientId,
	blockType,
	getBlock,
	canInsert,
	isInAllowedMode,
	rules,
) {
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

	return isRootBlockAllowed( blockType.name, rules, isInAllowedMode );
}

function isRootBlockAllowed( blockName, rules, isInAllowedMode ) {
	const isBlockInRules = rules.some( rule => rule === blockName );

	return isInAllowedMode ? isBlockInRules : ! isBlockInRules;
}
