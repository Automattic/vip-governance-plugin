<?php
/**
 * The rules parser engine
 *
 * @package vip-governance
 */

namespace WPCOMVIP\Governance;

use JsonException;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use stdClass;
use WP_Error;

defined( 'ABSPATH' ) || die();

/**
 * Class for parsing and validating governance rules.
 */
class RulesParser {
	private const ALLOWED_FEATURES = [ 'codeEditor', 'lockBlocks' ];
	private const BLOCK_NAME_REGEX = '/^[a-z][a-z0-9-]*\/(?:[a-z][a-z0-9-]*|\*)$/';
	private const ROOT_KEYS        = [ '$schema', 'version', 'rules' ];

	// Update this when the rules schema changes.
	public const TYPE_TO_RULES_MAP = [
		'role'     => 'roles',
		'postType' => 'postTypes',
	];

	// Keep this order this way, as it's used for determining the priority of rules in governance-utilities.
	public const RULE_TYPES         = [ 'postType', 'role', 'default' ];
	private const RULE_KEYS_GENERAL = [ 'allowedFeatures', 'allowedBlocks', 'blockSettings' ];

	/**
	 * Parses and validates governance rules.
	 *
	 * @param string $rules_content Contents of rules file.
	 *
	 * @return array|WP_Error
	 */
	public static function parse( string $rules_content ): array|WP_Error {
		if ( '' === trim( $rules_content ) ) {
			// An empty file is an explicitly supported form of no rules.
			return [];
		}

		$rules_parsed = self::parse_rules_from_json( $rules_content );
		if ( is_wp_error( $rules_parsed ) ) {
			return $rules_parsed;
		}

		if ( ! $rules_parsed instanceof stdClass ) {
			return new WP_Error( 'logic-invalid-root', __( 'Governance JSON should contain a root-level object.', 'vip-governance' ) );
		}

		if ( [] === get_object_vars( $rules_parsed ) ) {
			// An empty object is retained as a backwards-compatible form of no rules.
			return [];
		}

		$rule_validation_result = self::validate_rule_logic( $rules_parsed );
		if ( is_wp_error( $rule_validation_result ) ) {
			return $rule_validation_result;
		}

		return self::convert_objects_to_arrays( $rules_parsed->rules );
	}

