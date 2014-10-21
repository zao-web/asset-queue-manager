<?php
/**
 * Plugin Name: Asset Queue Manager
 * Plugin URI: http://themeofthecrop.com
 * Description: A tool for front-end experts to take control of all scripts and styles enqueued on their site.
 * Version: 0.0.1
 * Author: Theme of the Crop
 * Author URI: http://themeofthecrop.com
 * License:     GNU General Public License v2.0 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Text Domain: asset-queue-manager
 * Domain Path: /languages/
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation; either version 2 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write
 * to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
if ( ! defined( 'ABSPATH' ) )
	exit;

if ( !class_exists( 'aqmInit' ) ) {
class aqmInit {

	/**
	 * The single instance of this class
	 */
	private static $instance;

	/**
	 * Path to the plugin directory
	 */
	static $plugin_dir;

	/**
	 * URL to the plugin
	 */
	static $plugin_url;

	/**
	 * Array of assets to be managed
	 */
	public $assets;

	/**
	 * Create or retrieve the single instance of the class
	 *
	 * @since 0.1
	 */
	public static function instance() {

		if ( !isset( self::$instance ) ) {

			self::$instance = new aqmInit;

			self::$plugin_dir = untrailingslashit( plugin_dir_path( __FILE__ ) );
			self::$plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );

			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {

		// Textdomain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Handle queue management requests via Ajax
		add_action( 'wp_ajax_nopriv_aqm-modify-asset' , array( $this , 'ajax_nopriv_default' ) );
		add_action( 'wp_ajax_aqm-modify-asset', array( $this, 'ajax_modify_asset' ) );

		// Add the rest of the hooks which are only needed when the
		// admin bar is showing
		add_action( 'admin_bar_init', array( $this, 'admin_bar_init' ) );

	}

	/**
	 * Add the hooks to display the asset panel in the admin bar
	 * @since 0.0.1
	 */
	public function admin_bar_init() {

		if ( !is_super_admin() || !is_admin_bar_showing() || $this->is_wp_login() || is_admin() ) {
			return;
		}

		// Store all assets enqueued in the head
		add_action( 'wp_head', array( $this, 'store_head_assets' ), 1000 );

		// Store any new assets enqueued in the footer
		add_action( 'wp_footer', array( $this, 'store_footer_assets' ), 1000 );
	}

	/**
	 * Load the plugin textdomain for localistion
	 * @since 0.0.1
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'asset-queue-manager', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Check if we're on the login page, because the admin bar isn't
	 * shown there. Thanks to debug-bar for the heads-up.
	 * https://wordpress.org/plugins/debug-bar/
	 *
	 * @since 0.0.1
	 */
	public function is_wp_login() {
		return 'wp-login.php' == basename( $_SERVER['SCRIPT_NAME'] );
	}

	/**
	 * Store assets found in the list of enqueued assets
	 * @since 0.0.1
	 */
	public function store_asset_list( $enqueued_slugs, $asset_data, $location, $type ) {
		
		foreach( $enqueued_slugs as $slug ) {
			$this->store_asset( $slug, $asset_data[ $slug ], $location, $type );
		}
	}

	/**
	 * Store a single asset's data
	 * @since 0.0.1
	 */
	public function store_asset( $slug, $data, $location, $type ) {

		if ( !isset( $this->assets[ $location ] ) ) {
			$this->assets[ $location ] = array();
		}

		if ( !isset( $this->assets[ $location ][ $type ] ) ) {
			$this->assets[ $location ][ $type ] = array();
		}

		if ( $this->is_asset_stored( $slug, $location, $type ) ) {
			return;
		}

		$this->assets[ $location ][ $type ][ $slug ] = $data;
	}

	/**
	 * Check if an asset has already been added to our list
	 * @since 0.0.1
	 */
	public function is_asset_stored( $slug, $location, $type ) {

		// Only check in the footer
		if ( $location !== 'footer' ) {
			return false;
		}

		if ( isset( $this->assets[ 'head' ] ) && isset( $this->assets[ 'head' ][ $type ] ) && isset( $this->assets[ 'head' ][ $type ][ $slug ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Store assets enqueued in the head
	 * @since 0.0.1
	 */
	public function store_head_assets() {

		global $wp_scripts;
		$this->store_asset_list( $wp_scripts->done, $wp_scripts->registered, 'head', 'scripts' );

		global $wp_styles;
		$this->store_asset_list( $wp_styles->done, $wp_styles->registered, 'head', 'styles' );
	}

	/**
	 * Store assets enqueued in the footer
	 * @since 0.0.1
	 */
	public function store_footer_assets() {

		global $wp_scripts;
		$this->store_asset_list( $wp_scripts->done, $wp_scripts->registered, 'footer', 'scripts' );

		global $wp_styles;
		$this->store_asset_list( $wp_styles->done, $wp_styles->registered, 'footer', 'styles' );
	}

	/**
	 * Retrieve assets dequeued by this plugin
	 * @since 0.0.1
	 */
	public function get_dequeued_assets() {

		if ( !isset( $this->assets['dequeued'] ) ) {
			$this->assets['dequeued'] = get_option( 'aqm-dequeued' );
		}

		return $this->assets['dequeued'];
	}

	/**
	 * Handle all ajax requests from logged out users
	 * @since 0.0.1
	 */
	public function ajax_nopriv_default() {

		wp_send_json_error(
			array(
				'error' => 'loggedout',
				'msg' => __( 'You have been logged out. Please login again to perform this request.', 'asset-queue-manager' ),
			)
		);
	}

	/**
	 * Handle ajax request to dequeue or re-enqueue an asset
	 * @since 0.0.1
	 */
	public function ajax_modify_asset() {

		// @todo

	}

}
} // endif;

/**
 * This function returns one aqmInit instance everywhere
 * and can be used like a global, without needing to declare the global.
 *
 * Example: $aqm = aqmInit();
 */
if ( !function_exists( 'aqmInit' ) ) {
function aqmInit() {
	return aqmInit::instance();
}
add_action( 'plugins_loaded', 'aqmInit' );
} // endif;
