<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use Exception;

class Admin {
	private static $governance_errors = null;
	
	public static function init() {
		try {
			InitGovernance::get_governance_rules( WPCOMVIP_GOVERNANCE_RULES_FILENAME, true );
		} catch ( Exception $e ) {
			self::$governance_errors = $e->getMessage();
		}

		add_action( 'admin_menu', [ __CLASS__, 'governance_plugin_setup_menu' ] );
	}

	public static function governance_plugin_setup_menu() {
		add_menu_page( 'VIP Governance', 'VIP Governance', 'manage_options', 'vip-governance', [ __CLASS__, 'governance_init' ] );
	}

	public static function governance_init() {
		// ToDo items still left:
		// 1. Ability to turn off the plugin without re-deploying
		// 2: Prettify the HTML that's shown here
		echo '<div class="wrap">';
		echo '<h1>VIP Governance</h1>';
		echo '<h2> Debug Information </h2>';
		echo '<p>Plugin Version: ' . esc_html( WPCOMVIP__GOVERNANCE__PLUGIN_VERSION ) . '</p>';
		echo '<h2>Schema Validation Result:</h2>';
		// only print the governance_error if its not null, otherwise output no errors found
		if ( null === self::$governance_errors ) {
			echo '<p>No errors found</p>';
		} else {
			// escape and print the output of the governance_errors
			echo '<pre>' . esc_html( self::$governance_errors ) . '</pre>';
		}
		echo '</div>';
	}

}

Admin::init();
