<?php
/**
 * Settings
 * 
 * @package vip-governance
 */

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

/**
 * Settings class used for the settings page.
 */
class Settings {
	public const MENU_SLUG              = 'vip-block-governance';
	public const OPTIONS_KEY            = 'vip-governance';
	public const OPTIONS_KEY_IS_ENABLED = 'is-enabled';

	/**
	 * Initialize the class.
	 *
	 * @return void
	 * 
	 * @access private
	 */
	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
	}

	/**
	 * Register the settings in the settings page.
	 * 
	 * @return void
	 * 
	 * @access private
	 */
	public static function register_settings(): void {
		register_setting(
			self::OPTIONS_KEY,
			self::OPTIONS_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'validate_options' ],
				'default'           => [ self::OPTIONS_KEY_IS_ENABLED => true ],
			]
		);
	}

	/**
	 * Register the menu in wp-admin
	 *
	 * @return void
	 * 
	 * @access private
	 */
	public static function register_menu(): void {
		$hook = add_menu_page( __( 'VIP Block Governance', 'vip-governance' ), __( 'VIP Block Governance', 'vip-governance' ), 'manage_options', self::MENU_SLUG, [ __CLASS__, 'render' ], 'dashicons-groups' );
		add_action( 'load-' . $hook, static function (): void {
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		} );
	}

	/**
	 * Enqueue assets for the settings page.
	 */
	public static function enqueue_assets(): void {
		$handle = 'wpcomvip-governance-settings';
		wp_enqueue_style(
			$handle,
			plugins_url( '/governance/settings/settings.css', WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_FILE ),
			[ 'wp-components' ],
			WPCOMVIP__GOVERNANCE__PLUGIN_VERSION
		);

		$asset_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/build/settings.asset.php';
		if ( ! is_readable( $asset_file_path ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'VIP Block Governance settings asset manifest is not readable: %s', $asset_file_path ) );
			return;
		}

		$asset_file = include $asset_file_path;
		if ( ! is_array( $asset_file ) || ! isset( $asset_file['dependencies'], $asset_file['version'] ) || ! is_array( $asset_file['dependencies'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'VIP Block Governance settings asset manifest is invalid: %s', $asset_file_path ) );
			return;
		}

		wp_enqueue_script(
			$handle,
			plugins_url( '/build/settings.js', WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_FILE ),
			$asset_file['dependencies'],
			$asset_file['version'],
			/* in footer */ true
		);
		wp_set_script_translations( $handle, 'vip-governance' );
	}

	/**
	 * Render the view for the setting page, which includes form to enable/disable the plugin
	 * and to show the rules along with its errors.
	 *
	 * @return void
	 * 
	 * @access private
	 */
	public static function render(): void {
		$governance_rules_json = GovernanceUtilities::get_governance_rules_json();
		$governance_error      = false;
		$governance_warnings   = [];
		if ( is_wp_error( $governance_rules_json ) ) {
			$governance_error      = $governance_rules_json->get_error_message();
			$governance_rules_json = false;
		} else {
			$parse_result = RulesParser::parse_with_warnings( $governance_rules_json );

			if ( is_wp_error( $parse_result ) ) {
				$governance_error = $parse_result->get_error_message();
			} else {
				$governance_warnings = $parse_result['warnings'];
			}
		}

		$role_labels = [ '' => __( 'My roles', 'vip-governance' ) ];
		foreach ( wp_roles()->roles as $role_slug => $registered_role ) {
			$role_name                 = $registered_role['name'] ?? '';
			$role_labels[ $role_slug ] = '' === $role_name ? $role_slug : translate_user_role( $role_name );
		}
		$role_labels = self::disambiguate_preview_labels( $role_labels );

		$post_type_labels = [ '' => __( 'No post type selected', 'vip-governance' ) ];
		foreach ( get_post_types( [], 'objects' ) as $registered_post_type ) {
			$label = $registered_post_type->labels->singular_name ?? '';
			if ( '' === $label ) {
				$label = $registered_post_type->label ?? '';
			}
			$post_type_labels[ $registered_post_type->name ] = '' === $label ? $registered_post_type->name : $label;
		}
		$post_type_labels = self::disambiguate_preview_labels( $post_type_labels );

		include __DIR__ . '/settings-view.php';
	}

	/**
	 * Add slugs to repeated preview labels.
	 *
	 * @param array $labels Display labels keyed by slug, with an empty key for the placeholder.
	 * @return array Display labels keyed by the original slugs.
	 */
	private static function disambiguate_preview_labels( array $labels ): array {
		$counts = array_count_values( $labels );
		foreach ( $labels as $slug => $label ) {
			if ( '' !== $slug && $counts[ $label ] > 1 ) {
				/* translators: 1: Option label, 2: Option slug. */
				$labels[ $slug ] = sprintf( __( '%1$s (%2$s)', 'vip-governance' ), $label, $slug );
			}
		}
		return $labels;
	}

	/**
	 * Ensure that the options are valid.
	 *
	 * @param mixed $options Options that have been submitted.
	 * 
	 * @return array Sanitized settings.
	 */
	public static function validate_options( $options ): array {
		return [
			self::OPTIONS_KEY_IS_ENABLED => is_array( $options )
				&& isset( $options[ self::OPTIONS_KEY_IS_ENABLED ] )
				&& 'yes' === $options[ self::OPTIONS_KEY_IS_ENABLED ],
		];
	}

	/**
	 * Check if the plugin is to be enabled or not.
	 *
	 * @return boolean True, if it is or false otherwise.
	 * 
	 * @access private
	 */
	public static function is_enabled(): bool {
		$options = get_option( self::OPTIONS_KEY, [] );
		$options = is_array( $options ) ? $options : [];
		return true === ( $options[ self::OPTIONS_KEY_IS_ENABLED ] ?? true );
	}
}

Settings::init();
