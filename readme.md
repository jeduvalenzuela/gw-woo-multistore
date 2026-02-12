# WooCommerce Multistore Elementor Widget

Plugin de WordPress que permite mostrar productos de múltiples tiendas WooCommerce remotas en un loop unificado mediante un widget de Elementor Pro.

## Características

- ✅ Conexión a múltiples tiendas WooCommerce vía REST API
- ✅ Widget de Elementor con loop de productos unificado
- ✅ Paginación global entre todas las tiendas
- ✅ Opción de seleccionar una tienda específica o todas
- ✅ Sistema de caché con transients (5 minutos por defecto)
- ✅ Filtros por categoría, etiqueta, precio
- ✅ Ordenamiento por fecha, precio, título
- ✅ Diseño responsive con grid CSS
- ✅ Página de administración para gestionar tiendas

## Requisitos

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 4.0+
- Elementor Pro 3.0+

## Instalación

1. Sube la carpeta `woocommerce-multistore-elementor` a `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Ve a **Tiendas Remotas** en el menú de administración
4. Configura las tiendas remotas con sus credenciales de API

## Configuración de Tiendas

Para cada tienda remota necesitas:

- **ID**: Identificador único (ej: `store_1`)
- **Nombre**: Nombre descriptivo
- **URL Base**: URL de la tienda (ej: `https://tienda.com`)
- **Consumer Key**: Clave de consumidor de WooCommerce REST API
- **Consumer Secret**: Secreto de consumidor de WooCommerce REST API
- **Versión API**: Versión de la API (por defecto: `wc/v3`)

### Cómo obtener las credenciales de API

En cada tienda remota:
1. Ve a **WooCommerce > Ajustes > Avanzado > REST API**
2. Crea una nueva clave
3. Permisos: **Lectura**
4. Copia el Consumer Key y Consumer Secret

## Uso del Widget

1. Edita una página con Elementor
2. Busca el widget **Multistore Products Loop**
3. Configura:
   - Modo de selección (todas las tiendas o una específica)
   - Productos por página
   - Ordenamiento
   - Filtros (opcional)
   - Diseño (columnas, paginación)

## Caché

El plugin cachea las consultas durante 5 minutos por defecto. Puedes limpiar el caché desde **Tiendas Remotas > Herramientas > Limpiar Caché**.

Para cambiar el tiempo de caché, edita la constante `CACHE_TTL` en `class-msw-remote-products-service.php`.

## Desarrollo

### Estructura de archivos

gw-woo-multistore/
├── gw-woo-multistore.php (archivo principal)
├── includes/
│ ├── class-msw-loader.php (cargador principal)
│ ├── class-msw-settings.php (página de opciones)
│ ├── class-msw-remote-products-service.php (servicio de productos)
│ └── elementor/
│ └── class-msw-widget-products-loop.php (widget)
└── assets/
├── css/ (estilos)
└── js/ (scripts)

## Soporte

Para reportar bugs o solicitar funcionalidades, contacta a info@gavaweb.com.

## Licencia

GPL v2 or later