<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

define( 'WPCOMVIP__GOVERNANCE__STAT_NAME___USAGE', 'vip-governance-usage' );
define( 'WPCOMVIP__GOVERNANCE__STAT_NAME___ERROR', 'vip-governance-error' );

class Analytics {
	private static $analytics_to_send = [];

	public static function init() {
		add_action( 'shutdown', [ __CLASS__, 'send_analytics' ] );
	}

	public static function record_usage() {
		self::$analytics_to_send[ WPCOMVIP__GOVERNANCE__STAT_NAME___USAGE ] = self::get_identifier();
	}

	/**
	 * @param WP_Error $error
	 *
	 * @return void
	 */
	public static function record_error() {
		if ( self::is_wpvip_site() && defined( 'FILES_CLIENT_SITE_ID' ) ) {
			// Record error data from WPVIP for follow-up
			self::$analytics_to_send[ WPCOMVIP__GOVERNANCE__STAT_NAME___ERROR ] = constant( 'FILES_CLIENT_SITE_ID' );
		}
	}

	public static function send_analytics() {
		if ( empty( self::$analytics_to_send ) ) {
			return;
		}

		$has_usage_analytics = isset( self::$analytics_to_send[ WPCOMVIP__GOVERNANCE__STAT_NAME___USAGE ] );
		$has_error_analytics = isset( self::$analytics_to_send[ WPCOMVIP__GOVERNANCE__STAT_NAME___ERROR ] );

		if ( $has_usage_analytics && $has_error_analytics ) {
			// Do not send usage analytics when errors are present.
			unset( self::$analytics_to_send[ WPCOMVIP__GOVERNANCE__STAT_NAME___USAGE ] );
		}

		self::send_pixel( self::$analytics_to_send );
	}

	private static function send_pixel( $stats ) {
		$query_args = [
			'v' => 'wpcom-no-pv',
		];

		foreach ( $stats as $name => $group ) {
			$query_param = rawurlencode( 'x_' . $name );
			$query_value = rawurlencode( $group );

			$query_args[ $query_param ] = $query_value;
		}

		$pixel = add_query_arg( $query_args, 'http://pixel.wp.com/b.gif' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		wp_remote_get( $pixel, array(
			'blocking' => false,
			'timeout'  => 1,
		) );
	}

	private static function get_identifier() {
		if ( self::is_wpvip_site() && defined( 'FILES_CLIENT_SITE_ID' ) ) {
			return constant( 'FILES_CLIENT_SITE_ID' );
		} else {
			return 'Unknown';
		}
	}

	private static function is_wpvip_site() {
		return defined( 'WPCOM_IS_VIP_ENV' ) && constant( 'WPCOM_IS_VIP_ENV' ) === true
			&& defined( 'WPCOM_SANDBOXED' ) && constant( 'WPCOM_SANDBOXED' ) === false;
	}
}

Analytics::init();
