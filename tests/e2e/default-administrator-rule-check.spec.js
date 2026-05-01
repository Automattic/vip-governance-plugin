import { expect, test } from '@wordpress/e2e-test-utils-playwright';

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

	test( 'should confirm that only the administrator and default block settings are picked, and applied correctly', async ( {
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

		// Insert a media-text, and a heading under it as that should be allowed as well.
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

		// Pick the custom red colour for the heading.
		await editor.openDocumentSettingsSidebar();
		const nestedTextColor = page
			.getByRole( 'region', {
				name: 'Editor settings',
			} )
			.getByRole( 'button', { name: 'Text' } );
		await nestedTextColor.click();
		await pageUtils.pressKeys( 'Tab' );
		await pageUtils.pressKeys( 'Enter' );

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

	test( "replaces Gutenberg's default deny-snackbar with a customisable governance message", async ( {
		page,
	} ) => {
		// Simulate Gutenberg dispatching its default deny snackbar (the same payload
		// Gutenberg's block inserter dispatches in @wordpress/block-editor's
		// use-block-types-state.js when canInsertBlockType returns false).
		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice(
				// eslint-disable-next-line quotes
				`Block "Audio" can't be inserted.`,
				{ type: 'snackbar', id: 'inserter-notice' }
			);
		} );

		// Our subscription should remove Gutenberg's snackbar and dispatch ours.
		// Scope to the snackbar component to avoid matching the a11y live region.
		await expect( page.getByTestId( 'snackbar' ) ).toHaveText(
			"The 'Audio' block is restricted by your site's governance rules."
		);

		const wpInserterNoticeStillPresent = await page.evaluate( () => {
			return window.wp.data
				.select( 'core/notices' )
				.getNotices()
				.some( notice => notice.id === 'inserter-notice' );
		} );
		expect( wpInserterNoticeStillPresent ).toBe( false );
	} );

	test( 'lets integrators override the deny message via the vip_governance__deny_message filter', async ( {
		page,
	} ) => {
		await page.evaluate( () => {
			window.wp.hooks.addFilter(
				'vip_governance__deny_message',
				'vip-governance-plugin/test-override',
				( _message, _blockName, blockTitle ) =>
					`Reach out to #content before using "${ blockTitle }".`
			);
			window.wp.data.dispatch( 'core/notices' ).createErrorNotice(
				// eslint-disable-next-line quotes
				`Block "Video" can't be inserted.`,
				{ type: 'snackbar', id: 'inserter-notice' }
			);
		} );

		await expect( page.getByTestId( 'snackbar' ) ).toHaveText(
			'Reach out to #content before using "Video".'
		);

		await page.evaluate( () => {
			window.wp.hooks.removeFilter(
				'vip_governance__deny_message',
				'vip-governance-plugin/test-override'
			);
		} );
	} );
} );
