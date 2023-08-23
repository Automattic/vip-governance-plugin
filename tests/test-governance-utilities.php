<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\GovernanceUtilities;
use PHPUnit\Framework\TestCase;

/**
 * @covers GovernanceUtilities
 */
class GovernanceUtilitiesTest extends TestCase {
	public function test_get_parsed_governance_rules__from_private_dir() {
		$expected_rules = [
			[
				'type'            => 'role',
				'allowedBlocks'   => [
					'core/quote',
					'core/media-text',
					'core/image',
				],
				'roles'           => [
					'administrator',
				],
				'allowedFeatures' => [
					'codeEditor',
					'lockBlocks',
				],
				'blockSettings'   => [
					'core/media-text' => [
						'allowedBlocks' => [ 'core/paragraph', 'core/heading', 'core/image' ],
						'core/heading'  => [
							'color' => [
								'text'    => true,
								'palette' => [
									[
										'name'  => 'Custom red',
										'slug'  => 'custom-red',
										'color' => '#ff0000',
									],
								],
							],
						],
					],
					'core/quote'      => [
						'allowedBlocks'  => [ 'core/paragraph', 'core/heading' ],
						'core/paragraph' => [
							'color' => [
								'text'    => true,
								'palette' => [
									[
										'name'  => 'Custom green',
										'slug'  => 'custom-green',
										'color' => '#00FF00',
									],
								],
							],
						],
					],
				],
			],
			[
				'type'          => 'default',
				'allowedBlocks' => [
					'core/heading',
					'core/paragraph',
				],
				'blockSettings' => [
					'core/heading' => [
						'color' => [
							'text'    => true,
							'palette' => [
								[
									'name'  => 'Custom yellow',
									'slug'  => 'custom-yellow',
									'color' => '#FFFF00',
								],
							],
						],
					],
				],
			],
		];

		$result = GovernanceUtilities::get_parsed_governance_rules();

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_for_user__administrator() {
		$expected_rules = [
			'allowedBlocks'   => [
				'core/quote',
				'core/media-text',
				'core/image',
				'core/heading',
				'core/paragraph', 
			],
			'allowedFeatures' => [
				'codeEditor',
				'lockBlocks',
			],
			'blockSettings'   => [
				'core/media-text' => [
					'allowedBlocks' => [ 'core/paragraph', 'core/heading', 'core/image' ],
					'core/heading'  => [
						'color' => [
							'text'    => true,
							'palette' => [
								[
									'name'  => 'Custom red',
									'slug'  => 'custom-red',
									'color' => '#ff0000',
								],
							],
						],
					],
				],
				'core/quote'      => [
					'allowedBlocks'  => [ 'core/paragraph', 'core/heading' ],
					'core/paragraph' => [
						'color' => [
							'text'    => true,
							'palette' => [
								[
									'name'  => 'Custom green',
									'slug'  => 'custom-green',
									'color' => '#00FF00',
								],
							],
						],
					],
				],
				'core/heading'    => [
					'color' => [
						'text'    => true,
						'palette' => [
							[
								'name'  => 'Custom yellow',
								'slug'  => 'custom-yellow',
								'color' => '#FFFF00',
							],
						],
					],
				],
			],
		];

		$result = GovernanceUtilities::get_rules_for_user( $this->get_parsed_governance_rules(), [ 'administrator' ] );

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_for_user__editor() {
		$expected_rules = [
			'allowedBlocks'   => [
				'core/heading',
				'core/paragraph', 
			],
			'allowedFeatures' => [],
			'blockSettings'   => [
				'core/heading' => [
					'color' => [
						'text'    => true,
						'palette' => [
							[
								'name'  => 'Custom yellow',
								'slug'  => 'custom-yellow',
								'color' => '#FFFF00',
							],
						],
					],
				],
			],
		];

		$result = GovernanceUtilities::get_rules_for_user( $this->get_parsed_governance_rules(), [ 'editor' ] );

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	private function get_parsed_governance_rules() {
		return [
			[
				'type'            => 'role',
				'allowedBlocks'   => [
					'core/quote',
					'core/media-text',
					'core/image',
				],
				'roles'           => [
					'administrator',
				],
				'allowedFeatures' => [
					'codeEditor',
					'lockBlocks',
				],
				'blockSettings'   => [
					'core/media-text' => [
						'allowedBlocks' => [ 'core/paragraph', 'core/heading', 'core/image' ],
						'core/heading'  => [
							'color' => [
								'text'    => true,
								'palette' => [
									[
										'name'  => 'Custom red',
										'slug'  => 'custom-red',
										'color' => '#ff0000',
									],
								],
							],
						],
					],
					'core/quote'      => [
						'allowedBlocks'  => [ 'core/paragraph', 'core/heading' ],
						'core/paragraph' => [
							'color' => [
								'text'    => true,
								'palette' => [
									[
										'name'  => 'Custom green',
										'slug'  => 'custom-green',
										'color' => '#00FF00',
									],
								],
							],
						],
					],
				],
			],
			[
				'type'          => 'default',
				'allowedBlocks' => [
					'core/heading',
					'core/paragraph',
				],
				'blockSettings' => [
					'core/heading' => [
						'color' => [
							'text'    => true,
							'palette' => [
								[
									'name'  => 'Custom yellow',
									'slug'  => 'custom-yellow',
									'color' => '#FFFF00',
								],
							],
						],
					],
				],
			],
		];
	}
}
