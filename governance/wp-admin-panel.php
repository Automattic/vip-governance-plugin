<?php

namespace WPCOMVIP\Governance;

class Admin {
	
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'governance_plugin_setup_menu' ] );
	}

	public static function governance_plugin_setup_menu() {
		add_menu_page( 'VIP Governance', 'VIP Governance', 'manage_options', 'vip-governance', [ __CLASS__, 'governance_init' ] );
	}

	public static function governance_init() {
		// ToDo: Populate this page with the relevant info:
		// 1: Debug Info related to the plugin
		// 2: Schema Validation results
		// 3: Ability to turn off the plugin without re-deploying
		echo '<h1> VIP Governance </h1>';
	}
}

Admin::init();
