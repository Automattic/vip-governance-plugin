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
 *
 * Preset value, slug, and ruleset helpers are inherited from WP_Theme_JSON.
 * Nesting, robust selector parsing, and per-preset CSS ordering remain local.
 * See NESTED-GOVERNANCE.md for the core API dependency and compatibility tests.
 */
class NestedGovernanceProcessing extends WP_Theme_JSON {
	/**
	 * Nested settings and css that's used to process nested settings.
	 *
	 * @var array
	 */
	private static ?array $nested_settings_and_css = null;

	/**
	 * Default origin, and preset base for the settings.
	 *
	 * @var string
	 */
	private const DEFAULT_ORIGIN = 'theme';

	/**
	 * Get the nested settings and css that's used to process nested settings.
	 *
	 * @param array $governance_rules Ggovernance rules, specific to a user.
	 *
	 * @return array Nested settings and css.
	 *
	 * @access private
	 */
	public static function get_nested_settings_and_css( array $governance_rules ): array {
		if ( null !== self::$nested_settings_and_css ) {
			return self::$nested_settings_and_css;
		}

		if ( class_exists( 'WP_Theme_JSON_Gutenberg' ) ) {
			$presets_metadata = WP_Theme_JSON_Gutenberg::PRESETS_METADATA;
		} else {
			$presets_metadata = WP_Theme_JSON::PRESETS_METADATA;
		}

		$settings   = $governance_rules;
		$stylesheet = static::process_nested_settings( $settings, WP_Block_Type_Registry::get_instance()->get_all_registered(), $presets_metadata );

		self::$nested_settings_and_css = [
			'settings' => $settings,
			'css'      => $stylesheet,
		];

		return self::$nested_settings_and_css;
	}

	/**
	 * Normalize presets and render supported block rules in their original order.
	 *
	 * @param array       $settings          Block settings, normalized in place.
	 * @param array       $blocks_registered Registered block types.
	 * @param array       $presets_metadata  Core or Gutenberg preset metadata.
	 * @param string|null $parent_selector   Selector of the enclosing block.
	 * @return string Nested preset CSS.
	 */
	private static function process_nested_settings( array &$settings, array $blocks_registered, array $presets_metadata, ?string $parent_selector = null ): string {
		$stylesheet = '';
		foreach ( $settings as $block_name => &$block_settings ) {
			$is_wildcard = '*' === $block_name || str_ends_with( $block_name, '/*' );
			if ( ! $is_wildcard && ! array_key_exists( $block_name, $blocks_registered ) ) {
				continue;
			}

			// Paragraphs have no wp-block class. Preserve the existing broad wildcard selector.
			$block_selector = $is_wildcard ? 'p, [class*=wp-block]' : wp_get_block_css_selector( $blocks_registered[ $block_name ] );
			$selector       = null === $block_selector ? $parent_selector : self::scope_nested_selector( $parent_selector, $block_selector );

			$stylesheet .= static::get_preset_css( $block_settings, $selector, $presets_metadata, $block_name );
			$stylesheet .= static::process_nested_settings( $block_settings, $blocks_registered, $presets_metadata, $selector );
		}
		unset( $block_settings );
		return $stylesheet;
	}

