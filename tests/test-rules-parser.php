<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\RulesParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers RulesParser
 */
class RulesParserTest extends TestCase {
	public function test_validate_schema__with_empty_content__returns_empty_rules() {
		$rules_content = '';

		$this->assertEqualsRules( [], RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_empty_object__returns_empty_rules() {
		$rules_content = '{}';

		$this->assertEqualsRules( [], RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_empty_rules_array__returns_empty_rules() {
		$rules_content = '{
			"rules": []
		}';

		$this->assertEqualsRules( [], RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_invalid_json__returns_error() {
		$rules_content = '{ test: [}';

		$this->assertInstanceOf( 'WP_Error', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__without_rules_array__returns_error() {
		$rules_content = '{ "invalid": "rules" }';

		$this->assertInstanceOf( 'WP_Error', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_default_allowed_blocks_rule__passes_validation() {
		$rules_content = '{
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [
						"core/paragraph",
						"core/heading",
						"core/media-text"
					]
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [
					'core/paragraph',
					'core/heading',
					'core/media-text',
				],
			],
		], RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_default_rule_with_roles__returns_error() {
		$rules_content = '{
			"rules": [
				{
					"type": "default",
					"roles": [ "adminstrator" ],
					"allowed": [ "core/paragraph" ]
				}
			]
		}';

		// A "default"-type rule should not allow "roles" to be specified
		$this->assertInstanceOf( 'WP_Error', RulesParser::parse( $rules_content ) );
	}

	// Utility methods
	private function assertEqualsRules( $expected, $actual ) {
		// Enhance assertEquals by returning unexpected WP_Error message in test failure
		if ( is_wp_error( $actual ) ) {
			$error_message = $actual->get_error_message();

			$this->assertEquals( $expected, $actual, $error_message );
		} else {
			$this->assertEquals( $expected, $actual );
		}
	}
}
