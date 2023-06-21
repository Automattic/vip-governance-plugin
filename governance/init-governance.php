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
			$parsed_governance_rule      = self::get_governance_rules( WPCOMVIP_GOVERNANCE_RULES_FILENAME );
			$governance_rule             = self::get_rules_for_user( $parsed_governance_rule );
			$interactive_governance_rule = $governance_rule['blockSettings'];
			$governance_errors           = false;
			$nested_settings_and_css     = NestedGovernanceProcessing::get_nested_settings_and_css( $interactive_governance_rule );
		} catch ( Exception $e ) {
			$governance_errors = $e->getMessage();
		}

		wp_localize_script('wpcomvip-governance', 'VIP_GOVERNANCE', [
			'errors'         => $governance_errors,
			'governanceRule' => $governance_rule,
			'nestedSettings' => isset( $nested_settings_and_css['settings'] ) ? $nested_settings_and_css['settings'] : array(),

			// Temporary hardcoded block locking settings
			'isLockdownMode' => BlockLocking::$is_lockdown_mode,
			'allowedBlocks'  => BlockLocking::$allowed_blocks,
		]);

		wp_enqueue_script( 'wpcomvip-governance' );
	}

	public static function load_css() {
		try {
			$parsed_governance_rule      = self::get_governance_rules( WPCOMVIP_GOVERNANCE_RULES_FILENAME );
			$governance_rule             = self::get_rules_for_user( $parsed_governance_rule );
			$interactive_governance_rule = $governance_rule['blockSettings'];
			$nested_settings_and_css     = NestedGovernanceProcessing::get_nested_settings_and_css( $interactive_governance_rule );
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

		if ( ! file_exists( $governance_file_path ) ) {
			$governance_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/' . $file_name;

			if ( ! file_exists( $governance_file_path ) ) {
				/* translators: %s: rules file doesn't exist */
				throw new Exception( sprintf( __( 'Governance rules (%s) could not be found in private, or plugin folders.', 'vip-governance' ), $file_name ) );
			}
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$governance_rules_json = file_get_contents( $governance_file_path );

		$governance_rules = RulesParser::parse( $governance_rules_json );

		if ( is_wp_error( $governance_rules ) ) {
			$error_message = $governance_rules->get_error_message();
			$error_details = $governance_rules->get_error_data();

			/* translators: %s: governance file name */
			$error_message = sprintf( __( 'Governance rules could not be loaded: %s', 'vip-governance' ), $error_message );

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

		$allowed_blocks = array();
		$block_settings = array();

		foreach ( $governance_rules['rules'] as $rule ) {
			// The allowed blocks can be merged together with the default role to get a super set
			// The Block Settings are only to be picked up from the default role, if a role specific one doesn't exist
			if ( isset( $rule['type'] ) && 'role' === $rule['type'] && isset( $rule['roles'] ) && array_intersect( $user_roles, $rule['roles'] ) ) { 
				$allowed_blocks = isset( $rule['allowedBlocks'] ) ? array_merge( $allowed_blocks, $rule['allowedBlocks'] ) : $allowed_blocks;
				$block_settings = isset( $rule['blockSettings'] ) ? $rule['blockSettings'] : $block_settings;
			} elseif ( isset( $rule['type'] ) && 'default' === $rule['type'] ) {
				$allowed_blocks = isset( $rule['allowedBlocks'] ) ? array_merge( $allowed_blocks, $rule['allowedBlocks'] ) : $allowed_blocks;
				$block_settings = isset( $rule['blockSettings'] ) && empty( $block_settings ) ? $rule['blockSettings'] : $block_settings;
			}
		}

		// return array of allowed_blocks and block_settings
		return array(
			'allowedBlocks' => $allowed_blocks,
			'blockSettings' => $block_settings,
		);
	}

	#endregion Block filters
}

InitGovernance::init();
