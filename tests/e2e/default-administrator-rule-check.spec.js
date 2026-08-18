import { expect, test } from '@wordpress/e2e-test-utils-playwright';

async function openTextColorPicker( page ) {
	// WordPress 6.8 labels this control "Text". Current WordPress versions label
	// the typography control "Color" and also expose a later background control.
	const legacyTextControl = page.getByText( 'Text', { exact: true } );
	const modernColorControl = page
		.getByRole( 'region', { name: 'Editor settings' } )
		.getByRole( 'button', { name: 'Color', exact: true } )
		.first();

	await legacyTextControl.or( modernColorControl ).first().click();
}

test.describe( 'Role/Post Type - Default, Administrator and Post Rules Flow', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost( { legacyCanvas: true } );
	} );

	test( 'should confirm that only the administrator and default allowedBlocks are allowed to be inserted', async ( {
		editor,
		page,
	} ) => {
		// Verify that only the allowedBlocks can be inserted at the root level.
		const rootBlockInserter = page
			.getByRole( 'toolbar', { name: 'Document tools' } )
			.getByRole( 'button', { name: 'Block Inserter' } );
		const rootBlockLibrary = page.getByRole( 'region', {
			name: 'Block Library',
		} );
		await rootBlockInserter.click();
		await expect( rootBlockLibrary ).toBeVisible();
		await Promise.all(
			[ 'Paragraph', 'Heading', 'Image', 'Media & Text' ].map( blockName =>
				expect(
					rootBlockLibrary.getByRole( 'option', {
						name: blockName,
						exact: true,
					} )
				).toBeVisible()
			)
		);

		// WordPress 6.8+ displays disallowed blocks, but rejects their insertion.
		await editor.insertBlock( { name: 'core/list' } );
		await expect
			.poll( async () => ( await editor.getBlocks() ).map( ( { name } ) => name ) )
			.not.toContain( 'core/list' );
		await rootBlockInserter.click();

		await editor.insertBlock( {
			name: 'core/media-text',
			innerBlocks: [
				{
					name: 'core/paragraph',
					attributes: {
						attributes: { placeholder: 'Add a description' },
					},
				},
			],
		} );

		// Select the media-text inner block.
		await editor.canvas
			.getByRole( 'document', {
				name: 'Empty block',
			} )
			.click();

		// Verify that only the allowedBlocks can be inserted within the media-text.
		const nestedBlockInserter = page
			.getByRole( 'toolbar', { name: 'Document tools' } )
			.getByRole( 'button', { name: 'Block Inserter' } );
		const nestedBlockLibrary = page.getByRole( 'region', {
			name: 'Block Library',
		} );
		await nestedBlockInserter.click();
		await expect( nestedBlockLibrary ).toBeVisible();
		await Promise.all(
			[ 'Paragraph', 'Heading', 'Image', 'Media & Text' ].map( blockName =>
				expect(
					nestedBlockLibrary.getByRole( 'option', {
						name: blockName,
						exact: true,
					} )
				).toBeVisible()
			)
		);

		const [ mediaText ] = ( await editor.getBlocks( { full: true } ) ).filter(
			( { name } ) => name === 'core/media-text'
		);
		await editor.insertBlock( { name: 'core/list' }, { clientId: mediaText.clientId } );
		await expect
			.poll( async () => {
				const [ currentMediaText ] = ( await editor.getBlocks() ).filter(
					( { name } ) => name === 'core/media-text'
				);
				return currentMediaText.innerBlocks.map( ( { name } ) => name );
			} )
			.not.toContain( 'core/list' );
	} );

	test( 'should confirm that only the administrator and default block settings are picked, and applied correctly', async ( {
		editor,
		page,
	} ) => {
		// Insert a heading block first, as that should be allowed.
		await editor.insertBlock( {
			name: 'core/heading',
			attributes: {
				content: 'This is a heading',
				level: 2,
			},
		} );

		// Change the colour of the heading to be the custom yellow that we have defined.
		await editor.openDocumentSettingsSidebar();
		await openTextColorPicker( page );
		await page.locator( 'button[aria-label="Custom yellow"]' ).click();

		// Lock the heading.
		await editor.clickBlockOptionsMenuItem( 'Lock' );
		await page.click( 'role=checkbox[name="Lock all"]' );
		await page.click( 'role=button[name="Apply"]' );

		// Insert a paragraph block next, as that should be allowed too.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: {
				content: 'This is a paragraph',
			},
		} );

		// Insert a media-text, and a heading under it as that should be allowed as well.
		await editor.insertBlock( { name: 'core/media-text' } );
		const [ mediaText ] = ( await editor.getBlocks( { full: true } ) ).filter(
			( { name } ) => name === 'core/media-text'
		);
		await editor.insertBlock(
			{
				name: 'core/heading',
				attributes: {
					content: 'This is a heading inside a media-text',
					level: 2,
				},
			},
			{ clientId: mediaText.clientId }
		);
		await expect
			.poll( async () =>
				( await editor.getBlocks( { clientId: mediaText.clientId } ) ).map( ( { name } ) => name )
			)
			.toContain( 'core/heading' );
		const [ nestedHeadingBlock ] = (
			await editor.getBlocks( { clientId: mediaText.clientId, full: true } )
		).filter( ( { name } ) => name === 'core/heading' );
		await page.evaluate( clientId => {
			globalThis.wp.data.dispatch( 'core/block-editor' ).selectBlock( clientId );
		}, nestedHeadingBlock.clientId );

		// Pick the custom red colour for the heading.
		await editor.openDocumentSettingsSidebar();
		await openTextColorPicker( page );
		await page.locator( 'button[aria-label="Custom red"]' ).click();

		// Verify all the settings are exactly like what we expect.
		await expect.poll( editor.getBlocks ).toMatchObject( [
			{
				name: 'core/heading',
				attributes: {
					content: 'This is a heading',
					level: 2,
					lock: {
						move: true,
						remove: true,
					},
					textColor: 'custom-yellow',
				},
				innerBlocks: [],
			},
			{
				name: 'core/paragraph',
				attributes: {
					content: 'This is a paragraph',
					dropCap: false,
				},
				innerBlocks: [],
			},
			{
				name: 'core/media-text',
				attributes: {
					align: 'none',
					isStackedOnMobile: true,
					mediaAlt: '',
					mediaPosition: 'left',
					mediaWidth: 50,
				},
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: {
							content: '',
							dropCap: false,
							placeholder: 'Content…',
						},
						innerBlocks: [],
					},
					{
						name: 'core/heading',
						attributes: {
							content: 'This is a heading inside a media-text',
							level: 2,
							textColor: 'custom-red',
						},
						innerBlocks: [],
					},
				],
			},
		] );

		// Verify if the CSS was actually applied.
		const frame = page.frame( 'editor-canvas' );
		const rootHeading = frame.locator( 'text="This is a heading"' );
		await expect( rootHeading ).toHaveCSS( 'color', 'rgb(255, 255, 0)' );

		const nestedHeading = frame.locator( 'text="This is a heading inside a media-text"' );
		await expect( nestedHeading ).toHaveCSS( 'color', 'rgb(255, 0, 0)' );
	} );
} );
