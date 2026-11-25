import { createElement, createRoot } from '@wordpress/element';

import { SettingsApp } from './app';

import type { SettingsData } from './app';

const container = document.getElementById( 'vip-governance-settings' );
if ( container?.dataset.settings ) {
	const settings = JSON.parse( container.dataset.settings ) as SettingsData;
	createRoot( container ).render( createElement( SettingsApp, settings ) );
}
