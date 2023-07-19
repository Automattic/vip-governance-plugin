<?php
/**
 * Class RestApiTest
 *
 * @package vip-governance-plugin
 */

namespace WPCOMVIP\Governance\Tests;

use Exception;
use WP_UnitTestCase;
use WP_REST_Server;
use WP_REST_Request;

/**
 * e2e tests to ensure that the REST API endpoint is available.
 */
class RestApiTest extends WP_UnitTestCase {
	private $server;

	protected function setUp(): void {
		parent::setUp();

		$this->server = new WP_REST_Server();

		global $wp_rest_server;
		$wp_rest_server = $this->server;
		do_action( 'rest_api_init', $wp_rest_server );
	}

	protected function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tearDown();
	}

	public function test_rest_api_returns_rules() {
		$request  = new WP_REST_Request( 'GET', '/vip-governance/v1/adminstrator/rules' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$result = $response->get_data();
	}
}
