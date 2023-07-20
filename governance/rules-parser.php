<?php

namespace WPCOMVIP\Governance;

use NumberFormatter;
use WP_Error;

use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;

class RulesParser {
	private const RULE_TYPES = [ 'default', 'role' ];

	/**
	 * Parses and validates governance rules.
	 *
	 * @param string $rules_content Contents of rules file.
	 *
	 * @return array|WP_Error
	 */
	public static function parse( $rules_content ) {
		if ( empty( $rules_content ) ) {
			// Allow an empty file to be valid for no rules.
			return [];
		}

		// Parse JSON from rules file
		$rules_parsed = self::parse_rules_from_json( $rules_content );

		if ( is_wp_error( $rules_parsed ) ) {
			return $rules_parsed;
		} elseif ( empty( $rules_parsed ) ) {
			// Allow an empty object to be valid for no rules.
			return [];
		}

		// Validate governance rule logic
		$rule_validation_result = self::validate_rule_logic( $rules_parsed );

		if ( is_wp_error( $rule_validation_result ) ) {
			return $rule_validation_result;
		}

		// Validate against governance rules schema
		$schema_validation_result = self::validate_rules_schema( $rules_parsed );

		if ( is_wp_error( $schema_validation_result ) ) {
			return $schema_validation_result;
		}

		return $rules_parsed['rules'];
	}

	private static function parse_rules_from_json( $rules_content ) {
		$rules_parsed = json_decode( $rules_content, true );

		if ( null === $rules_parsed && JSON_ERROR_NONE !== json_last_error() ) {
			// PHP's JSON parsing failed. Use JsonParser to get a more detailed error.
			$parser = new JsonParser();
			$result = $parser->lint( $rules_content, JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC );

			if ( $result instanceof ParsingException ) {
				/* translators: %s: Technical data - JSON parsing error */
				$error_message = sprintf( __( 'There was an error parsing JSON: %s', 'vip-governance' ), $result->getMessage() );
				return new WP_Error( 'parsing-error-from-json', $error_message, $result->getDetails() );
			} else {
				// If the parser failed to return an error, return default PHP error message.

				/* translators: %s: Technical data - JSON parsing error */
				$error_message = sprintf( __( 'There was an error decoding JSON: %s', 'vip-governance' ), json_last_error_msg() );
				return new WP_Error( 'parsing-error-generic', $error_message );
			}
		}

		if ( empty( $rules_parsed ) ) {
			// If parsed rules contain an empty object, treat this as a valid form of no rules.
			return [];
		}

		return $rules_parsed;
	}


	/**
	 * @param string $rules Parsed contents of a governance rules file.
	 *
	 * @return true|WP_Error
	 */
	private static function validate_rule_logic( $rules_parsed ) {
		if ( ! isset( $rules_parsed['version'] ) ) {
			/* translators: %s: Latest schema version, e.g. 0.1.0 */
			$error_message = sprintf( __( 'Governance JSON should have a root-level "version" key set to "%s".', 'vip-governance' ), WPCOMVIP__GOVERNANCE__RULES_SCHEMA_VERSION );
			return new WP_Error( 'logic-missing-version', $error_message );
		} elseif ( ! isset( $rules_parsed['rules'] ) ) {
			// If parsed rules contain values but no 'rules' key, return an error.
			return new WP_Error( 'logic-missing-rules', __( 'Governance JSON should have a root-level "rules" key.', 'vip-governance' ) );
		} elseif ( ! is_array( $rules_parsed['rules'] ) ) {
			return new WP_Error( 'logic-non-array-rules', __( 'Governance JSON "rules" key should be an array.', 'vip-governance' ) );
		}

		$rules             = $rules_parsed['rules'];
		$ordinal_formatter = new NumberFormatter( get_locale(), NumberFormatter::ORDINAL );

		foreach ( $rules as $rule_index => $rule ) {
			$rule_type    = $rule['type'] ?? null;
			$rule_ordinal = $ordinal_formatter->format( $rule_index + 1 );

			if ( null === $rule_type || ! in_array( $rule_type, self::RULE_TYPES ) ) {
				$rule_types = implode(',', array_map( function( $rule_type ) {
					return sprintf( '"%s"', $rule_type );
				}, self::RULE_TYPES ) );
				/* translators: 1: Ordinal number of rule, e.g. 1st 2: Comma-separated list of rule types */
				$error_message = sprintf( __( '%1$s rule should have a "type" key set to one of these values: %2$s.', 'vip-governance' ), $rule_ordinal, $rule_types );
				return new WP_Error( 'logic-incorrect-rule-type', $error_message );
			}

			if ( 'default' === $rule_type ) {
				$verify_rule_result = self::verify_default_rule( $rule );
			} elseif ( 'role' === $rule_type ) {
				$verify_rule_result = self::verify_role_rule( $rule );
			}

			if ( is_wp_error( $verify_rule_result ) ) {
				// Add rule index to error message.
				/* translators: 1: Ordinal number of rule, e.g. 1st 2: Error message for failed rule */
				$error_message = sprintf( __( 'Error parsing %1$s rule: %2$s', 'vip-governance' ), $rule_ordinal, $verify_rule_result->get_error_message() );
				return new WP_Error( $verify_rule_result->get_error_code(), $error_message );
			}
		}

		return true;
	}

	private static function verify_default_rule( $rule ) {
		if ( isset( $rule['roles'] ) ) {
			return new WP_Error( 'logic-rule-default-roles', __( '"default"-type rule should not contain "roles" key. Default rules apply to all roles.', 'vip-governance' ), );
		}
	}

	private static function verify_role_rule( $rule ) {
		return true;
	}

	/**
	 * @param string $rules Parsed contents of a governance rules file.
	 *
	 * @return true|WP_Error
	 */
	private static function validate_rules_schema( $rules ) {
		$schema_file_path = WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR . '/governance-schema.json';

		if ( ! file_exists( $schema_file_path ) ) {
			return new WP_Error( 'schema-missing', __( 'Governance validation schema could not be loaded.', 'vip-governance' ) );
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- File location is hardcoded.
		$schema_contents = file_get_contents( $schema_file_path );

		$validator = new Validator();
		// Ensures that we don't overload the user with errors.
		$validator->setMaxErrors( 5 );
		$rules_as_stdclass = Helper::toJSON( $rules );
		$validation_result = $validator->validate( $rules_as_stdclass, $schema_contents );

		if ( $validation_result->isValid() ) {
			return true;
		} else {
			$error = $validation_result->error();

			$formatter       = new ErrorFormatter();
			$formatted_error = wp_json_encode( $formatter->format( $error, /* multiple */ false ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

			/* translators: %s: Technical data - JSON parsing error */
			$error_message = sprintf( __( 'Schema validation failed: %s', 'vip-governance' ), $formatted_error );
			return new WP_Error( 'schema-validation', $error_message );
		}

		return true;
	}
}
