<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MSW_Remote_Products_Service {

    const OPTION_STORES = 'msw_remote_stores';
    const CACHE_TTL     = 300; // 5 minutos

    /**
     * Consulta productos remotos de una o varias tiendas y aplica paginación global.
     *
     * @param array $args
     * @return array
     */
    public function get_products( array $args ): array {
        $defaults = [
            'stores'    => [],
            'page'      => 1,
            'per_page'  => 12,
            'orderby'   => 'date',
            'order'     => 'DESC',
            'category'  => '',
            'tag'       => '',
            'min_price' => '',
            'max_price' => '',
        ];

        $args = wp_parse_args( $args, $defaults );

        $page     = max( 1, (int) $args['page'] );
        $per_page = max( 1, (int) $args['per_page'] );

        // 1) Intentar cache
        $cache_key = $this->build_cache_key( $args );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // 2) Obtener lista de tiendas a usar
        $stores = $this->get_stores( $args['stores'] );

        if ( empty( $stores ) ) {
            return [
                'items'         => [],
                'total'         => 0,
                'max_num_pages' => 0,
            ];
        }

        $limit_per_store = min( $page * $per_page, 100 );

        $all_products = [];
        $total        = 0;

        foreach ( $stores as $store ) {
            $result = $this->fetch_store_products( $store, $args, $limit_per_store );

            if ( is_wp_error( $result ) ) {
                // Log error and continue
                error_log( 'MSW Error fetching products from store ' . ( $store['id'] ?? 'unknown' ) . ': ' . $result->get_error_message() );
                continue;
            }

            $total        += (int) $result['total'];
            $all_products = array_merge( $all_products, $result['items'] );
        }

        if ( empty( $all_products ) ) {
            $response = [
                'items'         => [],
                'total'         => 0,
                'max_num_pages' => 0,
            ];
            set_transient( $cache_key, $response, self::CACHE_TTL );
            return $response;
        }

        // 3) Ordenar globalmente
        $all_products = $this->sort_products( $all_products, $args['orderby'], $args['order'] );

        // 4) Paginación global
        $offset      = ( $page - 1 ) * $per_page;
        $paged_items = array_slice( $all_products, $offset, $per_page );

        $response = [
            'items'         => $paged_items,
            'total'         => $total,
            'max_num_pages' => (int) ceil( $total / $per_page ),
        ];

        // 5) Guardar en cache
        set_transient( $cache_key, $response, self::CACHE_TTL );

        return $response;
    }

    /**
     * Construye una clave de transient basada en los parámetros de consulta.
     */
    protected function build_cache_key( array $args ): string {
        $key_data = [
            'stores'    => (array) $args['stores'],
            'page'      => (int) $args['page'],
            'per_page'  => (int) $args['per_page'],
            'orderby'   => (string) $args['orderby'],
            'order'     => strtoupper( (string) $args['order'] ),
            'category'  => (string) $args['category'],
            'tag'       => (string) $args['tag'],
            'min_price' => (string) $args['min_price'],
            'max_price' => (string) $args['max_price'],
        ];

        $hash = md5( wp_json_encode( $key_data ) );

        return 'msw_products_' . $hash;
    }

    /**
     * Devuelve el array de tiendas a usar según IDs pasados y opción global.
     *
     * @param array $requested_ids
     * @return array
     */
    protected function get_stores( array $requested_ids ): array {
        $all_stores = get_option( self::OPTION_STORES, [] );

        if ( empty( $all_stores ) || ! is_array( $all_stores ) ) {
            return [];
        }

        // Filtrar habilitadas
        $enabled_stores = array_filter( $all_stores, function ( $store ) {
            return ! empty( $store['enabled'] );
        } );

        if ( empty( $requested_ids ) ) {
            return array_values( $enabled_stores );
        }

        // Filtrar sólo las solicitadas
        $requested_ids = array_map( 'strval', $requested_ids );

        $selected = array_filter( $enabled_stores, function ( $store ) use ( $requested_ids ) {
            return isset( $store['id'] ) && in_array( (string) $store['id'], $requested_ids, true );
        } );

        return array_values( $selected );
    }

    /**
     * Llama a la API de una tienda y devuelve productos normalizados.
     *
     * @param array $store
     * @param array $args
     * @param int   $limit
     * @return array|\WP_Error
     */
    protected function fetch_store_products( array $store, array $args, int $limit ) {
        $base_url = rtrim( $store['base_url'] ?? '', '/' );
        $version  = ! empty( $store['version'] ) ? $store['version'] : 'wc/v3';

        if ( empty( $base_url ) || empty( $store['consumer_key'] ) || empty( $store['consumer_secret'] ) ) {
            return new WP_Error( 'msw_missing_store_config', 'Configuración incompleta de la tienda remota.' );
        }

        $endpoint = $base_url . '/wp-json/' . $version . '/products';

        $query_params = [
            'per_page' => $limit,
            'page'     => 1,
            'status'   => 'publish',
            'orderby'  => $args['orderby'],
            'order'    => $args['order'],
        ];

        if ( ! empty( $args['category'] ) ) {
            $query_params['category'] = $args['category'];
        }
        if ( ! empty( $args['tag'] ) ) {
            $query_params['tag'] = $args['tag'];
        }
        if ( $args['min_price'] !== '' ) {
            $query_params['min_price'] = $args['min_price'];
        }
        if ( $args['max_price'] !== '' ) {
            $query_params['max_price'] = $args['max_price'];
        }

        // Autenticación vía query params
        $query_params['consumer_key']    = $store['consumer_key'];
        $query_params['consumer_secret'] = $store['consumer_secret'];

        $url = add_query_arg( $query_params, $endpoint );

        $response = wp_remote_get( $url, [
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'msw_http_error', 'Error HTTP en la tienda remota: ' . $code );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return new WP_Error( 'msw_invalid_json', 'Respuesta JSON inválida de la tienda remota.' );
        }

        $total_header = wp_remote_retrieve_header( $response, 'x-wp-total' );
        $total        = $total_header !== '' ? (int) $total_header : count( $data );

        $normalized = [];
        foreach ( $data as $product ) {
            $normalized[] = $this->normalize_product( $product, $store );
        }

        return [
            'items' => $normalized,
            'total' => $total,
        ];
    }

    /**
     * Normaliza un producto remoto a un formato interno común.
     */
    protected function normalize_product( array $product, array $store ): array {
        $first_image = '';
        if ( ! empty( $product['images'] ) && is_array( $product['images'] ) ) {
            $first_image = $product['images'][0]['src'] ?? '';
        }

        $categories = [];
        if ( ! empty( $product['categories'] ) && is_array( $product['categories'] ) ) {
            foreach ( $product['categories'] as $cat ) {
                $categories[] = $cat['name'] ?? '';
            }
        }

        $tags = [];
        if ( ! empty( $product['tags'] ) && is_array( $product['tags'] ) ) {
            foreach ( $product['tags'] as $tag ) {
                $tags[] = $tag['name'] ?? '';
            }
        }

        return [
            'id'                => $product['id'] ?? 0,
            'store_id'          => $store['id'] ?? '',
            'store_name'        => $store['name'] ?? '',
            'name'              => $product['name'] ?? '',
            'slug'              => $product['slug'] ?? '',
            'permalink'         => $product['permalink'] ?? '',
            'price'             => isset( $product['price'] ) ? (float) $product['price'] : null,
            'regular_price'     => isset( $product['regular_price'] ) ? (float) $product['regular_price'] : null,
            'sale_price'        => isset( $product['sale_price'] ) ? (float) $product['sale_price'] : null,
            'image'             => $first_image,
            'short_description' => wp_strip_all_tags( $product['short_description'] ?? '' ),
            'description'       => $product['description'] ?? '',
            'categories'        => $categories,
            'tags'              => $tags,
            'stock_status'      => $product['stock_status'] ?? '',
            'date_created'      => $product['date_created'] ?? '',
            'date_modified'     => $product['date_modified'] ?? '',
        ];
    }

    /**
     * Ordena productos en PHP según orderby/order.
     */
    protected function sort_products( array $products, string $orderby, string $order ): array {
        $order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        usort( $products, function ( $a, $b ) use ( $orderby, $order ) {

            $valueA = $a[ $orderby ] ?? null;
            $valueB = $b[ $orderby ] ?? null;

            if ( $orderby === 'price' ) {
                $valueA = $a['price'] ?? 0;
                $valueB = $b['price'] ?? 0;
            } elseif ( $orderby === 'title' || $orderby === 'name' ) {
                $valueA = $a['name'] ?? '';
                $valueB = $b['name'] ?? '';
            } elseif ( $orderby === 'date' ) {
                $valueA = $a['date_created'] ?? '';
                $valueB = $b['date_created'] ?? '';
            }

            if ( is_numeric( $valueA ) && is_numeric( $valueB ) ) {
                $cmp = $valueA <=> $valueB;
            } else {
                $cmp = strcmp( (string) $valueA, (string) $valueB );
            }

            return ( $order === 'ASC' ) ? $cmp : -$cmp;
        } );

        return $products;
    }
}