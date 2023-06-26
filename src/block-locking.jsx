/**
 * WordPress dependencies
 */
import { Disabled } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	store as blockEditorStore,
	privateApis as blockEditorPrivateApis,
} from '@wordpress/block-editor';

/**
 * Private WordPress dependencies
 */
import { __dangerousOptInToUnstableAPIsOnlyForCoreModules } from '@wordpress/private-apis';
export const { lock, unlock } =
	__dangerousOptInToUnstableAPIsOnlyForCoreModules(
		'I know using unstable features means my plugin or theme will inevitably break on the next WordPress release.',
		'@wordpress/block-editor' // Name of the package calling __dangerousOptInToUnstableAPIsOnlyForCoreModules,
		// (not the name of the package whose APIs you want to access)
	);

const { useBlockEditingMode } = unlock( blockEditorPrivateApis );

/**
 * Internal dependencies
 */
import { doesBlockNameMatchBlockRegex } from './block-utils';

export function setupBlockLocking( allowedBlocks ) {
	const withDisabledBlocks = createHigherOrderComponent( BlockEdit => {
		return props => {
			const blockName = props.name;
			const isAllowed = allowedBlocks.some( allowedBlock => doesBlockNameMatchBlockRegex( blockName, allowedBlock ) );

			if ( isAllowed ) {
				return <BlockEdit { ...props } />;
			} else {
				// Warning: Unstable API.
				// Valid block editing modes are 'disabled', 'contentOnly', or 'default':
				// https://github.com/WordPress/gutenberg/blob/075d937/packages/block-editor/src/components/block-editing-mode/index.js#L30-L36
				useBlockEditingMode( 'disabled' );

				return (
					<Disabled>
						<div style={ { opacity: 0.6, 'background-color': '#eee', border: '2px dashed #999' } }>
							<BlockEdit { ...props } />
						</div>
					</Disabled>
				);
			}
		};
	}, 'withDisabledBlocks' );

	addFilter( 'editor.BlockEdit', 'wpcomvip-governance/with-disabled-blocks', withDisabledBlocks );
}
