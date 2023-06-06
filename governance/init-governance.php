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
			'insertionRules' => self::get_rules_for_user( $insertion_governance_rules ),
			'nestedSettings' => isset( $nested_settings_and_css['settings'] ) ? $nested_settings_and_css['settings'] : array(),

			// Temporary hardcoded block locking settings
			'isLockdownMode' => BlockLocking::$is_lockdown_mode,
			'allowedBlocks'  => BlockLocking::$allowed_blocks,
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

	/**
	 * Get the governance rules from the private directory, or the plugin directory if not found.
	 */
	private static function get_governance_rules( $file_name ) {
		$governance_file_path = WPCOM_VIP_PRIVATE_DIR . '/' . $file_name;

		// ToDo: Ensure before release the default rule set is just core/heading, core/image and core/paragraph
		if ( ! file_exists( $governance_file_path ) ) {
			$governance_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/' . $file_name;

			if ( ! file_exists( $governance_file_path ) ) {
				/* translators: %s: rules file doesn't exist */
				throw new Exception( sprintf( __( 'Governance rules (%s) could not be found.', 'vip-governance' ), $file_name ) );
			}
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

	/**
	 * Get the rules for the current user, with a default fallback rule set of 
	 * allowing core/heading, core/paragraph and core/image
	 */
	private static function get_rules_for_user( $governance_rules ) {
		if ( empty( $governance_rules ) || ! isset( $governance_rules['rules'] ) ) {
			return array();
		}

		$current_user = wp_get_current_user();
		$user_roles   = $current_user->roles;

		// Only get the rules where the role matches the current role of the user
		$rules_for_user = array_filter( $governance_rules['rules'], function( $rule ) use ( $user_roles ) {
			$is_role_rule = isset( $rule['type'] ) && 'role' === $rule['type'];
			return $is_role_rule && isset( $rule['roles'] ) && array_intersect( $user_roles, $rule['roles'] );
		} );

		if ( empty( $rules_for_user ) ) {
			$rules_for_user = array_filter( $governance_rules['rules'], function( $rule ) {
				return isset( $rule['type'] ) && 'default' === $rule['type'];
			} );
		}

		// If no rules are found, allow everything by default
		if ( empty( $rules_for_user ) ) {
			$rules_for_user = array( 'allowed' => array( 'core/heading', 'core/paragraph', 'core/image' ) );
		}

		// ToDo: Do this efficiently because this is bad if the rules array is huge
		return array_values(array_map( function( $rule ) {
			unset( $rule['roles'] );
			unset( $rule['type'] );
			return $rule;
		}, $rules_for_user ));
	}

	#endregion Block filters
}

InitGovernance::init();
