<?php
/**
 * Output fixtures captured from trunk 5a55166 on WordPress 6.8.
 *
 * @package vip-governance
 */

namespace WPCOMVIP\Governance\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPCOMVIP\Governance\NestedGovernanceProcessing;

#[CoversClass( NestedGovernanceProcessing::class )]
class NestedGovernanceCompatibilityTest extends TestCase {
	private const BLOCK_SELECTORS = [
		'compat/parent'          => '.compat-parent',
		'compat/child'           => '.compat-child',
		'compat/compound-parent' => ':is(.parent, .alternate), [data-parent=","]',
		'compat/compound-child'  => ':where(.child, .other), [data-child=","]',
	];

	protected function setUp(): void {
		parent::setUp();
		$this->reset_cache();
		foreach ( self::BLOCK_SELECTORS as $name => $selector ) {
			register_block_type( $name, [ 'selectors' => [ 'root' => $selector ] ] );
		}
	}

	protected function tearDown(): void {
		foreach ( self::BLOCK_SELECTORS as $name => $selector ) {
			unregister_block_type( $name );
		}
		$this->reset_cache();
		parent::tearDown();
	}

	private function reset_cache(): void {
		( new \ReflectionProperty( NestedGovernanceProcessing::class, 'nested_settings_and_css' ) )->setValue( null, null );
	}

	public static function output_cases(): iterable {
		$inputs   = json_decode( file_get_contents( __DIR__ . '/private/nested-governance-inputs.json' ), true, 512, JSON_THROW_ON_ERROR );
		$expected = json_decode( file_get_contents( __DIR__ . '/private/nested-governance-expected.json' ), true, 512, JSON_THROW_ON_ERROR );
		// The one authorized change is explicit; the original trunk fixture stays frozen.
		$expected['fluid typography settings']['css'] = '.compat-child{--wp--preset--font-size--large: clamp(27.894px, 1.743rem + ((1vw - 4px) * 3.351), 48px);}.compat-child.has-large-font-size{font-size: var(--wp--preset--font-size--large) !important;}';
		foreach ( $inputs as $name => $settings ) {
			yield $name => [ $settings, $expected[ $name ] ];
		}
	}

	#[DataProvider( 'output_cases' )]
	public function test_output_and_input_preservation( array $settings, array $expected ): void {
		$original = $settings;
		$this->assertSame( $expected, NestedGovernanceProcessing::get_nested_settings_and_css( $settings ) );
		$this->assertSame( $original, $settings );
	}

	public static function font_size_cases(): iterable {
		yield 'block opt-out' => [ false, [], '48px', false ];
		yield 'block opt-out overrides theme' => [ false, [], '48px', true ];
		yield 'omitted fluid preserves native theme output' => [ null, [], null, true ];
		yield 'omitted fluid with a static theme' => [ null, [], '48px', false ];
		yield 'preset opt-out' => [ true, [ 'fluid' => false ], '48px', false ];
		yield 'preset limits and block viewport' => [
			[
				'minViewportWidth' => '400px',
				'maxViewportWidth' => '1000px',
			],
			[
				'fluid' => [
					'min' => '24px',
					'max' => '48px',
				],
			],
			'clamp(24px, 1.5rem + ((1vw - 4px) * 4), 48px)',
			false,
		];
		yield 'partial fluid settings retain theme bounds' => [
			[ 'minViewportWidth' => '400px' ],
			[
				'fluid' => [
					'min' => '24px',
					'max' => '48px',
				],
			],
			'clamp(24px, 1.5rem + ((1vw - 4px) * 4), 48px)',
			[ 'maxViewportWidth' => '1000px' ],
		];
		yield 'partial governance settings retain native preset bounds' => [
			[ 'minViewportWidth' => '500px' ],
			[
				'slug'  => 'native-large',
				'fluid' => [
					'min' => '24px',
					'max' => '48px',
				],
			],
			'clamp(24px, 1.5rem + ((1vw - 5px) * 3.429), 48px)',
			[
				'minViewportWidth' => '320px',
				'maxViewportWidth' => '1000px',
			],
			[
				'typography' => [
					'fluid'     => [
						'minViewportWidth' => '400px',
						'maxViewportWidth' => '1200px',
					],
					'fontSizes' => [
						[
							'slug' => 'native-large',
							'size' => '48px',
						],
					],
				],
			],
		];
	}

