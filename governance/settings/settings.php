<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use Exception;

class Settings {
	public const MENU_SLUG              = 'vip-governance';
	public const OPTIONS_KEY            = 'vip-governance';
	public const OPTIONS_KEY_IS_ENABLED = 'is-enabled';

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
	}

	// Registration

	public static function register_settings() {
		register_setting( self::OPTIONS_KEY, self::OPTIONS_KEY, [ __CLASS__, 'validate_options' ] );

		$section_id = 'plugin-settings';
		add_settings_section( $section_id, __( 'Plugin Settings' ), '__return_null', self::MENU_SLUG );
		add_settings_field( self::OPTIONS_KEY_IS_ENABLED, 'Enable plugin', [ __CLASS__, 'render_is_enabled' ], self::MENU_SLUG, $section_id, [
			'label_for' => self::OPTIONS_KEY_IS_ENABLED,
		] );
	}

	public static function register_menu() {
		add_menu_page( 'VIP Governance', 'VIP Governance', 'manage_options', self::MENU_SLUG, [ __CLASS__, 'render' ], 'dashicons-groups' );
	}

	// Views

	public static function render() {
		$governance_errors = false;

		try {
			GovernanceUtilities::get_governance_rules( WPCOMVIP_GOVERNANCE_RULES_FILENAME, true );
		} catch ( Exception $e ) {
			$governance_errors = $e->getMessage();
		}

		include __DIR__ . '/settings.view.php';
	}

	// Settings handling

	public static function render_is_enabled() {
		$options = get_option( self::OPTIONS_KEY );

		$is_enabled = $options[ self::OPTIONS_KEY_IS_ENABLED ] ?? true;
		printf( '<input id="%1$s" name="%2$s[%1$s]" type="checkbox" value="yes" %3$s />', esc_attr( self::OPTIONS_KEY_IS_ENABLED ), esc_attr( self::OPTIONS_KEY ), checked( $is_enabled, true, false ) );
	}

	public static function validate_options( $options ) {
		$options[ self::OPTIONS_KEY_IS_ENABLED ] = 'yes' === $options[ self::OPTIONS_KEY_IS_ENABLED ];

		return $options;
	}

	// Public API

	public static function is_enabled() {
		$options = get_option( self::OPTIONS_KEY );
		return $options[ self::OPTIONS_KEY_IS_ENABLED ] ?? true;
	}
}

Settings::init();
