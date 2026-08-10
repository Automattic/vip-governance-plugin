<?php

namespace WPCOMVIP\Governance\Tests;

use PHPUnit\Framework\TestCase;
use WPCOMVIP\Governance\InitGovernance;
use function WPCOMVIP\Governance\vip_governance_pre_init;

/**
 * @covers InitGovernance
 */
class InitGovernanceTest extends TestCase {
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

	public function test_load_css__does_not_enqueue_styles_outside_admin(): void {
		InitGovernance::load_css();

		$this->assertFalse( wp_style_is( 'wpcomvip-governance', 'registered' ) );
	}
}
