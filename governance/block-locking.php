<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

class BlockLocking {
	private static $should_block_locking_be_disabled = true;
	private static $should_code_editor_be_disabled   = true;

	public static function init( $allowed_features ) {
		if ( ! in_array( 'moveBlocks', $allowed_features ) ) {
			self::$should_block_locking_be_disabled = false;
		}

		if ( ! in_array( 'codeEditor', $allowed_features ) ) {
			self::$should_code_editor_be_disabled = false;
		}

		add_filter( 'block_editor_settings_all', [ __CLASS__, 'lockdown_block_editor_settings' ], 10, 2 );
	}

	public static function lockdown_block_editor_settings( $settings, $context ) {
		if ( self::$should_block_locking_be_disabled ) {
			// Disable unlocking blocks
			$settings['canLockBlocks'] = false;
		}

		if ( self::$should_code_editor_be_disabled ) {
			// Disable switching to code editing mode
			$settings['codeEditingEnabled'] = false;
		}

		return $settings;
	}

}
