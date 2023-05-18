<?php

namespace WPCOMVIP\Governance;

use WP_Theme_JSON;
use WP_Theme_JSON_Gutenberg;
use WP_Block_Type_Registry;

defined( 'ABSPATH' ) || die();

class NestedGovernanceProcessing {
	private static $nested_settings_and_css = null;

	public static function get_nested_settings_and_css( $governance_rules ) {
		if ( null !== self::$nested_settings_and_css ) {
			return self::$nested_settings_and_css;
		}

		$setting_nodes                 = self::get_nested_setting_nodes( $governance_rules );
		self::$nested_settings_and_css = self::apply_settings_transformations( $governance_rules, $setting_nodes );

		return self::$nested_settings_and_css;
	}

	protected static function get_preset_classes( $theme_json, $setting_nodes, $origins ) {
		$preset_rules = '';

		foreach ( $setting_nodes as $metadata ) {
			if ( null === $metadata['selector'] ) {
				continue;
			}

			$selector      = $metadata['selector'];
			$node          = _wp_array_get( $theme_json, $metadata['path'], array() );
			$preset_rules .= self::compute_preset_classes( $node, $selector, $origins );
		}

		return $preset_rules;
	}

	protected static function append_to_selector( $selector, $to_append, $position = 'right' ) {
		$new_selectors = array();
		$selectors     = explode( ',', $selector );
		foreach ( $selectors as $sel ) {
			$new_selectors[] = 'right' === $position ? $sel . $to_append : $to_append . $sel;
		}
		return implode( ',', $new_selectors );
	}

	protected static function compute_preset_classes( $settings, $selector, $origins ) {
		if ( class_exists( 'WP_Theme_JSON_Gutenberg' ) ) {
			$presets_metadata    = WP_Theme_JSON_Gutenberg::PRESETS_METADATA;
			$root_block_selector = WP_Theme_JSON_Gutenberg::ROOT_BLOCK_SELECTOR;
		} else {
			$presets_metadata    = WP_Theme_JSON::PRESETS_METADATA;
			$root_block_selector = WP_Theme_JSON::ROOT_BLOCK_SELECTOR;
		}

		if ( $root_block_selector === $selector ) {
			// Classes at the global level do not need any CSS prefixed,
			// and we don't want to increase its specificity.
			$selector = '';
		}

		$stylesheet = '';
		foreach ( $presets_metadata as $preset_metadata ) {
			$slugs = self::get_settings_slugs( $settings, $preset_metadata, $origins );
			foreach ( $preset_metadata['classes'] as $class => $property ) {
				foreach ( $slugs as $slug ) {
					$css_var     = self::replace_slug_in_string( $preset_metadata['css_vars'], $slug );
					$class_name  = self::replace_slug_in_string( $class, $slug );
					$stylesheet .= self::to_ruleset(
						self::append_to_selector( $selector, $class_name ),
						array(
							array(
								'name'  => $property,
								'value' => 'var(' . $css_var . ') !important',
							),
						)
					);
				}
			}
		}

		return $stylesheet;
	}

	protected static function get_settings_slugs( $settings, $preset_metadata, $origins ) {
		$preset_per_origin = _wp_array_get( $settings, $preset_metadata['path'], array() );

		$result = array();
		foreach ( $origins as $origin ) {
			if ( ! isset( $preset_per_origin[ $origin ] ) ) {
				continue;
			}
			foreach ( $preset_per_origin[ $origin ] as $preset ) {
				$slug = _wp_to_kebab_case( $preset['slug'] );

				// Use the array as a set so we don't get duplicates.
				$result[ $slug ] = $slug;
			}
		}
		return $result;
	}

	protected static function get_css_variables( $theme_json, $nodes, $origins ) {
		$stylesheet = '';
		foreach ( $nodes as $metadata ) {
			if ( null === $metadata['selector'] ) {
				continue;
			}

			$selector = $metadata['selector'];

			$node         = _wp_array_get( $theme_json, $metadata['path'], array() );
			$declarations = array_merge( self::compute_preset_vars( $node, $origins ), self::compute_theme_vars( $node ) );

			$stylesheet .= self::to_ruleset( $selector, $declarations );
		}

		return $stylesheet;
	}

