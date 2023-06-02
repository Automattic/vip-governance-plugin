<?php

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

class BlockLocking {
	public static $is_lockdown_mode = false;
	public static $allowed_blocks   = [
		'core/image',
		'core/gallery',
	];

	public static function init() {
		if ( self::$is_lockdown_mode ) {
			add_filter( 'block_editor_settings_all', [ __CLASS__, 'lockdown_block_editor_settings' ], 10, 2 );
			add_filter( 'allowed_block_types_all', [ __CLASS__, 'lockdown_allowed_block_types' ] );
		}
	}

	public static function lockdown_block_editor_settings( $settings, $context ) {
		// Disable unlocking blocks
		$settings['canLockBlocks'] = false;

		// Disable switching to code editing mode
		$settings['codeEditingEnabled'] = false;

		return $settings;
	}

	public static function lockdown_allowed_block_types() {
		return self::$allowed_blocks;
	}
}

BlockLocking::init();
