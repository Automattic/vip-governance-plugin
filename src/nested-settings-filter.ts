import { doesBlockNameMatchBlockWildcard } from './block-matching';

import type { BlockWildcardMatch } from './block-matching';
import type { BlockEditorSelectors, BlockName, BlockSettings, ClientId } from './types';

const ALLOWED_BLOCKS_KEY = 'allowedBlocks';

export interface NestedSettingRules {
	pathsByBlock: Map< BlockName, Set< string > >;
	settings: BlockSettings;
}

interface ResolveForBlockNamesOptions {
	blockNames: BlockName[];
	defaultValue: unknown;
	path: string;
	rules: NestedSettingRules;
	matchWildcard?: BlockWildcardMatch;
}

interface ResolveNestedSettingOptions
	extends Omit< ResolveForBlockNamesOptions, 'blockNames' | 'rules' >, BlockEditorSelectors {
	clientId: ClientId;
	currentBlockName?: BlockName;
	rules?: NestedSettingRules | null;
}

export interface NestedSettingResult {
	depth: number;
	value: unknown;
}

function isRecord( value: unknown ): value is Record< string, unknown > {
	return typeof value === 'object' && value !== null && ! Array.isArray( value );
}

function addSettingPaths( value: unknown, prefix: string, paths: Set< string > ): void {
	if ( typeof value !== 'object' || value === null ) {
		return;
	}

	for ( const [ key, childValue ] of Object.entries( value ) ) {
		const path = `${ prefix }${ key }`;
		paths.add( path );

		if ( isRecord( childValue ) ) {
			addSettingPaths( childValue, `${ path }.`, paths );
		}
	}
}

/** Index visual/editor setting paths by their final block name or wildcard. */
export function createNestedSettingRules( settings: BlockSettings ): NestedSettingRules {
	const pathsByBlock: NestedSettingRules[ 'pathsByBlock' ] = new Map();

	const visit = ( nestedSettings: BlockSettings, currentBlock?: BlockName ) => {
		for ( const [ key, value ] of Object.entries( nestedSettings ) ) {
			if ( key === ALLOWED_BLOCKS_KEY ) {
				continue;
			}

			if ( ( key.includes( '/' ) || key === '*' ) && isRecord( value ) ) {
				visit( value, key );
			} else if ( currentBlock ) {
				const paths = pathsByBlock.get( currentBlock ) ?? new Set();
				addSettingPaths( value, `${ key }.`, paths );
				pathsByBlock.set( currentBlock, paths );
			}
		}
	};

	visit( settings );
	return { pathsByBlock, settings };
}

function deepGet( value: unknown, path: string | string[] ): unknown {
	const keys = Array.isArray( path )
		? path
		: path
				.replace( /(\[(\d)\])/g, '.$2' )
				.replace( /^\./, '' )
				.split( '.' );

	for ( const key of keys ) {
		if ( typeof value !== 'object' || value === null || ! ( key in value ) ) {
			return undefined;
		}
		value = ( value as Record< string, unknown > )[ key ];
	}
	return value;
}

/** Resolve an exact-name setting from a nearest-block-first hierarchy. */
export function getNestedSetting(
	blockNamePath: BlockName[],
	normalizedPath: string,
	settings: BlockSettings,
	result: NestedSettingResult = { depth: 0, value: undefined },
	depth = 1
): NestedSettingResult {
	if ( blockNamePath.length === 0 ) {
		return result;
	}

	let candidates = new Map< BlockSettings, number >( [ [ settings, depth ] ] );
	for ( let index = blockNamePath.length - 1; index > 0; index-- ) {
		const next = new Map< BlockSettings, number >();
		const remember = ( branch: BlockSettings, branchDepth: number ) => {
			const previousDepth = next.get( branch );
			if ( previousDepth === undefined || branchDepth >= previousDepth ) {
				// Keep the later occurrence when equally deep paths reach the same rule.
				next.delete( branch );
				next.set( branch, branchDepth );
			}
		};
		for ( const [ branch, branchDepth ] of candidates ) {
			const nested = branch[ blockNamePath[ index ] ];
			if ( isRecord( nested ) ) {
				remember( nested, branchDepth + 1 );
			}
			// Matching before skipping preserves trunk's later-match tie breaking.
			remember( branch, branchDepth );
		}
		candidates = next;
	}

	for ( const [ branch, branchDepth ] of candidates ) {
		const candidate = branch[ blockNamePath[ 0 ] ];
		const value = deepGet( isRecord( candidate ) ? candidate : undefined, normalizedPath );
		if ( value !== undefined && branchDepth >= result.depth ) {
			result = { depth: branchDepth, value };
		}
	}
	return result;
}

/** Return block names from the current block through the root ancestor. */
export function getBlockNamePath(
	clientId: ClientId,
	getBlockParents: BlockEditorSelectors[ 'getBlockParents' ],
	getBlockName: BlockEditorSelectors[ 'getBlockName' ],
	currentBlockName?: BlockName
): BlockName[] {
	return [
		currentBlockName ?? getBlockName( clientId ),
		...getBlockParents( clientId, true ).map( getBlockName ),
	].filter( ( blockName ): blockName is BlockName => blockName !== undefined );
}

function unwrapThemeValue( value: unknown ): unknown {
	return isRecord( value ) && value.theme ? value.theme : value;
}

/** Resolve a governed value for an already resolved block-name hierarchy. */
export function resolveSettingForBlockNames( {
	blockNames,
	defaultValue,
	path,
	rules,
	matchWildcard = doesBlockNameMatchBlockWildcard,
}: ResolveForBlockNamesOptions ): unknown {
	const currentBlockName = blockNames[ 0 ];

	if ( ! currentBlockName ) {
		return defaultValue;
	}

	if ( rules.pathsByBlock.get( currentBlockName )?.has( path ) ) {
		return unwrapThemeValue( getNestedSetting( blockNames, path, rules.settings ).value );
	}

	for ( const [ blockPattern, settingPaths ] of rules.pathsByBlock ) {
		if (
			blockPattern.includes( '*' ) &&
			settingPaths.has( path ) &&
			matchWildcard( currentBlockName, blockPattern )
		) {
			const normalizedBlockNames = [ ...blockNames ];
			normalizedBlockNames[ 0 ] = blockPattern;
			return unwrapThemeValue(
				getNestedSetting( normalizedBlockNames, path, rules.settings ).value
			);
		}
	}

	return defaultValue;
}

/** Resolve the legacy-precedence setting for a block editor client ID. */
export function resolveNestedSetting( {
	clientId,
	currentBlockName,
	defaultValue,
	getBlockName,
	getBlockParents,
	path,
	rules,
	matchWildcard,
}: ResolveNestedSettingOptions ): unknown {
	if ( ! rules || rules.pathsByBlock.size === 0 ) {
		return defaultValue;
	}

	return resolveSettingForBlockNames( {
		blockNames: getBlockNamePath( clientId, getBlockParents, getBlockName, currentBlockName ),
		defaultValue,
		path,
		rules,
		matchWildcard,
	} );
}
