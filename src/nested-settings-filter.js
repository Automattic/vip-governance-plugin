import { doesBlockNameMatchBlockWildcard } from './block-utils';
import { getNestedSetting, getNestedSettingPaths } from './nested-governance-loader';

/**
 * Build lookup maps for exact and wildcard block setting paths.
 *
 * @param {Object} nestedSettings Nested governance settings.
 * @return {{ exactPaths: Map, wildcardPaths: Map }} Setting path lookup maps.
 */
export function createNestedSettingPathMaps( nestedSettings ) {
	const exactPaths = new Map();
	const wildcardPaths = new Map();

	for ( const [ blockName, paths ] of Object.entries( getNestedSettingPaths( nestedSettings ) ) ) {
		const destination = blockName.includes( '*' ) ? wildcardPaths : exactPaths;
		destination.set( blockName, paths );
	}

	return { exactPaths, wildcardPaths };
}

/**
 * Return block names from the root ancestor through the current block.
 *
 * @param {string}   clientId        Current block client ID.
 * @param {Function} getBlockParents Block editor parent selector.
 * @param {Function} getBlockName    Block editor name selector.
 * @return {string[]} Block name path.
 */
export function getBlockNamePath( clientId, getBlockParents, getBlockName ) {
	const ancestorIds = [ ...getBlockParents( clientId, true ) ].reverse();
	return [ ...ancestorIds, clientId ].map( getBlockName );
}

/**
 * Resolve a governed setting for a block, preserving exact-rule precedence.
 *
 * @param {Object}   options                       Resolution options.
 * @param {*}        options.defaultValue          Original WordPress setting value.
 * @param {string}   options.path                  Setting path being requested.
 * @param {string}   options.clientId              Current block client ID.
 * @param {string}   options.blockName             Current block name.
 * @param {Object}   options.nestedSettings        Nested governance settings.
 * @param {Map}      options.exactPaths            Exact block path lookup.
 * @param {Map}      options.wildcardPaths         Wildcard block path lookup.
 * @param {Function} options.getBlockParents       Block editor parent selector.
 * @param {Function} options.getBlockName          Block editor name selector.
 * @return {*} Governed setting value or the original value when no rule applies.
 */
export function resolveNestedSetting( {
	defaultValue,
	path,
	clientId,
	blockName,
	nestedSettings,
	exactPaths,
	wildcardPaths,
	getBlockParents,
	getBlockName,
} ) {
	let matchedBlockName;

	if ( exactPaths.get( blockName )?.[ path ] === true ) {
		matchedBlockName = blockName;
	} else {
		matchedBlockName = [ ...wildcardPaths.entries() ].find(
			( [ candidate, paths ] ) =>
				doesBlockNameMatchBlockWildcard( blockName, candidate ) && paths[ path ] === true
		)?.[ 0 ];
	}

	if ( ! matchedBlockName ) {
		return defaultValue;
	}

	const blockNamePath = getBlockNamePath( clientId, getBlockParents, getBlockName );
	blockNamePath[ blockNamePath.length - 1 ] = matchedBlockName;

	const { value } = getNestedSetting( blockNamePath, path, nestedSettings );
	return value?.theme ?? value;
}