	#[DataProvider( 'font_size_cases' )]
	public function test_font_size_output( array|bool|null $block_fluid, array $preset_overrides, ?string $expected_size, array|bool $theme_fluid, array $native_block_settings = [] ): void {
		$filter = static function ( $theme_json ) use ( $theme_fluid, $native_block_settings ) {
			return $theme_json->update_with( [
				'version'  => 3,
				'settings' => [
					'typography' => [ 'fluid' => $theme_fluid ],
					'blocks'     => [ 'core/heading' => $native_block_settings ],
				],
			] );
		};
		add_filter( 'wp_theme_json_data_theme', $filter );
		$this->reset_theme_settings_cache();
		try {
			$this->assertSame( $theme_fluid, wp_get_global_settings( [ 'typography', 'fluid' ] ) );
			$preset = array_merge( [
				'slug' => 'large',
				'size' => '48px',
			], $preset_overrides );
			// Reference WordPress directly, without passing any governance settings.
			$expected_size ??= wp_get_typography_font_size_value( $preset );
			$typography      = [ 'fontSizes' => [ $preset ] ];
			if ( null !== $block_fluid ) {
				$typography['fluid'] = $block_fluid;
			}
			$result = NestedGovernanceProcessing::get_nested_settings_and_css( [ 'core/heading' => [ 'typography' => $typography ] ] );
			$slug   = _wp_to_kebab_case( $preset['slug'] );
			$this->assertSame(
				'.wp-block-heading{--wp--preset--font-size--' . $slug . ': ' . $expected_size . ';}.wp-block-heading.has-' . $slug . '-font-size{font-size: var(--wp--preset--font-size--' . $slug . ') !important;}',
				$result['css']
			);
		} finally {
			remove_filter( 'wp_theme_json_data_theme', $filter );
			$this->reset_theme_settings_cache();
		}
	}

	private function reset_theme_settings_cache(): void {
		\WP_Theme_JSON_Resolver::clean_cached_data();
		wp_cache_delete( 'wp_get_global_settings_theme', 'theme_json' );
		wp_cache_delete( 'wp_get_global_settings_custom', 'theme_json' );
	}

	public static function native_font_size_cases(): iterable {
		$preset = [
			'slug' => 'review-parity',
			'size' => '48px',
		];
		yield 'root preset inheritance' => [ true, [] ];
		yield 'native presets with explicit opt-out' => [
			true,
			[
				'typography' => [
					'fluid'     => false,
					'fontSizes' => [ $preset ],
				],
			],
		];
		yield 'native presets without fluid' => [ true, [ 'typography' => [ 'fontSizes' => [ $preset ] ] ] ];
		yield 'native unrelated typography preserves inherited preset' => [ true, [ 'typography' => [ 'customFontSize' => false ] ] ];
		yield 'native opt-out without own preset preserves inherited preset' => [ true, [ 'typography' => [ 'fluid' => false ] ] ];
		yield 'another native preset preserves inherited preset' => [
			true,
			[
				'typography' => [
					'fontSizes' => [
						[
							'slug' => 'other',
							'size' => '24px',
						],
					],
				],
			],
		];
		yield 'mixed native and inherited presets' => [
			true,
			[
				'typography' => [
					'fontSizes' => [
						[
							'slug' => 'other',
							'size' => '24px',
						],
					],
				],
			],
			[
				[
					'slug' => 'other',
					'size' => '24px',
				],
			],
		];
		yield 'native partial viewport bounds'  => [
			[
				'minViewportWidth' => '320px',
				'maxViewportWidth' => '1000px',
			],
			[
				'typography' => [
					'fluid'     => [ 'minViewportWidth' => '400px' ],
					'fontSizes' => [ $preset ],
				],
			],
		];
	}

	#[DataProvider( 'native_font_size_cases' )]
	public function test_omitted_settings_match_native_preset_css( array|bool $root_fluid, array $native_settings, array $additional_presets = [] ): void {
		$preset = [
			'slug' => 'review-parity',
			'size' => '48px',
		];
		$filter = static function ( $theme_json ) use ( $root_fluid, $native_settings, $preset ) {
			return $theme_json->update_with( [
				'version'  => 3,
				'settings' => [
					'typography' => [
						'fluid'     => $root_fluid,
						'fontSizes' => [ $preset ],
					],
					'blocks'     => [ 'core/heading' => $native_settings ],
				],
			] );
		};
		add_filter( 'wp_theme_json_data_theme', $filter );
		$this->reset_theme_settings_cache();
		try {
			// Core emits the root variable first, then a heading override when one exists.
			$native_css   = \WP_Theme_JSON_Resolver::get_merged_data()->get_stylesheet( [ 'variables' ] );
			$presets      = array_merge( [ $preset ], $additional_presets );
			$declarations = '';
			$classes      = '';
			foreach ( $presets as $declared_preset ) {
				$slug = $declared_preset['slug'];
				preg_match_all( '/--wp--preset--font-size--' . preg_quote( $slug, '/' ) . ': ([^;]+);/', $native_css, $matches );
				$this->assertNotEmpty( $matches[1] );
				$declarations .= '--wp--preset--font-size--' . $slug . ': ' . end( $matches[1] ) . ';';
				$classes      .= '.wp-block-heading.has-' . $slug . '-font-size{font-size: var(--wp--preset--font-size--' . $slug . ') !important;}';
			}
			$result = NestedGovernanceProcessing::get_nested_settings_and_css( [
				'core/heading' => [ 'typography' => [ 'fontSizes' => $presets ] ],
			] );
			$this->assertSame( '.wp-block-heading{' . $declarations . '}' . $classes, $result['css'] );
		} finally {
			remove_filter( 'wp_theme_json_data_theme', $filter );
			$this->reset_theme_settings_cache();
		}
	}

	public function test_first_result_is_cached_for_the_request(): void {
		$expected = NestedGovernanceProcessing::get_nested_settings_and_css( [ 'compat/child' => [ 'color' => [ 'text' => false ] ] ] );
		$this->assertSame( $expected, NestedGovernanceProcessing::get_nested_settings_and_css( [] ) );
	}
}
