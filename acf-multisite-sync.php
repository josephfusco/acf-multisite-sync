<?php
/**
 * Plugin Name: ACF Multisite Sync
 * Description: Synchronizes ACF settings from the primary site to all subsites in a multisite network.
 * Version:     1.0.0
 * Author:      Joseph Fusco
 * License:     GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: acf-multisite-sync
 *
 * @package AcfMultisiteSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'ACF_MS_SYNC_VERSION', '1.0.0' );
define( 'ACF_MS_SYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACF_MS_SYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes.
require_once ACF_MS_SYNC_PLUGIN_DIR . 'includes/class-acf-sync.php';

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'AcfMultisiteSync\ACF_Sync', 'get_instance' ) );
