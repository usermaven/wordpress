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

    $api_key = sanitize_text_field($_POST['api_key']);
    $custom_domain = '';
//  $server_token = '';
    $shared_link = '';

    if ( ! empty( $_POST['custom_domain'] ) ) {
        $custom_domain = sanitize_url($_POST['custom_domain']);
    }

       # Todo: Server side token code to be included in next release
//     if ( ! empty( $_POST['server_token'] ) ) {
//         $server_token = esc_attr($_POST['server_token']);
//     }

    if ( ! empty( $_POST['shared_link'] ) ) {
        $shared_link = sanitize_url($_POST['shared_link']);
    }


    $error = '';
    // Validate the API key
    if ( empty( $api_key ) ) {
         $error = "API key can't be empty";
    }

    $pattern = '/^(https?:\/\/)?[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)*\.[a-zA-Z]{2,63}(\/\S*)?$/i';

    // Validate the custom domain
    if ( ! empty( $custom_domain ) && ! preg_match( $pattern, $custom_domain ) ) {
            $error = "Invalid custom domain";
    }

    // Validate the shared link
    if ( ! empty( $shared_link ) && ! preg_match( $pattern, $shared_link ) ) {
           $error = "Invalid shared link";
    }

    if (!$error) {
      // Save the form data in the options table
      update_option( 'usermaven_autocapture', $autocapture );
      update_option( 'usermaven_cookie_less_tracking', $cookie_less_tracking );
      update_option( 'usermaven_embed_dashboard', $embed_dashboard );
      update_option( 'usermaven_shared_link', $shared_link);
      update_option( 'usermaven_api_key', $api_key );
      update_option( 'usermaven_custom_domain', $custom_domain );
//       update_option( 'usermaven_server_token', $server_token);

     // Display a success message
     echo '<div class="notice notice-success"><p>Inputs saved successfully</p></div>';
    } else {
      echo '<div class="notice notice-error"><p>' . $error . '</p></div>';
    }
  } else {
    // Display the form
    wp_enqueue_style( 'usermaven-activation-form-styles', plugin_dir_url( __FILE__ ) . 'css/usermaven-settings-form.css' );
    ?>
      <div class="header-section">
        <div class="header-left">
            <img src="<?php echo esc_url(plugin_dir_url( __FILE__ ) . '../admin/icons/um-favicon-without-white-bg.svg'); ?>" alt="Company Logo" class="company-logo">
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
        <input type="text" name="api_key" id="api_key" placeholder="Enter your API key here" value="<?php echo esc_attr(get_option('usermaven_api_key')); ?>" required>
        </div>
        <div class="input-block">
        <p class="input-text">
        By default the tracking host is "https://events.usermaven.com". You can use your own custom domain in the
        tracking script to bypass ad-blockers. For using your own custom domain, you will have to first add your
        custom domain <a href="https://app.usermaven.com/env/<?php echo esc_attr(get_option('usermaven_api_key')); ?>/settings/custom_domain" target="blank"> here.</a>
        </p>
        <label for="custom_domain">Custom Domain</label>
        <input type="text" name="custom_domain" id="custom_domain" placeholder="Enter your custom domain here" value="<?php echo esc_attr(get_option('usermaven_custom_domain')); ?>">
        </div>
        <!--
        <div class="input-block">
        <p class="input-text">
        Along with API key, server token is used to authenticate server side event tracking calls for your workspace.
        You can get your server token after making an account in <a href="https://app.usermaven.com/" target="blank"> Usermaven.</a>
        </p>
        <label for="server_token">Server Token (For server side tracking)</label>
        <input type="text" name="server_token" id="server_token" placeholder="Enter your server token here" value="<?php echo esc_attr(get_option('usermaven_server_token')); ?>">
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
        <a href="<?php echo esc_url(admin_url( 'admin.php?page=usermaven_dashboard' )); ?>">View Statistics</a>
        </p>
        <label for="embed_dashboard">
        Shared Link: <input class="shared-link" type="text" name="shared_link" id="shared_link" placeholder="Enter your shared link here" value="<?php echo esc_attr(get_option('usermaven_shared_link')); ?>">
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
