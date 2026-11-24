<?php

namespace WPCOMVIP\Governance\Tests;

use PHPUnit\Framework\TestCase;
use WPCOMVIP\Governance\Settings;

#[\PHPUnit\Framework\Attributes\CoversClass( Settings::class )]
class SettingsTest extends TestCase {
	protected function tearDown(): void {
		remove_all_filters( 'vip_governance__governance_rules_json' );
		parent::tearDown();
	}

	public function test_validate_options__with_enabled_checkbox(): void {
		$this->assertSame(
			[ Settings::OPTIONS_KEY_IS_ENABLED => true ],
			Settings::validate_options( [ Settings::OPTIONS_KEY_IS_ENABLED => 'yes' ] )
		);
	}

	public function test_validate_options__without_enabled_checkbox(): void {
		$this->assertSame(
			[ Settings::OPTIONS_KEY_IS_ENABLED => false ],
			Settings::validate_options( [] )
		);
	}

	public function test_validate_options__with_invalid_value(): void {
		$this->assertSame(
			[ Settings::OPTIONS_KEY_IS_ENABLED => false ],
			Settings::validate_options( 'invalid' )
		);
	}

	public function test_render__with_repaired_rules__shows_escaped_warnings_and_combined_rules_tool(): void {
		$filter_calls = 0;
		add_filter(
			'vip_governance__governance_rules_json',
			static function () use ( &$filter_calls ): string {
				++$filter_calls;
				return '{
					"version": "1.0.0",
					"rules": [
						{
							"type": "default",
							"allowedBlocks": "core/paragraph",
							"<unsafe>": true
						}
					]
				}';
			}
		);

		ob_start();
		Settings::render();
		$output = ob_get_clean();

		$this->assertSame( 1, $filter_calls, 'Settings should retrieve and parse the rules JSON only once.' );
		$data = $this->settings_data( $output );
		$this->assertNotEmpty( $data['warnings'] );
		$this->assertStringContainsString( 'converted allowedBlocks to an array.', implode( ' ', $data['warnings'] ) );
		$this->assertStringContainsString( '&lt;unsafe&gt;', $output );
		$this->assertFalse( $data['error'] );
	}

	public function test_render__with_fatal_parser_error__shows_error_and_hides_combined_rules_tool(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{ "version": "1.0.0" }'
		);

		ob_start();
		Settings::render();
		$output = ob_get_clean();

		$this->assertNotEmpty( $this->settings_data( $output )['error'] );
	}

	public function test_render__with_valid_rules__shows_success(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{
				"version": "1.0.0",
				"rules": [
					{ "type": "default", "allowedBlocks": [ "core/paragraph" ] }
				]
			}'
		);

		ob_start();
		Settings::render();
		$output = ob_get_clean();

		$data = $this->settings_data( $output );
		$this->assertFalse( $data['error'] );
		$this->assertSame( [], $data['warnings'] );
	}

	public function test_render__shows_registry_labels_and_preserves_preview_option_values(): void {
		wp_roles()->add_role( 'governance_test_role', 'Editorial & <Review>' );
		wp_roles()->add_role( 'governance_editor', 'Editor' );
		wp_roles()->add_role( 'governance_unlabeled', '' );
		register_post_type( 'governance_internal', [
			'labels' => [ 'singular_name' => 'Internal & <Review>' ],
			'public' => false,
		] );
		register_post_type( 'governance_default' );
		$unlabeled_post_type        = register_post_type( 'governance_no_label' );
		$unlabeled_post_type->label = '';
		unset( $unlabeled_post_type->labels->singular_name );

		$role_values      = [ '', ...array_keys( wp_roles()->roles ) ];
		$post_type_values = [ '', ...array_values( get_post_types() ) ];
		ob_start();
		try {
			Settings::render();
		} finally {
			$output = ob_get_clean();
			unregister_post_type( 'governance_no_label' );
			unregister_post_type( 'governance_default' );
			unregister_post_type( 'governance_internal' );
			remove_role( 'governance_unlabeled' );
			remove_role( 'governance_editor' );
			remove_role( 'governance_test_role' );
		}

		$data = $this->settings_data( $output );
		$this->assertSame( $role_values, array_keys( $data['roles'] ) );
		$this->assertSame( $post_type_values, array_keys( $data['postTypes'] ) );
		$this->assertSame( 'My roles', $data['roles'][''] );
		$this->assertSame( 'No post type selected', $data['postTypes'][''] );
		$this->assertSame( 'Editorial & <Review>', $data['roles']['governance_test_role'] );
		$this->assertSame( 'governance_unlabeled', $data['roles']['governance_unlabeled'] );
		$this->assertSame( 'Internal & <Review>', $data['postTypes']['governance_internal'] );
		$this->assertSame( 'governance_no_label', $data['postTypes']['governance_no_label'] );
		$this->assertSame( 'Post (post)', $data['postTypes']['post'] );
		$this->assertSame( 'Post (governance_default)', $data['postTypes']['governance_default'] );
		$this->assertSame( 'Editor (editor)', $data['roles']['editor'] );
		$this->assertSame( 'Editor (governance_editor)', $data['roles']['governance_editor'] );
	}

	private function settings_data( string $output ): array {
		$this->assertSame( 1, preg_match( '/data-settings="([^"]*)"/', $output, $match ) );
		return json_decode( html_entity_decode( $match[1], ENT_QUOTES ), true, 512, JSON_THROW_ON_ERROR );
	}
}
