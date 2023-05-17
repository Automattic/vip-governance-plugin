<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use JsonException;

class BlockEditorFilters {
	public static function init() {
		add_action( 'init_governance', [ __CLASS__, 'init_governance' ] );
	}

	#region Block filters

	public static function init_governance() {
		$asset_file = include WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/build/index.asset.php';

		wp_register_script(
			'wpcomvip-governance',
			plugins_url( 'build/js/index.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true /* in_footer */
		);

		$governance_rules = self::get_governance_rules();

		if ( empty( $governance_rules ) ) {
			return;
		}
		
		$interactive_governance       = new InteractiveGovernance( $governance_rules );
		$interactive_settings_and_css = $interactive_governance->get_interactive_settings();

		wp_localize_script('wpcomvip-governance', 'VIP_GOVERNANCE', [
			'nestedSettings' => $interactive_settings_and_css['settings'],
		]);
		wp_enqueue_script( 'wpcomvip-governance' );

		wp_register_style(
			'wpcomvip-governance',
			plugins_url( 'css/vip-governance.css', __FILE__ ),
			/* dependencies */ array(),
			WPCOMVIP_GOVERNANCE_VERSION
		);
		wp_add_inline_style( 'wpcomvip-governance', $nested_settings_and_css['css'] );
		wp_enqueue_style( 'wpcomvip-governance' );
	}

	private static function get_governance_rules() {
		$governance_file_path = get_theme_file_path( WPCOMVIP_GOVERNANCE_SOURCE_FILENAME );

		if ( ! file_exists( $governance_file_path ) ) {
			return array();
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$governance_rules_json = file_get_contents( $governance_file_path );

		try {
			$governance_rules = json_decode( $governance_rules_json, /* associative */ true, /* depth */ 512, /* flags */ JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$json_error = sprintf( '%s at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() );
			/* translators: %s: plugin name */
			$error_message = sprintf( __( 'Block editor settings in %s could not be parsed', 'vip-governance' ), WPCOMVIP_GOVERNANCE_SOURCE_FILENAME, $json_error );
			add_action( 'admin_notices', static function () use ( $error_message ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error_message ) );
			} );
			return;
		}

		return $governance_rules;
	}

	#endregion Block filters
}

BlockEditorFilters::init();
