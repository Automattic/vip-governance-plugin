/**
 * Given a block name, a parent list and a set of governance rules, determine if
 * the block can be inserted.
 *
 * By default, will return if the block is allowed to be inserted at the root level
 * per the user's rules. If a parent block contains a rule for allowedChildren,
 * the function will return if the block is allowed as a child of that parent.
 *
 * Rules declared in allowedChildren will override root level rules when the block
 * is currently a child of the parent with allowedChildren.
 *
 * @param {string}   blockName        The current block's name.
 * @param {string[]} parentBlockNames A list of zero or more parent block names,
 *                                    starting with the most recent parent ancestor.
 * @param {Object}   governanceRules  An object containing the full set of governance
 *                                    rules for the current user.
 * @returns True if the block is allowed in set of parent blocks, or false otherwise.
 */
export function isBlockAllowedInHierarchy( blockName, parentBlockNames, governanceRules ) {
	if ( parentBlockNames.length > 0 ) {
		// If the block is being inserted into a parent, check this block against the parent type.

		// Todo: Recurse into parent hierarchy. Only checks rules for the parent block now.
		const parentBlockName = parentBlockNames[ 0 ];
		const hasParentRule =
			governanceRules.blockSettings &&
			governanceRules.blockSettings[ String( parentBlockName ) ] &&
			governanceRules.blockSettings[ String( parentBlockName ) ].allowedChildren;

		if ( hasParentRule ) {
			const parentAllowedChildren =
				governanceRules.blockSettings[ String( parentBlockName ) ].allowedChildren;

			return isBlockAllowedByBlockRegexes( blockName, parentAllowedChildren );
		}
	}

	// If there is no parent block to match for rules, or the block is being inserted
	// at the root level, match against the root level rules for this role.
	return isBlockAllowedByBlockRegexes( blockName, governanceRules.allowedBlocks );
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