	protected static function compute_preset_vars( $settings, $origins ) {
		if ( class_exists( 'WP_Theme_JSON_Gutenberg' ) ) {
			$presets_metadata = WP_Theme_JSON_Gutenberg::PRESETS_METADATA;
		} else {
			$presets_metadata = WP_Theme_JSON::PRESETS_METADATA;
		}

		$declarations = array();
		foreach ( $presets_metadata as $preset_metadata ) {
			$values_by_slug = self::get_settings_values_by_slug( $settings, $preset_metadata, $origins );
			foreach ( $values_by_slug as $slug => $value ) {
				$declarations[] = array(
					'name'  => self::replace_slug_in_string( $preset_metadata['css_vars'], $slug ),
					'value' => $value,
				);
			}
		}

		return $declarations;
	}

	protected static function compute_theme_vars( $settings ) {
		$declarations  = array();
		$custom_values = _wp_array_get( $settings, array( 'custom' ), array() );
		$css_vars      = self::flatten_tree( $custom_values );
		foreach ( $css_vars as $key => $value ) {
			$declarations[] = array(
				'name'  => '--wp--custom--' . $key,
				'value' => $value,
			);
		}

		return $declarations;
	}

	protected static function flatten_tree( $tree, $prefix = '', $token = '--' ) {
		$result = array();
		foreach ( $tree as $property => $value ) {
			$new_key = $prefix . str_replace(
				'/',
				'-',
				strtolower( _wp_to_kebab_case( $property ) )
			);

			if ( is_array( $value ) ) {
				$new_prefix = $new_key . $token;
				$result     = array_merge(
					$result,
					self::flatten_tree( $value, $new_prefix, $token )
				);
			} else {
				$result[ $new_key ] = $value;
			}
		}
		return $result;
	}

	protected static function to_ruleset( $selector, $declarations ) {
		if ( empty( $declarations ) ) {
			return '';
		}

		$declaration_block = array_reduce(
			$declarations,
			static function ( $carry, $element ) {
				return $carry .= $element['name'] . ': ' . $element['value'] . ';'; },
			''
		);

		return $selector . '{' . $declaration_block . '}';
	}

	protected static function get_settings_values_by_slug( $settings, $preset_metadata, $origins ) {
		$preset_per_origin = _wp_array_get( $settings, $preset_metadata['path'], array() );

		$result = array();
		foreach ( $origins as $origin ) {
			if ( ! isset( $preset_per_origin[ $origin ] ) ) {
				continue;
			}
			foreach ( $preset_per_origin[ $origin ] as $preset ) {
				$slug = _wp_to_kebab_case( $preset['slug'] );

				$value = '';
				if ( isset( $preset_metadata['value_key'], $preset[ $preset_metadata['value_key'] ] ) ) {
					$value_key = $preset_metadata['value_key'];
					$value     = $preset[ $value_key ];
				} elseif (
					isset( $preset_metadata['value_func'] ) &&
					is_callable( $preset_metadata['value_func'] )
				) {
					$value_func = $preset_metadata['value_func'];
					$value      = call_user_func( $value_func, $preset );
				} else {
					// If we don't have a value, then don't add it to the result.
					continue;
				}

				$result[ $slug ] = $value;
			}
		}
		return $result;
	}

	protected static function replace_slug_in_string( $input, $slug ) {
		return strtr( $input, array( '$slug' => $slug ) );
	}

	private static function apply_settings_transformations( $nested_settings, $nodes ) {
		if ( class_exists( 'WP_Theme_JSON_Gutenberg' ) ) {
			$presets_metadata = WP_Theme_JSON_Gutenberg::PRESETS_METADATA;
		} else {
			$presets_metadata = WP_Theme_JSON::PRESETS_METADATA;
		}

		// Insert nested settings in expected theme.json path
		$theme_json = array(
			'settings' => array(
				'blocks' => $nested_settings,
			),
		);

		foreach ( $nodes as $node ) {
			foreach ( $presets_metadata as $preset_metadata ) {
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				// Path is merged ['path', 'to', 'setting', 'node'] + ['path', 'to', 'setting'] e.g.
				// ['settings', 'blocks', 'core/heading'] + ['color', 'palette']
				$path = array_merge( $node['path'], $preset_metadata['path'] );

				$preset = _wp_array_get( $theme_json, $path, null );
				if ( null !== $preset ) {
					// If the preset is not already keyed with an origin.
					if ( isset( $preset[0] ) || empty( $preset ) ) {
						// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
						// Under $path, add ['theme' => '<original option value>']
						_wp_array_set( $theme_json, $path, array( 'theme' => $preset ) );
					}
				}
			}
		}

		// Unwrap nested settings from theme.json path
		$nested_settings = $theme_json['settings']['blocks'];

		$extra_css_variables = self::get_css_variables( $theme_json, $nodes, [ 'default', 'theme', 'custom' ] );

		$extra_css_variables .= self::get_preset_classes( $theme_json, $nodes, [ 'default', 'theme', 'custom' ] );

		return array(
			'settings' => $nested_settings,
			'css'      => $extra_css_variables,
		);
	}

