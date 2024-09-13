<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\GovernanceUtilities;
use PHPUnit\Framework\TestCase;

/**
 * @covers GovernanceUtilities
 */
class GovernanceUtilitiesTest extends TestCase {
	public function test_get_parsed_governance_rules__from_private_dir() {
		$result = GovernanceUtilities::get_parsed_governance_rules();

		$this->assertEquals( $this->get_parsed_governance_rules(), $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_for_user__administrator() {
		$expected_rules = array(
			'allowedBlocks'   => array(
				'core/media-text',
				'core/heading',
				'core/paragraph',
				'core/image',
			),
			'allowedFeatures' => array(
				'codeEditor',
				'lockBlocks',
			),
			'blockSettings'   => array(
				'core/media-text' => array(
					'allowedBlocks' => array( 'core/image' ),
					'core/heading'  => array(
						'color' => array(
							'text'    => true,
							'palette' => array(
								array(
									'name'  => 'Custom red',
									'slug'  => 'custom-red',
									'color' => '#ff0000',
								),
							),
						),
					),
				),
				'core/heading'    => array(
					'color' => array(
						'text'    => true,
						'palette' => array(
							array(
								'name'  => 'Custom yellow',
								'slug'  => 'custom-yellow',
								'color' => '#FFFF00',
							),
						),
					),
				),
			),
		);

		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array( 'administrator' ) );

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_for_user__editor() {
		$expected_rules = array(
			'allowedBlocks'   => array(
				'core/heading',
				'core/paragraph',
				'core/image',
			),
			'allowedFeatures' => array(),
			'blockSettings'   => array(
				'core/heading' => array(
					'color' => array(
						'text'    => true,
						'palette' => array(
							array(
								'name'  => 'Custom yellow',
								'slug'  => 'custom-yellow',
								'color' => '#FFFF00',
							),
						),
					),
				),
			),
		);

		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array( 'editor' ) );

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_for_post_type__post() {
		$expected_rules = array(
			'allowedBlocks'   => array(
				'core/quote',
				'core/heading',
				'core/paragraph',
				'core/image',
			),
			'allowedFeatures' => array(
				'lockBlocks',
			),
			'blockSettings'   => array(
				'core/quote'   => array(
					'core/*' => array(
						'color' => array(
							'text'    => true,
							'palette' => array(
								array(
									'name'  => 'Custom green',
									'slug'  => 'custom-green',
									'color' => '#00FF00',
								),
							),
						),
					),
				),
				'core/heading' => array(
					'color' => array(
						'text'    => true,
						'palette' => array(
							array(
								'name'  => 'Custom yellow',
								'slug'  => 'custom-yellow',
								'color' => '#FFFF00',
							),
						),
					),
				),
			),
		);

		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array(), 'post' );

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_for_post_type__page() {
		$expected_rules = array(
			'allowedBlocks'   => array(
				'core/heading',
				'core/paragraph',
				'core/image',
			),
			'allowedFeatures' => array(),
			'blockSettings'   => array(
				'core/heading' => array(
					'color' => array(
						'text'    => true,
						'palette' => array(
							array(
								'name'  => 'Custom yellow',
								'slug'  => 'custom-yellow',
								'color' => '#FFFF00',
							),
						),
					),
				),
			),
		);

		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array(), 'page' );

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_for_post_type_and_role_type__administrator_post() {
		$expected_rules = array(
			'allowedBlocks'   => array(
				'core/media-text',
				'core/heading',
				'core/paragraph',
				'core/image',
			),
			'allowedFeatures' => array(
				'codeEditor',
				'lockBlocks',
			),
			'blockSettings'   => array(
				'core/media-text' => array(
					'allowedBlocks' => array( 'core/image' ),
					'core/heading'  => array(
						'color' => array(
							'text'    => true,
							'palette' => array(
								array(
									'name'  => 'Custom red',
									'slug'  => 'custom-red',
									'color' => '#ff0000',
								),
							),
						),
					),
				),
				'core/heading'    => array(
					'color' => array(
						'text'    => true,
						'palette' => array(
							array(
								'name'  => 'Custom yellow',
								'slug'  => 'custom-yellow',
								'color' => '#FFFF00',
							),
						),
					),
				),
			),
		);

		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array( 'administrator' ), 'post' );

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_for_post_type_and_role_type__author_page() {
		$expected_rules = array(
			'allowedBlocks'   => array(
				'core/heading',
				'core/paragraph',
				'core/image',
			),
			'allowedFeatures' => array(),
			'blockSettings'   => array(
				'core/heading' => array(
					'color' => array(
						'text'    => true,
						'palette' => array(
							array(
								'name'  => 'Custom yellow',
								'slug'  => 'custom-yellow',
								'color' => '#FFFF00',
							),
						),
					),
				),
			),
		);

		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array( 'author' ), 'page' );

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	private function get_parsed_governance_rules() {
		return array(
			array(
				'type'            => 'role',
				'allowedBlocks'   => array(
					'core/media-text',
				),
				'roles'           => array(
					'administrator',
				),
				'allowedFeatures' => array(
					'codeEditor',
					'lockBlocks',
				),
				'blockSettings'   => array(
					'core/media-text' => array(
						'allowedBlocks' => array( 'core/image' ),
						'core/heading'  => array(
							'color' => array(
								'text'    => true,
								'palette' => array(
									array(
										'name'  => 'Custom red',
										'slug'  => 'custom-red',
										'color' => '#ff0000',
									),
								),
							),
						),
					),
				),
			),
			array(
				'type'            => 'postType',
				'allowedBlocks'   => array(
					'core/quote',
				),
				'postTypes'       => array(
					'post',
				),
				'allowedFeatures' => array(
					'lockBlocks',
				),
				'blockSettings'   => array(
					'core/quote' => array(
						'core/*' => array(
							'color' => array(
								'text'    => true,
								'palette' => array(
									array(
										'name'  => 'Custom green',
										'slug'  => 'custom-green',
										'color' => '#00FF00',
									),
								),
							),
						),
					),
				),
			),
			array(
				'type'          => 'default',
				'allowedBlocks' => array(
					'core/heading',
					'core/paragraph',
					'core/image',
				),
				'blockSettings' => array(
					'core/heading' => array(
						'color' => array(
							'text'    => true,
							'palette' => array(
								array(
									'name'  => 'Custom yellow',
									'slug'  => 'custom-yellow',
									'color' => '#FFFF00',
								),
							),
						),
					),
				),
			),
		);
	}
}
