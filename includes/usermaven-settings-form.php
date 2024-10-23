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
    $identify_verification = isset( $_POST['identify_verification'] ) ? true : false;
    $embed_dashboard = isset( $_POST['embed_dashboard'] ) ? true : false;

    $api_key = sanitize_text_field($_POST['api_key']);
    $server_token = isset($_POST['server_token']) ? sanitize_text_field($_POST['server_token']) : '';
    $custom_domain = '';
    $shared_link = '';

    if ( ! empty( $_POST['custom_domain'] ) ) {
        $custom_domain = sanitize_url($_POST['custom_domain']);
    }

    if ( ! empty( $_POST['shared_link'] ) ) {
        $shared_link = sanitize_url($_POST['shared_link']);
    }


    $error = '';
    // Validate the API key
    if ( empty( $api_key ) ) {
      $error = "API key can't be empty";
    }

    // check if the url contains http or https, if not add https.
    if (!empty($custom_domain)) {
        $custom_domain = preg_replace("/^http:/i", "https:", $custom_domain);
        if (!preg_match('/^https?:\/\//', $custom_domain)) {
            $custom_domain = 'https://' . $custom_domain;
        }
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
      update_option( 'usermaven_identify_verification', $identify_verification );
      update_option( 'usermaven_embed_dashboard', $embed_dashboard );
      update_option( 'usermaven_shared_link', $shared_link);
      update_option( 'usermaven_api_key', $api_key );
      update_option( 'usermaven_custom_domain', $custom_domain );

      // Only update server token if it's provided
      if (!empty($server_token)) {
        update_option( 'usermaven_server_token', $server_token );
      }

      // Roles to be tracked
        update_option( 'usermaven_role_administrator', isset( $_POST['role_administrator'] ) ? true : false );
        update_option( 'usermaven_role_author', isset( $_POST['role_author'] ) ? true : false );
        update_option( 'usermaven_role_contributor', isset( $_POST['role_contributor'] ) ? true : false );
        update_option( 'usermaven_role_editor', isset( $_POST['role_editor'] ) ? true : false );
        update_option( 'usermaven_role_subscriber', isset( $_POST['role_subscriber'] ) ? true : false );
        update_option( 'usermaven_role_translator', isset( $_POST['role_translator'] ) ? true : false );

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
        <h3>Authentication</h3>

        <p class="input-text">
        API key is used to authenticate event tracking calls for your workspace. You can get your API key from your '<a href="https://app.usermaven.com/" target="blank">Usermaven account</a> > Workspace settings > General' page.
        </p>
        <label for="api_key">API Key</label>
        <input type="text" name="api_key" id="api_key" placeholder="Enter your API key here" value="<?php echo esc_attr(get_option('usermaven_api_key')); ?>" required>
        
        <p class="input-text">
        Along with API key, server token is used to authenticate server side event tracking calls for your workspace.
        You can get your server token after making an account in <a href="https://app.usermaven.com/" target="blank"> Usermaven.</a>
        </p>
        <label for="server_token">Server Token (For server side tracking)</label>
        <input type="text" name="server_token" id="server_token" placeholder="Enter your server token here" value="<?php echo esc_attr(get_option('usermaven_server_token')); ?>">
        </div>
        <div class="input-block">
        <h3>Bypass adblockers with pixel white-labeling</h2>

        <p class="input-text">
        By default the tracking host is "https://events.usermaven.com". You can use your own custom domain in the
        tracking script to bypass ad-blockers. You can read more about it <a href="https://usermaven.com/docs/getting-started/pixel-whitelabeling#1-add-your-custom-domain" target="blank">here</a>.
        </p>
        <label for="custom_domain">Custom Domain</label>
        <input type="text" name="custom_domain" id="custom_domain" placeholder="Enter your custom domain here" value="<?php echo esc_attr(get_option('usermaven_custom_domain')); ?>">
        </div>
        
        <div class="input-block">
        <h3>Tracking options</h2>

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
        <h3>Tracking identified users</h3>
        <p class="input-text">
        By default, we don't send attributes of logged-in users to Usermaven. If you have a membership site and you want to track behavior of your signed-up users, please enable this option. You'll be able to view the user activity in 'Contacts hub > Users' page in Usermaven.
        </p>
        <label for="identify_verification">
        <input type="checkbox" name="identify_verification" id="identify_verification" value="true" <?php checked( get_option('usermaven_identify_verification'), true ); ?>>
        Identify logged-in users in Useramven
        </label>
        </div>
        <div class="input-block">
        <h3>Enable tracking for user roles</h3>

        <p class="input-text">
        By default, visits from logged in users are not tracked. If you want to track visits from certain user roles, you can enable this setting.
        </p>
        <label for="role_administrator">
            <input type="checkbox" name="role_administrator" id="role_administrator" value="false" <?php checked( get_option('usermaven_role_administrator'), true ); ?>>
            Administrator
        </label>

        <label for="role_author">
            <input type="checkbox" name="role_author" id="role_author" value="false" <?php checked( get_option('usermaven_role_author'), true ); ?>>
            Author
        </label>

        <label for="role_contributor">
            <input type="checkbox" name="role_contributor" id="role_contributor" value="false" <?php checked( get_option('usermaven_role_contributor'), true ); ?>>
            Contributor
        </label>

        <label for="role_editor">
            <input type="checkbox" name="role_editor" id="role_editor" value="false" <?php checked( get_option('usermaven_role_editor'), true ); ?>>
            Editor
        </label>

        <label for="role_subscriber">
            <input type="checkbox" name="role_subscriber" id="role_subscriber" value="false" <?php checked( get_option('usermaven_role_subscriber'), true ); ?>>
            Subscriber
        </label>

        <label for="role_translator">
            <input type="checkbox" name="role_translator" id="role_translator" value="false" <?php checked( get_option('usermaven_role_translator'), true ); ?>>
            Translator
        </label>
        </div>
        <div class="input-block">
        <h3>Add web analytics to WP dashboard</h3>

        <p class="input-text">
        Create a shared link from your workspace. Enable this setting, paste your shared link here and save the settings
        to view your stats in your Wordpress dashboard. To view the stats, click on
        <a href="<?php echo esc_url(admin_url( 'admin.php?page=usermaven_dashboard' )); ?>">View Statistics</a>
        </p>
        <label for="embed_dashboard">
        <input type="checkbox" name="embed_dashboard" id="embed_dashboard" value="true" <?php checked( get_option('usermaven_embed_dashboard'), true ); ?>>
        Enable your stats in your WordPress dashboard
        </label>

        <br>

        <label for="shared_link">
        Shared Link: <input class="shared-link" type="text" name="shared_link" id="shared_link" placeholder="Enter your shared link here" value="<?php echo esc_attr(get_option('usermaven_shared_link')); ?>">
        </label>

        <p class="input-text" style="margin-bottom: 0px;">
        You can read more about shared link and how to create it <a href="https://usermaven.com/docs/website-analytics-shareable-dashboard" target="blank">here</a>.
        </p>
        </div>
        <div class="form-button">
        <input type="submit" name="submit" value="Save Changes">
        </div>
      </form>
      </div>
    <?php
  }
}
