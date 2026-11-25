<?php
/**
 * Settings application shell.
 *
 * @package vip-governance
 */

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

$settings_data = [
	'enabled'   => Settings::is_enabled(),
	'error'     => $governance_error,
	'warnings'  => $governance_warnings,
	'source'    => $governance_rules_json,
	'roles'     => $role_labels,
	'postTypes' => $post_type_labels,
	'version'   => WPCOMVIP__GOVERNANCE__PLUGIN_VERSION,
];

// Preserve literal HTML entities when the browser reads the attribute.
$settings_json = wp_json_encode( $settings_data, JSON_HEX_AMP );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'WordPress VIP Block Governance', 'vip-governance' ); ?></h1>
	<?php settings_errors(); ?>
	<form action="options.php" method="post">
		<?php settings_fields( Settings::OPTIONS_KEY ); ?>
		<div id="vip-governance-settings" data-settings="<?php echo esc_attr( $settings_json ); ?>"></div>
		<noscript><?php esc_html_e( 'Enable JavaScript to use governance settings.', 'vip-governance' ); ?></noscript>
	</form>
</div>
