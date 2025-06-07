<?php

class WC_Product_Similarity {
    
    private static $instance = null;
    private $batch_size = 2;
    private $max_similar_products = 5; // Ограничиваем количество похожих товаров
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Обновляем похожие товары при сохранении товара
        add_action('woocommerce_update_product', array($this, 'update_product_similarities'), 10, 1);
        add_action('woocommerce_create_product', array($this, 'update_product_similarities'), 10, 1);
    }
    
    public function update_product_similarities($product_id) {
        try {
            global $wpdb;
            
            error_log("Starting update_product_similarities for product ID: " . $product_id);
            
            $current_product = wc_get_product($product_id);
            if (!$current_product) {
                error_log("Product not found: " . $product_id);
                return;
            }
            
            // Получаем базовые данные текущего товара
            $current_data = array(
                'categories' => $current_product->get_category_ids(),
                'tags' => $current_product->get_tag_ids(),
                'price' => (float)$current_product->get_price(),
                'attributes' => array()
            );
            
            // Получаем атрибуты более эффективным способом
            $attributes = $current_product->get_attributes();
            foreach ($attributes as $attribute) {
                if (is_object($attribute)) {
                    $current_data['attributes'][] = $attribute->get_name();
                }
            }
            
            error_log("Current product data retrieved successfully");
            
            // Удаляем старые записи
            $table_name = $wpdb->prefix . 'product_similarities';
            $wpdb->delete($table_name, array('product_id' => $product_id));
            
            // Получаем ID других товаров порциями
            $offset = 0;
            $limit = 50;
            
            do {
                $other_products = $wpdb->get_col($wpdb->prepare("
                    SELECT ID 
                    FROM {$wpdb->posts} 
                    WHERE post_type = 'product' 
                    AND post_status = 'publish' 
                    AND ID != %d
                    LIMIT %d OFFSET %d
                ", $product_id, $limit, $offset));
                
                if (empty($other_products)) {
                    break;
                }
                
                error_log(sprintf("Processing batch of %d products, offset: %d", count($other_products), $offset));
                
                $similarities = array();
                
                foreach ($other_products as $other_id) {
                    $other_product = wc_get_product($other_id);
                    if (!$other_product) {
                        continue;
                    }
                    
                    // Получаем данные сравниваемого товара
                    $other_data = array(
                        'categories' => $other_product->get_category_ids(),
                        'tags' => $other_product->get_tag_ids(),
                        'price' => (float)$other_product->get_price(),
                        'attributes' => array()
                    );
                    
                    $other_attributes = $other_product->get_attributes();
                    foreach ($other_attributes as $attribute) {
                        if (is_object($attribute)) {
                            $other_data['attributes'][] = $attribute->get_name();
                        }
                    }
                    
                    // Вычисляем схожесть
                    $score = $this->calculate_similarity_score($current_data, $other_data);
                    
                    if ($score > 0) {
                        $similarities[] = array(
                            'product_id' => $other_id,
                            'score' => $score
                        );
                    }
                    
                    // Очищаем объект товара
                    unset($other_product);
                }
                
                // Сортируем и берем только лучшие совпадения
                usort($similarities, function($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                
                $similarities = array_slice($similarities, 0, $this->max_similar_products);
                
                // Сохраняем результаты
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
                
                $offset += $limit;
                
                // Очищаем память
                wp_cache_flush();
                if (function_exists('wc_cache_helper')) {
                    wc_cache_helper()->get_transient_version('product', true);
                }
                
            } while (count($other_products) === $limit);
            
            error_log("Completed update_product_similarities for product ID: " . $product_id);
            return true;
            
        } catch (Exception $e) {
            error_log("Error in update_product_similarities: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
    
    public function get_similar_products($product_id, $limit = 5) {
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
        global $wpdb;
        
        // Очищаем таблицу перед пересчетом
        $table_name = $wpdb->prefix . 'product_similarities';
        $wpdb->query("TRUNCATE TABLE {$table_name}");
        
        // Получаем все ID товаров
        $product_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish'
        ");
        
        if (empty($product_ids)) {
            return;
        }
        
        // Обрабатываем товары пакетами
        $total_products = count($product_ids);
        $processed = 0;
        
        for ($i = 0; $i < $total_products; $i += $this->batch_size) {
            $batch = array_slice($product_ids, $i, $this->batch_size);
            
            foreach ($batch as $product_id) {
                $this->process_single_product($product_id, $product_ids);
                $processed++;
                
                // Очищаем кэш WooCommerce и WordPress
                if ($processed % 10 === 0) {
                    WC_Cache_Helper::get_transient_version('product', true);
                    wp_cache_flush();
                }
            }
            
            // Принудительно очищаем память после каждого пакета
            $this->clean_up_memory();
        }
    }
    
    private function process_single_product($product_id, $all_product_ids) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'product_similarities';
        
        $current_product = wc_get_product($product_id);
        if (!$current_product) {
            return;
        }
        
        // Получаем данные текущего товара
        $current_data = $this->get_product_comparison_data($current_product);
        
        $similarities = array();
        
        // Обрабатываем другие товары пакетами
        $batch_size = 50;
        for ($i = 0; $i < count($all_product_ids); $i += $batch_size) {
            $batch_ids = array_slice($all_product_ids, $i, $batch_size);
            
            foreach ($batch_ids as $compare_id) {
                if ($compare_id === $product_id) {
                    continue;
                }
                
                $compare_product = wc_get_product($compare_id);
                if (!$compare_product) {
                    continue;
                }
                
                $compare_data = $this->get_product_comparison_data($compare_product);
                $score = $this->calculate_similarity_score($current_data, $compare_data);
                
                if ($score > 0) {
                    $wpdb->insert(
                        $table_name,
                        array(
                            'product_id' => $product_id,
                            'similar_product_id' => $compare_id,
                            'similarity_score' => $score
                        ),
                        array('%d', '%d', '%f')
                    );
                }
                
                // Очищаем объект товара из памяти
                unset($compare_product);
            }
            
            // Очищаем память после каждого пакета сравнений
            $this->clean_up_memory();
        }
        
        // Очищаем объект текущего товара из памяти
        unset($current_product);
    }
    
    private function get_product_comparison_data($product) {
        return array(
            'categories' => $product->get_category_ids(),
            'tags' => $product->get_tag_ids(),
            'price' => (float)$product->get_price(),
            'attributes' => $product->get_attributes()
        );
    }
    
    private function calculate_similarity_score($data1, $data2) {
        $score = 0;
        
        // Сравнение категорий (30%)
        if (!empty($data1['categories']) && !empty($data2['categories'])) {
            $common_categories = array_intersect($data1['categories'], $data2['categories']);
            $category_score = count($common_categories) / 
                max(count($data1['categories']), count($data2['categories']));
            $score += $category_score * 0.3;
        }
        
        // Сравнение цен (20%)
        if ($data1['price'] > 0 && $data2['price'] > 0) {
            $price_diff = abs($data1['price'] - $data2['price']) / max($data1['price'], $data2['price']);
            $price_score = 1 - min($price_diff, 1);
            $score += $price_score * 0.2;
        }
        
        // Сравнение атрибутов (30%)
        if (!empty($data1['attributes']) && !empty($data2['attributes'])) {
            $common_attributes = array_intersect($data1['attributes'], $data2['attributes']);
            $attribute_score = count($common_attributes) / 
                max(count($data1['attributes']), count($data2['attributes']));
            $score += $attribute_score * 0.3;
        }
        
        // Сравнение тегов (20%)
        if (!empty($data1['tags']) && !empty($data2['tags'])) {
            $common_tags = array_intersect($data1['tags'], $data2['tags']);
            $tag_score = count($common_tags) / 
                max(count($data1['tags']), count($data2['tags']));
            $score += $tag_score * 0.2;
        }
        
        return $score;
    }
    
    private function clean_up_memory() {
        global $wpdb;
        
        // Очищаем кэш запросов WordPress
        $wpdb->queries = array();
        
        // Очищаем внутренний кэш WooCommerce
        WC_Cache_Helper::get_transient_version('product', true);
        
        // Очищаем кэш объектов WordPress
        wp_cache_flush();
        
        // Принудительно очищаем неиспользуемую память
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
} 