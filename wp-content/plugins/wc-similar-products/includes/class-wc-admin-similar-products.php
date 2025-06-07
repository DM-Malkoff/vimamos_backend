<?php

class WC_Admin_Similar_Products {
    
    private $batch_size = 1; // Обрабатываем по одному товару за раз
    
    public function __construct() {
        // Добавляем меню только если WooCommerce активен
        if ($this->is_woocommerce_active()) {
            add_action('admin_menu', array($this, 'add_admin_menu'), 99);
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            add_action('wp_ajax_recalculate_similarities_batch', array($this, 'ajax_recalculate_similarities_batch'));
        }
    }
    
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('woocommerce_page_wc-similar-products' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'wc-similar-products-admin',
            plugins_url('/assets/js/admin.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.1',
            true
        );
        
        wp_localize_script('wc-similar-products-admin', 'wcSimilarProducts', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_similar_products_nonce'),
            'processing_text' => __('Processing... %s%% complete', 'wc-similar-products'),
            'success_text' => __('Recalculation completed successfully!', 'wc-similar-products'),
            'error_text' => __('An error occurred. Please check error log for details.', 'wc-similar-products')
        ));
    }
    
    public function ajax_recalculate_similarities_batch() {
        try {
            check_ajax_referer('wc_similar_products_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                throw new Exception('Insufficient permissions');
            }
            
            // Увеличиваем лимит времени выполнения и памяти
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');
            
            global $wpdb;
            
            $batch = isset($_POST['batch']) ? intval($_POST['batch']) : 0;
            
            // В первом пакете очищаем таблицу
            if ($batch === 0) {
                $table_name = $wpdb->prefix . 'product_similarities';
                $wpdb->query("TRUNCATE TABLE {$table_name}");
                error_log('Similar products table truncated');
            }
            
            // Получаем общее количество товаров
            $total_products = $wpdb->get_var("
                SELECT COUNT(ID) 
                FROM {$wpdb->posts} 
                WHERE post_type = 'product' 
                AND post_status = 'publish'
            ");
            
            if ($total_products === null) {
                throw new Exception('Failed to get total products count');
            }
            
            // Получаем текущий товар
            $product = $wpdb->get_row($wpdb->prepare("
                SELECT ID, post_title 
                FROM {$wpdb->posts} 
                WHERE post_type = 'product' 
                AND post_status = 'publish'
                ORDER BY ID ASC
                LIMIT %d, 1
            ", $batch));
            
            if ($product) {
                error_log(sprintf('Processing product ID: %d (batch %d)', $product->ID, $batch));
                
                try {
                    // Обрабатываем товар
                    WC_Product_Similarity::get_instance()->update_product_similarities($product->ID);
                    
                    // Получаем дополнительную информацию о товаре
                    $wc_product = wc_get_product($product->ID);
                    $product_info = array(
                        'id' => $product->ID,
                        'title' => $product->post_title,
                        'edit_link' => get_edit_post_link($product->ID, 'raw'),
                        'view_link' => $wc_product ? $wc_product->get_permalink() : '',
                        'thumbnail' => $wc_product ? get_the_post_thumbnail_url($product->ID, 'thumbnail') : '',
                        'sku' => $wc_product ? $wc_product->get_sku() : '',
                        'price' => $wc_product ? $wc_product->get_price() : ''
                    );
                    
                    // Очищаем кэш
                    wp_cache_flush();
                    if (function_exists('wc_cache_helper')) {
                        wc_cache_helper()->get_transient_version('product', true);
                    }
                    
                    $processed = $batch + 1;
                    $percentage = min(round(($processed / $total_products) * 100), 100);
                    
                    error_log(sprintf('Completed processing product ID: %d. Progress: %d%%', $product->ID, $percentage));
                    
                    wp_send_json_success(array(
                        'processed' => $processed,
                        'total' => $total_products,
                        'percentage' => $percentage,
                        'complete' => $processed >= $total_products,
                        'product' => $product_info
                    ));
                } catch (Exception $e) {
                    error_log(sprintf('Error processing product ID %d: %s', $product->ID, $e->getMessage()));
                    throw $e;
                }
            } else {
                error_log('No more products to process');
                wp_send_json_success(array(
                    'complete' => true,
                    'percentage' => 100
                ));
            }
            
        } catch (Exception $e) {
            error_log('Error in recalculate_similarities_batch: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function add_admin_menu() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        add_submenu_page(
            'woocommerce',
            __('Similar Products', 'wc-similar-products'),
            __('Similar Products', 'wc-similar-products'),
            'manage_woocommerce',
            'wc-similar-products',
            array($this, 'render_admin_page')
        );
    }
    
    public function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-similar-products'));
        }
        
        // Показываем сообщения об ошибках/успехе
        settings_errors('wc_similar_products');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Similar Products', 'wc-similar-products'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Recalculate Similar Products', 'wc-similar-products'); ?></h2>
                <p><?php _e('Click the button below to recalculate similar products for all products in your store.', 'wc-similar-products'); ?></p>
                <p><?php _e('This process may take some time depending on the number of products in your store.', 'wc-similar-products'); ?></p>
                <p><?php _e('The process will run in the background and can be safely interrupted if needed.', 'wc-similar-products'); ?></p>
                
                <div class="progress-wrapper" style="display: none;">
                    <div class="progress-bar" style="background-color: #f0f0f1; height: 20px; border: 1px solid #ccc; margin: 10px 0;">
                        <div class="progress" style="background-color: #2271b1; width: 0; height: 100%; transition: width 0.3s;"></div>
                    </div>
                    <p class="progress-status"></p>
                </div>
                
                <p>
                    <button type="button" id="recalculate-similarities" class="button button-primary">
                        <?php _e('Recalculate Similar Products', 'wc-similar-products'); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }
} 