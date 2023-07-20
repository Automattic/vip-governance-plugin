<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\RulesParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers RulesParser
 */
class RulesParserTest extends TestCase {
	#region Empty rules tests

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
			"version": "0.1.0",
			"rules": []
		}';

		$this->assertEqualsRules( [], RulesParser::parse( $rules_content ) );
	}

	#endredion Empty rules tests

	#region JSON error tests

	public function test_validate_schema__with_invalid_json__returns_error() {
		$rules_content = '{ test: [}';

		$this->assertWPErrorCode( 'parsing-error-from-json', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_trailing_comma__returns_error() {
		$rules_content = '{
			"version": "0.1.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph" ]
				}
			],
		}';

		$this->assertWPErrorCode( 'parsing-error-from-json', RulesParser::parse( $rules_content ) );
	}

	#region JSON errors

	public function test_validate_schema__without_version__returns_error() {
		$rules_content = '{ "invalid": "rules" }';

		$this->assertWPErrorCode( 'logic-missing-version', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__without_rules_array__returns_error() {
		$rules_content = '{ "version": "0.1.0" }';

		$this->assertWPErrorCode( 'logic-missing-rules', RulesParser::parse( $rules_content ) );
	}

	#endregion JSON errors

	#region General rules errors

	public function test_validate_schema__with_rules_wrong_type__returns_error() {
		$rules_content = '{
			"version": "0.1.0",
			"rules": 7
		}';

		$this->assertWPErrorCode( 'logic-non-array-rules', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_rule_missing_type__returns_error() {
		$rules_content = '{
			"version": "0.1.0",
			"rules": [ {} ]
		}';

		$this->assertWPErrorCode( 'logic-incorrect-rule-type', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_incorrect_rule_type__returns_error() {
		$rules_content = '{
			"version": "0.1.0",
			"rules": [
				{
					"type": "notarule",
					"roles": [ "adminstrator" ],
					"allowed": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-incorrect-rule-type', RulesParser::parse( $rules_content ) );
	}

	#endregion General rules errors

	#region Default-type rule errors

	public function test_validate_schema__with_default_empty_rule__returns_error() {
		$rules_content = '{
			"version": "0.1.0",
			"rules": [
				{
					"type": "default"
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-empty', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_default_rule_type_with_roles__returns_error() {
		$rules_content = '{
			"version": "0.1.0",
			"rules": [
				{
					"type": "default",
					"roles": [ "adminstrator" ],
					"allowed": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-default-roles', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_default_rule_with_roles__returns_error() {
		$rules_content = '{
			"version": "0.1.0",
			"rules": [
				{
					"type": "default",
					"roles": [ "administrator", "editor" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-default-roles', RulesParser::parse( $rules_content ) );
	}

	#endregion Default-type rule errors

	#region Role-type rule errors

	public function test_validate_schema__with_role_rule_missing_roles__returns_error() {
		$rules_content = '{
			"version": "0.1.0",
			"rules": [
				{
					"type": "role",
					"allowedBlocks": [ "core/media-text" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-role-missing-roles', RulesParser::parse( $rules_content ) );
	}

	#endregion Role-type rule errors

	#region Valid rules testing

	public function test_validate_schema__with_default_allowed_blocks_rule__passes_validation() {
		$rules_content = '{
			"version": "0.1.0",
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

	#endregion Valid rules testing

	// Utility methods
	private function assertWPErrorCode( $expected, $actual ) {
		$this->assertInstanceOf( 'WP_Error', $actual );
		$this->assertEquals( $expected, $actual->get_error_code() );
	}

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
