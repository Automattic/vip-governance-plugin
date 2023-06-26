/**
 * WordPress dependencies
 */
import { Disabled } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as blockEditorStore, privateApis } from '@wordpress/block-editor';
import { select } from '@wordpress/data';

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

const { useBlockEditingMode } = unlock( privateApis );

/**
 * Internal dependencies
 */
import { doesBlockNameMatchBlockRegex } from './block-utils';

export function setupBlockLocking( allowedBlocks ) {
	const withDisabledBlocks = createHigherOrderComponent( BlockEdit => {
		return props => {
			const { name: blockName, clientId } = props;

			const { getBlockParents, getBlockAttributes } = select( blockEditorStore );
			const parentClientIds = getBlockParents(clientId, true);
			const isParentLocked = parentClientIds.some( parentClientId => {
				const parentAttributes = getBlockAttributes( parentClientId );

				return parentAttributes['vip-locked'] === true;
			});

			if ( isParentLocked ) {
				// To avoid layout issues, only disable the outermost locked block
				return <BlockEdit { ...props } />;
			}

			const isAllowed = allowedBlocks.some( allowedBlock => doesBlockNameMatchBlockRegex( blockName, allowedBlock ) );

			if ( isAllowed ) {
				return <BlockEdit { ...props } />;
			} else {
				// Warning: Unstable API.
				// Valid block editing modes are 'disabled', 'contentOnly', or 'default':
				// https://github.com/WordPress/gutenberg/blob/075d937/packages/block-editor/src/components/block-editing-mode/index.js#L30-L36
				useBlockEditingMode( 'disabled' );

				// Set 'vip-locked' so that children can detect they're within an existing locked block
				props.setAttributes({ 'vip-locked': true });

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
