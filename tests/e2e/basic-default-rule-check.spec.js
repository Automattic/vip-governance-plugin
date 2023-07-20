import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Site Works', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test.afterEach( async ( { admin } ) => {
		await admin.trashPost();
	} );

	test( 'should allow inserting everything due to the default rules', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( {
			name: 'core/heading',
		} );

		await page.keyboard.type( 'This is a heading' );

		await editor.insertBlock( {
			name: 'core/paragraph',
		} );

		await page.keyboard.type( 'This is a paragraph' );

		const firstBlockTagName = await editor.canvas.evaluate( () => {
			// eslint-disable-next-line no-undef
			return document.querySelector( '[data-block]' ).tagName;
		} );

		expect( firstBlockTagName ).toBe( 'P' );

		await editor.insertBlock( {
			name: 'core/quote',
		} );
	} );
} );
