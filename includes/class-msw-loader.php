<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MSW_Loader {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Instanciamos la clase de settings inmediatamente para que sus hooks se registren
        MSW_Settings::instance();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        // Registrar los settings de la base de datos
        add_action( 'admin_init', [ 'MSW_Settings', 'register_settings' ] );

        // Registrar Elementor widgets
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );

        // Enqueue scripts y styles
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_enqueue_scripts' ] );
    }

    public function register_widgets( $widgets_manager ) {
        require_once MSW_PLUGIN_DIR . 'includes/elementor/class-msw-widget-products-loop.php';
        $widgets_manager->register( new MSW_Widget_Products_Loop() );
    }

    public function admin_enqueue_scripts( $hook ) {
        // Ajustamos el hook para que coincida con el submenú de WooCommerce
        if ( 'woocommerce_page_msw-settings' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'msw-admin', MSW_PLUGIN_URL . 'assets/css/admin.css', [], MSW_VERSION );
        wp_enqueue_script( 'msw-admin', MSW_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], MSW_VERSION, true );
    }

    public function frontend_enqueue_scripts() {
        wp_enqueue_style( 'msw-frontend', MSW_PLUGIN_URL . 'assets/css/frontend.css', [], MSW_VERSION );
        wp_enqueue_script( 'msw-frontend', MSW_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], MSW_VERSION, true );
    }
}