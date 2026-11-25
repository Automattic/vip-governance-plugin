<?php
/**
 * Block Locking
 * 
 * @package vip-governance
 */

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

/**
 * Block Locking class that's used to disable block locking and code editing
 */
class BlockLocking {
	/**
	 * Apply feature restrictions to the block editor settings.
	 *
	 * @param array $settings Editor settings.
	 * @param array $allowed_features Features allowed by governance.
	 * @return array Editor settings.
	 */
	public static function apply_editor_settings( array $settings, array $allowed_features ): array {
		if ( ! in_array( 'lockBlocks', $allowed_features, true ) ) {
			$settings['canLockBlocks'] = false;
		}
		if ( ! in_array( 'codeEditor', $allowed_features, true ) ) {
			$settings['codeEditingEnabled'] = false;
		}
		return $settings;
	}
}
