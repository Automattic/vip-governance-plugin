<?php

namespace WPCOMVIP\Governance;

use WP_Error;

use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;

class RulesParser {
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
				return new WP_Error( 'parsing-error-from-parser', $error_message, $result->getDetails() );
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
		} elseif ( ! isset( $rules_parsed['rules'] ) ) {
			// If parsed rules contain values but no 'rules' key, return an error.
			return new WP_Error( 'parsing-no-rules', __( 'Governance JSON should have a root-level "rules" key.', 'vip-governance' ) );
		} elseif ( ! is_array( $rules_parsed['rules'] ) ) {
			return new WP_Error( 'parsing-non-array-rules', __( 'Governance JSON "rules" key should be an array.', 'vip-governance' ) );
		}

		return $rules_parsed;
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
			$formatted_error = wp_json_encode( $formatter->format( $error, /* multiple */ true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

			/* translators: %s: Technical data - JSON parsing error */
			$error_message = sprintf( __( 'Schema validation failed: %s', 'vip-governance' ), $formatted_error );
			return new WP_Error( 'schema-validation', $error_message );
		}

		return true;
	}
}
