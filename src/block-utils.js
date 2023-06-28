/**
 * Matches a rule to a block name, with the following cases being possible:
 *
 * 1. ['*'] - matches all blocks
 * 2. '*' can be located somewhere else alongside a string, e.g. 'core/*' - matches all core blocks
 * 3. ['core/paragraph'] - matches only the core/paragraph block
 *
 * @param {*} blockName
 * @param {*} rule
 * @returns True if the block name matches the rule, or false otherwise
 */
export function doesBlockNameMatchBlockRegex( blockName, rule ) {
	if ( rule.includes( '*' ) ) {
		// eslint-disable-next-line security/detect-non-literal-regexp
		return blockName.match( new RegExp( rule.replace( '*', '.*' ) ) );
	}

	return rule === blockName;
}

/**
 *
 * Check if the block is part of the default core block rules. If it is, we need to allow it.
 *
 * @param {*} parentBlock
 * @param {*} blockType
 * @returns true if the block name matches the default core block rules, false otherwise
 */
export function doesBlockMatchDefaultBlockRules( parentBlock, blockType ) {
	// Need a basic set of rules here for some blocks
	// 1 - for quote -> paragraph is always allowed
	// 2 - for media/text -> image and paragraph is always allowed
	// 3 - for pullquote -> paragraph is always allowed
	if ( parentBlock.name === 'core/quote' && blockType.name === 'core/paragraph' ) {
		return true;
	} else if (
		parentBlock.name === 'core/media-text' &&
		blockType.name in [ 'core/image', 'core/paragraph' ]
	) {
		return true;
	} else if ( parentBlock.name === 'core/pullquote' && blockType.name === 'core/paragraph' ) {
		return true;
	}

	return false;
}
