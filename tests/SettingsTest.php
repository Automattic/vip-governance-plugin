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
		$this->assertStringContainsString( '⚠️ Governance Rules Validation', $output );
		$this->assertStringContainsString( 'Rules loaded with warnings:', $output );
		$this->assertStringContainsString( 'converted allowedBlocks to an array.', $output );
		$this->assertStringContainsString( '&quot;&lt;unsafe&gt;&quot;', $output );
		$this->assertStringContainsString( 'View Governance Rules For A Rule Type', $output );
	}

	public function test_render__with_fatal_parser_error__shows_error_and_hides_combined_rules_tool(): void {
		add_filter(
			'vip_governance__governance_rules_json',
			static fn (): string => '{ "version": "1.0.0" }'
		);

		ob_start();
		Settings::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '❌ Governance Rules Validation', $output );
		$this->assertStringContainsString( 'Failed to load:', $output );
		$this->assertStringNotContainsString( 'View Governance Rules For A Rule Type', $output );
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

		$this->assertStringContainsString( '✅ Governance Rules Validation', $output );
		$this->assertStringContainsString( 'Rules loaded successfully.', $output );
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

		$this->assertSame( 1, preg_match( '/<select[^>]*id="user-role-selector"[^>]*>(.*?)<\/select>/s', $output, $roles ) );
		$this->assertSame( 1, preg_match( '/<select[^>]*id="post-type-selector"[^>]*>(.*?)<\/select>/s', $output, $post_types ) );
		preg_match_all( '/<option value="([^"]*)">/', $roles[1], $role_options );
		preg_match_all( '/<option value="([^"]*)">/', $post_types[1], $post_type_options );

		$this->assertSame( $role_values, $role_options[1] );
		$this->assertSame( $post_type_values, $post_type_options[1] );
		$this->assertStringContainsString( '<option value="">My roles</option>', $roles[1] );
		$this->assertStringContainsString( '<option value="governance_test_role">Editorial &amp; &lt;Review&gt;</option>', $roles[1] );
		$this->assertStringContainsString( '<option value="governance_unlabeled">governance_unlabeled</option>', $roles[1] );
		$this->assertStringContainsString( '<option value="">No post type selected</option>', $post_types[1] );
		$this->assertStringContainsString( '<option value="governance_internal">Internal &amp; &lt;Review&gt;</option>', $post_types[1] );
		$this->assertStringContainsString( '<option value="governance_no_label">governance_no_label</option>', $post_types[1] );
		$this->assertStringContainsString( '<option value="editor">Editor (editor)</option>', $roles[1] );
		$this->assertStringContainsString( '<option value="governance_editor">Editor (governance_editor)</option>', $roles[1] );
		$this->assertStringContainsString( '<option value="post">Post (post)</option>', $post_types[1] );
		$this->assertStringContainsString( '<option value="governance_default">Post (governance_default)</option>', $post_types[1] );
	}
}
