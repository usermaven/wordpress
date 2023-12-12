<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://usermaven.com/
 * @since      1.0.4
 *
 * @package    Usermaven
 * @subpackage Usermaven/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.4
 * @package    Usermaven
 * @subpackage Usermaven/includes
 * @author     Usermaven <awais.ahmed@d4interactive.io>
 */
class Usermaven_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.4
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'usermaven',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
