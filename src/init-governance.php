<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use JsonException;

class InitGovernance {
	public static function init() {
		// Assets for block editor UI
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'load_settings' ] );

		// Assets for iframed block editor and editor UI
		add_action( 'enqueue_block_assets', [ __CLASS__, 'load_css' ] );
	}

	#region Block filters

	public static function load_settings() {
		$asset_file = include WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/build/index.asset.php';

		wp_register_script(
			'wpcomvip-governance',
			WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true /* in_footer */
		);

		$governance_rules = self::get_governance_rules();

		if ( isset( $governance_rules['error'] ) ) {
			$nested_settings_and_css = array();
			$governance_errors       = $governance_rules['error'];
		} else {
			$governance_errors            = false;
			$nested_governance_processing = new NestedGovernanceProcessing( $governance_rules['rules'] );
			$nested_settings_and_css      = $nested_governance_processing->get_nested_settings_and_css();
		}

		if ( empty( $nested_settings_and_css ) && ! $governance_errors ) {
			return;
		}

		wp_localize_script('wpcomvip-governance', 'VIP_GOVERNANCE', [
			'nestedSettings'      => isset( $nested_settings_and_css['settings'] ) ? $nested_settings_and_css['settings'] : array(),
			'nestedSettingsError' => $governance_errors,
		]);

		wp_enqueue_script( 'wpcomvip-governance' );
	}

	public static function load_css() {
		$governance_rules = self::get_governance_rules();

		if ( ! isset( $governance_rules['error'] ) ) {
			$nested_governance_processing = new NestedGovernanceProcessing( $governance_rules['rules'] );
			$nested_settings_and_css      = $nested_governance_processing->get_nested_settings_and_css();
		}

		if ( isset( $nested_settings_and_css['css'] ) ) {
			wp_register_style(
				'wpcomvip-governance',
				WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/css/vip-governance.css',
				/* dependencies */ array(),
				WPCOMVIP__GOVERNANCE__PLUGIN_VERSION
			);

			wp_add_inline_style( 'wpcomvip-governance', $nested_settings_and_css['css'] );

			wp_enqueue_style( 'wpcomvip-governance' );
		}
	}

	private static function get_governance_rules() {
		$governance_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/' . WPCOMVIP_GOVERNANCE_SOURCE_FILENAME;

		if ( ! file_exists( $governance_file_path ) ) {
			return [
				/* translators: %s: rules file doesn't exist */
				'error' => sprintf( __( 'Governance rules (%s) could not be found.', 'vip-governance' ), WPCOMVIP_GOVERNANCE_SOURCE_FILENAME ),
			];
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$governance_rules_json = file_get_contents( $governance_file_path );

		try {
			$governance_rules = json_decode( $governance_rules_json, /* associative */ true, /* depth */ 512, /* flags */ JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$json_error = sprintf( '%s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() );
			/* translators: %s: plugin name */
			$error_message = sprintf( __( 'Governance rules (%s) could not be parsed', 'vip-governance' ), WPCOMVIP_GOVERNANCE_SOURCE_FILENAME, $json_error );
			return [
				'error' => $error_message,
			];
		}

		return [
			'rules' => $governance_rules,
		];
	}

	#endregion Block filters
}

InitGovernance::init();
