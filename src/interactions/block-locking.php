<?php

namespace WPCOMVIP\Governance\Interactions;

defined( 'ABSPATH' ) || die();

class BlockLocking {
	public static function init() {
		add_filter(
			'block_editor_settings_all',
			function( $settings, $context ) {
				// See https://gutenberg.10up.com/reference/Blocks/block-locking/#override-block-locking-ui-setting

				// Disable unlocking blocks
				$settings['canLockBlocks'] = false;

				// Disable switching to code editing mode
				$settings['codeEditingEnabled'] = false;
				return $settings;
			},
			10,
			2
		);
		add_filter('allowed_block_types_all', function() {
			return [
				'core/image',
				'core/gallery',
			];
		});
	}

	private static function get_rules() {
		$rules_json = file_get_contents( __DIR__ . '/block-locking.json' );
		$rules      = json_decode( $rules_json, /* associative */ true );

		return $rules['blockLockingRules'] ?? [];
	}
}

BlockLocking::init();
