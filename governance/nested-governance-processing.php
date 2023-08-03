<?php
/**
 * Nested governance processing.
 * 
 * @package vip-governance
 */

namespace WPCOMVIP\Governance;

use WP_Theme_JSON;
use WP_Theme_JSON_Gutenberg;
use WP_Block_Type_Registry;

defined( 'ABSPATH' ) || die();

/**
 * Nested governance processing class that's used to process nested settings.
 */
class NestedGovernanceProcessing {
	/**
	 * Nested settings and css that's used to process nested settings.
	 *
	 * @var array
	 */
	private static $nested_settings_and_css = null;

	/**
	 * Get the nested settings and css that's used to process nested settings.
	 *
	 * @param array $governance_rules the governance rules, specific to a user.
	 * 
	 * @return array the nested settings and css.
	 * 
	 * @access private
	 */
	public static function get_nested_settings_and_css( $governance_rules ) {
		if ( null !== self::$nested_settings_and_css ) {
			return self::$nested_settings_and_css;
		}

		$setting_nodes                 = static::get_nested_setting_nodes( $governance_rules );
		self::$nested_settings_and_css = static::apply_settings_transformations( $governance_rules, $setting_nodes );

		return self::$nested_settings_and_css;
	}

	/**
	 * Creates new rulesets as classes for each preset value such as:
	 *
	 *   .has-value-color {
	 *     color: value;
	 *   }
	 *
	 *   .has-value-background-color {
	 *     background-color: value;
	 *   }
	 *
	 *   .has-value-font-size {
	 *     font-size: value;
	 *   }
	 *
	 *   .has-value-gradient-background {
	 *     background: value;
	 *   }
	 *
	 *   p.has-value-gradient-background {
	 *     background: value;
	 *   }
	 *
	 * @since 5.9.0
	 *
	 * @param array $theme_json the theme json.
	 * @param array $setting_nodes Nodes with settings.
	 * @param array $origins       List of origins to process presets from.
	 * 
	 * @return string The new stylesheet.
	 */
	protected static function get_preset_classes( $theme_json, $setting_nodes, $origins ) {
		$preset_rules = '';

		foreach ( $setting_nodes as $metadata ) {
			if ( null === $metadata['selector'] ) {
				continue;
			}

			$selector      = $metadata['selector'];
			$node          = _wp_array_get( $theme_json, $metadata['path'], array() );
			$preset_rules .= static::compute_preset_classes( $node, $selector, $origins );
		}

		return $preset_rules;
	}

	/**
	 * Appends a sub-selector to an existing one.
	 *
	 * Given the compounded $selector "h1, h2, h3"
	 * and the $to_append selector ".some-class" the result will be
	 * "h1.some-class, h2.some-class, h3.some-class".
	 *
	 * @since 5.8.0
	 * @since 6.1.0 Added append position.
	 * @since 6.3.0 Removed append position parameter.
	 *
	 * @param string $selector  Original selector.
	 * @param string $to_append Selector to append.
	 * 
	 * @return string The new selector.
	 */
	protected static function append_to_selector( $selector, $to_append ) {
		if ( ! str_contains( $selector, ',' ) ) {
			return $selector . $to_append;
		}
		$new_selectors = array();
		$selectors     = explode( ',', $selector );
		foreach ( $selectors as $sel ) {
			$new_selectors[] = $sel . $to_append;
		}
		return implode( ',', $new_selectors );
	}

