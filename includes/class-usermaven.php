<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://usermaven.com/
 * @since      1.0.4
 *
 * @package    Usermaven
 * @subpackage Usermaven/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.4
 * @package    Usermaven
 * @subpackage Usermaven/includes
 * @author     Usermaven <awais.ahmed@d4interactive.io>
 */
class Usermaven {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.4
	 * @access   protected
	 * @var      Usermaven_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.4
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.4
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The tracking host of the plugin.
	 *
	 * @since    1.0.4
	 * @access   protected
	 * @var      string    $tracking_host    The tracking host of the plugin.
	 */
	protected $tracking_host;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.4
	 */
	
	public function __construct($tracking_host) {
		if ( defined( 'USERMAVEN_VERSION' ) ) {
			$this->version = USERMAVEN_VERSION;
		} else {
			$this->version = '1.2.7';
		}
		$this->plugin_name = 'usermaven';
		$this->tracking_host = $tracking_host;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
        add_action('wp_footer', array( $this, 'usermaven_events_tracking_print_js_snippet' ), 50);
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Usermaven_Loader. Orchestrates the hooks of the plugin.
	 * - Usermaven_i18n. Defines internationalization functionality.
	 * - Usermaven_Admin. Defines all hooks for the admin area.
	 * - Usermaven_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.4
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-usermaven-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-usermaven-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-usermaven-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-usermaven-public.php';

		$this->loader = new Usermaven_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Usermaven_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.4
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Usermaven_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.4
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Usermaven_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.4
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Usermaven_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.4
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.4
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.4
	 * @return    Usermaven_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.4
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	* Private function to check if the tracking is enabled for the current user role
	* Note: If the user role is not found in the usermaven roles, then tracking is enabled by default
    */
	private function is_tracking_enabled() {
		$current_user = wp_get_current_user();
		$is_logged_in = is_user_logged_in();
		
		if (!$is_logged_in) {
			return true;
		}
		
		$current_user_role = $current_user->roles[0] ?? '';
		
		if (!$current_user_role) {
			return true;
		}
		
		$usermaven_roles = [
			'administrator',
			'author',
			'contributor',
			'editor',
			'subscriber',
			'translator'
		];
		
		if (in_array($current_user_role, $usermaven_roles)) {
			$usermaven_tracking_enabled = get_option('usermaven_role_' . $current_user_role);
			return $usermaven_tracking_enabled;
		}
		
		// For roles other than the specified Usermaven roles, return true
		return true;
	}



    /**
    * This function includes the JS tracking snippet in the wordpress website
    */
	public function usermaven_events_tracking_print_js_snippet() {
		$tracking_host = $this->tracking_host;
	    $tracking_path = "https://t.usermaven.com/lib.js";
	    $api_key = get_option('usermaven_api_key');
	    if (empty($api_key)) {
	        return;
	    }
	    $custom_domain = get_option('usermaven_custom_domain');
	    $data_autocapture = get_option('usermaven_autocapture');
	    $form_tracking = get_option('usermaven_form_tracking');
	    $cookie_less_tracking = get_option('usermaven_cookie_less_tracking');
	    $identify_verification = get_option('usermaven_identify_verification');
	    $is_tracking_enabled = $this->is_tracking_enabled();

	    if (!$is_tracking_enabled) {
            return;
        }


	    $current_user = wp_get_current_user();
	    $is_logged_in = is_user_logged_in();

	    if ( !empty($custom_domain)) {
	        $custom_domain = rtrim($custom_domain, '/');
	        $tracking_path = $custom_domain . "/lib.js";
	        $tracking_host = $custom_domain;
	    } ?>

        <!-- Usermaven - privacy-friendly analytics tool -->
        <script type="text/javascript">
            (function () {
                window.usermaven = window.usermaven || (function () { (window.usermavenQ = window.usermavenQ || []).push(arguments); })
                var t = document.createElement('script'),
                    s = document.getElementsByTagName('script')[0];
                t.defer = true;
                t.id = 'um-tracker';
                t.setAttribute('data-tracking-host', '<?php echo esc_attr($tracking_host); ?>');
                t.setAttribute('data-key', '<?php echo esc_attr($api_key); ?>');
                <?php if($data_autocapture): ?>t.setAttribute('data-autocapture', 'true');<?php endif; ?>
                <?php if($form_tracking): ?>t.setAttribute('data-form-tracking', 'true');<?php endif; ?>
                <?php if($cookie_less_tracking): ?>t.setAttribute('data-privacy-policy', 'strict');<?php endif; ?>
                t.setAttribute('data-randomize-url', 'true');
                t.src = '<?php echo esc_attr($tracking_path); ?>';
                s.parentNode.insertBefore(t, s);
            })();
        </script>
        <!-- / Usermaven -->


        <?php if($is_logged_in && $identify_verification): ?>
            <!-- Usermaven - identify verification -->
            <script type="text/javascript">
                (function () {
                   usermaven('id', {
                       id: '<?php echo esc_attr($current_user->ID); ?>',
                       email: '<?php echo esc_attr($current_user->user_email); ?>',
                       name: '<?php echo esc_attr($current_user->display_name); ?>',
                       first_name: '<?php echo esc_attr($current_user->user_firstname); ?>',
                       last_name: '<?php echo esc_attr($current_user->user_lastname); ?>',
                       created_at: '<?php echo esc_attr($current_user->user_registered); ?>',
                       custom: {
                            role: '<?php echo esc_attr($current_user->roles[0]); ?>',
                       }
                   });
                })();
            </script>
            <!-- / Usermaven - identify verification -->
        <?php endif; ?>

        <?php
    }


        # Todo: Server side tracking to be included in next release.
//     /**
//     * This function is used to track the server side of wordpress website
//     */
//     public function usermaven_track_server_side_event( $user_id, $event_type, $company = array(), $event_attributes = array()) {
//         $event_api_url = 'https://eventcollectors.usermaven.com/api/v1/s2s/event/';
//         $api_key = get_option('usermaven_api_key');
//         $server_token = get_option('usermaven_server_token');
//         $token = $api_key . "." . $server_token;
//         $random_id = uuid_create();
//         $query_string = http_build_query( array(
//             'token' =>  $token,
//            ) );
//
//         $payload = array(
//                 'api_key' => $api_key,
//                 'event_type' => $event_type,
//                 'event_id' => "",
//                 'ids' => array(),
//                 'user' => array(
//                 'anonymous_id'=> $random_id,
//                 'id'=> $user_id),
//                 'screen_resolution' => "0",
//                 'src' => "usermaven-python",
//                 'event_attributes' => $event_attributes
//             );
//
//         // Validate the structure of the company parameter
//         if ( $company && isset( $company['id'] ) && isset( $company['name'] ) && isset( $company['created_at'] ) ) {
//             // The company parameter is valid, add it to the payload
//             $payload['company'] = $company;
//         } else {
//             // The company parameter is invalid, throw an error
//             throw new Exception( 'Invalid company parameter. The company parameter must contain id, name, and
//              created_at elements.' );
//         }
//
//         $response = wp_remote_post( $event_api_url . '?' . $query_string, array(
//             'method' => 'POST',
//             'timeout' => 45,
//             'redirection' => 5,
//             'httpversion' => '1.0',
//             'blocking' => true,
//             'headers' => array(
//                 'Content-Type' => 'application/json',
//             ),
//             'body' => json_encode( $payload ),
//             'cookies' => array()
//         ) );
//
//
//         if ( is_wp_error( $response ) ) {
//             // Log the error.
//             error_log( 'Error tracking event: ' . $response->get_error_message() );
//         } else {
//             // Event was successfully tracked.
//         }
//     }

}
