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
			"version": "1.0.0",
			"rules": []
		}';

		$this->assertEqualsRules( [], RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_non_object_root__returns_error() {
		$this->assertWPErrorCode( 'logic-invalid-root', RulesParser::parse( 'false' ) );
	}

	public function test_validate_schema__with_array_root__returns_error() {
		$this->assertWPErrorCode( 'logic-invalid-root', RulesParser::parse( '[]' ) );
	}

	#endredion Empty rules tests

	#region JSON error tests

	public function test_validate_schema__with_invalid_json__returns_error() {
		$rules_content = '{ test: [}';

		$this->assertWPErrorCode( 'parsing-error-from-json', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_trailing_comma__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph" ]
				}
			],
		}';

		$this->assertWPErrorCode( 'parsing-error-from-json', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_invalid_utf8__returns_error() {
		$rules_content = "{\"version\":\"1.0.0\",\"rules\":[],\"invalid\":\"\xB1\x31\"}";

		$this->assertWPErrorCode( 'parsing-error-from-json', RulesParser::parse( $rules_content ) );
	}

	#region JSON errors

	public function test_validate_schema__without_version__returns_error() {
		$rules_content = '{ "invalid": "rules" }';

		$this->assertWPErrorCode( 'logic-missing-version', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__without_rules_array__returns_error() {
		$rules_content = '{ "version": "1.0.0" }';

		$this->assertWPErrorCode( 'logic-missing-rules', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_non_string_schema_uri__returns_error() {
		$rules_content = '{
			"$schema": false,
			"version": "1.0.0",
			"rules": []
		}';

		$this->assertWPErrorCode( 'logic-invalid-schema-uri', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_unknown_root_key__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [],
			"unknown": true
		}';

		$this->assertWPErrorCode( 'logic-unsupported-root-keys', RulesParser::parse( $rules_content ) );
	}

	#endregion JSON errors

	#region General rules errors

	public function test_validate_schema__with_rules_wrong_type__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": 7
		}';

		$this->assertWPErrorCode( 'logic-non-array-rules', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_rule_missing_type__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [ {} ]
		}';

		$this->assertWPErrorCode( 'logic-incorrect-rule-type', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_non_object_rule__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [ "not-an-object" ]
		}';

		$this->assertWPErrorCode( 'logic-rule-not-object', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_array_rule__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [ [] ]
		}';

		$this->assertWPErrorCode( 'logic-rule-not-object', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_incorrect_rule_type__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
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

	public function test_validate_schema__with_non_array_allowed_blocks__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": "core/paragraph"
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-allowed-blocks', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_null_allowed_blocks__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": null
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-allowed-blocks', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_object_allowed_blocks__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": { "first": "core/paragraph" }
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-allowed-blocks', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_non_string_allowed_block__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph", false ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-allowed-blocks', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_unsupported_allowed_feature__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": [ "unsupported" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-unsupported-allowed-feature', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_duplicate_allowed_feature__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": [ "codeEditor", "codeEditor" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-duplicate-allowed-feature', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_null_allowed_features__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": null
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-allowed-features', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_object_allowed_features__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": { "first": "codeEditor" }
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-allowed-features', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_unknown_rule_key__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph" ],
					"unknown": true
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-unsupported-keys', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_array_block_settings__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": []
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-block-settings', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_invalid_top_level_block_name__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": { "color": {} }
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-block-name', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_invalid_nested_allowed_blocks__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"core/group": { "allowedBlocks": { "first": "core/paragraph" } }
					}
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-nested-allowed-blocks', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_non_object_block_settings_value__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": { "core/paragraph": [] }
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-block-settings', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_non_object_nested_block_settings__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"core/group": { "core/paragraph": false }
					}
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-invalid-block-settings', RulesParser::parse( $rules_content ) );
	}

	#endregion General rules errors

	#region Default-type rule errors

	public function test_validate_schema__with_default_empty_rule__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
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
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"roles": [ "adminstrator" ],
					"allowed": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-default-type', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_default_rule_with_roles__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"roles": [ "administrator", "editor" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-default-type', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_default_rule_with_post_types__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"postTypes": [ "page" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-default-type', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_multiple_default_rules__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph" ]
				},
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph", "core/image" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-default-multiple', RulesParser::parse( $rules_content ) );
	}

	#endregion Default-type rule errors

	#region Role-type rule errors

	public function test_validate_schema__with_role_rule_missing_roles__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"allowedBlocks": [ "core/media-text" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-type-missing-valid-types', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_role_rule_with_empty_roles__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": [],
					"allowedBlocks": [ "core/media-text" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-type-missing-valid-types', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_object_roles__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": { "first": "administrator" },
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-type-missing-valid-types', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_non_string_role__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": [ "administrator", false ],
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-type-invalid-types', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_role_empty_rule__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": [ "administrator", "editor" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-empty', RulesParser::parse( $rules_content ) );
	}

	#endregion Role-type rule errors

	#region PostType-type rule errors

	public function test_validate_schema__with_post_type_rule_missing_post_types__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "postType",
					"allowedBlocks": [ "core/media-text" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-type-missing-valid-types', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_post_type_rule_with_empty_post_types__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "postType",
					"postTypes": [],
					"allowedBlocks": [ "core/media-text" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-type-missing-valid-types', RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_post_type_empty_rule__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "postType",
					"postTypes": [ "administrator", "editor" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-empty', RulesParser::parse( $rules_content ) );
	}

	#endregion PostType-type rule errors

	#region Valid rules testing

	public function test_validate_schema__with_default_allowed_blocks_rule__passes_validation() {
		$rules_content = '{
			"version": "1.0.0",
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

		$this->assertEqualsRules( array(
			array(
				'type'          => 'default',
				'allowedBlocks' => array(
					'core/paragraph',
					'core/heading',
					'core/media-text',
				),
			),
		), RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_schema_example_block_settings__passes_validation() {
		$rules_content = '{
			"$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": [ "administrator" ],
					"allowedFeatures": [ "codeEditor", "lockBlocks" ],
					"allowedBlocks": [ "core/media-text" ],
					"blockSettings": {
						"core/media-text": {
							"allowedBlocks": [ "core/image" ],
							"core/heading": {
								"color": {
									"text": true,
									"palette": [
										{
											"color": "#ff0000",
											"name": "Custom red",
											"slug": "custom-red"
										}
									]
								}
							}
						}
					}
				}
			]
		}';

		$actual = RulesParser::parse( $rules_content );

		$this->assertIsArray( $actual );
		$this->assertCount( 1, $actual );
		$this->assertSame( 'custom-red', $actual[0]['blockSettings']['core/media-text']['core/heading']['color']['palette'][0]['slug'] );
	}

	public function test_validate_schema__preserves_native_numeric_decoding() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"core/paragraph": {
							"custom": { "largeInteger": 9223372036854775808 }
						}
					}
				}
			]
		}';

		$native_rules = json_decode( $rules_content, true, 512, JSON_THROW_ON_ERROR )['rules'];

		$this->assertSame( $native_rules, RulesParser::parse( $rules_content ) );
	}

	public function test_validate_schema__with_shipped_rules_files__passes_validation() {
		$rules_files = [
			WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/governance-rules.json',
			WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/tests/private/governance-rules.json',
		];

		foreach ( $rules_files as $rules_file ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local test fixture.
			$rules_content = file_get_contents( $rules_file );
			$this->assertIsString( $rules_content );

			$actual = RulesParser::parse( $rules_content );
			$this->assertIsArray( $actual, is_wp_error( $actual ) ? $actual->get_error_message() : '' );
			$this->assertNotEmpty( $actual );
		}
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
