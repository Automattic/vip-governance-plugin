<?php

namespace WPCOMVIP\Governance\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPCOMVIP\Governance\RestApi;
use WP_REST_Request;

/**
 * @covers RestApi
 */
class RestApiTest extends TestCase {
	protected function tearDown(): void {
		remove_all_filters( 'vip_governance__governance_rules_json' );
		parent::tearDown();
	}

	public function test_get_governance_rules__converts_throwable_to_rest_error(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static function () {
				throw new RuntimeException( 'Test exception' );
			}
		);

		$result = RestApi::get_governance_rules_for_rule_type( new WP_REST_Request( 'GET', '/vip-governance/v1/rules' ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'vip-governance-rules-error', $result->get_error_code() );
		$this->assertSame( 500, $result->get_error_data()['status'] );
	}

	public function test_register_rest_routes__preserves_valid_role_and_post_type_strings(): void {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();
		$args   = $routes['/vip-governance/v1/rules'][0]['args'];

		$this->assertSame( 'Super Admin', $args['role']['sanitize_callback']( 'Super Admin' ) );
		$this->assertSame( 'Custom Post Type', $args['postType']['sanitize_callback']( 'Custom Post Type' ) );
	}

	public function test_get_governance_rules__with_no_usable_rules__preserves_v1_empty_response(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{
				"version": "1.0.0",
				"rules": [
					{ "type": "unknown", "allowedBlocks": [ "core/paragraph" ] }
				]
			}'
		);

		$result = RestApi::get_governance_rules_for_rule_type( new WP_REST_Request( 'GET', '/vip-governance/v1/rules' ) );

		$this->assertSame( [], $result );
	}

	public function test_get_governance_rules__with_repaired_rules__keeps_v1_shape_without_warnings(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{
				"version": "1.0.0",
				"rules": [
					{ "type": "default", "allowedBlocks": "core/paragraph" }
				]
			}'
		);

		$result = RestApi::get_governance_rules_for_rule_type( new WP_REST_Request( 'GET', '/vip-governance/v1/rules' ) );

		$this->assertSame(
			[
				'allowedBlocks'   => [ 'core/paragraph' ],
				'blockSettings'   => [],
				'allowedFeatures' => [],
			],
			$result
		);
		$this->assertArrayNotHasKey( 'warnings', $result );
	}
}