	/**
	 * Builds metadata for the setting nodes, which returns in the form of:
	 *
	 *     [
	 *       [
	 *         'path'     => ['path', 'to', 'some', 'node' ],
	 *         'selector' => 'CSS selector for some node'
	 *       ],
	 *       [
	 *         'path'     => [ 'path', 'to', 'other', 'node' ],
	 *         'selector' => 'CSS selector for other node'
	 *       ],
	 *     ]
	 *
	 * @since 5.8.0
	 *
	 * @param array $theme_json The tree to extract setting nodes from.
	 * @param array $selectors  List of selectors per block.
	 * @return array
	 */
	protected static function get_nested_setting_nodes( $nested_settings ) {
		$nodes             = array();
		$registry          = WP_Block_Type_Registry::get_instance()->get_all_registered();
		$valid_block_names = array_keys( $registry );

		return self::get_settings_of_blocks( $valid_block_names, $nodes, $nested_settings );
	}

	/**
	 * Get the CSS selector for a block using the block name
	 */
	protected static function get_css_selector_for_block( $block_name ) {
		$registry = WP_Block_Type_Registry::get_instance();
		$blocks   = $registry->get_all_registered();

		if ( isset( $blocks[ $block_name ] ) ) {
			$block = $blocks[ $block_name ];
			if (
				isset( $block->supports['__experimentalSelector'] ) &&
				is_string( $block->supports['__experimentalSelector'] )
			) {
				return $block->supports['__experimentalSelector'];
			} else {
				return '.wp-block-' . str_replace( '/', '-', str_replace( 'core/', '', $block_name ) );
			}
		}

		// Selector for the block was not found.
		return null;
	}

	/**
	 * Builds the metadata for settings.blocks, whilst ensuring support for nested blocks. This returns in the form of:
	 *
	 *     [
	 *       [
	 *         'path'     => ['path', 'to', 'some', 'node' ],
	 *         'selector' => 'CSS selector for some node'
	 *       ],
	 *       [
	 *         'path'     => [ 'path', 'to', 'other', 'node' ],
	 *         'selector' => 'CSS selector for other node'
	 *       ],
	 *     ]
	 *
	 * @param array $selectors         List of selectors per block.
	 * @param array $valid_block_names List of valid block names.
	 * @param array $nodes             The metadata of the nodes that have been built so far.
	 * @param array $current_block     The current block to break down.
	 * @param array $current_selector  The current selector of the current block.
	 * @param array $current_path      The current path to the block.
	 * @return array
	 */
	protected static function get_settings_of_blocks( $valid_block_names, $nodes, $current_block, $current_selector = null, $current_path = array() ) {
		foreach ( $current_block as $block_name => $block ) {
			if ( in_array( $block_name, $valid_block_names, true ) ) {

				$selector = is_null( $current_selector ) ? null : $current_selector;

				$looked_up_selector = self::get_css_selector_for_block( $block_name );
				if ( ! is_null( $looked_up_selector ) ) {
					$selector = $selector . ' ' . $looked_up_selector;
				}

				$path = empty( $current_path ) ? array( 'settings', 'blocks' ) : $current_path;
				array_push( $path, $block_name );

				$nodes[] = array(
					'path'     => $path,
					'selector' => $selector,
				);

				$nodes = self::get_settings_of_blocks( $valid_block_names, $nodes, $block, $selector, $path );
			}
		}

		return $nodes;
	}

}
