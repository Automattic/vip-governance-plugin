<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\GovernanceUtilities;
use PHPUnit\Framework\TestCase;

/**
 * @covers GovernanceUtilities
 */
class GovernanceUtilitiesTest extends TestCase {
	protected function tearDown(): void {
		remove_all_filters( 'vip_governance__governance_file_path' );
		remove_all_filters( 'vip_governance__governance_rules_json' );
		remove_all_filters( 'vip_governance__default_role_for_user_without_roles' );
		parent::tearDown();
	}

	public function test_get_parsed_governance_rules__from_private_dir() {
		$result = GovernanceUtilities::get_parsed_governance_rules();

		$this->assertEquals( $this->get_parsed_governance_rules(), $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
	}

	public function test_get_governance_rules_json__with_invalid_filtered_path__returns_error() {
		add_filter( 'vip_governance__governance_file_path', '__return_empty_array' );

		$result = GovernanceUtilities::get_governance_rules_json();

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'governance-file-path-invalid', $result->get_error_code() );
	}

	public function test_get_governance_rules_json__with_invalid_filtered_content__returns_error() {
		add_filter( 'vip_governance__governance_rules_json', '__return_empty_array' );

		$result = GovernanceUtilities::get_governance_rules_json();

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'governance-rules-invalid', $result->get_error_code() );
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

	public function test_get_rules_by_type__default_block_settings_do_not_override_role_values(): void {
		$rules = [
			[
				'type'          => 'role',
				'roles'         => [ 'administrator' ],
				'blockSettings' => [
					'core/heading' => [
						'color' => [
							'text'    => false,
							'palette' => [ [ 'slug' => 'role-color' ] ],
						],
					],
				],
			],
			[
				'type'          => 'default',
				'blockSettings' => [
					'core/heading' => [
						'color' => [
							'text'       => true,
							'background' => true,
							'palette'    => [ [ 'slug' => 'default-color' ] ],
						],
					],
				],
			],
		];

		$result = GovernanceUtilities::get_rules_by_type( $rules, [ 'administrator' ], 'post' );

		$this->assertSame(
			[
				'text'       => false,
				'palette'    => [
					[ 'slug' => 'role-color' ],
					[ 'slug' => 'default-color' ],
				],
				'background' => true,
			],
			$result['blockSettings']['core/heading']['color']
		);
	}

	public function test_get_rules_by_type__default_block_settings_preserve_value_shapes(): void {
		$rules = [
			[
				'type'          => 'role',
				'roles'         => [ 'administrator' ],
				'blockSettings' => [
					'core/heading'   => [
						'color'      => false,
						'typography' => [
							'fontSizes' => [ 'custom' => true ],
						],
						'spacing'    => [
							'units' => [],
						],
					],
					'core/paragraph' => [],
				],
			],
			[
				'type'          => 'default',
				'blockSettings' => [
					'core/heading'   => [
						'color'      => [ 'text' => true ],
						'typography' => [
							'fontSizes' => [ [ 'slug' => 'default-size' ] ],
							'dropCap'   => true,
						],
						'spacing'    => [
							'units' => [ 'px', 'rem' ],
						],
					],
					'core/paragraph' => [
						'color' => [ 'text' => true ],
					],
				],
			],
		];

		$result = GovernanceUtilities::get_rules_by_type( $rules, [ 'administrator' ], 'post' );

		$this->assertSame(
			[
				'color'      => false,
				'typography' => [
					'fontSizes' => [ 'custom' => true ],
					'dropCap'   => true,
				],
				'spacing'    => [
					'units' => [ 'px', 'rem' ],
				],
			],
			$result['blockSettings']['core/heading']
		);
		$this->assertSame( [ 'color' => [ 'text' => true ] ], $result['blockSettings']['core/paragraph'] );
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

	/**
	 * Test that filter returns string role for user with no roles.
	 *
	 * @since 1.1.0
	 */
	public function test_get_rules_by_type__empty_roles_with_filter_returning_string() {
		// Add filter to return 'editor' role for users with no roles.
		add_filter( 'vip_governance__default_role_for_user_without_roles', function () {
			return 'editor';
		}, 10, 0 );

		// Call with explicit empty roles array.
		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules_with_editor(), array() );

		// Should return editor rules. Note: Since editor role rule has empty allowedBlocks,
		// it falls back to default rules, which is the expected behavior.
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

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );

