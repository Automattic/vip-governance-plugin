<?php

namespace WPCOMVIP\Governance\Tests;

use PHPUnit\Framework\TestCase;
use WPCOMVIP\Governance\InitGovernance;

/**
 * @covers InitGovernance
 */
class InitGovernanceTest extends TestCase {
	public function test_load_css__does_not_enqueue_styles_outside_admin(): void {
		InitGovernance::load_css();

		$this->assertFalse( wp_style_is( 'wpcomvip-governance', 'registered' ) );
	}
}
