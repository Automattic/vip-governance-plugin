<?php

namespace WPCOMVIP\Governance\Tests;

use PHPUnit\Framework\TestCase;
use WPCOMVIP\Governance\Settings;

/**
 * @covers Settings
 */
class SettingsTest extends TestCase {
	public function test_validate_options__with_enabled_checkbox(): void {
		$this->assertSame(
			[ Settings::OPTIONS_KEY_IS_ENABLED => true ],
			Settings::validate_options( [ Settings::OPTIONS_KEY_IS_ENABLED => 'yes' ] )
		);
	}

	public function test_validate_options__without_enabled_checkbox(): void {
		$this->assertSame(
			[ Settings::OPTIONS_KEY_IS_ENABLED => false ],
			Settings::validate_options( [] )
		);
	}

	public function test_validate_options__with_invalid_value(): void {
		$this->assertSame(
			[ Settings::OPTIONS_KEY_IS_ENABLED => false ],
			Settings::validate_options( 'invalid' )
		);
	}
}
