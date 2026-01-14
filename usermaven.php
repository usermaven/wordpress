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

 * @since             1.0.5
 * @package           Usermaven
 *
 * @wordpress-plugin
 * Plugin Name:       Usermaven
 * Plugin URI:        https://github.com/usermaven/wordpress
 * Description:       The Easiest Website and Product Analytics Platform

 * Version:           1.2.7
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
 * Start at version 1.0.4 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'USERMAVEN_VERSION', '1.2.7' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-usermaven-activator.php
 */
function activate_usermaven() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-usermaven-activator.php';
	Usermaven_Activator::activate();
}

/**
 * Function to add usermaven settings link to the plugin list page
 */
function add_usermaven_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=usermaven_options' ) . '">' . __( 'Settings' ) . '</a>';
    array_push( $links, $settings_link );
    return $links;
}
$filter_name = "plugin_action_links_" . plugin_basename(__FILE__);
add_filter( $filter_name, 'add_usermaven_settings_link' );

/**
 * Add Usermaven menu page with dashboard and settings as submenu pages
 */
function add_usermaven_settings_menu() {
    add_menu_page('Usermaven', 'Usermaven', 'manage_options', 'usermaven_options', 'usermaven_activation_form',  plugin_dir_url(__FILE__) . 'admin/icons/um-favicon-white.svg', 100);
    add_submenu_page( 'usermaven_options', 'Dashboard', 'Dashboard', 'manage_options', 'usermaven_dashboard', 'usermaven_embedded_stats_page' );
    add_submenu_page( 'usermaven_options', 'Settings', 'Settings', 'manage_options', 'usermaven_options', 'usermaven_activation_form' );
    remove_submenu_page( 'usermaven_options', 'usermaven_options' );
}
add_action('admin_menu', 'add_usermaven_settings_menu');

/**
 * Function to render embedded stats in wordpress dashboard
 */
function usermaven_embedded_stats_page() {
    $shared_link = get_option( 'usermaven_shared_link' );
    $embed_dashboard = get_option('usermaven_embed_dashboard');

    if( !$embed_dashboard || empty( $shared_link ) ) {
        ?>
        <div class="notice notice-warning">
            <p>The shared link or view stats option is not set. Please click <a href="<?php echo esc_url(admin_url( 'admin.php?page=usermaven_options' )); ?>">here</a> to set them up.</p>
        </div>
        <?php
    } else {
    ?>
        <div class="wrap">
            <iframe src="<?php echo esc_url( $shared_link ); ?>" scrolling="no" frameborder="0" style="width: 100%; height: 2970px;"></iframe>
        </div>
    <?php
    }
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
 * Form input file of usermaven settings page
 */
require_once('includes/usermaven-settings-form.php');

/**
 * The WooCommerce integration class for Usermaven
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-usermaven-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-usermaven-woocommerce.php';


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.4
 */
function run_usermaven() {
    // Tracking host
    $tracking_host = "https://events.usermaven.com";
    
    // Initialize the plugin with the tracking host
    $plugin = new Usermaven($tracking_host);
    $plugin->run();

    // Check if WooCommerce is active and tracking is enabled
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) 
        && get_option('usermaven_track_woocommerce', false) ) {
        new Usermaven_WooCommerce($tracking_host);
    }
}
run_usermaven();
