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
		add_settings_field( self::OPTIONS_KEY_IS_ENABLED, __( 'Enable governance' ), [ __CLASS__, 'render_is_enabled' ], self::MENU_SLUG, $section_id, [
			'label_for' => self::OPTIONS_KEY_IS_ENABLED,
		] );
	}

	public static function register_menu() {
		$hook = add_menu_page( 'VIP Governance', 'VIP Governance', 'manage_options', self::MENU_SLUG, [ __CLASS__, 'render' ], 'dashicons-groups' );
		add_action( 'load-' . $hook, [ __CLASS__, 'enqueue_resources' ] );
	}

	// Resources

	public static function enqueue_resources() {
		wp_enqueue_style(
			'wpcomvip-governance-settings',
			__DIR__ . '/settings.css',
			/* dependencies */ [],
			WPCOMVIP__GOVERNANCE__PLUGIN_VERSION
		);
	}

	// Views

	public static function render() {
		$governance_rules_json = GovernanceUtilities::get_governance_rules_json();
		$governance_rules      = GovernanceUtilities::get_parsed_governance_rules();
		$governance_error      = false;

		if ( is_wp_error( $governance_rules ) ) {
			$governance_error = $governance_rules->get_error_message();
		}

		include __DIR__ . '/settings.view.php';
	}

	// Settings handling

	public static function render_is_enabled() {
		$options    = get_option( self::OPTIONS_KEY );
		$is_enabled = $options[ self::OPTIONS_KEY_IS_ENABLED ] ?? true;

		printf( '<input id="%1$s" name="%2$s[%1$s]" type="checkbox" value="yes" %3$s />', esc_attr( self::OPTIONS_KEY_IS_ENABLED ), esc_attr( self::OPTIONS_KEY ), checked( $is_enabled, true, false ) );
		printf( '<label for="%s"><p class="description" style="display: inline-block; margin-left: 0.25rem">%s</p></label>', esc_attr( self::OPTIONS_KEY_IS_ENABLED ), esc_html__( 'Enable block editor governance rules for all users.' ) );
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
