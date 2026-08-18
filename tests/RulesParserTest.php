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

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_empty_object__returns_empty_rules() {
		$rules_content = '{}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_empty_rules_array__returns_empty_rules() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": []
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_empty_scalar_root__returns_empty_rules() {
		$this->assertEqualsRules( [], $this->parse_rules( 'false' ) );
	}

	public function test_validate_schema__with_null_root__returns_empty_rules() {
		$this->assertEqualsRules( [], $this->parse_rules( 'null' ) );
	}

	public function test_validate_schema__with_zero_root__returns_empty_rules() {
		$this->assertEqualsRules( [], $this->parse_rules( '0' ) );
	}

	public function test_validate_schema__with_empty_array_root__returns_empty_rules() {
		$this->assertEqualsRules( [], $this->parse_rules( '[]' ) );
	}

	#endregion Empty rules tests

	#region JSON error tests

	public function test_validate_schema__with_invalid_json__returns_error() {
		$rules_content = '{ test: [}';

		$this->assertWPErrorCode( 'parsing-error-from-json', $this->parse_rules( $rules_content ) );
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

		$this->assertWPErrorCode( 'parsing-error-from-json', $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_invalid_utf8__returns_error() {
		$rules_content = "{\"version\":\"1.0.0\",\"rules\":[],\"invalid\":\"\xB1\x31\"}";

		$this->assertWPErrorCode( 'parsing-error-from-json', $this->parse_rules( $rules_content ) );
	}

	#region JSON errors

	public function test_validate_schema__without_version__returns_error() {
		$rules_content = '{ "invalid": "rules" }';

		$this->assertWPErrorCode( 'logic-missing-version', $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_non_empty_scalar_root__returns_error() {
		$this->assertWPErrorCode( 'logic-invalid-root', $this->parse_rules( '"rules"' ) );
	}

	public function test_validate_schema__with_non_empty_array_root__returns_error() {
		$this->assertWPErrorCode( 'logic-invalid-root', $this->parse_rules( '[ { "version": "1.0.0" } ]' ) );
	}

	public function test_validate_schema__with_incorrect_version__returns_error() {
		$rules_content = '{
			"version": "2.0.0",
			"rules": []
		}';

		$this->assertWPErrorCode( 'logic-missing-version', $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__without_rules_array__returns_error() {
		$rules_content = '{ "version": "1.0.0" }';

		$this->assertWPErrorCode( 'logic-missing-rules', $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_non_string_schema_uri__ignores_metadata() {
		$rules_content = '{
			"$schema": false,
			"version": "1.0.0",
			"rules": []
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_unknown_root_key__ignores_unknown_key() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [],
			"unknown": true
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_unknown_root_key__keeps_valid_rules() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{ "type": "default", "allowedBlocks": [ "core/paragraph" ] }
			],
			"unknown": { "enabled": true }
		}';

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [ 'core/paragraph' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	#endregion JSON errors

	#region General rules errors

	public function test_validate_schema__with_rules_wrong_type__returns_error() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": 7
		}';

		$this->assertWPErrorCode( 'logic-non-array-rules', $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_rule_missing_type__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [ {} ]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_non_object_rule__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [ "not-an-object" ]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_array_rule__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [ [] ]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_incorrect_rule_type__drops_rule() {
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

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_string_allowed_blocks__wraps_value() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": "core/paragraph"
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [ 'core/paragraph' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_null_allowed_blocks__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": null
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_object_allowed_blocks__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": { "first": "core/paragraph" }
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_non_string_allowed_block__filters_value() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph", false ]
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [ 'core/paragraph' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_duplicate_allowed_blocks__deduplicates_values_in_original_order() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ "core/image", "core/paragraph", "core/image" ]
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [ 'core/image', 'core/paragraph' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_only_invalid_allowed_blocks__removes_property_and_keeps_other_settings() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ false, 7 ],
					"allowedFeatures": [ "codeEditor" ]
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'            => 'default',
				'allowedFeatures' => [ 'codeEditor' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_explicitly_empty_allowed_blocks__preserves_empty_list() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": []
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_unsupported_allowed_feature__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": [ "unsupported" ]
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_duplicate_allowed_feature__deduplicates_values() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": [ "codeEditor", "codeEditor" ]
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'            => 'default',
				'allowedFeatures' => [ 'codeEditor' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_string_allowed_feature__wraps_value() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": "lockBlocks"
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'            => 'default',
				'allowedFeatures' => [ 'lockBlocks' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_mixed_allowed_features__filters_and_deduplicates_values() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": [ "lockBlocks", false, "unsupported", "codeEditor", "lockBlocks" ]
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'            => 'default',
				'allowedFeatures' => [ 'lockBlocks', 'codeEditor' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_only_invalid_allowed_features__removes_property_and_keeps_other_settings() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph" ],
					"allowedFeatures": [ false, "unsupported" ]
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [ 'core/paragraph' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_explicitly_empty_allowed_features__preserves_empty_list() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": []
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'            => 'default',
				'allowedFeatures' => [],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_null_allowed_features__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": null
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_object_allowed_features__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"allowedFeatures": { "first": "codeEditor" }
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_unknown_rule_key__strips_unknown_key() {
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

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [ 'core/paragraph' ],
			],
		], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_array_block_settings__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": []
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_invalid_top_level_block_name__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": { "color": {} }
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_invalid_nested_allowed_blocks__drops_rule() {
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

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_non_object_block_settings_value__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": { "core/paragraph": [] }
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_non_object_nested_block_settings__drops_rule() {
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

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_partially_invalid_top_level_block_settings__keeps_valid_blocks() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"color": { "text": true },
						"core/image": false,
						"core/paragraph": { "color": { "text": true } }
					}
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'default',
					'blockSettings' => [
						'core/paragraph' => [
							'color' => [ 'text' => true ],
						],
					],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	public function test_validate_schema__with_partially_invalid_nested_block_settings__keeps_valid_settings() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"core/group": {
							"allowedBlocks": { "first": "core/paragraph" },
							"core/image": false,
							"core/paragraph": { "typography": { "fontSize": true } },
							"color": { "text": true }
						}
					}
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'default',
					'blockSettings' => [
						'core/group' => [
							'core/paragraph' => [
								'typography' => [ 'fontSize' => true ],
							],
							'color'          => [ 'text' => true ],
						],
					],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	public function test_validate_schema__with_nested_string_allowed_blocks__wraps_value() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"core/group": { "allowedBlocks": "core/paragraph" }
					}
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'default',
					'blockSettings' => [
						'core/group' => [
							'allowedBlocks' => [ 'core/paragraph' ],
						],
					],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	public function test_validate_schema__with_mixed_nested_allowed_blocks__filters_and_deduplicates_values() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"core/group": {
							"allowedBlocks": [ "core/image", false, "core/paragraph", "core/image" ]
						}
					}
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'default',
					'blockSettings' => [
						'core/group' => [
							'allowedBlocks' => [ 'core/image', 'core/paragraph' ],
						],
					],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	public function test_validate_schema__with_invalid_rule_between_valid_rules__keeps_valid_rules() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": [ "administrator" ],
					"allowedBlocks": [ "core/heading" ]
				},
				{
					"type": "unknown",
					"allowedBlocks": [ "core/image" ]
				},
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph", false ],
					"allowedFeatures": [ "codeEditor", "unsupported" ]
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'role',
					'roles'         => [ 'administrator' ],
					'allowedBlocks' => [ 'core/heading' ],
				],
				[
					'type'            => 'default',
					'allowedBlocks'   => [ 'core/paragraph' ],
					'allowedFeatures' => [ 'codeEditor' ],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	#endregion General rules errors

	#region Default-type rule errors

	public function test_validate_schema__with_default_empty_rule__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default"
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_default_rule_type_with_roles__drops_rule() {
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

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_default_rule_with_roles__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"roles": [ "administrator", "editor" ]
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_default_rule_with_post_types__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"postTypes": [ "page" ]
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_default_applicability_keys__strips_keys_and_keeps_effective_settings() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"roles": [ "administrator" ],
					"postTypes": [ "page" ],
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertEqualsRules( [
			[
				'type'          => 'default',
				'allowedBlocks' => [ 'core/paragraph' ],
			],
		], $this->parse_rules( $rules_content ) );
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

		$this->assertWPErrorCode( 'logic-rule-default-multiple', $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_multiple_default_rules__rejects_when_first_default_is_unusable() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{ "type": "default" },
				{
					"type": "default",
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertWPErrorCode( 'logic-rule-default-multiple', $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_multiple_default_rules__identifies_first_default_ordinal() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				false,
				{ "type": "default", "allowedBlocks": [ "core/paragraph" ] },
				{ "type": "default", "allowedBlocks": [ "core/image" ] }
			]
		}';

		$actual = $this->parse_rules( $rules_content );

		$this->assertWPErrorCode( 'logic-rule-default-multiple', $actual );
		$this->assertStringContainsString( '2nd rule', $actual->get_error_message() );
	}

	public function test_validate_schema__with_multiple_default_rules__formats_teen_ordinal() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				false, false, false, false, false, false, false, false, false, false,
				{ "type": "default", "allowedBlocks": [ "core/paragraph" ] },
				{ "type": "default", "allowedBlocks": [ "core/image" ] }
			]
		}';

		$actual = $this->parse_rules( $rules_content );

		$this->assertWPErrorCode( 'logic-rule-default-multiple', $actual );
		$this->assertStringContainsString( '11th rule', $actual->get_error_message() );
	}

	#endregion Default-type rule errors

	#region Role-type rule errors

	public function test_validate_schema__with_role_rule_missing_roles__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"allowedBlocks": [ "core/media-text" ]
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_role_rule_with_empty_roles__drops_rule() {
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

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_object_roles__drops_rule() {
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

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_only_non_string_roles__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": [ false, 7 ],
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_non_string_role__filters_value() {
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

		$this->assertEqualsRules(
			[
				[
					'type'          => 'role',
					'roles'         => [ 'administrator' ],
					'allowedBlocks' => [ 'core/paragraph' ],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	public function test_validate_schema__with_string_role__wraps_value() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": "administrator",
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'role',
					'roles'         => [ 'administrator' ],
					'allowedBlocks' => [ 'core/paragraph' ],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	public function test_validate_schema__with_mixed_roles__filters_and_deduplicates_values() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": [ "editor", false, "administrator", "editor" ],
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'role',
					'roles'         => [ 'editor', 'administrator' ],
					'allowedBlocks' => [ 'core/paragraph' ],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	public function test_validate_schema__with_role_empty_rule__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "role",
					"roles": [ "administrator", "editor" ]
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	#endregion Role-type rule errors

	#region PostType-type rule errors

	public function test_validate_schema__with_post_type_rule_missing_post_types__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "postType",
					"allowedBlocks": [ "core/media-text" ]
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_post_type_rule_with_empty_post_types__drops_rule() {
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

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_post_type_empty_rule__drops_rule() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "postType",
					"postTypes": [ "administrator", "editor" ]
				}
			]
		}';

		$this->assertEqualsRules( [], $this->parse_rules( $rules_content ) );
	}

	public function test_validate_schema__with_string_post_type__wraps_value() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "postType",
					"postTypes": "page",
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'postType',
					'postTypes'     => [ 'page' ],
					'allowedBlocks' => [ 'core/paragraph' ],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	public function test_validate_schema__with_mixed_post_types__filters_and_deduplicates_values() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "postType",
					"postTypes": [ "post", 7, "page", "post" ],
					"allowedBlocks": [ "core/paragraph" ]
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'postType',
					'postTypes'     => [ 'post', 'page' ],
					'allowedBlocks' => [ 'core/paragraph' ],
				],
			],
			$this->parse_rules( $rules_content )
		);
	}

	#endregion PostType-type rule errors

	#region Parser warning tests

	public function test_parse_with_warnings__with_valid_rules__returns_no_warnings(): void {
		$result = RulesParser::parse_with_warnings( '{
			"version": "1.0.0",
			"rules": [
				{ "type": "default", "allowedBlocks": [ "core/paragraph" ] }
			]
		}' );

		$this->assertIsArray( $result );
		$this->assertSame( [], $result['warnings'] );
		$this->assertSame( [ 'core/paragraph' ], $result['rules'][0]['allowedBlocks'] );
	}

	public function test_parse_with_warnings__reports_multiple_repairs_in_ordinal_order(): void {
		$result = RulesParser::parse_with_warnings( '{
			"version": "1.0.0",
			"rules": [
				false,
				{ "type": "unknown", "allowedBlocks": [ "core/image" ] },
				{
					"type": "role",
					"roles": "administrator",
					"allowedBlocks": [ "core/image", false, "core/image" ],
					"allowedFeatures": [ "codeEditor", "unsupported" ],
					"unknown": true
				}
			]
		}' );

		$this->assertIsArray( $result );
		$this->assertSame(
			[
				'1st rule: dropped because it is not an object.',
				'2nd rule: dropped because it has no valid type.',
				'3rd rule: converted roles to an array.',
				'3rd rule: removed unsupported property "unknown".',
				'3rd rule: removed 1 invalid allowedBlocks value.',
				'3rd rule: removed 1 duplicate allowedBlocks value.',
				'3rd rule: removed 1 unsupported allowedFeatures value.',
			],
			$result['warnings']
		);
		$this->assertSame(
			[
				[
					'type'            => 'role',
					'roles'           => [ 'administrator' ],
					'allowedBlocks'   => [ 'core/image' ],
					'allowedFeatures' => [ 'codeEditor' ],
				],
			],
			$result['rules']
		);
	}

	public function test_parse_with_warnings__reports_dropped_rule_after_invalid_targets(): void {
		$result = RulesParser::parse_with_warnings( '{
			"version": "1.0.0",
			"rules": [
				{ "type": "postType", "postTypes": [ false ], "allowedBlocks": [ "core/image" ] }
			]
		}' );

		$this->assertIsArray( $result );
		$this->assertSame( [], $result['rules'] );
		$this->assertSame(
			[
				'1st rule: removed 1 invalid postTypes value.',
				'1st rule: dropped because it has no valid postTypes.',
			],
			$result['warnings']
		);
	}

	public function test_parse_with_warnings__reports_removed_properties_before_dropping_empty_default(): void {
		$result = RulesParser::parse_with_warnings( '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"roles": [ "administrator" ],
					"allowedBlocks": null,
					"blockSettings": []
				}
			]
		}' );

		$this->assertIsArray( $result );
		$this->assertSame( [], $result['rules'] );
		$this->assertSame(
			[
				'1st rule: removed "roles" because default rules apply to everyone.',
				'1st rule: removed invalid allowedBlocks.',
				'1st rule: removed invalid blockSettings.',
				'1st rule: dropped because it has no usable governance settings.',
			],
			$result['warnings']
		);
	}

	public function test_parse_with_warnings__reports_nested_repairs_with_property_path(): void {
		$result = RulesParser::parse_with_warnings( '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"invalid": {},
						"core/group": {
							"allowedBlocks": [ "core/image", false ],
							"core/paragraph": false,
							"color": { "text": true }
						}
					}
				}
			]
		}' );

		$this->assertIsArray( $result );
		$this->assertSame(
			[
				'1st rule: removed invalid blockSettings entry "blockSettings.invalid".',
				'1st rule: removed 1 invalid blockSettings.core/group.allowedBlocks value.',
				'1st rule: removed invalid blockSettings entry "blockSettings.core/group.core/paragraph".',
			],
			$result['warnings']
		);
		$this->assertSame( [ 'core/image' ], $result['rules'][0]['blockSettings']['core/group']['allowedBlocks'] );
	}

	public function test_parse_with_warnings__reports_ignored_root_properties(): void {
		$result = RulesParser::parse_with_warnings( '{
			"$schema": false,
			"version": "1.0.0",
			"rules": [],
			"unknown": true
		}' );

		$this->assertIsArray( $result );
		$this->assertSame(
			[
				'Removed invalid root-level "$schema" metadata.',
				'Removed unsupported root-level property "unknown".',
			],
			$result['warnings']
		);
	}

	public function test_parse_with_warnings__keeps_fatal_errors_as_wp_error(): void {
		$result = RulesParser::parse_with_warnings( '{ "version": "1.0.0" }' );

		$this->assertWPErrorCode( 'logic-missing-rules', $result );
	}

	#endregion Parser warning tests

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
		), $this->parse_rules( $rules_content ) );
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

		$actual = $this->parse_rules( $rules_content );

		$this->assertIsArray( $actual );
		$this->assertCount( 1, $actual );
		$this->assertSame( 'custom-red', $actual[0]['blockSettings']['core/media-text']['core/heading']['color']['palette'][0]['slug'] );
	}

	public function test_validate_schema__preserves_standalone_wildcard_block_settings() {
		$rules_content = '{
			"version": "1.0.0",
			"rules": [
				{
					"type": "default",
					"blockSettings": {
						"*": {
							"color": { "text": true }
						},
						"core/group": {
							"*": {
								"typography": { "fontSize": true }
							}
						}
					}
				}
			]
		}';

		$this->assertEqualsRules(
			[
				[
					'type'          => 'default',
					'blockSettings' => [
						'*'          => [
							'color' => [ 'text' => true ],
						],
						'core/group' => [
							'*' => [
								'typography' => [ 'fontSize' => true ],
							],
						],
					],
				],
			],
			$this->parse_rules( $rules_content )
		);
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

		$this->assertSame( $native_rules, $this->parse_rules( $rules_content ) );
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

			$result = RulesParser::parse_with_warnings( $rules_content );
			$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
			$this->assertNotEmpty( $result['rules'] );
			$this->assertSame( [], $result['warnings'] );
		}
	}

	#endregion Valid rules testing

	// Utility methods
	private function parse_rules( string $rules_content ) {
		$result = RulesParser::parse_with_warnings( $rules_content );

		return is_wp_error( $result ) ? $result : $result['rules'];
	}

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
