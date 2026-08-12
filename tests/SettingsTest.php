<?php

namespace WPCOMVIP\Governance\Tests;

use PHPUnit\Framework\TestCase;
use WPCOMVIP\Governance\Settings;

/**
 * @covers Settings
 */
class SettingsTest extends TestCase {
	protected function tearDown(): void {
		remove_all_filters( 'vip_governance__governance_rules_json' );
		parent::tearDown();
	}

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

	public function test_render__with_repaired_rules__shows_escaped_warnings_and_combined_rules_tool(): void {
		$filter_calls = 0;
		add_filter(
			'vip_governance__governance_rules_json',
			static function () use ( &$filter_calls ): string {
				++$filter_calls;
				return '{
					"version": "1.0.0",
					"rules": [
						{
							"type": "default",
							"allowedBlocks": "core/paragraph",
							"<unsafe>": true
						}
					]
				}';
			}
		);

		ob_start();
		Settings::render();
		$output = ob_get_clean();

		$this->assertSame( 1, $filter_calls, 'Settings should retrieve and parse the rules JSON only once.' );
		$this->assertStringContainsString( '⚠️ Governance Rules Validation', $output );
		$this->assertStringContainsString( 'Rules loaded with warnings:', $output );
		$this->assertStringContainsString( 'converted allowedBlocks to an array.', $output );
		$this->assertStringContainsString( '&quot;&lt;unsafe&gt;&quot;', $output );
		$this->assertStringContainsString( 'View Governance Rules For A Rule Type', $output );
	}

	public function test_render__with_fatal_parser_error__shows_error_and_hides_combined_rules_tool(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{ "version": "1.0.0" }'
		);

		ob_start();
		Settings::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '❌ Governance Rules Validation', $output );
		$this->assertStringContainsString( 'Failed to load:', $output );
		$this->assertStringNotContainsString( 'View Governance Rules For A Rule Type', $output );
	}

	public function test_render__with_valid_rules__shows_success(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{
				"version": "1.0.0",
				"rules": [
					{ "type": "default", "allowedBlocks": [ "core/paragraph" ] }
				]
			}'
		);

		ob_start();
		Settings::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '✅ Governance Rules Validation', $output );
		$this->assertStringContainsString( 'Rules loaded successfully.', $output );
	}
}
