/**
 * WordPress dependencies
 */
import { Disabled } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { doesBlockNameMatchBlockRegex } from './block-utils';

export function setupBlockLocking( governanceRules ) {
	const withDisabledBlocks = createHigherOrderComponent( BlockEdit => {
		return props => {
			const { name: blockName, clientId } = props;

			const { getBlockParents, getBlockAttributes } = select( blockEditorStore );
			const parentClientIds = getBlockParents(clientId, true);

			const isParentLocked = parentClientIds.some( parentClientId => {
				const parentAttributes = getBlockAttributes( parentClientId );

				return parentAttributes['vip-governance-locked'] === true;
			});

			if ( isParentLocked ) {
				// To avoid layout issues, only disable the outermost locked block
				return <BlockEdit { ...props } />;
			}

			// ToDo: This doesn't support nested blocks if they aren't specified in the allowedChildren. That needs to be resolved.
			const isAllowed = governanceRules.allowedBlocks.some( allowedBlock => doesBlockNameMatchBlockRegex( blockName, allowedBlock ) || parentClientIds.length > 0 );

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

	if ( ! governanceRules.allowedFeatures || ! governanceRules.allowedFeatures.includes( 'moveBlocks' ) ) {
		// const withDisabledMove = ( blockAttributes, blockType, innerHTML, attributes ) => {
		// 	// ToDo: Make this overridable via a filter
		// 	const isAllowed = governanceRules.allowedBlocks.some( allowedBlock => doesBlockNameMatchBlockRegex( blockType, allowedBlock ) );

		// 	if ( isAllowed ) {
		// 		return blockAttributes;
		// 	}

		// 	const { lock } = blockAttributes;
		// 	const savedLock = lock ? lock : false;

		// 	return {
		// 		...blockAttributes,
		// 		lock: {
		// 			move: true,
		// 			remove: true,
		// 		},
		// 		'vip-governance-attribute-saved-lock': savedLock,
		// 	};
		// };

		// addFilter(
		// 	'blocks.getBlockAttributes',
		// 	'wpcomvip-governance/with-disabled-move',
		// 	withDisabledMove,
		// );

		const restoreSavedLockAttribute = ( element, blockType, attributes ) => {
			// skip if element is undefined
			if ( ! element ) {
				return;
			}

			// if ( attributes['vip-governance-attribute-saved-lock'] !== undefined ) {
			// 	console.log('element:', element);
			// 	console.log('attributes:', attributes);
			// 	console.log('--------')
			// }

			return element;
		};

		wp.hooks.addFilter(
			'blocks.getSaveElement',
			'wpcomvip-governance/restore-saved-lock',
			restoreSavedLockAttribute
		);

		// const restoreSavedLockAttribute = createHigherOrderComponent( BlockEdit => {
		// 	return props => {
		// 		const { name: blockName, clientId } = props;

		// 		// ToDo: This doesn't support nested blocks if they aren't specified in the allowedChildren. That needs to be resolved.
		// 		const isAllowed = true;

		// 		if ( isAllowed ) {
		// 			return <BlockEdit { ...props } />;
		// 		} else {
		// 			// Set 'vip-governance-locked' so that children can detect they're within an existing locked block
		// 			props.setAttributes({ 'vip-governance-locked': true });

		// 			return <>
		// 				<Disabled>
		// 					<div style={ { opacity: 0.6, 'background-color': '#eee', border: '2px dashed #999' } }>
		// 						<BlockEdit { ...props } />
		// 					</div>
		// 				</Disabled>
		// 			</>;
		// 		}
		// 	};
		// }, 'withDisabledBlocks' );

		// addFilter( 'editor.BlockEdit', 'wpcomvip-governance/restore-saved-lock', restoreSavedLockAttribute );

		// const restoreSavedLockAttribute = ( props, blockType, attributes ) => {
		// 	if ( attributes['vip-governance-attribute-saved-lock'] !== undefined ) {
		// 		console.log('props:', props);
		// 		console.log('attributes:', attributes);
		// 		console.log('--------')
		// 	}

		// 	return props;
		// };

		// addFilter(
		// 	'blocks.getSaveContent.extraProps',
		// 	'wpcomvip-governance/restore-saved-lock',
		// 	restoreSavedLockAttribute,
		// );
	}
}
