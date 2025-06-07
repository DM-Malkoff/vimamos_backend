<?php
require_once('wp-load.php');

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

// Проверяем создание таблицы
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
if ($table_exists) {
    echo "Table created successfully!\n";
    
    // Инициализируем класс для работы с похожими товарами
    require_once(ABSPATH . 'wp-content/plugins/woocommerce/includes/class-wc-product-similarity.php');
    
    // Пересчитываем похожие товары
    WC_Product_Similarity::get_instance()->recalculate_all_similarities();
    echo "Similarities recalculated!\n";
} else {
    echo "Failed to create table!\n";
} 