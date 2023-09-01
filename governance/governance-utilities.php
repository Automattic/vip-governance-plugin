<?php
/**
 * Utilities for Block Governance.
 * 
 * @package vip-governance
 */

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use WP_Error;

/**
 * Utilities class that has helper functions for processing the governance rules.
 */
class GovernanceUtilities {

	/**
	 * Retrieve parsed governance rules from the private directory, or the plugin directory if not found.
	 *
	 * @return array|WP_Error
	 * 
	 * @access private
	 */
	public static function get_parsed_governance_rules() {
		$governance_rules_json = self::get_governance_rules_json();
		return RulesParser::parse( $governance_rules_json );
	}

	/**
	 * Get raw governance rules content from the private directory, or the plugin directory if not found.
	 *
	 * @return string|WP_Error
	 * 
	 * @access private
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
	 * Get the rules using the provided type.
	 * 
	 * The default rule is the base upon which the other rules are built. Currently, that's postType and role.
	 *
	 * @param array $governance_rules Governance rules, not filtered based on the user role.
	 * @param array $user_roles User roles for the current WP site.
	 * @param array $post_type Post type for the current post.
	 * 
	 * @return array Governance rules, filtered by the matching user role or post type.
	 * 
	 * @access private
	 */
	public static function get_rules_by_type( $governance_rules, $user_roles = [], $post_type = '' ) {
		if ( empty( $governance_rules ) ) {
			return array();
		}

		if ( empty( $user_roles ) ) {
			$current_user = wp_get_current_user();
			$user_roles   = $current_user->roles;
		}

		if ( empty( $post_type ) ) {
			$post_type = get_post_type();
		}

		$allowed_features = array();
		$allowed_blocks   = array();
		$block_settings   = array();

		// Because PHP doesn't allow passing this in directly.
		$type_to_rules_map = RulesParser::TYPE_TO_RULES_MAP;

		// Assumption is that it's been ordered by priority, so it will process those rules first followed by default last.
		foreach ( RulesParser::RULE_TYPES as $priority ) {
			// look up the rule in $governance_rules where the field type matches priority.
			$governance_rules_for_priority = array_filter( $governance_rules, function( $rule ) use ( $priority, $user_roles, $post_type, $type_to_rules_map ) {
				// Its required to have the type, and its corresponding types set unless you are the default rule in which case you only need type set to default.
				if ( isset( $rule['type'] ) && $priority === $rule['type'] && ( 'default' === $priority || isset( $rule[ $type_to_rules_map[ $priority ] ] ) ) ) {
					if ( 'default' === $priority ) {
						return true;
					} elseif ( 'role' === $priority ) {
						// Only give back true if the roles match the current user.
						return array_intersect( $user_roles, $rule['roles'] );
					} elseif ( 'postType' === $priority ) {
						// Only give back true if the current post type matches the post types allowed.
						return in_array( $post_type, $rule['postTypes'], true );
					}
				}

				// Rule should be ignored if it doesn't match the needed criteria for priorities.
				return false;
			} );

			if ( ! empty( $governance_rules_for_priority ) ) {
				// Re-order the rule so that the 0 index is what's first, otherwise the index is preserved.
				$governance_rules_for_priority = array_values( $governance_rules_for_priority );

				$allowed_blocks   = self::get_allowed_blocks_or_features_for_rule_type( 'allowedBlocks', $allowed_blocks, $governance_rules_for_priority[0], $priority );
				$block_settings   = self::get_block_settings_for_rule_type( $block_settings, $governance_rules_for_priority[0], $priority );
				$allowed_features = self::get_allowed_blocks_or_features_for_rule_type( 'allowedFeatures', $allowed_features, $governance_rules_for_priority[0], $priority );
			}
		}

		// return array of allowed_blocks and block_settings.
		return array(
			'allowedBlocks'   => $allowed_blocks,
			'blockSettings'   => $block_settings,
			'allowedFeatures' => $allowed_features,
		);
	}

	/**
	 * Get the new allowedBlocks or allowedFeatures based on the rule type
	 * 
	 * The default rule's allowedBlocks and allowedFeatures is combined with the other rule types.
	 * For non-default rule types, only one allowedBlocks and allowedFeatures can be picked. It's not combined together.
	 *
	 * @param string $allowed_type allowedBlocks or allowedFeatures.
	 * @param array  $allowed_blocks_or_features allowedBlocks or allowedFeatures that have been combined so far.
	 * @param array  $governance_rule current rule being processed.
	 * @param string $rule_type type of rule being processed.
	 * @return array allowedBlocks or allowedFeatures that have been combined so far.
	 */
	private static function get_allowed_blocks_or_features_for_rule_type( $allowed_type, $allowed_blocks_or_features, $governance_rule, $rule_type ) {
		if ( isset( $governance_rule[ $allowed_type ] ) ) {
			// For the default rule the allowedBlocks and allowedFeatures are combined together.
			// Otherwise, there can only be one.
			if ( 'default' === $rule_type ) {
				return [ ...$allowed_blocks_or_features, ...$governance_rule[ $allowed_type ] ];
			} else {
				$allowed_blocks_or_features = $governance_rule[ $allowed_type ];
			}
		}

		return $allowed_blocks_or_features;
	}

	/**
	 * Get the new blockSettings based on the rule type
	 * 
	 * The default rule's blockSettings is combined with the other rule types.
	 * For non-default rule types, only one blockSettings can be picked. It's not combined together.
	 *
	 * @param array  $block_settings blockSettings that have been combined so far.
	 * @param array  $governance_rule current rule being processed.
	 * @param string $rule_type type of rule being processed.
	 * @return array blockSettings that have been combined so far.
	 */
	private static function get_block_settings_for_rule_type( $block_settings, $governance_rule, $rule_type ) {
		if ( isset( $governance_rule['blockSettings'] ) ) {
			// For the default rule the blockSettings are combined together.
			// Otherwise, there can only be one.
			if ( 'default' === $rule_type ) {
				return array_merge_recursive( $block_settings, $governance_rule['blockSettings'] );
			} else {
				$block_settings = $governance_rule['blockSettings'];
			}
		}

		return $block_settings;
	}

}
