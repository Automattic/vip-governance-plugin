import { get } from 'lodash';
import { addFilter } from '@wordpress/hooks';
import { select, dispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as noticeStore } from '@wordpress/notices';

function setup() {
	if ( VIP_GOVERNANCE.errors ) {
		dispatch( noticeStore ).createErrorNotice( VIP_GOVERNANCE.errors, {
			isDismissible: true,
		} );

		return;
	}

	const insertionRules = VIP_GOVERNANCE.insertionRules;
	const nestedSettings = VIP_GOVERNANCE.nestedSettings;
	const nestedSettingPaths = getNestedSettingPaths( nestedSettings );

	addFilter(
		'blockEditor.__unstableCanInsertBlockType',
		`wpcomvip-governance/block-insertion`,
		( canInsert, blockType, rootClientId, { getBlock } ) => {
			return isBlockAllowed( canInsert, blockType, rootClientId, insertionRules, {
				getBlock,
			} );
		},
	);

	addFilter(
		'blockEditor.useSetting.before',
		`wpcomvip-governance/nested-block-settings`,
		( result, path, clientId, blockName ) => {
			const hasCustomSetting =
				// eslint-disable-next-line security/detect-object-injection
				nestedSettingPaths[ blockName ] !== undefined &&
				// eslint-disable-next-line security/detect-object-injection
				nestedSettingPaths[ blockName ][ path ] === true;

			if ( result !== undefined || ! hasCustomSetting ) {
				return result;
			}

			const blockNamePath = [
				clientId,
				...select( blockEditorStore ).getBlockParents( clientId, /* ascending */ true ),
			]
				.map( candidateId => select( blockEditorStore ).getBlockName( candidateId ) )
				.reverse();

			( { value: result } = getNestedSetting( blockNamePath, path, nestedSettings ) );

			return result;
		},
	);
}

const isBlockAllowed = ( canInsert, blockType, rootClientId, insertionRules, { getBlock } ) => {
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

const isRootBlockAllowed = ( blockName, rules, isInAllowedMode ) => {
	if ( isInAllowedMode ) {
		return rules.some( rule => rule.blockName === blockName );
	}

	return ! rules.some( rule => rule.blockName === blockName && ! rule.children );
};

const getNestedSettingPaths = ( nestedSettings, nestedMetadata = {}, currentBlock = false ) => {
	for ( const [ settingKey, settingValue ] of Object.entries( nestedSettings ) ) {
		const isNestedBlock = settingKey.includes( '/' );

		if ( isNestedBlock ) {
			// This setting contains another block, look at child for metadata
			Object.entries( nestedSettings ).forEach( ( [ blockName, blockNestedSettings ] ) => {
				getNestedSettingPaths( blockNestedSettings, nestedMetadata, blockName );
			} );
		} else if ( currentBlock !== false ) {
			// This is a leaf block, add setting paths to nestedMetadata
			const settingPaths = flattenSettingPaths( settingValue, `${ settingKey }.` );

			// eslint-disable-next-line security/detect-object-injection
			nestedMetadata[ currentBlock ] = {
				// eslint-disable-next-line security/detect-object-injection
				...( nestedMetadata[ currentBlock ] ?? {} ),
				...settingPaths,
			};
		}
	}

	return nestedMetadata;
};

const flattenSettingPaths = ( settings, prefix = '' ) => {
	const result = {};

	Object.entries( settings ).forEach( ( [ key, value ] ) => {
		const isRegularObject = typeof value === 'object' && !! value && ! Array.isArray( value );

		if ( isRegularObject ) {
			result[ `${ prefix }${ key }` ] = true;
			Object.assign( result, flattenSettingPaths( value, `${ prefix }${ key }.` ) );
		} else {
			result[ `${ prefix }${ key }` ] = true;
		}
	} );

	return result;
};

/**
 * Find block settings nested in other block settings.
 *
 * Given an array of blocks names from the top level of the editor to the
 * current block (`blockNamePath`), return the value for the deepest-nested
 * settings value that applies to the current block.
 *
 * If two setting values share the same nesting depth, use the last one that
 * occurs in settings (like CSS).
 *
 * @param {string[]} blockNamePath  Block names representing the path to the
 *                                  current block from the top level of the
 *                                  block editor.
 * @param {string}   normalizedPath Path to the setting being retrieved.
 * @param {Object}   settings       Object containing all block settings.
 * @param {Object}   result         Optional. Object with keys `depth` and
 *                                  `value` used to track current most-nested
 *                                  setting.
 * @param {number}   depth          Optional. The current recursion depth used
 *                                  to calculate the most-nested setting.
 * @return {Object}                 Object with keys `depth` and `value`.
 *                                  Destructure the `value` key for the result.
 */
const getNestedSetting = (
	blockNamePath,
	normalizedPath,
	settings,
	result = { depth: 0, value: undefined },
	depth = 1,
) => {
	const [ currentBlockName, ...remainingBlockNames ] = blockNamePath;
	// eslint-disable-next-line security/detect-object-injection
	const blockSettings = settings[ currentBlockName ];

	if ( remainingBlockNames.length === 0 ) {
		const settingValue = get( blockSettings, normalizedPath );

		if ( settingValue !== undefined && depth >= result.depth ) {
			result.depth = depth;
			result.value = settingValue;
		}

		return result;
	} else if ( blockSettings !== undefined ) {
		// Recurse into the parent block's settings
		result = getNestedSetting(
			remainingBlockNames,
			normalizedPath,
			blockSettings,
			result,
			depth + 1,
		);
	}

	// Continue down the array of blocks
	return getNestedSetting( remainingBlockNames, normalizedPath, settings, result, depth );
};

setup();
