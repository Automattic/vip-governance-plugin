<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\NestedGovernanceProcessing;
use PHPUnit\Framework\TestCase;

/**
 * @covers NestedGovernanceProcessing
 */
class NestedGovernanceProcessingTest extends TestCase {

	public function test_get_nested_settings_and_css_happy_path() {
		$block_settings = array(
			'core/media-text' => array(
				'allowedBlocks' => array( 'core/paragraph', 'core/heading', 'core/image' ),
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
			'core/quote'      => array(
				'allowedBlocks'  => array( 'core/paragraph', 'core/heading' ),
				'core/paragraph' => array(
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
		);

		$expected_settings = array(
			'settings' => array(
				'core/media-text' => array(
					'allowedBlocks' => array( 'core/paragraph', 'core/heading', 'core/image' ),
					'core/heading'  => array(
						'color' => array(
							'text'    => true,
							'palette' => array(
								'theme' => array(
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
				'core/quote'      => array(
					'allowedBlocks'  => array( 'core/paragraph', 'core/heading' ),
					'core/paragraph' => array(
						'color' => array(
							'text'    => true,
							'palette' => array(
								'theme' => array(
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
				'core/heading'    => array(
					'color' => array(
						'text'    => true,
						'palette' => array(
							'theme' => array(
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
			'css'      => ' .wp-block-media-text .wp-block-heading{--wp--preset--color--custom-red: #ff0000;} .wp-block-media-text .wp-block-heading.has-custom-red-color{color: var(--wp--preset--color--custom-red) !important;} .wp-block-media-text .wp-block-heading.has-custom-red-background-color{background-color: var(--wp--preset--color--custom-red) !important;} .wp-block-media-text .wp-block-heading.has-custom-red-border-color{border-color: var(--wp--preset--color--custom-red) !important;} .wp-block-quote p{--wp--preset--color--custom-green: #00FF00;} .wp-block-quote p.has-custom-green-color{color: var(--wp--preset--color--custom-green) !important;} .wp-block-quote p.has-custom-green-background-color{background-color: var(--wp--preset--color--custom-green) !important;} .wp-block-quote p.has-custom-green-border-color{border-color: var(--wp--preset--color--custom-green) !important;} .wp-block-heading{--wp--preset--color--custom-yellow: #FFFF00;} .wp-block-heading.has-custom-yellow-color{color: var(--wp--preset--color--custom-yellow) !important;} .wp-block-heading.has-custom-yellow-background-color{background-color: var(--wp--preset--color--custom-yellow) !important;} .wp-block-heading.has-custom-yellow-border-color{border-color: var(--wp--preset--color--custom-yellow) !important;}',
		);

		$nested_settings_and_css = NestedGovernanceProcessing::get_nested_settings_and_css( $block_settings );

		$this->assertEquals( $expected_settings, $nested_settings_and_css, sprintf( 'Unexpected output: %s', wp_json_encode( $nested_settings_and_css ) ) );
	}
}
