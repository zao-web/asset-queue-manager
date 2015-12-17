<?php
/**
 * Plugin Name: Asset Queue Manager
 * Plugin URI: https://github.com/NateWr/asset-queue-manager
 * Description: A tool for front-end experts to take control of all scripts and styles enqueued on their site.
 * Version: 1.0.0
 * Author: Nate Wright
 * Author URI: https://github.com/NateWr
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

		// Process an emergency restore request
		add_action( 'init', array( $this, 'restore_queue' ) );

		// Add the rest of the hooks which are only needed when the
		// admin bar is showing
		add_action( 'admin_bar_init', array( $this, 'admin_bar_init' ) );

		// Deregister assets
		add_action( 'wp_head', array( $this, 'deregister_assets' ), 7 );
		add_action( 'wp_footer', array( $this, 'deregister_assets' ) );

	}

	/**
	 * Add the hooks to display the asset panel in the admin bar
	 * @since 0.0.1
	 */
	public function admin_bar_init() {

		if ( !is_super_admin() || !is_admin_bar_showing() || $this->is_wp_login() ) {
			return;
		}

		// Add links to the plugin listing on the installed plugins page
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2);

		// Don't bother showing the panel in the admin area
		if ( is_admin() ) {
			return;
		}

		// Enqueue assets for the control panel
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Store all assets enqueued in the head
		add_action( 'wp_head', array( $this, 'store_head_assets' ), 1000 );

		// Store any new assets enqueued in the footer
		add_action( 'wp_footer', array( $this, 'store_footer_assets' ), 1000 );

		// Add the Assets item to the admin bar
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );

		// Print the assets panel in the footer
		add_action( 'wp_footer', array( $this, 'print_assets_panel' ), 1000 );
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
	 * Enqueue the front-end CSS and Javascript for the control panel
	 * @since 0.0.1
	 */
	public function register_assets() {

		wp_enqueue_style( 'asset-queue-manager', self::$plugin_url . '/assets/css/aqm.css' );

		// Load unminified scripts in debug mode
		if ( WP_DEBUG ) {
			wp_enqueue_script( 'asset-queue-manager', self::$plugin_url . '/assets/js/aqm.js', array( 'jquery' ), '', true );
		} else {
			wp_enqueue_script( 'asset-queue-manager', self::$plugin_url . '/assets/js/aqm.min.js', array( 'jquery' ), '', true );
		}

		// Add translateable strings, nonce, and URLs for ajax requests
		wp_localize_script(
			'asset-queue-manager',
			'aqm',
			array(
				'nonce'		=> wp_create_nonce( 'asset-queue-manager' ),
				'siteurl'	=> get_bloginfo( 'url' ),
				'ajaxurl'	=> admin_url('admin-ajax.php'),
				'strings'	=> array(
					'head_scripts'		=> __( 'Head Scripts', 'asset-queue-manager' ),
					'footer_scripts'	=> __( 'Footer Scripts', 'asset-queue-manager' ),
					'head_styles'		=> __( 'Head Styles', 'asset-queue-manager' ),
					'footer_styles'		=> __( 'Footer Styles', 'asset-queue-manager' ),
					'dequeued_scripts'	=> __( 'Dequeued Scripts', 'asset-queue-manager' ),
					'dequeued_styles'	=> __( 'Dequeued Styles', 'asset-queue-manager' ),
					'no_src'			=> __( 'This asset handle calls its dependent assets but loads no source files itself.', 'asset-queue-manager' ),
					'requeued'			=> __( 'This asset is no longer being dequeued. Reload the page to view where it is enqueued.', 'asset-queue-manager' ),
					'deps'				=> __( 'Dependencies:', 'asset-queue-manager' ),
					'dequeue'			=> __( 'Dequeue Asset', 'asset-queue-manager' ),
					'enqueue'			=> __( 'Stop Dequeuing', 'asset-queue-manager' ),
					'view'				=> __( 'View Asset', 'asset-queue-manager' ),
					'sending'			=> __( 'Sending Request', 'asset-queue-manager' ),
					'unknown_error' 	=> __( 'There was an unknown error with this request. Sorry.', 'asset-queue-manager' )
				),
			)
		);
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
	 * Deregister all dequeued assets. This should be called before
	 * wp_head and wp_footer.
	 * @since 0.0.1
	 */
	public function deregister_assets() {

		$this->get_dequeued_assets();

		if ( !empty( $this->assets['dequeued']['scripts'] ) ) {
			foreach( $this->assets['dequeued']['scripts'] as $handle => $asset ) {
				wp_deregister_script( $handle );
			}
		}

		if ( !empty( $this->assets['dequeued']['styles'] ) ) {
			foreach( $this->assets['dequeued']['styles'] as $handle => $asset ) {
				wp_deregister_style( $handle );
			}
		}
	}

	/**
	 * Add an Assets item to the admin bar menu
	 * @since 0.0.1
	 */
	public function admin_bar_menu() {

		if ( is_admin() ) {
			return;
		}

		global $wp_admin_bar;

		$recovery_message = sprintf( __( 'The Asset Queue Manager panel did not load. This can happen if jQuery is not being loaded on the page. If you have encountered this error after dequeuing an asset by mistake, you can %srestore all assets%s dequeued by Asset Queue Manager. This message is only shown to administrators.', 'asset-queue-manager' ), '<a href="' . admin_url() . '?aqm=restore">', '</a>' );

		$wp_admin_bar->add_node(
			array(
				'id'     	=> 'asset-queue-manager',
				'parent'	=> 'top-secondary',
				'title'  	=> __( 'Assets', 'asset-queue-manager' ),
				'meta'		=> array(
					'html'	=> '<div class="inactive"><p>' . $recovery_message . '</p></div>'
				)
			)
		);
	}

	/**
	 * Print the assets panel and pass the assets array to the script
	 * for loading. We can't use wp_localize_script() because this has
	 * to come after the last enqueue opportunity.
	 * @since 0.0.1
	 */
	public function print_assets_panel() {

		// Add dequeued assets to the $assets array
		$this->get_dequeued_assets();

		$data = array(
			'assets'	=> $this->assets,
			'notices'	=> $this->get_notices(),
		);

		?>

<div id="aqm-panel" class="inactive"></div>
<script type='text/javascript'>
	/* <![CDATA[ */
	var aqmData = <?php echo json_encode( $data ); ?>
	/* ]]> */
</script>

		<?php
	}

	/**
	 * Define the notices and warnings to display for special assets
	 * @since 0.0.1
	 */
	public function get_notices() {

		$notices = array(
			'core'	=> array(
				'msg'		=> __( 'This asset is part of WordPress core. Dequeuing this asset could cause serious problems, including breaking the admin bar.', 'asset-queue-manager' ),
				'handles'	=> array(
					'jquery',
					'jquery-core',
					'jquery-migrate',
				),
			),
			'adminbar'	=> array(
				'msg'		=> __( 'This asset is commonly loaded with the admin bar for logged in users. It may not be loaded when logged-out users visit this page. Dequeuing this asset could break the admin bar, including this asset manager.', 'asset-queue-manager' ),
				'handles'	=> array(
					'open-sans',
					'dashicons',
					'admin-bar',
				),
			),
			'self'		=> array(
				'msg'		=> __( 'This asset is loaded by Asset Queue Manager. It will only be loaded for admin users and dequeuing it will prevent you from managing other assets.', 'asset-queue-manager' ),
				'handles'	=> array(
					'asset-queue-manager',
				)
			),
		);

		return apply_filters( 'aqm_notices', $notices );
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

		if ( !check_ajax_referer( 'asset-queue-manager', 'nonce' ) ||  !is_super_admin() ) {
			$this->ajax_nopriv_default();
		}

		if ( empty( $_POST['handle'] ) || empty( $_POST['type'] ) || empty( $_POST['asset_data'] ) ) {
			wp_send_json_error(
				array(
					'error' => 'noasset',
					'msg' => __( 'There was an error with this dequeue request. No asset information was passed.', 'asset-queue-manager' ),
					'post'	=> $_POST
				)
			);
		}


		if ( $_POST['type'] !== 'scripts' && $_POST['type'] !== 'styles' ) {
			wp_send_json_error(
				array(
					'error' => 'badtype',
					'msg' => __( 'There was an error with this dequeue request. The asset type was not recognized.', 'asset-queue-manager' ),
					'post'	=> $_POST
				)
			);
		}

		$handle = sanitize_key( $_POST['handle'] );
		$type = sanitize_key( $_POST['type'] );

		$this->get_dequeued_assets();

		// Initialize the array if nothing's been dequeued yet
		if ( empty( $this->assets['dequeued'][ $type ] ) ) {
			$this->assets['dequeued'][ $type ] = array();
		}

		// Handle dequeue request
		if ( $_POST['dequeue'] === 'true' ) {

			if ( in_array( $handle, $this->assets['dequeued'][ $type ] ) ) {
				wp_send_json_error(
					array(
						'error' => 'alreadydequeued',
						'msg' => __( 'This asset has already been dequeued. If the asset is still being loaded, the author may not have properly enqueued the asset using the wp_enqueue_* functions.', 'asset-queue-manager' ),
					)
				);
			}

			$this->assets['dequeued'][ $type ][ $handle ] = $_POST['asset_data'];

			update_option( 'aqm-dequeued', $this->assets['dequeued'] );

			wp_send_json_success(
				array(
					'type' => $type,
					'handle' => $handle,
					'option' => $this->assets['dequeued'],
					'dequeue' => true
				)
			);

		// Handle enqueue request
		} else {

			unset( $this->assets['dequeued'][ $type ][ $handle ] );

			update_option( 'aqm-dequeued', $this->assets['dequeued'] );

			wp_send_json_success(
				array(
					'type' => $type,
					'handle' => $handle,
					'option' => $this->assets['dequeued'],
					'dequeue' => false
				)
			);
		}
	}

	/**
	 * Delete dequeue option so that no assets are being blocked
	 *
	 * This is an emergency restore function in case people get
	 * themselves into a bit of a bind. Don't want them to have to get
	 * into the database to do this.
	 *
	 * @since 0.0.1
	 */
	public function restore_queue() {

		if ( empty( $_REQUEST['aqm'] ) || $_REQUEST['aqm'] !== 'restore' || !is_super_admin() ) {
			return;
		}

		delete_option( 'aqm-dequeued' );
	}

	/**
	 * Add links to the plugin listing on the installed plugins page
	 * @since 0.0.1
	 */
	public function plugin_action_links( $links, $plugin ) {

		if ( $plugin == plugin_basename( __FILE__ ) ) {

			$links['restore'] = '<a href="' . admin_url() . '?aqm=restore" title="' . __( 'Restore any assets dequeued by this plugin.', 'asset-queue-manager' ) . '">' . __( 'Restore Dequeued Assets', 'asset-queue-manager' ) . '</a>';
		}

		return $links;
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
