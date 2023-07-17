/**
 * Matches a rule to a block name, with the following cases being possible:
 *
 * 1. ['*'] - matches all blocks
 * 2. '*' can be located somewhere else alongside a string, e.g. 'core/*' - matches all core blocks
 * 3. ['core/paragraph'] - matches only the core/paragraph block
 *
 * @param {string} blockName
 * @param {string} rule
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
 * Matches a block name to a list of block regex rules.
 * For regex rules, see doesBlockNameMatchBlockRegex().
 *
 * @param {string} blockName
 * @param {string[]} rules
 * @returns True if the block name matches any of the rules, false otherwise.
 */
export function isBlockAllowedByBlockRegexes( blockName, rules ) {
	return rules.some( rule => doesBlockNameMatchBlockRegex( blockName, rule ) );
}
