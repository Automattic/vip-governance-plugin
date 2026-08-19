import { expect, test } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Governance settings page', () => {
	test( 'loads combined rules for the selected role and post type', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=vip-block-governance' );

		const roleSelector = page.getByLabel( 'User role' );
		const postTypeSelector = page.getByLabel( 'Post type' );
		const viewButton = page.getByRole( 'button', { name: 'View Rules' } );
		const output = page.locator( '#combined-governance-rules-json' );

		await expect( viewButton ).toBeHidden();
		await roleSelector.selectOption( 'administrator' );
		await postTypeSelector.selectOption( 'post' );
		await expect( viewButton ).toBeVisible();

		await viewButton.click();

		await expect( output ).toBeVisible();
		await expect( output ).toContainText( '"allowedBlocks"' );
		await expect( roleSelector ).toBeEnabled();
		await expect( postTypeSelector ).toBeEnabled();
		await expect( viewButton ).toBeEnabled();
	} );
} );
