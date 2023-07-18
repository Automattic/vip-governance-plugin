<?php

namespace WPCOMVIP\Governance;

use WP_Error;

defined( 'ABSPATH' ) || die();

class RestApi {
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
	}

	public static function register_rest_routes() {
		register_rest_route( WPCOMVIP__GOVERNANCE__RULES_REST_ROUTE, '/(?P<role>\w+)/rules', [
			'methods'             => 'GET',
			'permission_callback' => [ __CLASS__, 'permission_callback' ],
			'callback'            => [ __CLASS__, 'get_governance_rules_for_role' ],
			'args'                => [
				'role' => [
					'validate_callback' => function( $param ) {
						$all_roles = array_keys( wp_roles()->roles );
						$roles     = array( strval( $param ) );
						return array_intersect( $all_roles, $roles );
					},
					'sanitize_callback' => function( $param ) {
						return strval( $param );
					},
				],
			],
		] );
	}

	public static function permission_callback() {
		return current_user_can( 'manage_options' );
	}

	public static function get_governance_rules_for_role( $params ) {
		$role = $params['role'];

		try {
			$parsed_governance_rules = GovernanceUtilities::get_parsed_governance_rules();

			if ( is_wp_error( $parsed_governance_rules ) ) {
				return new WP_Error( 'vip-governance-rules-error', 'Error: Governance rules could not be loaded.', [ 'status' => 400 ] );
			} else {
				return GovernanceUtilities::get_rules_for_user( $parsed_governance_rules, array( $role ) );
			}
		} catch ( Exception | Error $e ) {
			return new WP_Error( 'vip-governance-rules-error', 'Error: Governance rules could not be loaded due to a plugin error.', [ 'status' => 500 ] );
		}
	}
}

RestApi::init();