	/**
	 * Given a settings array, returns the generated rulesets
	 * for the preset classes.
	 *
	 * @since 5.8.0
	 * @since 5.9.0 Added the `$origins` parameter.
	 *
	 * @param array  $settings Settings to process.
	 * @param string $selector Selector wrapping the classes.
	 * @param array  $origins  List of origins to process.
	 * @return string The result of processing the presets.
	 */
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
			$slugs = static::get_settings_slugs( $settings, $preset_metadata, $origins );
			foreach ( $preset_metadata['classes'] as $class => $property ) {
				foreach ( $slugs as $slug ) {
					$css_var    = static::replace_slug_in_string( $preset_metadata['css_vars'], $slug );
					$class_name = static::replace_slug_in_string( $class, $slug );

					// $selector is often empty, so we can save ourselves the `append_to_selector()` call then.
					$new_selector = '' === $selector ? $class_name : static::append_to_selector( $selector, $class_name );
					$stylesheet  .= static::to_ruleset(
						$new_selector,
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

	/**
	 * Similar to get_settings_values_by_slug, but doesn't compute the value.
	 *
	 * @since 5.9.0
	 *
	 * @param array $settings        Settings to process.
	 * @param array $preset_metadata One of the PRESETS_METADATA values.
	 * @param array $origins         List of origins to process.
	 * @return array Array of presets where the key and value are both the slug.
	 */
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

	/**
	 * Converts each styles section into a list of rulesets
	 * to be appended to the stylesheet.
	 * These rulesets contain all the css variables (custom variables and preset variables).
	 *
	 * See glossary at https://developer.mozilla.org/en-US/docs/Web/CSS/Syntax
	 *
	 * For each section this creates a new ruleset such as:
	 *
	 *     block-selector {
	 *       --wp--preset--category--slug: value;
	 *       --wp--custom--variable: value;
	 *     }
	 *
	 * @since 5.8.0
	 * @since 5.9.0 Added the `$origins` parameter.
	 *
	 * @param array $theme_json Theme JSON.
	 * @param array $nodes   Nodes with settings.
	 * @param array $origins List of origins to process.
	 * 
	 * @return string The new stylesheet.
	 */
	protected static function get_css_variables( $theme_json, $nodes, $origins ) {
		$stylesheet = '';
		foreach ( $nodes as $metadata ) {
			if ( null === $metadata['selector'] ) {
				continue;
			}

			$selector = $metadata['selector'];

			$node         = _wp_array_get( $theme_json, $metadata['path'], array() );
			$declarations = array_merge( static::compute_preset_vars( $node, $origins ), static::compute_theme_vars( $node ) );

			$stylesheet .= static::to_ruleset( $selector, $declarations );
		}

		return $stylesheet;
	}

	/**
	 * Given the block settings, extracts the CSS Custom Properties
	 * for the presets and adds them to the $declarations array
	 * following the format:
	 *
	 * ```php
	 * array(
	 *   'name'  => 'property_name',
	 *   'value' => 'property_value,
	 * )
	 * ```
	 *
	 * @since 5.8.0
	 * @since 5.9.0 Added the `$origins` parameter.
	 *
	 * @param array $settings Settings to process.
	 * @param array $origins  List of origins to process.
	 * @return array The modified $declarations.
	 */
	protected static function compute_preset_vars( $settings, $origins ) {
		if ( class_exists( 'WP_Theme_JSON_Gutenberg' ) ) {
			$presets_metadata = WP_Theme_JSON_Gutenberg::PRESETS_METADATA;
		} else {
			$presets_metadata = WP_Theme_JSON::PRESETS_METADATA;
		}

		$declarations = array();
		foreach ( $presets_metadata as $preset_metadata ) {
			$values_by_slug = static::get_settings_values_by_slug( $settings, $preset_metadata, $origins );
			foreach ( $values_by_slug as $slug => $value ) {
				$declarations[] = array(
					'name'  => static::replace_slug_in_string( $preset_metadata['css_vars'], $slug ),
					'value' => $value,
				);
			}
		}

		return $declarations;
	}

	/**
	 * Given an array of settings, extracts the CSS Custom Properties
	 * for the custom values and adds them to the $declarations
	 * array following the format:
	 *
	 * ```php
	 * array(
	 *   'name'  => 'property_name',
	 *   'value' => 'property_value,
	 * )
	 * ```
	 *
	 * @since 5.8.0
	 *
	 * @param array $settings Settings to process.
	 * @return array The modified $declarations.
	 */
	protected static function compute_theme_vars( $settings ) {
		$declarations  = array();
		$custom_values = _wp_array_get( $settings, array( 'custom' ), array() );
		$css_vars      = static::flatten_tree( $custom_values );
		foreach ( $css_vars as $key => $value ) {
			$declarations[] = array(
				'name'  => '--wp--custom--' . $key,
				'value' => $value,
			);
		}

		return $declarations;
	}

	/**
	 * Given a tree, it creates a flattened one
	 * by merging the keys and binding the leaf values
	 * to the new keys.
	 *
	 * It also transforms camelCase names into kebab-case
	 * and substitutes '/' by '-'.
	 *
	 * This is thought to be useful to generate
	 * CSS Custom Properties from a tree,
	 * although there's nothing in the implementation
	 * of this function that requires that format.
	 *
	 * For example, assuming the given prefix is '--wp'
	 * and the token is '--', for this input tree:
	 *
	 *     {
	 *       'some/property': 'value',
	 *       'nestedProperty': {
	 *         'sub-property': 'value'
	 *       }
	 *     }
	 *
	 * it'll return this output:
	 *
	 *     {
	 *       '--wp--some-property': 'value',
	 *       '--wp--nested-property--sub-property': 'value'
	 *     }
	 *
	 * @since 5.8.0
	 *
	 * @param array  $tree   Input tree to process.
	 * @param string $prefix Optional. Prefix to prepend to each variable. Default empty string.
	 * @param string $token  Optional. Token to use between levels. Default '--'.
	 * @return array The flattened tree.
	 */
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
					static::flatten_tree( $value, $new_prefix, $token )
				);
			} else {
				$result[ $new_key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Given a selector and a declaration list,
	 * creates the corresponding ruleset.
	 *
	 * @since 5.8.0
	 *
	 * @param string $selector     CSS selector.
	 * @param array  $declarations List of declarations.
	 * @return string The resulting CSS ruleset.
	 */
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

	/**
	 * Gets preset values keyed by slugs based on settings and metadata.
	 *
	 * <code>
	 * $settings = array(
	 *     'typography' => array(
	 *         'fontFamilies' => array(
	 *             array(
	 *                 'slug'       => 'sansSerif',
	 *                 'fontFamily' => '"Helvetica Neue", sans-serif',
	 *             ),
	 *             array(
	 *                 'slug'   => 'serif',
	 *                 'colors' => 'Georgia, serif',
	 *             )
	 *         ),
	 *     ),
	 * );
	 * $meta = array(
	 *    'path'      => array( 'typography', 'fontFamilies' ),
	 *    'value_key' => 'fontFamily',
	 * );
	 * $values_by_slug = get_settings_values_by_slug();
	 * // $values_by_slug === array(
	 * //   'sans-serif' => '"Helvetica Neue", sans-serif',
	 * //   'serif'      => 'Georgia, serif',
	 * // );
	 * </code>
	 *
	 * @since 5.9.0
	 *
	 * @param array $settings        Settings to process.
	 * @param array $preset_metadata One of the PRESETS_METADATA values.
	 * @param array $origins         List of origins to process.
	 * @return array Array of presets where each key is a slug and each value is the preset value.
	 */
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

	/**
	 * Transforms a slug into a CSS Custom Property.
	 *
	 * @since 5.9.0
	 *
	 * @param string $input String to replace.
	 * @param string $slug  The slug value to use to generate the custom property.
	 * @return string The CSS Custom Property. Something along the lines of `--wp--preset--color--black`.
	 */
	protected static function replace_slug_in_string( $input, $slug ) {
		return strtr( $input, array( '$slug' => $slug ) );
	}

	/**
	 * Transform the nested settings, into a format that Gutenberg understands
	 * including the CSS which would be injected into the editor.
	 *
	 * @param array $nested_settings the governance rules.
	 * @param array $nodes the nested settings within the rules.
	 * 
	 * @return array the transformed nested settings, and css.
	 */
	private static function apply_settings_transformations( $nested_settings, $nodes ) {
		if ( class_exists( 'WP_Theme_JSON_Gutenberg' ) ) {
			$presets_metadata = WP_Theme_JSON_Gutenberg::PRESETS_METADATA;
		} else {
			$presets_metadata = WP_Theme_JSON::PRESETS_METADATA;
		}

		// Insert nested settings in expected theme.json path.
		$theme_json = array(
			'settings' => array(
				'blocks' => $nested_settings,
			),
		);

		foreach ( $nodes as $node ) {
			foreach ( $presets_metadata as $preset_metadata ) {
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				// Path is merged ['path', 'to', 'setting', 'node'] + ['path', 'to', 'setting'] e.g.
				// ['settings', 'blocks', 'core/heading'] + ['color', 'palette'].
				$path = array_merge( $node['path'], $preset_metadata['path'] );

				$preset = _wp_array_get( $theme_json, $path, null );
				if ( null !== $preset ) {
					// If the preset is not already keyed with an origin.
					if ( isset( $preset[0] ) || empty( $preset ) ) {
						// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
						// Under $path, add ['theme' => '<original option value>'].
						_wp_array_set( $theme_json, $path, array( 'theme' => $preset ) );
					}
				}
			}
		}

		// Unwrap nested settings from theme.json path.
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
	 * @param array $nested_settings nested settings to be used.
	 * 
	 * @return array
	 */
	protected static function get_nested_setting_nodes( $nested_settings ) {
		$nodes             = array();
		$registry          = WP_Block_Type_Registry::get_instance()->get_all_registered();
		$valid_block_names = array_keys( $registry );

		return static::get_settings_of_blocks( $valid_block_names, $nodes, $nested_settings );
	}

	/**
	 * Get the CSS selector for a block using the block name
	 *
	 * @param string $block_name the name of the block.
	 * 
	 * @return string the css selector for the block.
	 */
	protected static function get_css_selector_for_block( $block_name ) {
		// ToDo: Add support for the selectors API as that's going to be going in, in the future.
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
	 * @param array $valid_block_names List of valid block names.
	 * @param array $nodes             The metadata of the nodes that have been built so far.
	 * @param array $current_block     The current block to break down.
	 * @param array $current_selector  The current selector of the current block.
	 * @param array $current_path      The current path to the block.
	 * 
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

				$nodes = static::get_settings_of_blocks( $valid_block_names, $nodes, $block, $selector, $path );
			}
		}

		return $nodes;
	}

}
