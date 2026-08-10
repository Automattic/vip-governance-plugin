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
}
