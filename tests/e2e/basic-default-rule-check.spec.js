import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Site Works', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost( { legacyCanvas: true } );
	} );

	test( 'should confirm that only the blocks allowed are allowed to be inserted', async ( {
		editor,
		page,
	} ) => {
		// Verify that only the allowedBlocks can be inserted at the root level.
		const rootBlockInserter = page
			.getByRole( 'toolbar', { name: 'Document tools' } )
			.getByRole( 'button', { name: 'Toggle block inserter' } );
		const rootBlockLibrary = page.getByRole( 'region', {
			name: 'Block Library',
		} );
		await rootBlockInserter.click();
		await expect( rootBlockLibrary ).toBeVisible();
		await expect( rootBlockLibrary.getByRole( 'option' ) ).toHaveText( [
			'Paragraph',
			'Heading',
			'Image',
			'Media & Text',
		] );
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
			.getByRole( 'button', { name: 'Toggle block inserter' } );
		const nestedBlockLibrary = page.getByRole( 'region', {
			name: 'Block Library',
		} );
		await nestedBlockInserter.click();
		await expect( nestedBlockLibrary ).toBeVisible();
		await expect( nestedBlockLibrary.getByRole( 'option' ) ).toHaveText( [
			'Paragraph',
			'Heading',
			'Image',
			'Media & Text',
		] );
	} );

	test( 'should confirm that the governance rules are loaded as expected', async ( {
		editor,
		page,
		pageUtils,
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
		const rootTextColor = page
			.getByRole( 'region', {
				name: 'Editor settings',
			} )
			.getByRole( 'button', { name: 'Text' } );
		await rootTextColor.click();
		await pageUtils.pressKeys( 'Tab' );
		await pageUtils.pressKeys( 'Enter' );

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

		// Insert a media-text as that should be allowed as well.
		await editor.insertBlock( {
			name: 'core/media-text',
		} );
		await page.keyboard.press( 'ArrowUp' );
		const blockAppender = editor.canvas.getByRole( 'button', {
			name: 'Add block',
		} );
		await expect( blockAppender ).toBeVisible();
		await blockAppender.click();
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'Enter' );
		await page.keyboard.type( 'This is a heading inside a media-text' );

		await editor.openDocumentSettingsSidebar();
		const nestedTextColor = page
			.getByRole( 'region', {
				name: 'Editor settings',
			} )
			.getByRole( 'button', { name: 'Text' } );
		await nestedTextColor.click();
		await pageUtils.pressKeys( 'Tab' );
		await pageUtils.pressKeys( 'Enter' );

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
				isValid: true,
			},
			{
				name: 'core/paragraph',
				attributes: {
					content: 'This is a paragraph',
					dropCap: false,
				},
				innerBlocks: [],
				isValid: true,
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
						isValid: true,
					},
					{
						name: 'core/heading',
						attributes: {
							content: 'This is a heading inside a media-text',
							level: 2,
							textColor: 'custom-red',
						},
						innerBlocks: [],
						isValid: true,
					},
				],
			},
		] );
	} );
} );
