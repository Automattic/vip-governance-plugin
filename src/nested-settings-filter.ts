import { doesBlockNameMatchBlockWildcard } from './block-matching';

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
}

interface ResolveNestedSettingOptions
	extends Omit< ResolveForBlockNamesOptions, 'blockNames' >, BlockEditorSelectors {
	clientId: ClientId;
	currentBlockName?: BlockName;
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

function isAllowedBlocksList( value: unknown ): value is BlockName[] {
	return Array.isArray( value ) && value.every( item => typeof item === 'string' );
}

function parentRuleKeys( blockName: BlockName ): BlockName[] {
	return [ '*', `${ blockName.split( '/' )[ 0 ] }/*`, blockName ];
}

function ranksAtLeastAsHigh( rank: string, otherRank: string ): boolean {
	return (
		rank.length > otherRank.length || ( rank.length === otherRank.length && rank >= otherRank )
	);
}

/** Resolve an insertion/editing list without changing visual setting precedence. */
export function getNestedAllowedBlocks(
	parentBlockNames: BlockName[],
	settings: BlockSettings
): { allowedBlocks: BlockName[]; usesWildcardParent: boolean } {
	const { value } = getNestedSetting( parentBlockNames, ALLOWED_BLOCKS_KEY, settings );
	if ( isAllowedBlocksList( value ) ) {
		return { allowedBlocks: value, usesWildcardParent: false };
	}
	if ( parentBlockNames.length === 0 ) {
		return { allowedBlocks: [], usesWildcardParent: false };
	}

	// One digit per matched level, nearest parent first. Higher digits mean more specific keys.
	let candidates = new Map< BlockSettings, string >( [ [ settings, '' ] ] );
	for ( let index = parentBlockNames.length - 1; index > 0; index-- ) {
		const keys = parentRuleKeys( parentBlockNames[ index ] );
		const next = new Map< BlockSettings, string >();
		const remember = ( branch: BlockSettings, rank: string ) => {
			const previousRank = next.get( branch );
			if ( previousRank === undefined || ranksAtLeastAsHigh( rank, previousRank ) ) {
				// Retain the exact resolver's later-match order on ties.
				next.delete( branch );
				next.set( branch, rank );
			}
		};
		for ( const [ branch, rank ] of candidates ) {
			keys.forEach( ( key, specificity ) => {
				const nested = branch[ key ];
				if ( isRecord( nested ) ) {
					remember( nested, specificity + rank );
				}
			} );
			remember( branch, rank );
		}
		candidates = next;
	}

	const keys = parentRuleKeys( parentBlockNames[ 0 ] );
	let best = { rank: '', allowedBlocks: [] as BlockName[] };
	for ( const [ branch, rank ] of candidates ) {
		keys.forEach( ( key, specificity ) => {
			const nested = branch[ key ];
			const allowedBlocks = isRecord( nested ) ? nested[ ALLOWED_BLOCKS_KEY ] : undefined;
			const candidateRank = specificity + rank;
			if (
				isAllowedBlocksList( allowedBlocks ) &&
				ranksAtLeastAsHigh( candidateRank, best.rank )
			) {
				best = { rank: candidateRank, allowedBlocks };
			}
		} );
	}
	return { allowedBlocks: best.allowedBlocks, usesWildcardParent: true };
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
			doesBlockNameMatchBlockWildcard( currentBlockName, blockPattern )
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
}: ResolveNestedSettingOptions ): unknown {
	return resolveSettingForBlockNames( {
		blockNames: getBlockNamePath( clientId, getBlockParents, getBlockName, currentBlockName ),
		defaultValue,
		path,
		rules,
	} );
}
