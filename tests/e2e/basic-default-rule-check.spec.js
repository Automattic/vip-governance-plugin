import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Site Works', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost( { legacyCanvas: true } );
	} );

	test( 'should allow inserting everything due to the default rules', async ( {
		editor,
		page,
	} ) => {
		// Insert a heading block, and lock it as that should be allowed.
		await editor.insertBlock( {
			name: 'core/heading',
		} );

		await page.keyboard.type( 'This is a heading' );

		await editor.clickBlockOptionsMenuItem( 'Lock' );

		await page.click( 'role=checkbox[name="Lock all"]' );
		await page.click( 'role=button[name="Apply"]' );

		// Insert a paragraph block next, as that should be allowed too.
		await editor.insertBlock( {
			name: 'core/paragraph',
		} );

		await page.keyboard.type( 'This is a paragraph' );

		// Lastly, insert a quote block next, as that should be allowed too.
		await editor.insertBlock( {
			name: 'core/quote',
		} );

		await page.keyboard.type( 'This is a paragraph within a quote' );

		await editor.openDocumentSettingsSidebar();

		const textColor = page
			.getByRole( 'region', {
				name: 'Editor settings',
			} )
			.getByRole( 'button', { name: 'Text' } );

		await textColor.click();

		await page
			.getByRole( 'option', {
				name: 'Color: Custom green',
			} )
			.click();

		// Close the popover.
		await textColor.click();

		// Verify that the content is as expected, including the locked blocks.
		await expect.poll( editor.getEditedPostContent ).toBe(
			`<!-- wp:heading {"lock":{"move":true,"remove":true}} -->
<h2 class="wp-block-heading">This is a heading</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This is a paragraph</p>
<!-- /wp:paragraph -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>This is a paragraph within a quote</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->`
		);
	} );
} );