	/**
	 * Validate JSON and decode it without discarding the distinction between JSON objects and arrays.
	 *
	 * @param string $rules_content Contents of rules file.
	 *
	 * @return mixed|WP_Error
	 */
	private static function parse_rules_from_json( string $rules_content ): mixed {
		try {
			return json_decode( $rules_content, false, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			// Native parsing failed. Use JsonParser to provide a more detailed error.
			$parser = new JsonParser();
			$result = $parser->lint( $rules_content, JsonParser::DETECT_KEY_CONFLICTS | JsonParser::VALIDATE_UTF8_ENCODING );

			if ( $result instanceof ParsingException ) {
				/* translators: %s: Technical data - JSON parsing error. */
				$error_message = sprintf( __( 'There was an error parsing JSON: %s', 'vip-governance' ), $result->getMessage() );
				return new WP_Error( 'parsing-error-from-json', $error_message, $result->getDetails() );
			}

			/* translators: %s: Technical data - JSON decoding error. */
			$error_message = sprintf( __( 'There was an error decoding JSON: %s', 'vip-governance' ), $exception->getMessage() );
			return new WP_Error( 'parsing-error-generic', $error_message );
		}
	}

	/**
	 * Evaluate parsed rules for schema and business-logic errors.
	 *
	 * @param stdClass $rules_parsed Parsed contents of a governance rules file.
	 *
	 * @return true|WP_Error
	 */
	private static function validate_rule_logic( stdClass $rules_parsed ): bool|WP_Error {
		if ( ! property_exists( $rules_parsed, 'version' ) || WPCOMVIP__GOVERNANCE__RULES_SCHEMA_VERSION !== $rules_parsed->version ) {
			/* translators: %s: Latest schema version, e.g. 1.0.0. */
			$error_message = sprintf( __( 'Governance JSON should have a root-level "version" key set to "%s".', 'vip-governance' ), WPCOMVIP__GOVERNANCE__RULES_SCHEMA_VERSION );
			return new WP_Error( 'logic-missing-version', $error_message );
		}

		if ( ! property_exists( $rules_parsed, 'rules' ) ) {
			return new WP_Error( 'logic-missing-rules', __( 'Governance JSON should have a root-level "rules" key.', 'vip-governance' ) );
		}

		if ( ! is_array( $rules_parsed->rules ) ) {
			return new WP_Error( 'logic-non-array-rules', __( 'Governance JSON "rules" key should be an array.', 'vip-governance' ) );
		}

		if ( property_exists( $rules_parsed, '$schema' ) && ! is_string( $rules_parsed->{'$schema'} ) ) {
			return new WP_Error( 'logic-invalid-schema-uri', __( 'Governance JSON "$schema" key should be a string.', 'vip-governance' ) );
		}

		$unknown_root_keys = array_diff( array_keys( get_object_vars( $rules_parsed ) ), self::ROOT_KEYS );
		if ( ! empty( $unknown_root_keys ) ) {
			/* translators: %s: Comma-separated list of unsupported root keys. */
			$error_message = sprintf( __( 'Governance JSON contains unsupported root-level keys: %s.', 'vip-governance' ), self::format_array_to_keys( $unknown_root_keys ) );
			return new WP_Error( 'logic-unsupported-root-keys', $error_message );
		}

		$default_rule_index = null;

		foreach ( $rules_parsed->rules as $rule_index => $rule ) {
			if ( ! $rule instanceof stdClass ) {
				/* translators: %s: Ordinal number of rule, e.g. 1st. */
				$error_message = sprintf( __( '%s rule should be an object.', 'vip-governance' ), self::format_number_with_ordinal( $rule_index + 1 ) );
				return new WP_Error( 'logic-rule-not-object', $error_message );
			}

			$rule_type    = $rule->type ?? null;
			$rule_ordinal = self::format_number_with_ordinal( $rule_index + 1 );

			if ( ! is_string( $rule_type ) || ! in_array( $rule_type, self::RULE_TYPES, true ) ) {
				$rule_types = self::format_array_to_keys( self::RULE_TYPES );
				/* translators: 1: Ordinal number of rule, e.g. 1st. 2: Comma-separated list of rule types. */
				$error_message = sprintf( __( '%1$s rule should have a "type" key set to one of these values: %2$s.', 'vip-governance' ), $rule_ordinal, $rule_types );
				return new WP_Error( 'logic-incorrect-rule-type', $error_message );
			}

			if ( 'default' === $rule_type ) {
				if ( null !== $default_rule_index ) {
					/* translators: %s: Ordinal number of rule, e.g. 1st. */
					$error_message = sprintf( __( 'Only one default rule is allowed, but the %s rule already contains a default rule.', 'vip-governance' ), self::format_number_with_ordinal( $default_rule_index + 1 ) );
					return new WP_Error( 'logic-rule-default-multiple', $error_message );
				}

				$verify_rule_result = self::verify_default_rule( $rule );
				$default_rule_index = $rule_index;
			} else {
				$verify_rule_result = self::verify_type_rule( $rule );
			}

			if ( is_wp_error( $verify_rule_result ) ) {
				/* translators: 1: Ordinal number of rule, e.g. 1st. 2: Error message for failed rule. */
				$error_message = sprintf( __( 'Error parsing %1$s rule: %2$s', 'vip-governance' ), $rule_ordinal, $verify_rule_result->get_error_message() );
				return new WP_Error( $verify_rule_result->get_error_code(), $error_message );
			}
		}

		return true;
	}

	/**
	 * Returns true if the given default rule is valid, or a WP_Error otherwise.
	 *
	 * @param stdClass $rule Parsed rule.
	 *
	 * @return true|WP_Error
	 */
	private static function verify_default_rule( stdClass $rule ): bool|WP_Error {
		foreach ( self::TYPE_TO_RULES_MAP as $type => $types ) {
			if ( property_exists( $rule, $types ) ) {
				/* translators: 1: Rule applicability key. 2: Rule type. */
				$error_message = sprintf( __( '"default"-type rule should not contain "%1$s" key. Default rules apply to all %2$s.', 'vip-governance' ), $types, $type );
				return new WP_Error( 'logic-rule-default-type', $error_message );
			}
		}

		$general_properties_result = self::verify_general_rule_properties( $rule );
		if ( is_wp_error( $general_properties_result ) ) {
			return $general_properties_result;
		}

		$allowed_keys_result = self::verify_allowed_rule_keys( $rule, [ 'type', ...self::RULE_KEYS_GENERAL ] );
		if ( is_wp_error( $allowed_keys_result ) ) {
			return $allowed_keys_result;
		}

		if ( 1 === count( get_object_vars( $rule ) ) ) {
			$rule_keys = self::format_array_to_keys( self::RULE_KEYS_GENERAL );
			/* translators: %s: Comma-separated list of valid rule keys. */
			$error_message = sprintf( __( 'This default rule is empty. Add additional keys (%s) to make it functional.', 'vip-governance' ), $rule_keys );
			return new WP_Error( 'logic-rule-empty', $error_message );
		}

		return true;
	}

	/**
	 * Returns true if the given role or post-type rule is valid, or a WP_Error otherwise.
	 *
	 * @param stdClass $rule Parsed rule.
	 *
	 * @return true|WP_Error
	 */
	private static function verify_type_rule( stdClass $rule ): bool|WP_Error {
		$type_to_be_checked = self::TYPE_TO_RULES_MAP[ $rule->type ];
		$type_values        = $rule->{$type_to_be_checked} ?? null;

		if ( ! is_array( $type_values ) || empty( $type_values ) ) {
			$rule_keys = self::format_array_to_keys( self::RULE_KEYS_GENERAL );
			/* translators: 1: Rule type. 2: Applicability key. 3: Applicability key. */
			$error_message = sprintf( __( '"%1$s"-type rules require a "%2$s" key containing an array of applicable "%3$s".', 'vip-governance' ), $rule->type, $type_to_be_checked, $type_to_be_checked );
			return new WP_Error( 'logic-rule-type-missing-valid-types', $error_message );
		}

		if ( ! self::contains_only_strings( $type_values ) ) {
			/* translators: %s: Rule applicability key, either roles or postTypes. */
			$error_message = sprintf( __( 'The "%s" key should contain only strings.', 'vip-governance' ), $type_to_be_checked );
			return new WP_Error( 'logic-rule-type-invalid-types', $error_message );
		}

		$general_properties_result = self::verify_general_rule_properties( $rule );
		if ( is_wp_error( $general_properties_result ) ) {
			return $general_properties_result;
		}

		$allowed_keys        = [ 'type', $type_to_be_checked, ...self::RULE_KEYS_GENERAL ];
		$allowed_keys_result = self::verify_allowed_rule_keys( $rule, $allowed_keys );
		if ( is_wp_error( $allowed_keys_result ) ) {
			return $allowed_keys_result;
		}

		if ( 2 === count( get_object_vars( $rule ) ) ) {
			$rule_keys = self::format_array_to_keys( self::RULE_KEYS_GENERAL );
			/* translators: %s: Comma-separated list of valid rule keys. */
			$error_message = sprintf( __( 'This rule doesn\'t apply any settings to the given type. Add additional keys (%s) to make it functional.', 'vip-governance' ), $rule_keys );
			return new WP_Error( 'logic-rule-empty', $error_message );
		}

		return true;
	}

	/**
	 * Validate the common properties supported by every rule type.
	 *
	 * @param stdClass $rule Parsed rule.
	 *
	 * @return true|WP_Error
	 */
	private static function verify_general_rule_properties( stdClass $rule ): bool|WP_Error {
		$rule_properties = get_object_vars( $rule );

		if ( array_key_exists( 'allowedBlocks', $rule_properties ) && ( ! is_array( $rule_properties['allowedBlocks'] ) || ! self::contains_only_strings( $rule_properties['allowedBlocks'] ) ) ) {
			return new WP_Error( 'logic-rule-invalid-allowed-blocks', __( 'Rule "allowedBlocks" should be an array of strings.', 'vip-governance' ) );
		}

		if ( array_key_exists( 'allowedFeatures', $rule_properties ) ) {
			$allowed_features = $rule_properties['allowedFeatures'];
			if ( ! is_array( $allowed_features ) || ! self::contains_only_strings( $allowed_features ) ) {
				return new WP_Error( 'logic-rule-invalid-allowed-features', __( 'Rule "allowedFeatures" should be an array of strings.', 'vip-governance' ) );
			}

			if ( count( $allowed_features ) !== count( array_unique( $allowed_features, SORT_STRING ) ) ) {
				return new WP_Error( 'logic-rule-duplicate-allowed-feature', __( 'Rule "allowedFeatures" should not contain duplicate values.', 'vip-governance' ) );
			}

			$invalid_features = array_diff( $allowed_features, self::ALLOWED_FEATURES );
			if ( ! empty( $invalid_features ) ) {
				return new WP_Error( 'logic-rule-unsupported-allowed-feature', __( 'Rule "allowedFeatures" contains an unsupported feature.', 'vip-governance' ) );
			}
		}

		if ( array_key_exists( 'blockSettings', $rule_properties ) ) {
			$block_settings = $rule_properties['blockSettings'];
			if ( ! $block_settings instanceof stdClass ) {
				return new WP_Error( 'logic-rule-invalid-block-settings', __( 'Rule "blockSettings" should be an object.', 'vip-governance' ) );
			}

			$block_settings_result = self::verify_rule_block_settings( $block_settings );
			if ( is_wp_error( $block_settings_result ) ) {
				return $block_settings_result;
			}
		}

		return true;
	}

	/**
	 * Validate the top-level blockSettings object.
	 *
	 * @param stdClass $block_settings Block settings object.
	 *
	 * @return true|WP_Error
	 */
	private static function verify_rule_block_settings( stdClass $block_settings ): bool|WP_Error {
		foreach ( get_object_vars( $block_settings ) as $block_name => $settings ) {
			if ( 1 !== preg_match( self::BLOCK_NAME_REGEX, $block_name ) ) {
				/* translators: %s: Invalid block name. */
				$error_message = sprintf( __( 'Rule "blockSettings" contains an invalid block name: "%s".', 'vip-governance' ), $block_name );
				return new WP_Error( 'logic-rule-invalid-block-name', $error_message );
			}

			if ( ! $settings instanceof stdClass ) {
				/* translators: %s: Block name. */
				$error_message = sprintf( __( 'Settings for block "%s" should be an object.', 'vip-governance' ), $block_name );
				return new WP_Error( 'logic-rule-invalid-block-settings', $error_message );
			}

			$nested_result = self::verify_nested_block_settings( $settings );
			if ( is_wp_error( $nested_result ) ) {
				return $nested_result;
			}
		}

		return true;
	}

	/**
	 * Validate schema-defined properties within nested block settings.
	 *
	 * Other properties are theme.json settings and are intentionally unrestricted by
	 * the governance schema.
	 *
	 * @param stdClass $settings Nested block settings object.
	 *
	 * @return true|WP_Error
	 */
	private static function verify_nested_block_settings( stdClass $settings ): bool|WP_Error {
		foreach ( get_object_vars( $settings ) as $property => $value ) {
			if ( 'allowedBlocks' === $property ) {
				if ( ! is_array( $value ) || ! self::contains_only_strings( $value ) ) {
					return new WP_Error( 'logic-rule-invalid-nested-allowed-blocks', __( 'Nested "allowedBlocks" should be an array of strings.', 'vip-governance' ) );
				}

				continue;
			}

			if ( 1 === preg_match( self::BLOCK_NAME_REGEX, $property ) ) {
				if ( ! $value instanceof stdClass ) {
					/* translators: %s: Block name. */
					$error_message = sprintf( __( 'Nested settings for block "%s" should be an object.', 'vip-governance' ), $property );
					return new WP_Error( 'logic-rule-invalid-block-settings', $error_message );
				}

				$nested_result = self::verify_nested_block_settings( $value );
				if ( is_wp_error( $nested_result ) ) {
					return $nested_result;
				}
			}
		}

		return true;
	}

	/**
	 * Verify that a rule contains no keys outside those permitted by its schema.
	 *
	 * @param stdClass $rule         Parsed rule.
	 * @param array    $allowed_keys Allowed keys.
	 *
	 * @return true|WP_Error
	 */
	private static function verify_allowed_rule_keys( stdClass $rule, array $allowed_keys ): bool|WP_Error {
		$unknown_keys = array_diff( array_keys( get_object_vars( $rule ) ), $allowed_keys );
		if ( empty( $unknown_keys ) ) {
			return true;
		}

		/* translators: %s: Comma-separated list of unsupported rule keys. */
		$error_message = sprintf( __( 'Rule contains unsupported keys: %s.', 'vip-governance' ), self::format_array_to_keys( $unknown_keys ) );
		return new WP_Error( 'logic-rule-unsupported-keys', $error_message );
	}

	/**
	 * Determine whether every array value is a string.
	 *
	 * @param array $values Values to inspect.
	 *
	 * @return bool
	 */
	private static function contains_only_strings( array $values ): bool {
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Recursively convert native decoded objects to the associative arrays expected by consumers.
	 *
	 * @param mixed $value Native decoded JSON value.
	 *
	 * @return mixed
	 */
	private static function convert_objects_to_arrays( mixed $value ): mixed {
		if ( $value instanceof stdClass ) {
			$value = get_object_vars( $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		return array_map( [ __CLASS__, 'convert_objects_to_arrays' ], $value );
	}

	/**
	 * Format the number with ordinal suffix, without the PHP number formatter.
	 *
	 * @param int $number Number to format.
	 *
	 * @return string
	 */
	private static function format_number_with_ordinal( int $number ): string {
		$ends = [ 'th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th' ];
		if ( ( $number % 100 ) >= 11 && ( $number % 100 ) <= 13 ) {
			return $number . 'th';
		}

		return $number . $ends[ $number % 10 ];
	}

	/**
	 * Format an array into a quoted, comma-separated list of keys for display.
	 *
	 * @param array $input_array Keys to format.
	 *
	 * @return string
	 */
	private static function format_array_to_keys( array $input_array ): string {
		return implode(
			', ',
			array_map(
				static function ( $item ): string {
					return sprintf( '"%s"', $item );
				},
				$input_array
			)
		);
	}
}
