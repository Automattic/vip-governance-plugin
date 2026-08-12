<?php

namespace WPCOMVIP\Governance\Tests;

use PHPUnit\Framework\TestCase;
use WPCOMVIP\Governance\InitGovernance;
use WPCOMVIP\Governance\NestedGovernanceProcessing;
use function WPCOMVIP\Governance\vip_governance_pre_init;

/**
 * @covers InitGovernance
 */
class InitGovernanceTest extends TestCase {
	protected function tearDown(): void {
		wp_dequeue_style( 'wpcomvip-governance' );
		wp_deregister_style( 'wpcomvip-governance' );
		remove_all_filters( 'vip_governance__governance_rules_json' );
		InitGovernance::$governance_configuration = [];
		$nested_cache                             = new \ReflectionProperty( NestedGovernanceProcessing::class, 'nested_settings_and_css' );
		$nested_cache->setValue( null, null );
		parent::tearDown();
	}

	public function test_pre_init_uses_canonical_wordpress_version(): void {
		global $wp_version;

		$original_wp_version = $wp_version;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Deliberately simulate a plugin overriding the version global.
		$wp_version = '1.0';

		try {
			$this->assertTrue( vip_governance_pre_init() );
			$this->assertNotSame( '1.0', $wp_version );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore shared test state.
			$wp_version = $original_wp_version;
		}
	}

	public function test_plugin_double_load_returns_without_redeclaring_bootstrap_function(): void {
		$this->assertTrue( function_exists( 'WPCOMVIP\\Governance\\vip_governance_pre_init' ) );

		require WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_FILE;

		$this->assertTrue( function_exists( 'WPCOMVIP\\Governance\\vip_governance_pre_init' ) );
	}

	public function test_load_css__enqueues_styles_outside_admin(): void {
		InitGovernance::$governance_configuration = [
			'nestedSettingsAndCss' => [ 'css' => '.test{color:red;}' ],
		];

		InitGovernance::load_css();

		$this->assertTrue( wp_style_is( 'wpcomvip-governance', 'enqueued' ) );
	}

	public function test_load_css__with_no_usable_rules__uses_compatibility_safe_fallback_without_error(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{
				"version": "1.0.0",
				"rules": [
					{ "type": "unknown", "allowedBlocks": [ "core/paragraph" ] }
				]
			}'
		);

		InitGovernance::load_css();

		$this->assertFalse( InitGovernance::$governance_configuration['error'] );
		$this->assertSame(
			[
				'allowedBlocks'   => [ '*' ],
				'blockSettings'   => [],
				'allowedFeatures' => [ 'codeEditor', 'lockBlocks' ],
			],
			InitGovernance::$governance_configuration['governanceRules']
		);
	}

	public function test_load_css__with_repaired_rules__does_not_include_settings_warnings_in_editor_configuration(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{
				"version": "1.0.0",
				"rules": [
					{ "type": "default", "allowedBlocks": "core/paragraph" }
				]
			}'
		);

		InitGovernance::load_css();

		$this->assertFalse( InitGovernance::$governance_configuration['error'] );
		$this->assertSame(
			[ 'core/paragraph' ],
			InitGovernance::$governance_configuration['governanceRules']['allowedBlocks']
		);
		$this->assertArrayNotHasKey( 'warnings', InitGovernance::$governance_configuration );
		$this->assertArrayNotHasKey( 'warnings', InitGovernance::$governance_configuration['governanceRules'] );
	}
}