		// Clean up filter.
		remove_all_filters( 'vip_governance__default_role_for_user_without_roles' );
	}

	/**
	 * Test that filter returns array of roles for user with no roles.
	 *
	 * @since 1.1.0
	 */
	public function test_get_rules_by_type__empty_roles_with_filter_returning_array() {
		// Add filter to return array of roles for users with no roles.
		add_filter( 'vip_governance__default_role_for_user_without_roles', function () {
			return array( 'administrator' );
		}, 10, 0 );

		// Call with explicit empty roles array.
		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array() );

		// Should return administrator rules, not default rules.
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

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );

		// Clean up filter.
		remove_all_filters( 'vip_governance__default_role_for_user_without_roles' );
	}

	/**
	 * Test that filter returning null uses default ruleset for user with no roles.
	 *
	 * @since 1.1.0
	 */
	public function test_get_rules_by_type__empty_roles_with_filter_returning_null() {
		// Add filter that returns null (should use default ruleset).
		add_filter( 'vip_governance__default_role_for_user_without_roles', function () {
			return null;
		}, 10, 0 );

		// Call with explicit empty roles array.
		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array() );

		// Should return default rules.
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

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );

		// Clean up filter.
		remove_all_filters( 'vip_governance__default_role_for_user_without_roles' );
	}

	/**
	 * Test that filter is not applied when user has roles.
	 *
	 * @since 1.1.0
	 */
	public function test_get_rules_by_type__with_roles_filter_not_applied() {
		// Add filter that would return 'administrator' for users with no roles.
		// This should NOT be called since we're passing 'editor' role.
		$filter_called = false;
		add_filter( 'vip_governance__default_role_for_user_without_roles', function () use ( &$filter_called ) {
			$filter_called = true;
			return 'administrator';
		}, 10, 0 );

		// Call with explicit editor role.
		$result = GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules_with_editor(), array( 'editor' ) );

		// Should return editor rules (filter should not have been called).
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

		$this->assertEquals( $expected_rules, $result, sprintf( 'Unexpected output: %s', wp_json_encode( $result ) ) );
		$this->assertFalse( $filter_called, 'Filter should not be called when user has roles' );

		// Clean up filter.
		remove_all_filters( 'vip_governance__default_role_for_user_without_roles' );
	}

	/**
	 * Test that filter receives correct parameters (current_user and site_id).
	 *
	 * @since 1.1.0
	 */
	public function test_get_rules_by_type__filter_receives_correct_parameters() {
		$received_user    = null;
		$received_site_id = null;
		$value            = null;

		// Add filter to capture parameters.
		add_filter( 'vip_governance__default_role_for_user_without_roles', function ( $value, $current_user, $site_id ) use ( &$received_user, &$received_site_id ) {
			$received_user    = $current_user;
			$received_site_id = $site_id;
			return 'administrator';
		}, 10, 3 );

		// Call with explicit empty roles array.
		GovernanceUtilities::get_rules_by_type( $this->get_parsed_governance_rules(), array() );

		// Verify filter received correct parameters.
		$this->assertInstanceOf( 'WP_User', $received_user, 'Filter should receive WP_User object' );
		$this->assertIsInt( $received_site_id, 'Filter should receive site ID as integer' );
		$this->assertEquals( get_current_blog_id(), $received_site_id, 'Filter should receive current site ID' );

		// Clean up filter.
		remove_all_filters( 'vip_governance__default_role_for_user_without_roles' );
	}

	/**
	 * Helper method to get governance rules with editor role.
	 *
	 * @since 1.1.0
	 * @return array Parsed governance rules including editor role.
	 */
	private function get_parsed_governance_rules_with_editor() {
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
				'type'          => 'role',
				'allowedBlocks' => array(),
				'roles'         => array(
					'editor',
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
