<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

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

	<h2><?php esc_html_e( 'Governance rules' ); ?></h2>
	<div class="governance-rules-validation">
		<div class="governance-rules">
			<pre>
				<code><?php echo esc_html( 'test' ); ?></code>
			</pre>
		</div>

		<div class="governance-validation">
			<?php if ( false === $governance_errors ) { ?>
			<p><?php esc_html_e( 'No errors found' ); ?></p>
			<?php } else { ?>
			<pre><?php echo esc_html( $governance_errors ); ?></pre>
			<?php } ?>
		</div>
	</div>

	<hr/>

	<h2><?php esc_html_e( 'Debug Information' ); ?></h2>
	<p>
		<?php
			// translators: %s - Plugin version number
			printf( esc_html__( 'Plugin Version: %s' ), esc_html( WPCOMVIP__GOVERNANCE__PLUGIN_VERSION ) );
		?>
	</p>
</div>
