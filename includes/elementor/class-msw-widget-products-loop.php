<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MSW_Widget_Products_Loop extends \Elementor\Widget_Base {

    public function get_name() {
        return 'msw_products_loop';
    }

    public function get_title() {
        return __( 'Multistore Products Loop', 'wc-multistore-elementor' );
    }

    public function get_icon() {
        return 'eicon-products';
    }

    public function get_categories() {
        return [ 'woocommerce-elements' ];
    }

    public function get_keywords() {
        return [ 'woocommerce', 'products', 'multistore', 'remote' ];
    }

    protected function register_controls() {
        // Store Selection Section
        $this->start_controls_section(
            'section_store_selection',
            [
                'label' => __( 'Selección de Tienda', 'wc-multistore-elementor' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'store_selection_mode',
            [
                'label'   => __( 'Modo de Selección', 'wc-multistore-elementor' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all'    => __( 'Todas las tiendas activas', 'wc-multistore-elementor' ),
                    'single' => __( 'Una tienda específica', 'wc-multistore-elementor' ),
                ],
            ]
        );

        $this->add_control(
            'selected_store',
            [
                'label'     => __( 'Seleccionar Tienda', 'wc-multistore-elementor' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => '',
                'options'   => $this->get_available_stores(),
                'condition' => [
                    'store_selection_mode' => 'single',
                ],
            ]
        );

        $this->end_controls_section();

        // Query Section
        $this->start_controls_section(
            'section_query',
            [
                'label' => __( 'Consulta de Productos', 'wc-multistore-elementor' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label'   => __( 'Productos por Página', 'wc-multistore-elementor' ),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 12,
                'min'     => 1,
                'max'     => 100,
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label'   => __( 'Ordenar Por', 'wc-multistore-elementor' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date'  => __( 'Fecha', 'wc-multistore-elementor' ),
                    'title' => __( 'Título', 'wc-multistore-elementor' ),
                    'price' => __( 'Precio', 'wc-multistore-elementor' ),
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label'   => __( 'Orden', 'wc-multistore-elementor' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'desc',
                'options' => [
                    'asc'  => __( 'Ascendente', 'wc-multistore-elementor' ),
                    'desc' => __( 'Descendente', 'wc-multistore-elementor' ),
                ],
            ]
        );

        $this->add_control(
            'category',
            [
                'label'       => __( 'Categoría (slug)', 'wc-multistore-elementor' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => 'electronics',
            ]
        );

        $this->add_control(
            'tag',
            [
                'label'       => __( 'Etiqueta (slug)', 'wc-multistore-elementor' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => 'featured',
            ]
        );

        $this->end_controls_section();

        // Layout Section
        $this->start_controls_section(
            'section_layout',
            [
                'label' => __( 'Diseño', 'wc-multistore-elementor' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'   => __( 'Columnas', 'wc-multistore-elementor' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => '4',
                'options' => [
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
            ]
        );

        $this->add_control(
            'show_pagination',
            [
                'label'        => __( 'Mostrar Paginación', 'wc-multistore-elementor' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Sí', 'wc-multistore-elementor' ),
                'label_off'    => __( 'No', 'wc-multistore-elementor' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $current_page = isset( $_GET['msw_page'] ) ? max( 1, intval( $_GET['msw_page'] ) ) : 1;

        // Determinar tiendas a consultar
        $stores = [];
        if ( $settings['store_selection_mode'] === 'single' && ! empty( $settings['selected_store'] ) ) {
            $stores = [ $settings['selected_store'] ];
        }

        $args = [
            'stores'   => $stores,
            'page'     => $current_page,
            'per_page' => (int) $settings['per_page'],
            'orderby'  => $settings['orderby'],
            'order'    => $settings['order'],
            'category' => $settings['category'] ?? '',
            'tag'      => $settings['tag'] ?? '',
        ];

        // Logs mínimos para debug si hace falta
        error_log( '=== MSW Widget Render ===' );
        error_log( 'Args: ' . print_r( $args, true ) );

        $service = new MSW_Remote_Products_Service();
        $result  = $service->get_products( $args );

        $products    = $result['items'] ?? [];
        $total       = $result['total'] ?? 0;
        $total_pages = $total > 0 ? ceil( $total / (int) $settings['per_page'] ) : 1;

        if ( empty( $products ) ) {
            echo '<p>' . esc_html__( 'No se encontraron productos.', 'wc-multistore-elementor' ) . '</p>';
            return;
        }

        ?>
        <div class="msw-products-grid msw-columns-<?php echo esc_attr( $settings['columns'] ); ?>">
            <?php foreach ( $products as $product ) : ?>
                <div class="msw-product-card">
                    <?php if ( ! empty( $product['image'] ) ) : ?>
                        <div class="msw-product-image">
                            <a href="<?php echo esc_url( $product['permalink'] ); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url( $product['image'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>">
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="msw-product-content">
                        <div>
                            <h3 class="msw-product-title">
                                <a href="<?php echo esc_url( $product['permalink'] ); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html( $product['name'] ); ?>
                                </a>
                            </h3>
    
                            <?php if ( ! empty( $product['price_html'] ) ) : ?>
                                <div class="msw-product-price">
                                    <?php echo wp_kses_post( $product['price_html'] ); ?>
                                </div>
                            <?php endif; ?>
    
                            <?php if ( ! empty( $product['store_name'] ) ) : ?>
                                <div class="msw-product-store">
                                    <?php echo esc_html( $product['store_name'] ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url( $product['permalink'] ); ?>" target="_blank" rel="noopener" class="msw-buy-now">
                            <?php echo 'comprar'; ?>
                        </a>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php

        if ( $settings['show_pagination'] === 'yes' && $total_pages > 1 ) {
            $this->render_pagination( $current_page, $total_pages );
        }
    }

    protected function render_pagination( $current_page, $total_pages ) {
        $base_url = remove_query_arg( 'msw_page' );
        ?>
        <nav class="msw-pagination">
            <?php if ( $current_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'msw_page', $current_page - 1, $base_url ) ); ?>" class="msw-pagination-prev">
                    <?php esc_html_e( '← Anterior', 'wc-multistore-elementor' ); ?>
                </a>
            <?php endif; ?>

            <span class="msw-pagination-info">
                <?php printf( esc_html__( 'Página %d de %d', 'wc-multistore-elementor' ), $current_page, $total_pages ); ?>
            </span>

            <?php if ( $current_page < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'msw_page', $current_page + 1, $base_url ) ); ?>" class="msw-pagination-next">
                    <?php esc_html_e( 'Siguiente →', 'wc-multistore-elementor' ); ?>
                </a>
            <?php endif; ?>
        </nav>
        <?php
    }

    protected function get_available_stores() {
        $stores_data = get_option( MSW_Remote_Products_Service::OPTION_STORES, [] );
        $stores      = [];

        if ( ! empty( $stores_data ) && is_array( $stores_data ) ) {
            foreach ( $stores_data as $store ) {
                if ( ! empty( $store['enabled'] ) && ! empty( $store['id'] ) ) {
                    $stores[ $store['id'] ] = $store['name'] ?? $store['id'];
                }
            }
        }

        if ( empty( $stores ) ) {
            $stores[''] = __( 'No hay tiendas configuradas', 'wc-multistore-elementor' );
        }

        return $stores;
    }
}