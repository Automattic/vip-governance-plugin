<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\NestedGovernanceProcessing;
use PHPUnit\Framework\TestCase;

/**
 * @covers NestedGovernanceProcessing
 */
class NestedGovernanceProcessingTest extends TestCase {
	public function test_scope_selector_scopes_every_selector_without_splitting_nested_commas() {
		$method = new \ReflectionMethod( NestedGovernanceProcessing::class, 'scope_selector' );

		$actual = $method->invoke(
			null,
			'.parent:is(.one, .two), [data-parent=","]',
			':where(.child-one, .child-two), [data-child=","]'
		);

		$this->assertSame(
			'.parent:is(.one, .two) :where(.child-one, .child-two), .parent:is(.one, .two) [data-child=","], [data-parent=","] :where(.child-one, .child-two), [data-parent=","] [data-child=","]',
			$actual
		);
	}

	public function test_scope_selector_leaves_root_selector_unchanged() {
		$method = new \ReflectionMethod( NestedGovernanceProcessing::class, 'scope_selector' );

		$this->assertSame( ':is(.one, .two), [data-value=","]', $method->invoke( null, null, ':is(.one, .two), [data-value=","]' ) );
	}

	public function test_append_to_selector_does_not_split_nested_commas() {
		$method = new \ReflectionMethod( NestedGovernanceProcessing::class, 'append_to_selector' );

		$this->assertSame(
			':is(.one, .two).has-color, [data-value=","].has-color',
			$method->invoke( null, ':is(.one, .two), [data-value=","]', '.has-color' )
		);
	}

	public function test_get_nested_settings_and_css_happy_path() {
		$block_settings = [
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
				'allowedBlocks' => [ 'core/paragraph', 'core/heading' ],
				'core/*'        => [
					'color' => [
						'gradients' => [
							[
								'slug'     => 'vertical-red-to-green',
								'gradient' => 'linear-gradient(to bottom,#ff0000 0%,#00FF00 100%)',
								'name'     => 'Vertical red to green',
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
		];

		$expected_settings = [
			'settings' => [
				'core/media-text' => [
					'allowedBlocks' => [ 'core/paragraph', 'core/heading', 'core/image' ],
					'core/heading'  => [
						'color' => [
							'text'    => true,
							'palette' => [
								'theme' => [
									[
										'name'  => 'Custom red',
										'slug'  => 'custom-red',
										'color' => '#ff0000',
									],
								],
							],
						],
					],
				],
				'core/quote'      => [
					'allowedBlocks' => [ 'core/paragraph', 'core/heading' ],
					'core/*'        => [
						'color' => [
							'gradients' => [
								'theme' => [
									[
										'name'     => 'Vertical red to green',
										'slug'     => 'vertical-red-to-green',
										'gradient' => 'linear-gradient(to bottom,#ff0000 0%,#00FF00 100%)',
									],
								],
							],
						],
					],
				],
				'core/heading'    => [
					'color' => [
						'text'    => true,
						'palette' => [
							'theme' => [
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
			'css'      => '.wp-block-media-text .wp-block-heading{--wp--preset--color--custom-red: #ff0000;}.wp-block-media-text .wp-block-heading.has-custom-red-color{color: var(--wp--preset--color--custom-red) !important;}.wp-block-media-text .wp-block-heading.has-custom-red-background-color{background-color: var(--wp--preset--color--custom-red) !important;}.wp-block-media-text .wp-block-heading.has-custom-red-border-color{border-color: var(--wp--preset--color--custom-red) !important;}.wp-block-quote p, .wp-block-quote [class*=wp-block]{--wp--preset--gradient--vertical-red-to-green: linear-gradient(to bottom,#ff0000 0%,#00FF00 100%);}.wp-block-quote p.has-vertical-red-to-green-gradient-background, .wp-block-quote [class*=wp-block].has-vertical-red-to-green-gradient-background{background: var(--wp--preset--gradient--vertical-red-to-green) !important;}.wp-block-heading{--wp--preset--color--custom-yellow: #FFFF00;}.wp-block-heading.has-custom-yellow-color{color: var(--wp--preset--color--custom-yellow) !important;}.wp-block-heading.has-custom-yellow-background-color{background-color: var(--wp--preset--color--custom-yellow) !important;}.wp-block-heading.has-custom-yellow-border-color{border-color: var(--wp--preset--color--custom-yellow) !important;}',
		];

		$nested_settings_and_css = NestedGovernanceProcessing::get_nested_settings_and_css( $block_settings );

		$this->assertEquals( $expected_settings, $nested_settings_and_css, sprintf( 'Unexpected output: %s', wp_json_encode( $nested_settings_and_css ) ) );
	}
}
