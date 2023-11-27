/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

async function globalSetup( config ) {
	const { storageState } = config.projects[ 0 ].use;
	const storageStatePath = typeof storageState === 'string' ? storageState : undefined;

	const requestUtils = await RequestUtils.setup( {
		storageStatePath,
	} );

	// Authenticate and save the storageState to disk.
	await requestUtils.setupRest();

	// Reset the test environment before running the tests.
	await Promise.all( [
		requestUtils.deleteAllPosts(),
		requestUtils.deleteAllBlocks(),
		requestUtils.resetPreferences(),
	] );
}

export default globalSetup;
