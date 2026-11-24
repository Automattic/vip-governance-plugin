import { initializeRulesViewer } from './rules-viewer';

const SETTINGS_MARKUP = `
	<select id="user-role-selector">
		<option value="">Any role</option>
		<option value="editor">Editor</option>
		<option value="author">Author</option>
	</select>
	<select id="post-type-selector">
		<option value="">Any post type</option>
		<option value="page">Page</option>
	</select>
	<button id="view-rules-button" type="button">View rules</button>
	<span class="vip-governance-query-spinner" hidden></span>
	<span id="vip-governance-preview-status" role="status" data-loading="Loading governance rules…" data-loaded="Governance rules loaded."></span>
	<pre id="json" data-error-message="Unable to load governance rules." hidden></pre>
`;

const roleSelector = () => document.getElementById( 'user-role-selector' ) as HTMLSelectElement;
const postTypeSelector = () => document.getElementById( 'post-type-selector' ) as HTMLSelectElement;
const viewButton = () => document.getElementById( 'view-rules-button' ) as HTMLButtonElement;
const spinner = () => document.querySelector( '.vip-governance-query-spinner' ) as HTMLElement;
const output = () => document.getElementById( 'json' ) as HTMLElement;
const status = () => document.getElementById( 'vip-governance-preview-status' ) as HTMLElement;

function deferredRequest() {
	let resolve!: ( value: unknown ) => void;
	let reject!: ( error: unknown ) => void;
	const promise = new Promise( ( _resolve, _reject ) => {
		resolve = _resolve;
		reject = _reject;
	} );
	return { promise, resolve, reject };
}

function select( control: HTMLSelectElement, value: string ) {
	control.value = value;
	control.dispatchEvent( new Event( 'change' ) );
}

function expectLoading( loading: boolean ) {
	expect( spinner().hidden ).toBe( ! loading );
	expect( spinner().classList.contains( 'is-active' ) ).toBe( loading );
}

