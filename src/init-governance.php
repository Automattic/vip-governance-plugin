<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use JsonException;
use Exception;

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

		try {
			$interactions_governance_rules = self::get_governance_rules( WPCOMVIP_INTERACTIONS_GOVERNANCE_SOURCE_FILENAME );
			$insertion_governance_rules    = self::get_governance_rules( WPCOMVIP_INSERTIONS_GOVERNANCE_SOURCE_FILENAME );
			$governance_errors             = false;
			$nested_settings_and_css       = NestedGovernanceProcessing::get_nested_settings_and_css( $interactions_governance_rules );
		} catch ( Exception $e ) {
			$governance_errors = $e->getMessage();
		}

		wp_localize_script('wpcomvip-governance', 'VIP_GOVERNANCE', [
			'errors'         => $governance_errors,
			'insertionRules' => empty( $insertion_governance_rules ) ? array() : $insertion_governance_rules,
			'nestedSettings' => isset( $nested_settings_and_css['settings'] ) ? $nested_settings_and_css['settings'] : array(),
		]);

		wp_enqueue_script( 'wpcomvip-governance' );
	}

	public static function load_css() {
		try {
			$interactions_governance_rules = self::get_governance_rules( WPCOMVIP_INTERACTIONS_GOVERNANCE_SOURCE_FILENAME );
			$nested_settings_and_css       = NestedGovernanceProcessing::get_nested_settings_and_css( $interactions_governance_rules );
			wp_register_style(
				'wpcomvip-governance',
				WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/css/vip-governance.css',
				/* dependencies */ array(),
				WPCOMVIP__GOVERNANCE__PLUGIN_VERSION
			);
			wp_add_inline_style( 'wpcomvip-governance', $nested_settings_and_css['css'] );
			wp_enqueue_style( 'wpcomvip-governance' );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e->getMessage() );
		}
	}

	private static function get_combined_governance_rules() {
		$governance_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/' . WPCOMVIP_GOVERNANCE_SOURCE_FILENAME;

		if ( ! file_exists( $governance_file_path ) ) {
			/* translators: %s: rules file doesn't exist */
			throw new Exception( sprintf( __( 'Governance rules (%s) could not be found.', 'vip-governance' ), WPCOMVIP_GOVERNANCE_SOURCE_FILENAME ) );
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$governance_rules_json = file_get_contents( $governance_file_path );

		try {
			$governance_rules = json_decode( $governance_rules_json, /* associative */ true, /* depth */ 512, /* flags */ JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$json_error = sprintf( '%s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() );
			/* translators: %s: plugin name */
			$error_message = sprintf( __( 'Governance rules (%s) could not be parsed', 'vip-governance' ), WPCOMVIP_GOVERNANCE_SOURCE_FILENAME, $json_error );
			throw new Exception( $error_message );
		}

		// check if json has rules set at the root
		if ( ! isset( $governance_rules['rules'] ) ) {
			/* translators: %s: plugin name */
			$error_message = sprintf( __( 'Governance rules (%s) are not in the correct format', 'vip-governance' ), WPCOMVIP_GOVERNANCE_SOURCE_FILENAME );
			throw new Exception( $error_message );
		}

		// check if under the rules property in json, insertions or interactions exist
		if ( ! isset( $governance_rules['rules']['insertions'] ) && ! isset( $governance_rules['rules']['interactions'] ) ) {
			/* translators: %s: plugin name */
			$error_message = sprintf( __( 'Governance rules (%s) are not in the correct format', 'vip-governance' ), WPCOMVIP_GOVERNANCE_SOURCE_FILENAME );
			throw new Exception( $error_message );
		}

		if ( isset( $governance_rules['rules']['insertions'] ) && ( ! isset( $governance_rules['rules']['insertions']['allowed'] ) && ! isset( $governance_rules['rules']['insertions']['blocked'] ) ) ) {
			/* translators: %s: plugin name */
			$error_message = sprintf( __( 'Governance rules (%s) are not in the correct format', 'vip-governance' ), WPCOMVIP_GOVERNANCE_SOURCE_FILENAME );
			throw new Exception( $error_message );
		}

		return $governance_rules;
	}

	private static function get_governance_rules( $file_name ) {
		$governance_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/' . $file_name;

		if ( ! file_exists( $governance_file_path ) ) {
			/* translators: %s: rules file doesn't exist */
			throw new Exception( sprintf( __( 'Governance rules (%s) could not be found.', 'vip-governance' ), $file_name ) );
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$governance_rules_json = file_get_contents( $governance_file_path );

		try {
			$governance_rules = json_decode( $governance_rules_json, /* associative */ true, /* depth */ 512, /* flags */ JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$json_error = sprintf( '%s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() );
			/* translators: %s: plugin name */
			$error_message = sprintf( __( 'Governance rules (%s) could not be parsed', 'vip-governance' ), $file_name, $json_error );
			throw new Exception( $error_message );
		}

		return $governance_rules;
	}

	#endregion Block filters
}

InitGovernance::init();
