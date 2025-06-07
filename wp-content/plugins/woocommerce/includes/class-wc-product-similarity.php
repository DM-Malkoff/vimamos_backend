<?php

class WC_Product_Similarity {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Создаем таблицу при активации
        add_action('init', array($this, 'create_similarities_table'));
        
        // Обновляем похожие товары при сохранении товара
        add_action('woocommerce_update_product', array($this, 'update_product_similarities'), 10, 1);
        add_action('woocommerce_create_product', array($this, 'update_product_similarities'), 10, 1);
    }
    
    public function create_similarities_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'product_similarities';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            product_id bigint(20) UNSIGNED NOT NULL,
            similar_product_id bigint(20) UNSIGNED NOT NULL,
            similarity_score float NOT NULL,
            PRIMARY KEY  (product_id, similar_product_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function update_product_similarities($product_id) {
        global $wpdb;
        
        // Получаем все товары кроме текущего
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'exclude' => array($product_id)
        ));
        
        $similarities = array();
        $current_product = wc_get_product($product_id);
        
        if (!$current_product) return;
        
        // Получаем данные текущего товара
        $current_categories = $current_product->get_category_ids();
        $current_tags = $current_product->get_tag_ids();
        $current_price = (float)$current_product->get_price();
        $current_attributes = $current_product->get_attributes();
        
        foreach ($products as $product) {
            $score = 0;
            
            // Сравнение категорий (30%)
            $product_categories = $product->get_category_ids();
            $common_categories = array_intersect($current_categories, $product_categories);
            $category_score = count($common_categories) ? 
                count($common_categories) / max(count($current_categories), count($product_categories)) : 0;
            $score += $category_score * 0.3;
            
            // Сравнение цен (20%)
            $product_price = (float)$product->get_price();
            if ($current_price > 0 && $product_price > 0) {
                $price_diff = abs($current_price - $product_price) / max($current_price, $product_price);
                $price_score = 1 - min($price_diff, 1);
                $score += $price_score * 0.2;
            }
            
            // Сравнение атрибутов (30%)
            $product_attributes = $product->get_attributes();
            $common_attributes = 0;
            $total_attributes = count($current_attributes) + count($product_attributes);
            
            if ($total_attributes > 0) {
                foreach ($current_attributes as $key => $attr) {
                    if (isset($product_attributes[$key])) {
                        $common_attributes++;
                    }
                }
                $attribute_score = $common_attributes / ($total_attributes / 2);
                $score += $attribute_score * 0.3;
            }
            
            // Сравнение тегов (20%)
            $product_tags = $product->get_tag_ids();
            $common_tags = array_intersect($current_tags, $product_tags);
            $tag_score = count($common_tags) ? 
                count($common_tags) / max(count($current_tags), count($product_tags)) : 0;
            $score += $tag_score * 0.2;
            
            $similarities[] = array(
                'product_id' => $product->get_id(),
                'score' => $score
            );
        }
        
        // Сортируем по убыванию схожести
        usort($similarities, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Берем только топ-10 похожих товаров
        $similarities = array_slice($similarities, 0, 10);
        
        $table_name = $wpdb->prefix . 'product_similarities';
        
        // Удаляем старые записи для текущего товара
        $wpdb->delete($table_name, array('product_id' => $product_id));
        
        // Добавляем новые записи
        foreach ($similarities as $similar) {
            $wpdb->insert(
                $table_name,
                array(
                    'product_id' => $product_id,
                    'similar_product_id' => $similar['product_id'],
                    'similarity_score' => $similar['score']
                ),
                array('%d', '%d', '%f')
            );
        }
    }
    
    public function get_similar_products($product_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'product_similarities';
        
        $similar_products = $wpdb->get_results($wpdb->prepare(
            "SELECT similar_product_id, similarity_score 
            FROM $table_name 
            WHERE product_id = %d 
            ORDER BY similarity_score DESC 
            LIMIT %d",
            $product_id,
            $limit
        ));
        
        $products = array();
        foreach ($similar_products as $similar) {
            $product = wc_get_product($similar->similar_product_id);
            if ($product && $product->is_visible()) {
                $products[] = array(
                    'product' => $product,
                    'similarity_score' => $similar->similarity_score
                );
            }
        }
        
        return $products;
    }
    
    public function recalculate_all_similarities() {
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1
        ));
        
        foreach ($products as $product) {
            $this->update_product_similarities($product->get_id());
        }
    }
}

// Инициализация
add_action('plugins_loaded', array('WC_Product_Similarity', 'get_instance')); 