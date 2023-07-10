<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use Exception;
use Error;

class InitGovernance {
	public static $governance_configuration = [];

	public static function init() {
		// Assets for block editor UI
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'load_settings' ] );

		// Assets for iframed block editor and editor UI
		add_action( 'enqueue_block_assets', [ __CLASS__, 'load_css' ] );
	}

	public static function load_settings() {
		if ( ! Settings::is_enabled() ) {
			return;
		} elseif ( empty( self::$governance_configuration ) ) {
			self::$governance_configuration = self::load_governance_configuration();
		}

		$asset_file = include WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/build/index.asset.php';

		wp_enqueue_script(
			'wpcomvip-governance',
			WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true /* in_footer */
		);

		$nested_settings_and_css = self::$governance_configuration['nestedSettingsAndCss'];

		wp_localize_script('wpcomvip-governance', 'VIP_GOVERNANCE', [
			'error'           => self::$governance_configuration['error'],
			'governanceRules' => self::$governance_configuration['governanceRules'],
			'nestedSettings'  => isset( $nested_settings_and_css['settings'] ) ? $nested_settings_and_css['settings'] : array(),
			'urlSettingsPage' => menu_page_url( Settings::MENU_SLUG, /* display */ false ),
		]);
	}

	public static function load_css() {
		if ( ! Settings::is_enabled() ) {
			return;
		} elseif ( empty( self::$governance_configuration ) ) {
			self::$governance_configuration = self::load_governance_configuration();
		}

		$nested_settings_and_css = self::$governance_configuration['nestedSettingsAndCss'];

		wp_enqueue_style(
			'wpcomvip-governance',
			WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/css/vip-governance.css',
			/* dependencies */ array(),
			WPCOMVIP__GOVERNANCE__PLUGIN_VERSION
		);

		wp_add_inline_style( 'wpcomvip-governance', $nested_settings_and_css['css'] );
	}

	private static function load_governance_configuration() {
		$governance_error          = false;
		$governance_rules_for_user = array();
		$nested_settings_and_css   = array();

		try {
			$parsed_governance_rules = GovernanceUtilities::get_parsed_governance_rules();

			if ( is_wp_error( $parsed_governance_rules ) ) {
				$governance_error = __( 'Governance rules could not be loaded.' );
			} else {
				$governance_rules_for_user = GovernanceUtilities::get_rules_for_user( $parsed_governance_rules );
				$block_settings_for_user   = $governance_rules_for_user['blockSettings'];
				$nested_settings_and_css   = NestedGovernanceProcessing::get_nested_settings_and_css( $block_settings_for_user );
				BlockLocking::init( $governance_rules_for_user['allowedFeatures'] );
				Analytics::record_usage();
			}
		} catch ( Exception | Error $e ) {
			// This is an unexpected exception. Record error for follow-up with WPVIP customers.
			Analytics::record_error();
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e->getMessage() );

			$governance_error = __( 'Governance rules could not be loaded due to a plugin error.' );
		}

		return [
			'error'                => $governance_error,
			'governanceRules'      => $governance_rules_for_user,
			'nestedSettingsAndCss' => $nested_settings_and_css,
		];
	}
}

InitGovernance::init();
