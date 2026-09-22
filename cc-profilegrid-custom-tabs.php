<?php
/**
 * Plugin Name: ProfileGrid Custom Tabs
 * Description: Adds configurable custom profile tabs with shortcode support and visibility controls to ProfileGrid.
 * Version: 0.1.1
 * Requires Plugins: profilegrid-user-profiles-groups-and-communities
 * License: GPL-2.0-or-later
 * Text Domain: cc-profilegrid-custom-tabs
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CCPGT_VERSION', '0.1.1' );
define( 'CCPGT_FILE', __FILE__ );
define( 'CCPGT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCPGT_URL', plugin_dir_url( __FILE__ ) );

require_once CCPGT_DIR . 'includes/class-cc-profilegrid-custom-tabs.php';

add_action( 'plugins_loaded', static function () {
    CC_ProfileGrid_Custom_Tabs::instance();
} );
