import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';

/**
 * The id used by Gutenberg's block inserter when a block selection is denied.
 * See @wordpress/block-editor's use-block-types-state.js.
 */
export const WP_INSERTER_NOTICE_ID = 'inserter-notice';

/**
 * The id used for the governance plugin's replacement deny snackbar.
 */
export const VIP_GOVERNANCE_DENY_NOTICE_ID = 'wpcomvip-governance-deny';

/**
 * Build a regex that matches Gutenberg's localised inserter-deny message
 * (`Block "%s" can't be inserted.`) and captures the block title in group 1.
 *
 * Pulling the template through `__()` keeps us aligned with the active locale.
 */
export function buildWpInserterNoticeRegex() {
	// Source string copied verbatim from @wordpress/block-editor's
	// use-block-types-state.js so it resolves to the same translation.
	const template = __( 'Block "%s" can\'t be inserted.' );
	const escaped = template.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	// Support both unnumbered (`%s`) and numbered (`%1$s`) placeholders.
	// In `escaped`, `%1$s` becomes `%1\$s` because `$` is escaped.
	const pattern = escaped.replace( /%\d+\\\$s|%s/, '(.+)' );
	return new RegExp( '^' + pattern + '$' );
}

/**
 * Resolve the deny message shown when a block is restricted by governance rules.
 *
 * Runs a default message through the `vip_governance__deny_message` filter so
 * site integrators can customise it.
 *
 * @param {Object}      args
 * @param {string|null} args.blockName       Namespaced block name (e.g. `core/audio`),
 *                                           when known.
 * @param {string|null} args.blockTitle      Human-readable block title (e.g. `Audio`).
 * @param {Object}      args.governanceRules Resolved governance rules for the user.
 * @return {string} Message to show in the snackbar.
 */
export function buildBlockDenyMessage( { blockName, blockTitle, governanceRules } ) {
	const label = blockTitle || blockName || '';
	const defaultMessage = sprintf(
		// translators: %s is the block title or name, e.g. "Audio" or "core/audio".
		__( "The '%s' block is restricted by your site's governance rules." ),
		label
	);

	/**
	 * Customise the message shown when a block insertion is denied by governance rules.
	 *
	 * @param {string}      message         Default deny message.
	 * @param {string|null} blockName       Namespaced block name, when resolvable.
	 * @param {string|null} blockTitle      Human-readable block title, when resolvable.
	 * @param {Object}      governanceRules Resolved governance rules for the current user.
	 */
	return applyFilters(
		'vip_governance__deny_message',
		defaultMessage,
		blockName,
		blockTitle,
		governanceRules
	);
}
