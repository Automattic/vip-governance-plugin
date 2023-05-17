<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use JsonException;

class InitGovernance {
	public static function init() {
		add_action( 'enqueue_block_assets', [ __CLASS__, 'init_governance' ] );
	}

	#region Block filters

	public static function init_governance() {
		$asset_file = include WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/build/index.asset.php';

		wp_register_script(
			'wpcomvip-governance',
			plugins_url( 'build/index.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true /* in_footer */
		);

		$governance_rules = self::get_governance_rules();

		$interactive_settings_and_css = array();
		if ( isset( $governance_rules['error'] ) ) {
			$governance_errors = $governance_rules['error'];
		} else {
			$interactions                 = new Interactions( $governance_rules['rules'] );
			$interactive_settings_and_css = $interactions->get_interactive_settings();
		}

		if ( ! $interactive_settings_and_css && ! isset( $governance_rules['error'] ) ) {
			return;
		}

		wp_localize_script('wpcomvip-governance', 'VIP_GOVERNANCE', [
			'nestedSettings'      => isset( $governance_rules['settings'] ) ? $governance_rules['settings'] : array(),
			'nestedSettingsError' => $governance_errors,
		]);
		wp_enqueue_script( 'wpcomvip-governance' );

		wp_register_style(
			'wpcomvip-governance',
			plugins_url( 'css/vip-governance.css', __FILE__ ),
			/* dependencies */ array(),
			WPCOMVIP__GOVERNANCE__PLUGIN_VERSION
		);

		if ( isset( $governance_rules['css'] ) && ! isset( $governance_rules['error'] ) ) {
			wp_add_inline_style( 'wpcomvip-governance', $interactive_settings_and_css['css'] );
		}

		wp_enqueue_style( 'wpcomvip-governance' );
	}

	private static function get_governance_rules() {
		$governance_file_path = plugin_dir_path( WPCOMVIP_GOVERNANCE_SOURCE_FILENAME ) . WPCOMVIP_GOVERNANCE_SOURCE_FILENAME;

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
