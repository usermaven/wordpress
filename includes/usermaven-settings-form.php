<?php

/**
 * Input form to get required values. This form will be shown at the time of activation.
 */
function usermaven_activation_form() {
  // Check if the form has been submitted
  if ( isset( $_POST['submit'] ) ) {
    // Get the form data
    $autocapture = isset( $_POST['autocapture'] ) ? true : false;
    $cookie_less_tracking = isset( $_POST['cookie_less_tracking'] ) ? true : false;
    $embed_dashboard = isset( $_POST['embed_dashboard'] ) ? true : false;

    $api_key = esc_attr($_POST['api_key']);
    $tracking_host = 'https://events.usermaven.com';
//  $server_token = '';
    $shared_link = '';

    if ( ! empty( $_POST['tracking_host'] ) ) {
        $tracking_host = esc_attr($_POST['tracking_host']);
    }

       # Todo: Server side token code to be included in next release
//     if ( ! empty( $_POST['server_token'] ) ) {
//         $server_token = esc_attr($_POST['server_token']);
//     }

    if ( ! empty( $_POST['shared_link'] ) ) {
        $shared_link = esc_attr($_POST['shared_link']);
    }

    // Save the form data in the options table
    update_option( 'usermaven_autocapture', $autocapture );
    update_option( 'usermaven_cookie_less_tracking', $cookie_less_tracking );
    update_option( 'usermaven_embed_dashboard', $embed_dashboard );
    update_option( 'usermaven_shared_link', $shared_link);
    update_option( 'usermaven_api_key', $api_key );
    update_option( 'usermaven_tracking_host', $tracking_host );
//     update_option( 'usermaven_server_token', $server_token);

    // Display a success message
    echo '<div class="notice notice-success"><p>Inputs saved successfully</p></div>';
  } else {
    // Display the form
    wp_enqueue_style( 'usermaven-activation-form-styles', plugin_dir_url( __FILE__ ) . 'css/usermaven-settings-form.css' );
    ?>
      <div class="header-section">
        <div class="header-left">
            <img src="<?php echo plugin_dir_url( __FILE__ ) . '../admin/icons/um-favicon-without-white-bg.svg'; ?>" alt="Company Logo" class="company-logo">
            <h1 class="header-text">Usermaven Settings</h1>
        </div>
        <div class="header-right">
            <a class="header-button" href="https://github.com/usermaven/wordpress/issues/new" target="blank"> REPORT A BUG </a>
        </div>
      </div>
      <div class="form-input">
      <form class="form" method="post">
        <h2 class="form-heading">Usermaven Tracking Setup</h2>
        <div class="input-block">
        <p class="input-text">
        API key is used to authenticate event tracking calls for your workspace. You can get your API key after making
        an account in <a href="https://app.usermaven.com/" target="blank"> Usermaven.</a>
        </p>
        <label for="api_key">API Key</label>
        <input type="text" name="api_key" id="api_key" placeholder="Enter your API key here" value="<?php echo wp_unslash(get_option('usermaven_api_key')); ?>" required>
        </div>
        <div class="input-block">
        <p class="input-text">
        By default the tracking host is "https://events.usermaven.com". You can use your own custom domain in the
        tracking script to bypass ad-blockers.
        </p>
        <label for="tracking_host">Tracking Host</label>
        <input type="text" name="tracking_host" id="tracking_host" placeholder="Enter your tracking host here" value="<?php echo wp_unslash(get_option('usermaven_tracking_host')); ?>">
        </div>
        <!--
        <div class="input-block">
        <p class="input-text">
        Along with API key, server token is used to authenticate server side event tracking calls for your workspace.
        You can get your server token after making an account in <a href="https://app.usermaven.com/" target="blank"> Usermaven.</a>
        </p>
        <label for="server_token">Server Token (For server side tracking)</label>
        <input type="text" name="server_token" id="server_token" placeholder="Enter your server token here" value="<?php echo wp_unslash(get_option('usermaven_server_token')); ?>">
        </div>
        -->
        <div class="input-block">
        <p class="input-text">
        To give you more control over your privacy, we have given you an option to enable/disable autocapture as well as
        an option to include cookie-less tracking.
        </p>
        <label for="cookie_less_tracking">
        <input type="checkbox" name="cookie_less_tracking" id="cookie_less_tracking" value="true" <?php checked( get_option('usermaven_cookie_less_tracking'), true ); ?>>
        Cookie-less tracking</label>
        <br>
        <label for="autocapture">
        <input type="checkbox" name="autocapture" id="autocapture" value="true" <?php checked( get_option('usermaven_autocapture'), true ); ?>>
        Automatically capture frontend events i.e button clicks, form submission etc.</label>
        </div>
        <div class="input-block">
        <p class="input-text">
        Create a shared link from your workspace. Enable this setting, paste your shared link here and save the settings
        to view your stats in your Wordpress dashboard. To view the stats, click on
        <a href="<?php echo admin_url( 'admin.php?page=embedded-stats' ); ?>">View Statistics</a>
        </p>
        <label for="embed_dashboard">
        Shared Link: <input class="shared-link" type="text" name="shared_link" id="shared_link" placeholder="Enter your shared link here" value="<?php echo wp_unslash(get_option('usermaven_shared_link')); ?>">
        <br>
        <input type="checkbox" name="embed_dashboard" id="embed_dashboard" value="true" <?php checked( get_option('usermaven_embed_dashboard'), true ); ?>>
        View your stats in your WordPress dashboard
        </div>
        <div class="form-button">
        <input type="submit" name="submit" value="Save Changes">
        </div>
      </form>
      </div>
    <?php
  }
}
