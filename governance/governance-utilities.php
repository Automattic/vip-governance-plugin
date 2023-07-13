<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use WP_Error;

class GovernanceUtilities {
	/**
	 * Retrieve parsed governance rules from the private directory, or the plugin directory if not found.
	 *
	 * @return array|WP_Error
	 */
	public static function get_parsed_governance_rules() {
		$governance_rules_json = self::get_governance_rules_json();
		return RulesParser::parse( $governance_rules_json );
	}

	/**
	 * Get raw governance rules content from the private directory, or the plugin directory if not found.
	 *
	 * @return string|WP_Error
	 */
	public static function get_governance_rules_json() {
		$governance_file_path = WPCOM_VIP_PRIVATE_DIR . '/' . WPCOMVIP_GOVERNANCE_RULES_FILENAME;

		if ( ! file_exists( $governance_file_path ) ) {
			$governance_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/' . WPCOMVIP_GOVERNANCE_RULES_FILENAME;

			if ( ! file_exists( $governance_file_path ) ) {
				/* translators: %s: governance file name */
				return new WP_Error( 'governance-file-not-found', sprintf( __( 'Governance rules (%s) could not be found in private or plugin folders.', 'vip-governance' ), WPCOMVIP_GOVERNANCE_RULES_FILENAME ) );
			}
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$governance_rules_json = file_get_contents( $governance_file_path );

		if ( false === $governance_rules_json ) {
			/* translators: %s: governance file name */
			return new WP_Error( 'governance-file-not-readable', sprintf( __( 'Governance rules (%s) could not be read from private folder.', 'vip-governance' ), WPCOMVIP_GOVERNANCE_RULES_FILENAME ) );
		}

		return $governance_rules_json;
	}

	/**
	 * Get the rules for the current user, using the "default" rules as a fallback.
	 *
	 * @return array
	 */
	public static function get_rules_for_user( $governance_rules ) {
		if ( empty( $governance_rules ) ) {
			return array();
		}

		$current_user = wp_get_current_user();
		$user_roles   = $current_user->roles;

		$allowed_features = array();
		$allowed_blocks   = array();
		$block_settings   = array();

		foreach ( $governance_rules as $rule ) {
			if ( isset( $rule['type'] ) && ( ( 'role' === $rule['type'] && isset( $rule['roles'] ) && array_intersect( $user_roles, $rule['roles'] ) ) || 'default' === $rule['type'] ) ) {
				$allowed_blocks   = isset( $rule['allowedBlocks'] ) ? [ ...$allowed_blocks, ...$rule['allowedBlocks'] ] : $allowed_blocks;
				$block_settings   = isset( $rule['blockSettings'] ) ? array_merge_recursive( $block_settings, $rule['blockSettings'] ) : $block_settings;
				$allowed_features = isset( $rule['allowedFeatures'] ) ? [ ...$allowed_features, ...$rule['allowedFeatures'] ] : $allowed_features;
			}
		}

		// return array of allowed_blocks and block_settings
		return array(
			'allowedBlocks'   => $allowed_blocks,
			'blockSettings'   => $block_settings,
			'allowedFeatures' => $allowed_features,
		);
	}
}
