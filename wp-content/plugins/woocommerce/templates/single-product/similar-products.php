<?php
/**
 * Similar Products Template
 */

if (!defined('ABSPATH')) {
    exit;
}

if ($similar_products) : ?>

    <section class="similar-products">
        
        <?php
        $heading = apply_filters('woocommerce_similar_products_heading', __('Similar Products', 'woocommerce'));

        if ($heading) :
            ?>
            <h2><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>

        <?php woocommerce_product_loop_start(); ?>

            <?php foreach ($similar_products as $similar_product) : ?>

                <?php
                $post_object = get_post($similar_product->get_id());
                setup_postdata($GLOBALS['post'] =& $post_object);
                wc_get_template_part('content', 'product');
                ?>

            <?php endforeach; ?>

        <?php woocommerce_product_loop_end(); ?>

    </section>

<?php
endif;

wp_reset_postdata(); 