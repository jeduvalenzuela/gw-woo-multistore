<?php
/**
 * Plugin Name: Woo Multistore Elementor Widget
 * Plugin URI: https://gavaweb.com/
 * Description: Elementor Widget to show multistore products of WooCommerce remotes.
 * Version: 1.0.0
 * Author: Eduardo Valenzuela
 * Text Domain: wc-multistore-elementor
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'MSW_VERSION', '1.0.1' );
define( 'MSW_PLUGIN_FILE', __FILE__ );
define( 'MSW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MSW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class WC_Multistore_Elementor {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once MSW_PLUGIN_DIR . 'includes/class-msw-loader.php';
        require_once MSW_PLUGIN_DIR . 'includes/class-msw-settings.php';
        require_once MSW_PLUGIN_DIR . 'includes/class-msw-remote-products-service.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
        add_action( 'admin_notices', [ $this, 'check_dependencies' ] );
    }

    /**
     * On plugins loaded
     */
    public function on_plugins_loaded() {
        // Check if Elementor is installed and activated
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }

        // Initialize loader
        MSW_Loader::instance();
    }

    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        $screen = get_current_screen();
        if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
            return;
        }

        $missing = [];

        if ( ! class_exists( 'WooCommerce' ) ) {
            $missing[] = '<strong>WooCommerce</strong>';
        }

        if ( ! did_action( 'elementor/loaded' ) ) {
            $missing[] = '<strong>Elementor</strong>';
        }

        if ( ! empty( $missing ) ) {
            $message = sprintf(
                /* translators: %s: comma-separated list of plugin names */
                __( 'WooCommerce Multistore Elementor Widget requiere %s para funcionar correctamente.', 'wc-multistore-elementor' ),
                implode( ', ', $missing )
            );

            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                wp_kses_post( $message )
            );
        }
    }
}

/**
 * Initialize the plugin
 */
function wc_multistore_elementor() {
    return WC_Multistore_Elementor::instance();
}

// Kick off the plugin
wc_multistore_elementor();