import { applyFilters } from '@wordpress/hooks';

export { doesBlockNameMatchBlockWildcard, isBlockAllowedByBlockWildcards } from './block-matching';
import { doesBlockNameMatchBlockWildcard, isBlockAllowedByBlockWildcards } from './block-matching';
import { getNestedAllowedBlocks } from './nested-settings-filter';

import type { BlockName, GovernanceRules } from './types';

const DEFAULT_CORE_BLOCK_LIST: Readonly< Record< BlockName, readonly BlockName[] > > = {
	'core/list': [ 'core/list-item' ],
	'core/columns': [ 'core/column' ],
	'core/page-list': [ 'core/page-list-item' ],
	'core/navigation': [ 'core/navigation-link', 'core/navigation-submenu' ],
	'core/navigation-link': [ 'core/navigation-link', 'core/navigation-submenu', 'core/page-list' ],
	'core/quote': [ 'core/paragraph' ],
	'core/media-text': [ 'core/paragraph' ],
	'core/social-links': [ 'core/social-link' ],
	'core/comments-pagination': [
		'core/comments-pagination-previous',
		'core/comments-pagination-numbers',
		'core/comments-pagination-next',
	],
};

/** Determine whether a block can be inserted or edited at a hierarchy location. */
export function isBlockAllowedInHierarchy(
	blockName: BlockName,
	parentBlockNames: BlockName[],
	governanceRules: GovernanceRules
): boolean {
	const isInCascadingMode = applyFilters(
		'vip_governance__is_block_allowed_in_hierarchy',
		true,
		blockName,
		parentBlockNames,
		governanceRules
	) as boolean;

	const allowedBlocks =
		isInCascadingMode || parentBlockNames.length === 0 ? [ ...governanceRules.allowedBlocks ] : [];
	let wildcardParentCandidates: BlockName[] = [];

	if ( parentBlockNames.length > 0 ) {
		const immediateParentDefaults = DEFAULT_CORE_BLOCK_LIST[ parentBlockNames[ 0 ] ];
		if ( immediateParentDefaults?.includes( blockName ) ) {
			return true;
		}

		if ( governanceRules.blockSettings ) {
			const nested = getNestedAllowedBlocks( parentBlockNames, governanceRules.blockSettings );
			if ( nested.usesWildcardParent ) {
				wildcardParentCandidates = nested.allowedBlocks;
			} else {
				allowedBlocks.push( ...nested.allowedBlocks );
			}
		}
	}

	return (
		isBlockAllowedByBlockWildcards( blockName, allowedBlocks ) ||
		wildcardParentCandidates.some( rule => {
			try {
				return doesBlockNameMatchBlockWildcard( blockName, rule );
			} catch ( error ) {
				// Newly active child lists can contain patterns that never compiled before.
				if ( error instanceof SyntaxError ) {
					return false;
				}
				throw error;
			}
		} )
	);
}
