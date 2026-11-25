import type { BlockName } from './types';

type BlockRuleMatch = boolean | RegExpMatchArray | null;
export type BlockWildcardMatch = ( blockName: BlockName, rule: string ) => BlockRuleMatch;

function compileWildcard( rule: string ): RegExp {
	// Keep compatibility with existing rules: only the first wildcard is expanded,
	// other regular-expression syntax remains active, and matching is not anchored.
	// This transforms trusted configuration, not untrusted input sanitization.
	// Expanding every '*' would change which blocks existing rules allow.
	// eslint-disable-next-line security/detect-non-literal-regexp
	return new RegExp( rule.replace( '*', '.*' ) ); // codeql[js/incomplete-sanitization]
}

function matchBlockRule(
	blockName: BlockName,
	rule: string,
	compile: typeof compileWildcard
): BlockRuleMatch {
	return rule.includes( '*' ) ? blockName.match( compile( rule ) ) : rule === blockName;
}

/** Match a block name using the plugin's legacy wildcard semantics. */
export function doesBlockNameMatchBlockWildcard(
	blockName: BlockName,
	rule: string
): BlockRuleMatch {
	return matchBlockRule( blockName, rule, compileWildcard );
}

/** Reuse compiled wildcard expressions within one editor setup. */
export class BlockWildcardMatcher {
	private readonly expressions = new Map< string, RegExp >();

	public readonly match: BlockWildcardMatch = ( blockName, rule ) =>
		matchBlockRule( blockName, rule, this.getExpression );

	private getExpression = ( rule: string ): RegExp => {
		let expression = this.expressions.get( rule );
		if ( ! expression ) {
			expression = compileWildcard( rule );
			this.expressions.set( rule, expression );
		}
		return expression;
	};
}

/** Return whether a block name matches at least one rule. */
export function isBlockAllowedByBlockWildcards(
	blockName: BlockName,
	rules: readonly string[],
	matchWildcard: BlockWildcardMatch = doesBlockNameMatchBlockWildcard
): boolean {
	return rules.some( rule => matchWildcard( blockName, rule ) );
}
