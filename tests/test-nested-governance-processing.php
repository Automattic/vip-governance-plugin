<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\NestedGovernanceProcessing;
use PHPUnit\Framework\TestCase;

/**
 * @covers NestedGovernanceProcessing
 */
class NestedGovernanceProcessingTest extends TestCase {

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
			'css'      => ' .wp-block-media-text .wp-block-heading{--wp--preset--color--custom-red: #ff0000;} .wp-block-media-text .wp-block-heading.has-custom-red-color{color: var(--wp--preset--color--custom-red) !important;} .wp-block-media-text .wp-block-heading.has-custom-red-background-color{background-color: var(--wp--preset--color--custom-red) !important;} .wp-block-media-text .wp-block-heading.has-custom-red-border-color{border-color: var(--wp--preset--color--custom-red) !important;} .wp-block-quote p, [class*=wp-block]{--wp--preset--gradient--vertical-red-to-green: linear-gradient(to bottom,#ff0000 0%,#00FF00 100%);} .wp-block-quote p.has-vertical-red-to-green-gradient-background, [class*=wp-block].has-vertical-red-to-green-gradient-background{background: var(--wp--preset--gradient--vertical-red-to-green) !important;} .wp-block-heading{--wp--preset--color--custom-yellow: #FFFF00;} .wp-block-heading.has-custom-yellow-color{color: var(--wp--preset--color--custom-yellow) !important;} .wp-block-heading.has-custom-yellow-background-color{background-color: var(--wp--preset--color--custom-yellow) !important;} .wp-block-heading.has-custom-yellow-border-color{border-color: var(--wp--preset--color--custom-yellow) !important;}',
		];

		// The expected settings and CSS are different for WordPress versions before 6.1.0.
		if ( version_compare( $GLOBALS['wp_version'], '6.1.0', '<' ) ) {
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
				'css'      => ' .wp-block-media-text h1,h2,h3,h4,h5,h6{--wp--preset--color--custom-red: #ff0000;} .wp-block-media-text h1.has-custom-red-color,h2.has-custom-red-color,h3.has-custom-red-color,h4.has-custom-red-color,h5.has-custom-red-color,h6.has-custom-red-color{color: var(--wp--preset--color--custom-red) !important;} .wp-block-media-text h1.has-custom-red-background-color,h2.has-custom-red-background-color,h3.has-custom-red-background-color,h4.has-custom-red-background-color,h5.has-custom-red-background-color,h6.has-custom-red-background-color{background-color: var(--wp--preset--color--custom-red) !important;} .wp-block-media-text h1.has-custom-red-border-color,h2.has-custom-red-border-color,h3.has-custom-red-border-color,h4.has-custom-red-border-color,h5.has-custom-red-border-color,h6.has-custom-red-border-color{border-color: var(--wp--preset--color--custom-red) !important;} .wp-block-quote p, [class*=wp-block]{--wp--preset--gradient--vertical-red-to-green: linear-gradient(to bottom,#ff0000 0%,#00FF00 100%);} .wp-block-quote p.has-vertical-red-to-green-gradient-background, [class*=wp-block].has-vertical-red-to-green-gradient-background{background: var(--wp--preset--gradient--vertical-red-to-green) !important;} h1,h2,h3,h4,h5,h6{--wp--preset--color--custom-yellow: #FFFF00;} h1.has-custom-yellow-color,h2.has-custom-yellow-color,h3.has-custom-yellow-color,h4.has-custom-yellow-color,h5.has-custom-yellow-color,h6.has-custom-yellow-color{color: var(--wp--preset--color--custom-yellow) !important;} h1.has-custom-yellow-background-color,h2.has-custom-yellow-background-color,h3.has-custom-yellow-background-color,h4.has-custom-yellow-background-color,h5.has-custom-yellow-background-color,h6.has-custom-yellow-background-color{background-color: var(--wp--preset--color--custom-yellow) !important;} h1.has-custom-yellow-border-color,h2.has-custom-yellow-border-color,h3.has-custom-yellow-border-color,h4.has-custom-yellow-border-color,h5.has-custom-yellow-border-color,h6.has-custom-yellow-border-color{border-color: var(--wp--preset--color--custom-yellow) !important;}',
			];
		}

		$nested_settings_and_css = NestedGovernanceProcessing::get_nested_settings_and_css( $block_settings );

		$this->assertEquals( $expected_settings, $nested_settings_and_css, sprintf( 'Unexpected output: %s', wp_json_encode( $nested_settings_and_css ) ) );
	}
}
