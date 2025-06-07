<?php

class WC_Shortcode_Similar_Products {
    
    public static function init() {
        add_shortcode('woocommerce_similar_products', array(__CLASS__, 'similar_products'));
    }
    
    public static function similar_products($atts) {
        if (!is_singular('product')) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'limit' => 4,
            'columns' => 4,
            'orderby' => 'similarity',
            'order' => 'desc'
        ), $atts, 'woocommerce_similar_products');
        
        $product_id = get_the_ID();
        
        // Получаем похожие товары
        $similar_products = WC_Product_Similarity::get_instance()->get_similar_products($product_id, $atts['limit']);
        
        if (empty($similar_products)) {
            return '';
        }
        
        $products = array_map(function($item) {
            return $item['product'];
        }, $similar_products);
        
        ob_start();
        
        wc_get_template('single-product/similar-products.php', array(
            'similar_products' => $products,
            'columns' => $atts['columns'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order']
        ));
        
        return ob_get_clean();
    }
}

WC_Shortcode_Similar_Products::init(); 