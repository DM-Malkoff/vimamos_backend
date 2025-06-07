CREATE TABLE IF NOT EXISTS wp_product_similarities (
    product_id bigint(20) UNSIGNED NOT NULL,
    similar_product_id bigint(20) UNSIGNED NOT NULL,
    similarity_score float NOT NULL,
    PRIMARY KEY (product_id, similar_product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 