<?php

namespace WPCOMVIP\Governance;

use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use \WP_Error;

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

		$rules = self::get_rules_from_json( $rules_content );

		if ( is_wp_error( $rules ) ) {
			return $rules;
		}

		return $rules;
	}

	private static function get_rules_from_json( $rules_content ) {
		$rules_parsed = json_decode( $rules_content, true );

		if ( null === $rules_parsed && JSON_ERROR_NONE !== json_last_error() ) {
			// PHP's JSON parsing failed. Use JsonParser to get a more detailed error.
			$parser = new JsonParser();
			$result = $parser->lint( $rules_content, JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC );

			if ( $result instanceof ParsingException ) {
				/* translators: %s: Technical data - JSON parsing error */
				$error_message = sprintf( __( 'There was an error parsing JSON: %s', 'vip-governance' ), $result->getMessage() );
				return new WP_Error( 'parsing-error', $error_message, $result->getDetails() );
			} else {
				// If the parser failed to return an error, return default PHP error message.

				/* translators: %s: Technical data - JSON parsing error */
				$error_message = sprintf( __( 'There was an error decoding JSON: %s', 'vip-governance' ), json_last_error_msg() );
				return new WP_Error( 'parsing-error', $error_message );
			}
		}

		if ( empty( $rules_parsed ) ) {
			// If parsed rules contain an empty object, treat this as a valid form of no rules.
			return [];
		} elseif ( ! isset( $rules_parsed['rules'] ) ) {
			// If parsed rules contain values but no 'rules' key, return an error.
			return new WP_Error( 'parsing-error', __( 'Governance JSON should have a root-level "rules" key.', 'vip-governance' ) );
		} elseif ( ! is_array( $rules_parsed['rules'] ) ) {
			return new WP_Error( 'parsing-error', __( 'Governance JSON "rules" key should be an array.', 'vip-governance' ) );
		}

		return $rules_parsed['rules'];
	}
}
