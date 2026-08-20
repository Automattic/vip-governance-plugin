import { doesBlockNameMatchBlockWildcard } from './block-utils';

const ALLOWED_BLOCKS_KEY = 'allowedBlocks';

/**
 * Compile nested governance settings into candidates keyed by setting path.
 *
 * Each candidate retains its complete block hierarchy so wildcard ancestors
 * can be matched alongside exact block names at runtime.
 *
 * @param {Object} nestedSettings Nested governance settings.
 * @return {Map<string, Array>} Setting candidates keyed by normalized path.
 */
export function createNestedSettingRules( nestedSettings ) {
	const rulesByPath = new Map();
	let declarationOrder = 0;

	const addSetting = ( path, value, blockPatterns ) => {
		const candidates = rulesByPath.get( path ) ?? [];
		candidates.push( {
			blockPatterns,
			value,
			depth: blockPatterns.length,
			exactMatches: blockPatterns.filter( pattern => ! pattern.includes( '*' ) ).length,
			order: declarationOrder++,
		} );
		rulesByPath.set( path, candidates );
	};

	const visitSetting = ( value, path, blockPatterns ) => {
		addSetting( path, value, blockPatterns );

		if ( typeof value !== 'object' || value === null || Array.isArray( value ) ) {
			return;
		}

		for ( const [ key, childValue ] of Object.entries( value ) ) {
			visitSetting( childValue, `${ path }.${ key }`, blockPatterns );
		}
	};

	const visitBlocks = ( settings, blockPatterns = [] ) => {
		for ( const [ key, value ] of Object.entries( settings ) ) {
			if ( key === ALLOWED_BLOCKS_KEY ) {
				continue;
			}

			const isBlockPattern = key.includes( '/' ) || key === '*';
			if ( isBlockPattern ) {
				visitBlocks( value, [ ...blockPatterns, key ] );
			} else if ( blockPatterns.length > 0 ) {
				visitSetting( value, key, blockPatterns );
			}
		}
	};

	visitBlocks( nestedSettings );
	return rulesByPath;
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
 * Determine whether a rule hierarchy matches a block hierarchy.
 *
 * The final rule must match the current block. Earlier rule segments can match
 * any ancestor in order, preserving the existing descendant matching behavior.
 *
 * @param {string[]} blockPatterns Rule block hierarchy.
 * @param {string[]} blockNames    Actual block hierarchy.
 * @return {boolean} Whether the rule applies to the current block.
 */
export function doesBlockHierarchyMatch( blockPatterns, blockNames ) {
	if ( blockPatterns.length === 0 || blockNames.length === 0 ) {
		return false;
	}

	let patternIndex = blockPatterns.length - 1;
	let blockIndex = blockNames.length - 1;

	if (
		! doesBlockNameMatchBlockWildcard( blockNames[ blockIndex ], blockPatterns[ patternIndex ] )
	) {
		return false;
	}

	patternIndex--;
	blockIndex--;

	while ( patternIndex >= 0 ) {
		while (
			blockIndex >= 0 &&
			! doesBlockNameMatchBlockWildcard( blockNames[ blockIndex ], blockPatterns[ patternIndex ] )
		) {
			blockIndex--;
		}

		if ( blockIndex < 0 ) {
			return false;
		}

		patternIndex--;
		blockIndex--;
	}

	return true;
}

/**
 * Resolve the most specific governed setting for a block.
 *
 * Deeper hierarchies win. At equal depth, exact block names win over
 * wildcards. Equal-specificity rules retain declaration-order precedence.
 *
 * @param {Object}   options                       Resolution options.
 * @param {*}        options.defaultValue          Original WordPress setting value.
 * @param {string}   options.path                  Setting path being requested.
 * @param {string}   options.clientId              Current block client ID.
 * @param {Map}      options.rulesByPath           Compiled setting candidates.
 * @param {Function} options.getBlockParents       Block editor parent selector.
 * @param {Function} options.getBlockName          Block editor name selector.
 * @return {*} Governed setting value or the original value when no rule applies.
 */
export function resolveNestedSetting( {
	defaultValue,
	path,
	clientId,
	rulesByPath,
	getBlockParents,
	getBlockName,
} ) {
	const blockNames = getBlockNamePath( clientId, getBlockParents, getBlockName );
	const candidates = ( rulesByPath.get( path ) ?? [] ).filter( candidate =>
		doesBlockHierarchyMatch( candidate.blockPatterns, blockNames )
	);

	if ( candidates.length === 0 ) {
		return defaultValue;
	}

	const winner = candidates.reduce( ( currentWinner, candidate ) => {
		if ( candidate.depth !== currentWinner.depth ) {
			return candidate.depth > currentWinner.depth ? candidate : currentWinner;
		}

		if ( candidate.exactMatches !== currentWinner.exactMatches ) {
			return candidate.exactMatches > currentWinner.exactMatches ? candidate : currentWinner;
		}

		return candidate.order > currentWinner.order ? candidate : currentWinner;
	} );

	return winner.value?.theme ?? winner.value;
}
