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
		const withDisabledMove = ( blockAttributes, blockType, innerHTML, attributes ) => {
			// ToDo: Make this overridable via a filter
			const isAllowed = governanceRules.allowedBlocks.some( allowedBlock => doesBlockNameMatchBlockRegex( blockType, allowedBlock ) );

			if ( isAllowed ) {
				return blockAttributes;
			}

			const lockedBlockAttributes = {
				...blockAttributes,
				lock: {
					move: true,
					remove: true,
				}
			}

			const { lock: currentLock, 'vip-governance-saved-lock': savedLock } = blockAttributes;

			if ( ! savedLock ) {
				// First time running this filter. Save the block's existing lock.
				console.log('Saving lock on:', blockType.name);
				lockedBlockAttributes['vip-governance-saved-lock'] = currentLock;
			}

			return lockedBlockAttributes;
		};

		addFilter(
			'blocks.getBlockAttributes',
			'wpcomvip-governance/with-disabled-move',
			withDisabledMove,
		);

		// const withDisabledMove = createHigherOrderComponent( BlockEdit => {
		// 	return props => {
		// 		const { name: blockName, clientId, attributes, setAttributes } = props;

		// 		const isAllowed = governanceRules.allowedBlocks.some( allowedBlock => doesBlockNameMatchBlockRegex( blockName, allowedBlock ) );

		// 		if ( isAllowed ) {
		// 			return <BlockEdit { ...props } />;
		// 		} else {
		// 			// debug: Always set lock on load
		// 			if ( !( attributes.lock?.move === true && attributes.lock?.remove === true ) ) {
		// 				console.log('Adding lock to:', blockName);
		// 				setAttributes({
		// 					lock: {
		// 						move: true,
		// 						remove: true,
		// 					},
		// 				});
		// 			}

		// 			// const { lock: currentLock, 'vip-governance-saved-lock': savedLock } = attributes;

		// 			// if ( savedLock === undefined ) {
		// 			// 	console.log('Locking and storing existing lock for:', blockName, 'with value:', currentLock ? currentLock : false);
		// 			// 	// First time running this filter. Save the block's existing lock options.
		// 			// 	setAttributes({
		// 			// 		lock: {
		// 			// 			move: true,
		// 			// 			remove: true,
		// 			// 		},
		// 			// 		'vip-governance-saved-lock': currentLock ? currentLock : false,
		// 			// 	});
		// 			// } else {
		// 			// 	console.log(blockName, 'already has a savedLock');
		// 			// }

		// 			return <BlockEdit { ...props } />;
		// 		}
		// 	};
		// }, 'withDisabledMove' );

		// addFilter( 'editor.BlockEdit', 'wpcomvip-governance/with-disabled-move', withDisabledMove );

		const restoreSavedLockAttribute = ( element, blockType, attributes ) => {
			// skip if element is undefined
			if ( ! element ) {
				return;
			}

			// debug: Always remove lock on save (loops infinitely)
			console.log('Removing lock from:', blockType.name);
			// delete attributes['lock'];

			// if ( attributes['vip-governance-saved-lock'] === false ) {
			// 	// This block previously had no lock, so remove the lock attribute.
			// 	console.log('Removing role lock for:', blockType.name);
			// 	delete attributes['lock'];
			// } else if ( attributes['vip-governance-saved-lock'] ) {
			// 	// Restore the block's saved lock.
			// 	console.log('Restoring role lock for:', blockType.name, ':', attributes['vip-governance-saved-lock']);
			// 	attributes['lock'] = attributes['vip-governance-saved-lock'];
			// }

			return element;
		};

		wp.hooks.addFilter(
			'blocks.getSaveElement',
			'wpcomvip-governance/restore-saved-lock',
			restoreSavedLockAttribute
		);

		// const restoreSavedLockAttribute = ( props, blockType, attributes ) => {
		// 	if ( blockType.name === 'core/quote' ) {
		// 		console.log('blockType:', blockType.name);
		// 		console.log('props:', props);
		// 		console.log('attributes:', attributes);
		// 		console.log('--------');
		// 	}

		// 	delete attributes['lock'];

		// 	return props;
		// };

		// addFilter(
		// 	'blocks.getSaveContent.extraProps',
		// 	'wpcomvip-governance/restore-saved-lock',
		// 	restoreSavedLockAttribute,
		// );
	}
}
