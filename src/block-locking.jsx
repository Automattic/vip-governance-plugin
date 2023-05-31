import { Disabled } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';

export function setupBlockLocking( allowedBlocks ) {
	const withDisabledBlocks = createHigherOrderComponent( BlockEdit => {
		return props => {
			const isAllowed = allowedBlocks.includes( props.name );

			if ( isAllowed ) {
				return <BlockEdit { ...props } />;
			}
			return (
				<Disabled>
					<div style={ { opacity: 0.6, 'background-color': '#eee', border: '2px dashed #999' } }>
						<BlockEdit { ...props } />
					</div>
				</Disabled>
			);
		};
	}, 'withDisabledBlocks' );

	addFilter( 'editor.BlockEdit', 'wpcomvip-governance/with-disabled-blocks', withDisabledBlocks );

	const withLockAttribute = ( blockAttributes, blockType, innerHTML, attributes ) => {
		const isAllowed = allowedBlocks.includes( blockType );

		if ( isAllowed ) {
			return blockAttributes;
		}
		return {
			...blockAttributes,
			lock: {
				move: true,
				remove: true,
			},
		};
	};

	addFilter(
		'blocks.getBlockAttributes',
		'wpcomvip-governance/with-disabled-move',
		withLockAttribute,
	);
}
