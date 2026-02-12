<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Settings page class
 */
class MSW_Settings {

    const OPTION_STORES = 'msw_remote_stores';

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
    }
    
    public function add_menu_page() {
        // Submenú dentro de WooCommerce
        add_submenu_page(
            'woocommerce', // slug del menú padre
            __( 'Tiendas Remotas', 'wc-multistore-elementor' ), // Título de la página
            __( 'Tiendas Remotas', 'wc-multistore-elementor' ), // Título del menú
            'manage_options',                                   // Capacidad requerida
            'msw-settings',                                     // slug de la página
            [ $this, 'render_settings_page' ]                   // Callback que renderiza la página
        );
    }

    public static function register_settings() {
        register_setting(
            'msw_settings_group',
            self::OPTION_STORES,
            [
                'type'              => 'array',
                'sanitize_callback' => [ __CLASS__, 'sanitize_stores' ],
                'default'           => [],
            ]
        );
    }

    public static function sanitize_stores( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $sanitized = [];

        foreach ( $input as $store ) {
            if ( ! is_array( $store ) ) {
                continue;
            }

            $sanitized[] = [
                'id'              => sanitize_key( $store['id'] ?? '' ),
                'name'            => sanitize_text_field( $store['name'] ?? '' ),
                'base_url'        => esc_url_raw( $store['base_url'] ?? '' ),
                'consumer_key'    => sanitize_text_field( $store['consumer_key'] ?? '' ),
                'consumer_secret' => sanitize_text_field( $store['consumer_secret'] ?? '' ),
                'version'         => sanitize_text_field( $store['version'] ?? 'wc/v3' ),
                'enabled'         => ! empty( $store['enabled'] ),
            ];
        }

        return $sanitized;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle form submission
        if ( isset( $_POST['msw_save_stores'] ) && check_admin_referer( 'msw_save_stores_action', 'msw_save_stores_nonce' ) ) {
            $stores = $_POST['stores'] ?? [];
            update_option( self::OPTION_STORES, self::sanitize_stores( $stores ) );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configuración guardada correctamente.', 'wc-multistore-elementor' ) . '</p></div>';
        }

        // Handle clear cache
        if ( isset( $_POST['msw_clear_cache'] ) && check_admin_referer( 'msw_clear_cache_action', 'msw_clear_cache_nonce' ) ) {
            $this->clear_all_transients();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Caché limpiado correctamente.', 'wc-multistore-elementor' ) . '</p></div>';
        }

        $stores = get_option( self::OPTION_STORES, [] );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'msw_save_stores_action', 'msw_save_stores_nonce' ); ?>

                <table class="wp-list-table widefat fixed striped" id="msw-stores-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php esc_html_e( 'Activa', 'wc-multistore-elementor' ); ?></th>
                            <th style="width: 100px;"><?php esc_html_e( 'ID', 'wc-multistore-elementor' ); ?></th>
                            <th><?php esc_html_e( 'Nombre', 'wc-multistore-elementor' ); ?></th>
                            <th><?php esc_html_e( 'URL Base', 'wc-multistore-elementor' ); ?></th>
                            <th><?php esc_html_e( 'Consumer Key', 'wc-multistore-elementor' ); ?></th>
                            <th><?php esc_html_e( 'Consumer Secret', 'wc-multistore-elementor' ); ?></th>
                            <th style="width: 100px;"><?php esc_html_e( 'Versión API', 'wc-multistore-elementor' ); ?></th>
                            <th style="width: 80px;"><?php esc_html_e( 'Acciones', 'wc-multistore-elementor' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="msw-stores-tbody">
                        <?php
                        if ( ! empty( $stores ) ) {
                            foreach ( $stores as $index => $store ) {
                                $this->render_store_row( $index, $store );
                            }
                        } else {
                            $this->render_store_row( 0, [] );
                        }
                        ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button" id="msw-add-store"><?php esc_html_e( 'Agregar Tienda', 'wc-multistore-elementor' ); ?></button>
                </p>

                <p class="submit">
                    <input type="submit" name="msw_save_stores" class="button button-primary" value="<?php esc_attr_e( 'Guardar Cambios', 'wc-multistore-elementor' ); ?>">
                </p>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Herramientas', 'wc-multistore-elementor' ); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field( 'msw_clear_cache_action', 'msw_clear_cache_nonce' ); ?>
                <p>
                    <input type="submit" name="msw_clear_cache" class="button" value="<?php esc_attr_e( 'Limpiar Caché de Productos', 'wc-multistore-elementor' ); ?>">
                </p>
            </form>
        </div>

        <script type="text/html" id="tmpl-msw-store-row">
            <?php $this->render_store_row( '{{data.index}}', [], true ); ?>
        </script>
        <?php
    }

    private function render_store_row( $index, $store = [], $is_template = false ) {
        $enabled         = $store['enabled'] ?? false;
        $id              = $store['id'] ?? '';
        $name            = $store['name'] ?? '';
        $base_url        = $store['base_url'] ?? '';
        $consumer_key    = $store['consumer_key'] ?? '';
        $consumer_secret = $store['consumer_secret'] ?? '';
        $version         = $store['version'] ?? 'wc/v3';

        if ( $is_template ) {
            $enabled         = false;
            $id              = '';
            $name            = '';
            $base_url        = '';
            $consumer_key    = '';
            $consumer_secret = '';
            $version         = 'wc/v3';
        }

        ?>
        <tr class="msw-store-row">
            <td>
                <input type="checkbox" name="stores[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $enabled ); ?>>
            </td>
            <td>
                <input type="text" name="stores[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" class="regular-text" placeholder="store_1" required>
            </td>
            <td>
                <input type="text" name="stores[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Tienda Madrid', 'wc-multistore-elementor' ); ?>" required>
            </td>
            <td>
                <input type="url" name="stores[<?php echo esc_attr( $index ); ?>][base_url]" value="<?php echo esc_attr( $base_url ); ?>" class="regular-text" placeholder="https://tienda.com" required>
            </td>
            <td>
                <input type="text" name="stores[<?php echo esc_attr( $index ); ?>][consumer_key]" value="<?php echo esc_attr( $consumer_key ); ?>" class="regular-text" placeholder="ck_xxxxx" required>
            </td>
            <td>
                <input type="text" name="stores[<?php echo esc_attr( $index ); ?>][consumer_secret]" value="<?php echo esc_attr( $consumer_secret ); ?>" class="regular-text" placeholder="cs_xxxxx" required>
            </td>
            <td>
                <input type="text" name="stores[<?php echo esc_attr( $index ); ?>][version]" value="<?php echo esc_attr( $version ); ?>" class="small-text" placeholder="wc/v3">
            </td>
            <td>
                <button type="button" class="button msw-remove-store"><?php esc_html_e( 'Eliminar', 'wc-multistore-elementor' ); ?></button>
            </td>
        </tr>
        <?php
        
        
        $stores_debug = get_option( MSW_Settings::OPTION_STORES, [] );
        echo '<pre style="background:#fff; padding:10px; border:1px solid #ccc; margin-top:20px;">';
        echo 'DEBUG STORES OPTION:' . "\n";
        print_r( $stores_debug );
        echo '</pre>';
        
        
    }

    private function clear_all_transients() {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_msw_products_' ) . '%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_timeout_msw_products_' ) . '%'
            )
        );
    }
}