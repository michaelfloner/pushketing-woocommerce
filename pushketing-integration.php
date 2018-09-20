<?php
/**
 * Plugin Name: WP Pushketing Integration
 * Description: Allows pushketing tracking.
 * Author: Michael Floner / Pushketing
 * Author URI: https://www.pushketing.com/
 * Version: 1.0.0
 * WC requires at least: 2.1
 * WC tested up to: 3.3
 * License: GPLv2 or later
 * Text Domain: woocommerce-pushketing-integration
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Pushketing_Integration' ) ) {

	/**
	 * WooCommerce Pushketing Integration main class.
	 */
	class WC_Pushketing_Integration {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '1.0.0';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin.
		 */
		private function __construct() {
			// Load plugin text domain
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.1-beta-1', '>=' ) ) {
				include_once 'includes/pushketing.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
		}

		public function plugin_links( $links ) {
			$settings_url = add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab' => 'integration',
				),
				admin_url( 'admin.php' )
			);

			$plugin_links = array(
				'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce_pushketing-integration' ) . '</a>',
				'<a href="https://wordpress.org/support/plugin/woocommerce_pushketing-integration">' . __( 'Support', 'woocommerce_pushketing-integration' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Load the plugin text domain for translation.
		 *
		 * @return void
		 */
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce_pushketing-integration' );

			load_textdomain( 'woocommerce_pushketing-integration', trailingslashit( WP_LANG_DIR ) . 'woocommerce_pushketing-integration/woocommerce_pushketing-integration-' . $locale . '.mo' );
			load_plugin_textdomain( 'woocommerce_pushketing-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * WooCommerce fallback notice.
		 *
		 * @return string
		 */
		public function woocommerce_missing_notice() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Pushketing depends on the last version of %s to work!', 'woocommerce_pushketing-integration' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce', 'woocommerce_pushketing-integration' ) . '</a>' ) . '</p></div>';
		}

		/**
		 * Add a new integration to WooCommerce.
		 *
		 * @param  array $integrations WooCommerce integrations.
		 *
		 * @return array               Pushketing integration.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Pushketing';

			return $integrations;
		}
	}

	add_action( 'plugins_loaded', array( 'WC_Pushketing_Integration', 'get_instance' ), 0 );

}
