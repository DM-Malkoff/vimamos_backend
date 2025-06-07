<?php

class WC_REST_Product_Similar_Controller {
    
    public function __construct() {
        // Добавляем фильтры для разных версий API
        add_filter('woocommerce_rest_prepare_product_object', array($this, 'add_similar_products_to_response'), 10, 3);
        add_filter('woocommerce_rest_prepare_product', array($this, 'add_similar_products_to_response'), 10, 3);
        
        // Регистрируем поле в API
        add_action('rest_api_init', array($this, 'register_rest_field'));
    }
    
    public function register_rest_field() {
        register_rest_field(
            'product',
            'similar_products',
            array(
                'get_callback' => array($this, 'get_similar_products_data'),
                'schema' => array(
                    'description' => __('Similar products.', 'woocommerce'),
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object'
                    ),
                    'context' => array('view', 'edit')
                )
            )
        );
    }
    
    public function get_similar_products_data($post, $field_name, $request) {
        $product_id = $post['id'];
        $similar_products = WC_Product_Similarity::get_instance()->get_similar_products($product_id, 10);
        
        if (empty($similar_products)) {
            return array();
        }
        
        $similar_data = array();
        foreach ($similar_products as $similar) {
            $product_data = $similar['product'];
            
            $similar_data[] = array(
                'id' => $product_data->get_id(),
                'name' => $product_data->get_name(),
                'slug' => $product_data->get_slug(),
                'permalink' => $product_data->get_permalink(),
                'price' => $product_data->get_price(),
                'regular_price' => $product_data->get_regular_price(),
                'sale_price' => $product_data->get_sale_price(),
                'image' => wp_get_attachment_url($product_data->get_image_id()),
                'similarity_score' => $similar['similarity_score']
            );
        }
        
        return $similar_data;
    }
    
    public function add_similar_products_to_response($response, $product, $request) {
        if (!($response instanceof WP_REST_Response)) {
            return $response;
        }

        $response_data = $response->get_data();
        
        // Если данные уже добавлены через register_rest_field, не добавляем их снова
        if (!isset($response_data['similar_products'])) {
            $similar_products = WC_Product_Similarity::get_instance()->get_similar_products($product->get_id(), 10);
            
            if (!empty($similar_products)) {
                $similar_data = array();
                
                foreach ($similar_products as $similar) {
                    $product_data = $similar['product'];
                    
                    $similar_data[] = array(
                        'id' => $product_data->get_id(),
                        'name' => $product_data->get_name(),
                        'slug' => $product_data->get_slug(),
                        'permalink' => $product_data->get_permalink(),
                        'price' => $product_data->get_price(),
                        'regular_price' => $product_data->get_regular_price(),
                        'sale_price' => $product_data->get_sale_price(),
                        'image' => wp_get_attachment_url($product_data->get_image_id()),
                        'similarity_score' => $similar['similarity_score']
                    );
                }
                
                $response_data['similar_products'] = $similar_data;
                $response->set_data($response_data);
            }
        }
        
        return $response;
    }
}

// Создаем экземпляр класса
function init_wc_rest_product_similar_controller() {
    new WC_REST_Product_Similar_Controller();
}

// Инициализация через хук plugins_loaded для уверенности, что WooCommerce уже загружен
add_action('plugins_loaded', 'init_wc_rest_product_similar_controller'); 