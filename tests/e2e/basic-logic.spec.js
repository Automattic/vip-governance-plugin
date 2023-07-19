import { test, expect } from '@playwright/test';

test( 'site works', async ( { page } ) => {
	await page.goto( '/wp-login.php' );

	const heading = page.getByRole( 'heading', { name: 'Powered by WordPress' } );

	await expect( heading ).toBeVisible();
} );
