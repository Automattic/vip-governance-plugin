import apiFetch from '@wordpress/api-fetch';
import { createElement, createRoot } from '@wordpress/element';

import { SettingsApp, SettingsData } from './app';

const container = document.getElementById( 'vip-governance-settings' );
if ( container?.dataset.settings ) {
	createRoot( container ).render(
		createElement( SettingsApp, {
			settings: JSON.parse( container.dataset.settings ) as SettingsData,
			request: apiFetch,
		} )
	);
}
