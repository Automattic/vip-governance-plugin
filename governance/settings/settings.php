<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use Exception;

class Settings {
	private static $governance_errors = null;

	public static function init() {
		try {
			GovernanceUtilities::get_governance_rules( WPCOMVIP_GOVERNANCE_RULES_FILENAME, true );
		} catch ( Exception $e ) {
			self::$governance_errors = $e->getMessage();
		}

		add_action( 'admin_menu', [ __CLASS__, 'governance_plugin_setup_menu' ] );
	}

	public static function governance_plugin_setup_menu() {
		add_menu_page( 'VIP Governance', 'VIP Governance', 'manage_options', 'vip-governance', [ __CLASS__, 'governance_init' ], 'dashicons-groups' );
	}

	public static function governance_init() {
		$governance_errors = self::$governance_errors;

		include __DIR__ . '/settings.view.php';
	}

}

Settings::init();
