<?php

/**
 * Input form to get required values. This form will be shown at the time of activation.
 */
function usermaven_activation_form() {
  // Check if the form has been submitted
  if ( isset( $_POST['submit'] ) ) {
    // Get the form data
    $autocapture = $_POST['autocapture'];
    $cookie_less_tracking = $_POST['cookie_less_tracking'];
    $embed_dashboard = $_POST['embed_dashboard'];
    $shared_link = esc_attr($_POST['shared_link']);
    $api_key = esc_attr($_POST['api_key']);
    $tracking_host = 'https://events.usermaven.com';
    $server_token = '';

    if ( ! empty( $_POST['tracking_host'] ) ) {
        $tracking_host = esc_attr($_POST['tracking_host']);
    }

    if ( ! empty( $_POST['server_token'] ) ) {
        $server_token = esc_attr($_POST['server_token']);
    }

    // Save the form data in the options table
    update_option( 'usermaven_autocapture', $autocapture );
    update_option( 'usermaven_cookie_less_tracking', $cookie_less_tracking );
    update_option( 'usermaven_embed_dashboard', $embed_dashboard );
    update_option( 'usermaven_shared_link', $shared_link);
    update_option( 'usermaven_api_key', $api_key );
    update_option( 'usermaven_tracking_host', $tracking_host );
    update_option( 'usermaven_server_token', $server_token);

    // Display a success message
    echo '<div class="notice notice-success"><p>Inputs saved successfully</p></div>';
  } else {
    // Display the form
    wp_enqueue_style( 'usermaven-activation-form-styles', plugin_dir_url( __FILE__ ) . 'css/usermaven-settings-form.css' );
    ?>
      <div class="header-section">
        <img src="<?php echo plugin_dir_url( __FILE__ ) . '../admin/icons/um-favicon-without-white-bg.svg'; ?>" alt="Company Logo" class="company-logo">
        <h1 class="header-text">Usermaven Settings</h1>
      </div>
      <div class="form-input">
      <form method="post">
        <h2 class="form-heading">Usermaven Credentials</h2>
        <div class="input-block">
        <label for="autocapture">
        <input type="checkbox" name="autocapture" id="autocapture" value="true" <?php checked( get_option('usermaven_autocapture'), 'true' ); ?>>
        Automatically capture frontend events i.e button clicks, form submission etc.</label>
        <br>
        <label for="cookie_less_tracking">
        <input type="checkbox" name="cookie_less_tracking" id="cookie_less_tracking" value="true" <?php checked( get_option('usermaven_cookie_less_tracking'), 'true' ); ?>>
        Cookie-less tracking</label>
        <br>
        </div>
        <div class="input-block">
        <label for="api_key">API Key</label>
        <input type="text" name="api_key" id="api_key" placeholder="Enter your API key here" value="<?php echo wp_unslash(get_option('usermaven_api_key')); ?>" required>
        <br>
        </div>
        <div class="input-block">
        <label for="tracking_host">Tracking Host</label>
        <input type="text" name="tracking_host" id="tracking_host" placeholder="Enter your tracking host here" value="<?php echo wp_unslash(get_option('usermaven_tracking_host')); ?>">
        <br>
        </div>
        <div class="input-block">
        <label for="server_token">Server Token (For server side tracking)</label>
        <input type="text" name="server_token" id="server_token" placeholder="Enter your server token here" value="<?php echo wp_unslash(get_option('usermaven_server_token')); ?>">
        <br>
        </div>
         <div class="input-block">
        <label for="embed_dashboard">
        <input type="checkbox" name="embed_dashboard" id="embed_dashboard" value="true" <?php checked( get_option('usermaven_embed_dashboard'), 'true' ); ?>>
        View your stats in your WordPress dashboard
        <br>
        Shared Link: <input type="text" name="shared_link" id="shared_link" placeholder="Enter your shared link here" value="<?php echo wp_unslash(get_option('usermaven_shared_link')); ?>">
        <br>
        <a href="<?php echo admin_url( 'admin.php?page=embedded-stats' ); ?>">View Statistics</a>
        </div>
        <div class="input-block">
        <input type="submit" name="submit" value="Save">
        </div>
      </form>
      </div>
    <?php
  }
}

/**
 * Add Usermaven menu page for the form inputs
 */
function add_menu() {
    add_menu_page('Usermaven', 'Usermaven', 'manage_options', 'usermaven_options', 'usermaven_activation_form');
}
add_action('admin_menu', 'add_menu');
