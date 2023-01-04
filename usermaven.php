<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://usermaven.com/
 * @since             1.0.0
 * @package           Usermaven
 *
 * @wordpress-plugin
 * Plugin Name:       Usermaven
 * Plugin URI:        https://github.com/usermaven/wordpress
 * Description:       The Easiest Website and Product Analytics Platform
 * Version:           1.0.0
 * Author:            Usermaven
 * Author URI:        https://usermaven.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       usermaven
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'USERMAVEN_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-usermaven-activator.php
 */
function activate_usermaven() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-usermaven-activator.php';
	Usermaven_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-usermaven-deactivator.php
 */
function deactivate_usermaven() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-usermaven-deactivator.php';
	Usermaven_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_usermaven' );
register_deactivation_hook( __FILE__, 'deactivate_usermaven' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-usermaven.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_usermaven() {

	$plugin = new Usermaven();
	$plugin->run();

}
run_usermaven();
