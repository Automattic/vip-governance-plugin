<?php

/**
 * Plugin Name: VIP Governance Plugin
 * Plugin URI: https://wpvip.com
 * Description: Plugin bundle to manage block governance options in Gutenberg.
 * Author: WordPress VIP
 * Text Domain: vip-governance
 * Version: 0.1.0
 * Requires at least: 5.6.0
 * Tested up to: 5.7.1
 * Requires PHP: 7.2
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package vip-governance
 */

namespace WPCOMVIP\Governance;

defined( 'ABSPATH' ) || die();

define( 'WPCOMVIP__GOVERNANCE__PLUGIN_VERSION', '0.1.0' );

if ( ! defined( 'WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_FILE' ) ) {
	define( 'WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR' ) ) {
	define( 'WPCOMVIP_GOVERNANCE_ROOT_PLUGIN_DIR', __DIR__ );
}

define( 'WPCOMVIP_GOVERNANCE_SOURCE_FILENAME', 'block-governance.json' );

require_once __DIR__ . '/src/init-governance.php';

require_once __DIR__ . '/src/interactions/nested-governance-processing.php';
