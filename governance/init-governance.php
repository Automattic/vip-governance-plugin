<?php
/**
 * Intialize Block Governance
 *
 * @package vip-governance
 */

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

use Throwable;

/**
 * Initializes the block governance plugin.
 */
class InitGovernance {
	/**
	 * Governance configuration.
	 *
	 * @var array
	 *
	 * @access private
	 */
	public static array $governance_configuration = [];

	/**
	 * Context available before WordPress collects assets for the editor iframe.
	 *
	 * @var \WP_Block_Editor_Context|null
	 */
	private static ?\WP_Block_Editor_Context $editor_context = null;

	/**
	 * Initialize the class
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'allowed_block_types_all', [ __CLASS__, 'capture_editor_context' ], 10, 2 );
		add_filter( 'block_editor_settings_all', [ __CLASS__, 'apply_editor_settings' ] );

		// Assets for block editor UI.
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'load_settings' ] );

		// Assets for iframed block editor and editor UI.
		add_action( 'enqueue_block_assets', [ __CLASS__, 'load_css' ] );
	}

	/**
	 * Capture editor context before WordPress enqueues iframe styles.
	 *
	 * @param bool|string[]            $allowed_blocks WordPress's allowed block types.
	 * @param \WP_Block_Editor_Context $context        The editor being rendered.
	 * @return bool|string[] WordPress's allowed block types.
	 */
	public static function capture_editor_context( bool|array $allowed_blocks, \WP_Block_Editor_Context $context ): bool|array {
		self::$editor_context = $context;
		return $allowed_blocks;
	}

	/**
	 * Apply governance feature settings to the current editor.
	 *
	 * @param array $settings Editor settings.
	 * @return array Editor settings.
	 */
	public static function apply_editor_settings( array $settings ): array {
		if ( ! Settings::is_enabled() ) {
			return $settings;
		}

		$rules = self::get_governance_configuration()['governanceRules'];
		return isset( $rules['allowedFeatures'] )
			? BlockLocking::apply_editor_settings( $settings, $rules['allowedFeatures'] )
			: $settings;
	}

	/**
	 * Load the settings necessary for the block editor UI.
	 *
	 * @return void
	 *
	 * @access private
	 */
	public static function load_settings(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		$configuration = self::get_governance_configuration();

		$asset_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/build/index.asset.php';
		if ( ! is_readable( $asset_file_path ) ) {
			Analytics::record_error();
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'VIP Block Governance asset manifest is not readable: %s', $asset_file_path ) );
			return;
		}

		$asset_file = include $asset_file_path;
		if ( ! is_array( $asset_file ) || ! isset( $asset_file['dependencies'], $asset_file['version'] ) || ! is_array( $asset_file['dependencies'] ) ) {
			Analytics::record_error();
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'VIP Block Governance asset manifest is invalid: %s', $asset_file_path ) );
			return;
		}

		wp_enqueue_script(
			'wpcomvip-governance',
			plugins_url( '/build/index.js', WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_FILE ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true /* in_footer */
		);
		wp_set_script_translations( 'wpcomvip-governance', 'vip-governance' );

		$nested_settings_and_css = $configuration['nestedSettingsAndCss'];

		wp_localize_script(
			'wpcomvip-governance',
			'VIP_GOVERNANCE',
			[
				'error'           => $configuration['error'],
				'governanceRules' => $configuration['governanceRules'],
				'nestedSettings'  => $nested_settings_and_css['settings'] ?? [],
				'urlSettingsPage' => menu_page_url( Settings::MENU_SLUG, /* display */ false ),
			]
		);
	}

	/**
	 * Load the CSS necessary for the block editor UI.
	 *
	 * @return void
	 *
	 * @access private
	 */
	public static function load_css(): void {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		$nested_settings_and_css = self::get_governance_configuration()['nestedSettingsAndCss'];

		// Hack to load the CSS dynamically for the block editor without needing a blank CSS file.
		wp_register_style( 'wpcomvip-governance', false, [], WPCOMVIP__GOVERNANCE__PLUGIN_VERSION );
		wp_enqueue_style( 'wpcomvip-governance' );
		wp_add_inline_style( 'wpcomvip-governance', $nested_settings_and_css['css'] ?? '' );
	}

	/**
	 * Get the governance configuration for this request.
	 *
	 * @return array Governance configuration.
	 */
	private static function get_governance_configuration(): array {
		if ( empty( self::$governance_configuration ) ) {
			self::$governance_configuration = self::load_governance_configuration();
		}
		return self::$governance_configuration;
	}

	/**
	 * Load the governance configuration, based on the user role and ensure the rules are valid.
	 *
	 * @return array Governance rules, based on the user role.
	 */
	private static function load_governance_configuration(): array {
		$governance_error          = false;
		$governance_rules_for_user = [];
		$nested_settings_and_css   = [];

		try {
			$parsed_governance_rules = GovernanceUtilities::get_parsed_governance_rules();

			if ( is_wp_error( $parsed_governance_rules ) ) {
				$governance_error = __( 'Governance rules could not be loaded.', 'vip-governance' );
			} else {
				$context                   = self::$editor_context?->name;
				$post_type                 = null === $context || 'core/edit-post' === $context ? ( self::$editor_context?->post?->post_type ?? '' ) : null;
				$governance_rules_for_user = empty( $parsed_governance_rules )
					? self::get_permissive_governance_rules()
					: GovernanceUtilities::get_rules_by_type( $parsed_governance_rules, [], $post_type );

				if ( 'core/edit-widgets' === $context && ! in_array( 'core/widget-area', $governance_rules_for_user['allowedBlocks'], true ) ) {
					// The block editor uses this internal block to keep widget areas editable.
					$governance_rules_for_user['allowedBlocks'][] = 'core/widget-area';
				}
				$block_settings_for_user = $governance_rules_for_user['blockSettings'];
				$nested_settings_and_css = NestedGovernanceProcessing::get_nested_settings_and_css( $block_settings_for_user );

				if ( ( time() % 10 ) === 0 ) {
					// Sample results. Only send analytics on 10% of configuration loads.
					Analytics::record_usage();
				}
			}
		} catch ( Throwable $throwable ) {
			// This is an unexpected exception. Record error for follow-up with WPVIP customers.
			Analytics::record_error();
			// ToDo: Log the error to QueryMonitor instead of doing this.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $throwable->getMessage() );

			$governance_error = __( 'Governance rules could not be loaded due to a plugin error.', 'vip-governance' );
		}

		return [
			'error'                => $governance_error,
			'governanceRules'      => $governance_rules_for_user,
			'nestedSettingsAndCss' => $nested_settings_and_css,
		];
	}

	/**
	 * Return a no-op ruleset when no usable rules remain.
	 *
	 * Trunk effectively skips governance when parsing produces no rules. Keeping all blocks and
	 * supported features available preserves that customer-facing behavior without surfacing an
	 * editor error from attempting to process an unshaped empty rules array.
	 *
	 * @return array Permissive effective governance rules.
	 */
	private static function get_permissive_governance_rules(): array {
		return [
			'allowedBlocks'   => [ '*' ],
			'blockSettings'   => [],
			'allowedFeatures' => [ 'codeEditor', 'lockBlocks' ],
		];
	}
}

InitGovernance::init();
