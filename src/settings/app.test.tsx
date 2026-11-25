import apiFetch from '@wordpress/api-fetch';
import { createRoot } from '@wordpress/element';
// React is supplied by @wordpress/element. Its test helper is not re-exported.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { SettingsApp, SettingsData } from './app';

jest.mock( '@wordpress/theme', () => ( {
	ThemeProvider: ( { children }: { children: React.ReactNode } ) => children,
} ) );

jest.mock( '@wordpress/api-fetch' );

jest.mock( 'uuid', () => ( { v4: () => 'test-id' } ) );

const settings: SettingsData = {
	enabled: true,
	error: false,
	warnings: [],
	source: '{"rules":[]}',
	roles: { '': 'My roles', editor: 'Editor', author: 'Author' },
	postTypes: { '': 'No post type selected', post: 'Post' },
	version: 'test',
};

async function flush( callback: () => void ) {
	await act( async () => {
		callback();
		await Promise.resolve();
	} );
}

function deferred() {
	let resolve!: ( value: unknown ) => void;
	let reject!: ( reason: unknown ) => void;
	const promise = new Promise( ( _resolve, _reject ) => {
		resolve = _resolve;
		reject = _reject;
	} );
	return { promise, resolve, reject };
}

describe( 'SettingsApp', () => {
	let container: HTMLDivElement;
	let root: ReturnType< typeof createRoot >;
	beforeEach( () => {
		jest.mocked( apiFetch ).mockReset();
		Object.assign( globalThis, { IS_REACT_ACT_ENVIRONMENT: true } );
		window.matchMedia = jest
			.fn()
			.mockReturnValue( { matches: false, addListener: jest.fn(), removeListener: jest.fn() } );
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
	} );
	afterEach( async () => {
		await flush( () => root.unmount() );
		container.remove();
	} );

	async function select( value: string ) {
		await flush( () => {
			const control = container.querySelector( 'select' ) as HTMLSelectElement;
			control.value = value;
			control.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	}
	async function clickPreview() {
		await flush( () => {
			const button = Array.from( container.querySelectorAll( 'button' ) ).find(
				item => item.textContent === 'View Rules'
			) as HTMLButtonElement;
			button.click();
		} );
	}

	it.each( [ 'resolve', 'reject' ] as const )(
		'ignores stale %s and clears changed selections',
		async settle => {
			const old = deferred();
			const current = deferred();
			const request = jest
				.mocked( apiFetch )
				.mockReturnValueOnce( old.promise )
				.mockReturnValueOnce( current.promise );
			await flush( () => root.render( <SettingsApp { ...settings } /> ) );
			await select( 'editor' );
			await clickPreview();
			await select( 'author' );
			expect( request.mock.calls[ 0 ][ 0 ].signal?.aborted ).toBe( true );
			await clickPreview();
			await flush( () => old[ settle ]( { message: 'Stale' } ) );
			expect( container.querySelector( '#json' ) ).toBeNull();
			expect( container.querySelector( '[role="status"]' )?.textContent ).toContain( 'Loading' );
			await flush( () => current.resolve( { allowedBlocks: [ 'core/heading' ] } ) );
			expect( container.querySelector( '#json' )?.textContent ).toContain( 'core/heading' );
			expect( request.mock.lastCall?.[ 0 ].path ).toBe( '/vip-governance/v1/rules?role=author' );
			expect( request.mock.lastCall?.[ 0 ].signal?.aborted ).toBe( false );
			await select( '' );
			expect( container.querySelector( '#json' ) ).toBeNull();
			expect( request ).toHaveBeenCalledTimes( 2 );
		}
	);

	it( 'keeps a current error after an older success and invalidates pending work on selection change', async () => {
		const old = deferred();
		const current = deferred();
		const pending = deferred();
		const request = jest
			.mocked( apiFetch )
			.mockReturnValueOnce( old.promise )
			.mockReturnValueOnce( current.promise )
			.mockReturnValueOnce( pending.promise );
		await flush( () => root.render( <SettingsApp { ...settings } /> ) );
		await select( 'editor' );
		await clickPreview();
		await clickPreview();
		expect( request.mock.calls[ 0 ][ 0 ].signal?.aborted ).toBe( true );
		await flush( () => current.reject( new Error( 'Current error' ) ) );
		await flush( () => old.resolve( { stale: true } ) );
		expect( container.textContent ).toContain( 'Current error' );
		expect( container.querySelector( '#json' ) ).toBeNull();
		await clickPreview();
		expect( container.textContent ).not.toContain( 'Current error' );
		await select( 'author' );
		await flush( () => pending.resolve( { stale: true } ) );
		expect( container.querySelector( '#json' ) ).toBeNull();
		expect( container.querySelector( '[role="status"]' )?.textContent ).toBe( '' );
	} );

	it( 'submits the existing checkbox value and escapes raw rules', async () => {
		await flush( () =>
			root.render( <SettingsApp { ...settings } source="<script>unsafe</script>" /> )
		);
		const checkbox = container.querySelector( 'input[type="checkbox"]' ) as HTMLInputElement;
		expect( checkbox.name ).toBe( 'vip-governance[is-enabled]' );
		expect( checkbox.value ).toBe( 'yes' );
		expect( checkbox.checked ).toBe( true );
		expect( container.querySelector( 'script' ) ).toBeNull();
		await flush( () => checkbox.click() );
		expect( checkbox.checked ).toBe( false );
	} );
} );
