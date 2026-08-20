import { initializeRulesViewer } from './rules-viewer';

const SETTINGS_MARKUP = `
	<select id="user-role-selector">
		<option value="">Any role</option>
		<option value="editor">Editor</option>
	</select>
	<select id="post-type-selector">
		<option value="">Any post type</option>
		<option value="page">Page</option>
	</select>
	<button id="view-rules-button" type="button">View rules</button>
	<span class="vip-governance-query-spinner" hidden></span>
	<pre id="combined-governance-rules-json" data-error-message="Unable to load governance rules." hidden></pre>
`;

describe( 'initializeRulesViewer', () => {
	beforeEach( () => {
		document.body.innerHTML = SETTINGS_MARKUP;
	} );

	it( 'only shows the action when at least one filter is selected', () => {
		initializeRulesViewer( { request: jest.fn() } );

		const roleSelector = document.getElementById( 'user-role-selector' );
		const viewButton = document.getElementById( 'view-rules-button' );

		expect( viewButton.hidden ).toBe( true );

		roleSelector.value = 'editor';
		roleSelector.dispatchEvent( new Event( 'change' ) );

		expect( viewButton.hidden ).toBe( false );
	} );

	it( 'resets browser-restored selections during initialization', () => {
		const roleSelector = document.getElementById( 'user-role-selector' );
		const postTypeSelector = document.getElementById( 'post-type-selector' );
		roleSelector.value = 'editor';
		postTypeSelector.value = 'page';

		initializeRulesViewer( { request: jest.fn() } );

		expect( roleSelector.value ).toBe( '' );
		expect( postTypeSelector.value ).toBe( '' );
		expect( document.getElementById( 'view-rules-button' ).hidden ).toBe( true );
	} );

	it( 'requests and displays rules for both selected filters', async () => {
		const request = jest.fn().mockResolvedValue( { allowedBlocks: [ 'core/paragraph' ] } );
		initializeRulesViewer( { request } );

		document.getElementById( 'user-role-selector' ).value = 'editor';
		document.getElementById( 'post-type-selector' ).value = 'page';
		document.getElementById( 'view-rules-button' ).click();

		await Promise.resolve();
		await Promise.resolve();

		expect( request ).toHaveBeenCalledWith( {
			path: '/vip-governance/v1/rules?role=editor&postType=page',
		} );
		expect( document.getElementById( 'combined-governance-rules-json' ).textContent ).toBe(
			JSON.stringify( { allowedBlocks: [ 'core/paragraph' ] }, null, 4 )
		);
	} );

	it( 'restores the controls and displays a useful request error', async () => {
		const request = jest.fn().mockRejectedValue( new Error( 'Request failed' ) );
		initializeRulesViewer( { request } );

		const roleSelector = document.getElementById( 'user-role-selector' );
		const viewButton = document.getElementById( 'view-rules-button' );
		const spinner = document.querySelector( '.vip-governance-query-spinner' );

		roleSelector.value = 'editor';
		viewButton.click();

		expect( viewButton.disabled ).toBe( true );
		expect( roleSelector.disabled ).toBe( true );
		expect( spinner.hidden ).toBe( false );

		await Promise.resolve();
		await Promise.resolve();

		expect( document.getElementById( 'combined-governance-rules-json' ).textContent ).toBe(
			'Request failed'
		);
		expect( viewButton.disabled ).toBe( false );
		expect( roleSelector.disabled ).toBe( false );
		expect( spinner.hidden ).toBe( true );
	} );

	it( 'uses the localized fallback when a request has no error message', async () => {
		const request = jest.fn().mockRejectedValue( {} );
		initializeRulesViewer( { request } );

		document.getElementById( 'user-role-selector' ).value = 'editor';
		document.getElementById( 'view-rules-button' ).click();

		await Promise.resolve();
		await Promise.resolve();

		expect( document.getElementById( 'combined-governance-rules-json' ).textContent ).toBe(
			'Unable to load governance rules.'
		);
	} );

	it( 'does nothing when the settings markup is not present', () => {
		document.body.innerHTML = '';

		expect( () => initializeRulesViewer( { request: jest.fn() } ) ).not.toThrow();
	} );
} );
