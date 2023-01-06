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
 * Input form to get required values. This form will be shown at the time of activation.
 */
function usermaven_activation_form() {
  // Check if the form has been submitted
  if ( isset( $_POST['submit'] ) ) {
    // Get the form data
    $autocapture = $_POST['autocapture'];
    $data_key = $_POST['data_key'];
    $tracking_host = 'https://events.usermaven.com';
    $tracking_path = 'https://t.usermaven.com/lib.js';
    $server_token = '';

    if ( ! empty( $_POST['tracking_host'] ) ) {
        $tracking_host = $_POST['tracking_host'];
    }

    if ( ! empty( $_POST['tracking_path'] ) ) {
        $tracking_path = $_POST['tracking_path'];
    }

    if ( ! empty( $_POST['server_token'] ) ) {
        $server_token = $_POST['server_token'];
    }

    // Save the form data in the options table
    add_option( 'usermaven_autocapture', $autocapture );
    add_option( 'usermaven_data_key', $data_key );
    add_option( 'usermaven_data_tracking_host', $tracking_host );
    add_option( 'usermaven_tracking_path', $tracking_path );
    add_option( 'usermaven_server_token', $server_token);

    // Display a success message
    echo '<div class="notice notice-success"><p>Inputs saved successfully</p></div>';
  } else {
    // Display the form
    ?>
      <style>
        form label {
            display: block;
            margin-top: 5px;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: bold;
            }
        form input[type="text"], form input[type="email"], form input[type="password"] {
            width: 250px;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            }
        form input[type="submit"] {
            width: 100px;
            background-color: #4CAF50;
            color: white;
            padding: 14px 20px;
            margin: 8px 0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            }
      </style>

      <form method="post">
        <label for="data_key">Data Key</label>
        <input type="text" name="data_key" id="data_key" required>
        <br>
        <label for="tracking_host">Data Tracking Host</label>
        <input type="text" name="tracking_host" id="tracking_host">
        <br>
        <label for="tracking_path">Tracking Path</label>
        <input type="text" name="tracking_path" id="tracking_path">
        <br>
        <label for="server_token">Server Token (For server side tracking)</label>
        <input type="text" name="server_token" id="server_token">
        <br>
        <label for="autocapture">
        <input type="checkbox" name="autocapture" id="autocapture" value="true">
        Automatically capture frontend events i.e button clicks, form submission etc.</label>
        <br>
        <input type="submit" name="submit" value="Save">
      </form>
    <?php
  }
}


/**
 * To display the form for input values at the time of activating the plugin
 */
function usermaven_display_activation_form() {
  // Check if the plugin is being activated
  if ( isset( $_GET['activate'] ) ) {
    usermaven_activation_form();
  }
}
add_action( 'admin_notices', 'usermaven_display_activation_form' );


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