describe( 'initializeRulesViewer', () => {
	beforeEach( () => {
		document.body.innerHTML = SETTINGS_MARKUP;
	} );

	it( 'only shows the action when at least one filter is selected', () => {
		initializeRulesViewer( { request: jest.fn() } );

		expect( viewButton().hidden ).toBe( true );
		roleSelector().value = 'editor';
		roleSelector().dispatchEvent( new Event( 'change' ) );

		expect( viewButton().hidden ).toBe( false );
	} );

	it( 'resets browser-restored selections during initialization', () => {
		roleSelector().value = 'editor';
		postTypeSelector().value = 'page';

		initializeRulesViewer( { request: jest.fn() } );

		expect( roleSelector().value ).toBe( '' );
		expect( postTypeSelector().value ).toBe( '' );
		expect( viewButton().hidden ).toBe( true );
	} );

	it( 'requests and displays rules for both selected filters', async () => {
		const request = jest.fn().mockResolvedValue( { allowedBlocks: [ 'core/paragraph' ] } );
		initializeRulesViewer( { request } );

		roleSelector().value = 'editor';
		postTypeSelector().value = 'page';
		viewButton().click();

		await Promise.resolve();
		await Promise.resolve();

		expect( request ).toHaveBeenCalledWith( {
			path: '/vip-governance/v1/rules?role=editor&postType=page',
		} );
		expect( output().textContent ).toBe(
			JSON.stringify( { allowedBlocks: [ 'core/paragraph' ] }, null, 4 )
		);
	} );

	it( 'displays a useful request error and restores the spinner', async () => {
		const request = jest.fn().mockRejectedValue( {
			code: 'rest_forbidden',
			message: 'Request failed',
		} );
		initializeRulesViewer( { request } );

		roleSelector().value = 'editor';
		viewButton().click();

		expect( spinner().hidden ).toBe( false );

		await Promise.resolve();
		await Promise.resolve();

		expect( output().textContent ).toBe( 'Request failed' );
		expect( status().textContent ).toBe( 'Request failed' );
		expectLoading( false );
	} );

	it( 'uses the localized fallback when a request has no error message', async () => {
		const request = jest.fn().mockRejectedValue( {} );
		initializeRulesViewer( { request } );

		roleSelector().value = 'editor';
		viewButton().click();

		await Promise.resolve();
		await Promise.resolve();

		expect( output().textContent ).toBe( 'Unable to load governance rules.' );
	} );

	it.each( [
		[ 'resolve', 'before' ],
		[ 'reject', 'before' ],
		[ 'resolve', 'after' ],
		[ 'reject', 'after' ],
	] as const )( 'ignores an older %s %s the current response', async ( settle, order ) => {
		const older = deferredRequest();
		const current = deferredRequest();
		initializeRulesViewer( {
			request: jest
				.fn()
				.mockReturnValueOnce( older.promise )
				.mockReturnValueOnce( current.promise ),
		} );

		select( roleSelector(), 'editor' );
		viewButton().click();
		select( roleSelector(), 'author' );
		viewButton().click();

		const staleResponse = settle === 'resolve' ? { role: 'editor' } : new Error( 'Stale response' );
		if ( order === 'before' ) {
			older[ settle ]( staleResponse );
			await Promise.resolve();
		}

		expect( output().hidden ).toBe( true );
		expect( output().textContent ).toBe( '' );
		expect( status().textContent ).toBe( 'Loading governance rules…' );
		expectLoading( true );

		current.resolve( { role: 'author' } );
		await Promise.resolve();

		if ( order === 'after' ) {
			older[ settle ]( staleResponse );
			await Promise.resolve();
		}

		expect( output().hidden ).toBe( false );
		expect( output().textContent ).toBe( JSON.stringify( { role: 'author' }, null, 4 ) );
		expect( status().textContent ).toBe( 'Governance rules loaded.' );
		expectLoading( false );
	} );

	it( 'treats repeated clicks with identical filters as separate requests', async () => {
		const first = deferredRequest();
		const retry = deferredRequest();
		initializeRulesViewer( {
			request: jest.fn().mockReturnValueOnce( first.promise ).mockReturnValueOnce( retry.promise ),
		} );

		select( roleSelector(), 'editor' );
		viewButton().click();
		viewButton().click();
		first.resolve( { result: 'Older rules' } );
		await Promise.resolve();

		expect( output().hidden ).toBe( true );
		expectLoading( true );
		retry.reject( new Error( 'Retry failed' ) );
		await Promise.resolve();

		expect( output().textContent ).toBe( 'Retry failed' );
		expect( status().textContent ).toBe( 'Retry failed' );
		expectLoading( false );
	} );

	it.each( [ 'change', 'clear' ] )( 'invalidates a pending preview on filter %s', async action => {
		const pending = deferredRequest();
		const request = jest.fn().mockReturnValue( pending.promise );
		initializeRulesViewer( { request } );

		select( postTypeSelector(), 'page' );
		viewButton().click();
		if ( action === 'change' ) {
			select( roleSelector(), 'author' );
		} else {
			select( postTypeSelector(), '' );
		}

		expect( viewButton().hidden ).toBe( action === 'clear' );
		expectLoading( false );
		pending.resolve( { postType: 'page' } );
		await Promise.resolve();

		expect( request ).toHaveBeenCalledTimes( 1 );
		expect( output().hidden ).toBe( true );
		expect( output().textContent ).toBe( '' );
		expect( status().textContent ).toBe( '' );
		expectLoading( false );
	} );

	it( 'clears completed output on a filter change or a retry with the same filters', async () => {
		const pending = deferredRequest();
		const request = jest
			.fn()
			.mockResolvedValueOnce( { role: 'editor' } )
			.mockReturnValueOnce( pending.promise );
		initializeRulesViewer( { request } );

		select( roleSelector(), 'editor' );
		viewButton().click();
		await Promise.resolve();
		expect( output().hidden ).toBe( false );

		viewButton().click();
		expect( output().hidden ).toBe( true );
		expect( output().textContent ).toBe( '' );
		expectLoading( true );
		pending.reject( new Error( 'Current error' ) );
		await Promise.resolve();
		expect( output().textContent ).toBe( 'Current error' );

		select( postTypeSelector(), 'page' );
		expect( output().hidden ).toBe( true );
		expect( output().textContent ).toBe( '' );
		expect( status().textContent ).toBe( '' );
		expect( request ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does nothing when the settings markup is not present', () => {
		document.body.innerHTML = '';

		expect( () => initializeRulesViewer( { request: jest.fn() } ) ).not.toThrow();
	} );
} );
