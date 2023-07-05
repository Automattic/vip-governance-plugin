<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

class BlockLocking {
	public static function init( $allowed_features ) {
		$allow_move_blocks = false;
		$allow_code_editor = false;

		if ( in_array( 'moveBlocks', $allowed_features ) ) {
			$allow_move_blocks = true;
		}

		if ( in_array( 'codeEditor', $allowed_features ) ) {
			$allow_code_editor = true;
		}

		add_filter( 'block_editor_settings_all', function( $settings, $context ) use ( $allow_move_blocks, $allow_code_editor ) {
			if ( false === $allow_move_blocks ) {
				// Disable unlocking blocks
				$settings['canLockBlocks'] = false;
			}

			if ( false === $allow_code_editor ) {
				// Disable switching to code editing mode
				$settings['codeEditingEnabled'] = false;
			}

			return $settings;
		}, 10, 2 );
	}
}
