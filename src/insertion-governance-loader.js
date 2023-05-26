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

	// Go over the allowed blocks and match up to what's being inserted
	for ( const ruleBlock of isInAllowedMode ? insertionRules.allowed : insertionRules.blocked ) {
		if ( ruleBlock.blockName === parentBlock.name && ruleBlock.children?.length > 0 ) {
			return isRootBlockAllowed( blockType.name, ruleBlock.children, isInAllowedMode );
		}
	}

	// By default, for allowed its false and for blocked its true
	return ! isInAllowedMode;
};

export const isRootBlockAllowed = ( blockName, rules, isInAllowedMode ) => {
	if ( isInAllowedMode ) {
		return rules.some( rule => rule.blockName === blockName );
	}

	return ! rules.some( rule => rule.blockName === blockName && ! rule.children );
};
