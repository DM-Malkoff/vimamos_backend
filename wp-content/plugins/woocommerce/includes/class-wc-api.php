<?php
/**
 * WooCommerce API
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_API class.
 */
class WC_API {

    /**
     * Setup class.
     */
    public function __construct() {
        // API endpoints.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        // Подключаем наш контроллер для похожих товаров
        require_once dirname( __FILE__ ) . '/api/class-wc-rest-product-similar-controller.php';
        
        // Остальные контроллеры WooCommerce...
    }
} 