	/**
	 * Wrap theme presets and render each preset's variables followed by its classes.
	 *
	 * @param array       $settings         Settings for one block, normalized in place.
	 * @param string|null $selector         Scoped block selector.
	 * @param array       $presets_metadata Core or Gutenberg preset metadata.
	 * @param string      $block_name       Block whose native settings provide defaults.
	 * @return string Preset CSS for this block.
	 */
	private static function get_preset_css( array &$settings, ?string $selector, array $presets_metadata, string $block_name ): string {
		$stylesheet = '';
		foreach ( $presets_metadata as $metadata ) {
			$preset = _wp_array_get( $settings, $metadata['path'], null );
			if ( null !== $preset && ( isset( $preset[0] ) || empty( $preset ) ) ) {
				$preset = [ self::DEFAULT_ORIGIN => $preset ];
				_wp_array_set( $settings, $metadata['path'], $preset );
			}
			if ( null === $selector ) {
				continue;
			}

			if ( ! empty( $preset[ self::DEFAULT_ORIGIN ] ) && is_callable( $metadata['value_func'] ?? null ) ) {
				$core_settings   = wp_get_global_settings();
				$native_settings = $core_settings['blocks'][ $block_name ] ?? [];
				$root_slugs      = static::get_settings_slugs( $core_settings, $metadata );
				$native_slugs    = static::get_settings_slugs( $native_settings, $metadata );
				$contexts        = [
					'root'  => array_replace_recursive( $core_settings, $settings ),
					'block' => array_replace_recursive( array_replace( $core_settings, $native_settings ), $settings ),
				];
				foreach ( $contexts as &$context ) {
					// Defaults are callback context, not additional allowed presets.
					_wp_array_set( $context, $metadata['path'], $preset );
				}
				unset( $context );
				$value_func             = $metadata['value_func'];
				$metadata['value_func'] = static function ( $preset ) use ( $value_func, $contexts, $root_slugs, $native_slugs ) {
					$slug = _wp_to_kebab_case( $preset['slug'] );
					// Existing root variables retain their root context until overridden by a native preset.
					$context = isset( $native_slugs[ $slug ] ) || ! isset( $root_slugs[ $slug ] ) ? 'block' : 'root';
					return call_user_func( $value_func, $preset, $contexts[ $context ] );
				};
			}
			$declarations = [];
			foreach ( static::get_settings_values_by_slug( $settings, $metadata, [ self::DEFAULT_ORIGIN ] ) as $slug => $value ) {
				$declarations[] = [
					'name'  => static::replace_slug_in_string( $metadata['css_vars'], $slug ),
					'value' => $value,
				];
			}
			$stylesheet .= static::to_ruleset( $selector, $declarations );

			$slugs = static::get_settings_slugs( $settings, $metadata, [ self::DEFAULT_ORIGIN ] );
			foreach ( $metadata['classes'] as $class => $property ) {
				foreach ( $slugs as $slug ) {
					$css_var        = static::replace_slug_in_string( $metadata['css_vars'], $slug );
					$class_name     = static::replace_slug_in_string( $class, $slug );
					$class_selector = '' === $selector ? $class_name : static::append_nested_selector( $selector, $class_name );
					$stylesheet    .= static::to_ruleset( $class_selector, [
						[
							'name'  => $property,
							'value' => 'var(' . $css_var . ') !important',
						],
					] );
				}
			}
		}
		return $stylesheet;
	}

	/**
	 * Scope every child selector to every parent selector.
	 *
	 * @param string|null $parent_selector Parent selector, or null for a root-level block.
	 * @param string      $child_selector  Child selector to scope.
	 *
	 * @return string Scoped selector.
	 */
	private static function scope_nested_selector( ?string $parent_selector, string $child_selector ): string {
		if ( null === $parent_selector ) {
			return $child_selector;
		}

		$scoped_selectors = [];
		$parent_selectors = self::split_nested_selector_list( $parent_selector );
		$child_selectors  = self::split_nested_selector_list( $child_selector );

		foreach ( $parent_selectors as $parent ) {
			foreach ( $child_selectors as $child ) {
				$scoped_selectors[] = $parent . ' ' . $child;
			}
		}

		return implode( ', ', $scoped_selectors );
	}

	/**
	 * Split a CSS selector list without treating commas inside brackets, parentheses,
	 * or quoted strings as selector separators.
	 *
	 * @param string $selector Selector list.
	 *
	 * @return array
	 */
	private static function split_nested_selector_list( string $selector ): array {
		$selectors         = [];
		$current_selector  = '';
		$parentheses_depth = 0;
		$bracket_depth     = 0;
		$quote             = null;
		$is_escaped        = false;

		foreach ( str_split( $selector ) as $character ) {
			if ( $is_escaped ) {
				$current_selector .= $character;
				$is_escaped        = false;
				continue;
			}

			if ( '\\' === $character ) {
				$current_selector .= $character;
				$is_escaped        = true;
				continue;
			}

			if ( null !== $quote ) {
				$current_selector .= $character;
				if ( $quote === $character ) {
					$quote = null;
				}
				continue;
			}

			if ( '"' === $character || "'" === $character ) {
				$current_selector .= $character;
				$quote             = $character;
				continue;
			}

			if ( '(' === $character ) {
				++$parentheses_depth;
			} elseif ( ')' === $character && $parentheses_depth > 0 ) {
				--$parentheses_depth;
			} elseif ( '[' === $character ) {
				++$bracket_depth;
			} elseif ( ']' === $character && $bracket_depth > 0 ) {
				--$bracket_depth;
			}

			if ( ',' === $character && 0 === $parentheses_depth && 0 === $bracket_depth ) {
				$selectors[]      = trim( $current_selector );
				$current_selector = '';
				continue;
			}

			$current_selector .= $character;
		}

		$selectors[] = trim( $current_selector );

		return $selectors;
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
	 * @return string New selector.
	 */
	private static function append_nested_selector( string $selector, string $to_append ): string {
		$selectors = self::split_nested_selector_list( $selector );

		return implode(
			', ',
			array_map(
				static function ( $single_selector ) use ( $to_append ): string {
					return $single_selector . $to_append;
				},
				$selectors
			)
		);
	}
}
