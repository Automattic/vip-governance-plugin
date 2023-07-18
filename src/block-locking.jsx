/**
 * WordPress dependencies
 */
import { Disabled } from '@wordpress/components';
import { addFilter, applyFilters } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { isBlockAllowedInHierarchy } from './block-utils';

export function setupBlockLocking( governanceRules ) {
	const withDisabledBlocks = createHigherOrderComponent( BlockEdit => {
		return props => {
			const { name: blockName, clientId } = props;

			const { getBlockParents, getBlockAttributes, getBlockName } = select( blockEditorStore );
			const parentClientIds = getBlockParents(clientId, true);

			const isParentLocked = parentClientIds.some( parentClientId => {
				const parentAttributes = getBlockAttributes( parentClientId );

				return parentAttributes['vip-governance-locked'] === true;
			});

			if ( isParentLocked ) {
				// To avoid layout issues, only disable the outermost locked block
				return <BlockEdit { ...props } />;
			}

			const parentBlockNames = parentClientIds.map( parentClientId =>
				getBlockName( parentClientId )
			);

			let isAllowed = isBlockAllowedInHierarchy( blockName, parentBlockNames, governanceRules );

			isAllowed = applyFilters(
				'vip_governance__is_block_allowed_for_editing',
				isAllowed,
				blockName,
				parentBlockNames,
				governanceRules
			);

			if ( isAllowed ) {
				return <BlockEdit { ...props } />;
			} else {
				// Set 'vip-governance-locked' so that children can detect they're within an existing locked block
				props.setAttributes({ 'vip-governance-locked': true });

				return <>
					<Disabled>
						<div style={ { opacity: 0.6, 'background-color': '#eee', border: '2px dashed #999' } }>
							<BlockEdit { ...props } />
						</div>
					</Disabled>
				</>;
			}
		};
	}, 'withDisabledBlocks' );

	addFilter( 'editor.BlockEdit', 'wpcomvip-governance/with-disabled-blocks', withDisabledBlocks );
}
