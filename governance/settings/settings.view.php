<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

$is_governance_error = false !== $governance_error;

?>

<div class="wrap">
	<h1><?php esc_html_e( 'VIP Governance' ); ?></h1>

	<form action="options.php" method="post">
		<?php
		settings_fields( Settings::OPTIONS_KEY );
		do_settings_sections( Settings::MENU_SLUG );
		submit_button();
		?>
	</form>

	<hr/>

	<?php /* translators: %s: A ✅ or ❌ emoji */ ?>
	<h2><?php printf( esc_html__( '%s Governance Rules Validation' ), $is_governance_error ? '❌' : '✅' ); ?></h2>

	<div class="governance-rules <?php echo $is_governance_error ? 'with-errors' : ''; ?>">
		<div class="governance-rules-validation">
			<?php if ( $is_governance_error ) { ?>
			<p class="validation-errors"><?php esc_html_e( 'Failed to load:' ); ?></p>
			<pre><?php echo esc_html( $governance_error ); ?></pre>
			<?php } else { ?>
			<p><?php esc_html_e( 'Rules loaded successfully.' ); ?></p>
			<?php } ?>
		</div>
	</div>

	<hr/>

	<?php if ( ! $is_governance_error ) { ?>
		<div class="governance-rules-json">
			<h2><?php esc_html_e( 'View Governance Rules as another Role' ); ?></h2>
			<label for="user-role-selector">Choose the user role:</label>
			<select name="user-role-selector" id="user-role-selector">
				<?php wp_dropdown_roles(); ?>
			</select>
			<pre><code id="json">Rules specific to the role will be shown here. Change your selection above.</code></pre>
			<script type="text/javascript">
				let roleSelector = document.getElementById("user-role-selector");
				roleSelector.onchange = () => {
					window.wp.apiRequest({path: `/vip-governance/v1/${ roleSelector.value }/rules`})
					.then(rules => {
						document.getElementById("json").textContent = JSON.stringify(rules, undefined, 2);
					});
				}
			</script>
		</div>
	<?php } ?>

	<hr/>

	<h2><?php esc_html_e( 'Debug Information' ); ?></h2>
	<p>
		<?php
			/* translators: %s: Plugin version number */
			printf( esc_html__( 'Plugin Version: %s' ), esc_html( WPCOMVIP__GOVERNANCE__PLUGIN_VERSION ) );
		?>
	</p>
</div>
