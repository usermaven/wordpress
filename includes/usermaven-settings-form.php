<?php

/**
 * Input form to get required values. This form will be shown at the time of activation.
 */
function usermaven_activation_form() {
  // Check if the form has been submitted
  if ( isset( $_POST['submit'] ) ) {
    // Verify the nonce for security
    if ( ! isset( $_POST['usermaven_settings_nonce'] ) || ! wp_verify_nonce( $_POST['usermaven_settings_nonce'], 'usermaven_settings_action' ) ) {
      // Nonce verification failed
      $error = "Security verification failed. Please try again.";
      $success_message = '<div class="notice-toast notice-error"><p>' . $error . '</p></div>';
    } else {
      // Get the form data
      $autocapture = isset( $_POST['autocapture'] ) ? true : false;
      $form_tracking = isset( $_POST['form_tracking'] ) ? true : false;
      $cookie_less_tracking = isset( $_POST['cookie_less_tracking'] ) ? true : false;
      $identify_verification = isset( $_POST['identify_verification'] ) ? true : false;
      $embed_dashboard = isset( $_POST['embed_dashboard'] ) ? true : false;
      $track_woocommerce = isset( $_POST['track_woocommerce'] ) ? true : false;

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
        update_option( 'usermaven_form_tracking', $form_tracking );
        update_option( 'usermaven_cookie_less_tracking', $cookie_less_tracking );
        update_option( 'usermaven_identify_verification', $identify_verification );
        update_option( 'usermaven_embed_dashboard', $embed_dashboard );
        update_option( 'usermaven_shared_link', $shared_link);
        update_option( 'usermaven_api_key', $api_key );
        update_option( 'usermaven_custom_domain', $custom_domain );

        // Always update server token, even if empty
        update_option( 'usermaven_server_token', $server_token );

        // If server token is empty, disable WooCommerce tracking
        if (empty($server_token)) {
          update_option( 'usermaven_track_woocommerce', false );
        } else {
          update_option( 'usermaven_track_woocommerce', $track_woocommerce );
        }

        // Roles to be tracked
          update_option( 'usermaven_role_administrator', isset( $_POST['role_administrator'] ) ? true : false );
          update_option( 'usermaven_role_author', isset( $_POST['role_author'] ) ? true : false );
          update_option( 'usermaven_role_contributor', isset( $_POST['role_contributor'] ) ? true : false );
          update_option( 'usermaven_role_editor', isset( $_POST['role_editor'] ) ? true : false );
          update_option( 'usermaven_role_subscriber', isset( $_POST['role_subscriber'] ) ? true : false );
          update_option( 'usermaven_role_translator', isset( $_POST['role_translator'] ) ? true : false );

       // Display a success message
       $success_message = '<div class="notice-toast notice-success"><p>Settings saved successfully</p></div>';
      } else {
        $success_message = '<div class="notice-toast notice-error"><p>' . $error . '</p></div>';
      }
    }
  }

  // Display the form
  wp_enqueue_style( 'usermaven-activation-form-styles', plugin_dir_url( __FILE__ ) . 'css/usermaven-settings-form.css' );
  ?>
    <?php if (isset($success_message)) : ?>
      <?php echo $success_message; ?>
    <?php endif; ?>
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
        <?php wp_nonce_field('usermaven_settings_action', 'usermaven_settings_nonce'); ?>
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
        <br>
        <label for="form_tracking">
        <input type="checkbox" name="form_tracking" id="form_tracking" value="true" <?php checked( get_option('usermaven_form_tracking'), true ); ?>>
        Track form submissions</label>
        <br>
        <label for="track_woocommerce">
        <input type="checkbox" name="track_woocommerce" id="track_woocommerce" value="true" 
            <?php 
                $server_token = get_option('usermaven_server_token');
                checked(get_option('usermaven_track_woocommerce'), true); 
                echo empty($server_token) ? 'disabled' : '';
            ?>>
        Track WooCommerce events
        <?php if (empty($server_token)): ?>
            <span class="tooltip-text" style="color: #d63638; font-size: 12px; display: block; margin-top: 5px;">
                Server token is required to enable WooCommerce tracking. Please add your server token above.
            </span>
        <?php endif; ?>
        </label>
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
        You can read more about shared link and how to create it <a href="https://usermaven.com/docs/website-analytics/shareable-dashboard" target="blank">here</a>.
        </p>
        </div>
        <div class="form-button">
        <input type="submit" name="submit" value="Save Changes">
        </div>
      </form>
      </div>

      <style>
        .notice-toast {
          position: fixed;
          top: 32px;
          right: 20px;
          padding: 16px 24px;
          border-radius: 8px;
          z-index: 9999;
          min-width: 300px;
          backdrop-filter: blur(10px);
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
          animation: slideIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55), fadeOut 0.5s ease-in-out 2.5s forwards;
        }
        .notice-toast p {
          margin: 0;
          font-size: 14px;
          line-height: 1.4;
          font-weight: 500;
          display: flex;
          align-items: center;
        }
        .notice-toast p::before {
          content: '';
          display: inline-block;
          width: 20px;
          height: 20px;
          margin-right: 12px;
          background-position: center;
          background-repeat: no-repeat;
          background-size: contain;
        }
        .notice-success {
          background: linear-gradient(135deg, rgba(70, 180, 80, 0.95) 0%, rgba(60, 170, 70, 0.95) 100%);
          color: white;
          border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .notice-success p::before {
          background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>');
        }
        .notice-error {
          background: linear-gradient(135deg, rgba(220, 50, 50, 0.95) 0%, rgba(200, 40, 40, 0.95) 100%);
          color: white;
          border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .notice-error p::before {
          background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>');
        }
        @keyframes slideIn {
          from {
            transform: translateX(100%) translateY(-50%);
            opacity: 0;
          }
          to {
            transform: translateX(0) translateY(0);
            opacity: 1;
          }
        }
        @keyframes fadeOut {
          from {
            opacity: 1;
            transform: translateX(0) translateY(0);
          }
          to {
            opacity: 0;
            transform: translateX(10px) translateY(0);
            visibility: hidden;
          }
        }
      </style>
    <?php
}
