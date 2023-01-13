<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://usermaven.com/
 * @since      1.0.0
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
 * @since      1.0.0
 * @package    Usermaven
 * @subpackage Usermaven/includes
 * @author     Usermaven <awais.ahmed@d4interactive.io>
 */
class Usermaven {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Usermaven_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'USERMAVEN_VERSION' ) ) {
			$this->version = USERMAVEN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'usermaven';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
        add_action( 'wp_enqueue_scripts',  array( $this, 'usermaven_events_tracking_enqueue_scripts' ) );
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
	 * @since    1.0.0
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
	 * @since    1.0.0
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
	 * @since    1.0.0
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
	 * @since    1.0.0
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
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Usermaven_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}


    /**
    * This function includes the JS tracking snippet in the wordpress website
    */
	public function usermaven_events_tracking_enqueue_scripts() {
	    $tracking_path = "https://t.usermaven.com/lib.js";
	    $api_key = get_option('usermaven_api_key');
	    $tracking_host = get_option('usermaven_tracking_host');
	    $data_autocapture = get_option('usermaven_autocapture');
	    $cookie_less_tracking = get_option('usermaven_cookie_less_tracking');
	    $tracking_host = rtrim($tracking_host, '/');

	    if ($tracking_host !== 'https://events.usermaven.com') {
	        $tracking_path = $tracking_host . "/lib.js";
	    }

	    wp_enqueue_script( 'usermaven-tracking', $tracking_path , array(), USERMAVEN_VERSION, true );

	    $data = array(
		'api_key' => $api_key,
		'tracking_host' => $tracking_host,
	     );

	     if ($data_autocapture) {
            $data['data_autocapture'] = $data_autocapture;
         }

         if ($cookie_less_tracking) {
            $data['data_privacy_policy'] = "strict";
         }

	    wp_localize_script( 'usermaven-tracking', 'usermaven_data', $data );
    }

    /**
    * This function is used to track the server side of wordpress website
    */
    public function track_server_side_event( $user_id, $event_type, $company = array(), $event_attributes = array()) {
        $event_api_url = 'https://eventcollectors.usermaven.com/api/v1/s2s/event/';
        $api_key = get_option('usermaven_api_key');
        $server_token = get_option('usermaven_server_token');
        $token = $api_key . "." . $server_token;
        $query_string = http_build_query( array(
            'token' =>  $token,
           ) );

        $payload = array(
                'api_key' => $api_key,
                'event_type' => $event_type,
                'event_id' => "",
                'ids' => array(),
                'user_id' => $user_id,
                'screen_resolution' => "0",
                'src' => "usermaven-python",
                'event_attributes' => $event_attributes
            );

        // Validate the structure of the company parameter
        if ( $company && isset( $company['id'] ) && isset( $company['name'] ) && isset( $company['created_at'] ) ) {
            // The company parameter is valid, add it to the payload
            $payload['company'] = $company;
        } else {
            // The company parameter is invalid, throw an error
            throw new Exception( 'Invalid company parameter. The company parameter must contain id, name, and
             created_at elements.' );
        }

        $response = wp_remote_post( $event_api_url . '?' . $query_string, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( $payload ),
            'cookies' => array()
        ) );


        if ( is_wp_error( $response ) ) {
            // Log the error.
            error_log( 'Error tracking event: ' . $response->get_error_message() );
        } else {
            // Event was successfully tracked.
        }
    }

}
