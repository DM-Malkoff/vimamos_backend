<?php

class WC_Admin_Similar_Products {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_recalculate'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Similar Products', 'woocommerce'),
            __('Similar Products', 'woocommerce'),
            'manage_woocommerce',
            'wc-similar-products',
            array($this, 'render_admin_page')
        );
    }
    
    public function handle_recalculate() {
        if (
            isset($_POST['wc_recalculate_similarities']) &&
            isset($_POST['_wpnonce']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'wc_recalculate_similarities')
        ) {
            WC_Product_Similarity::get_instance()->recalculate_all_similarities();
            WC_Admin_Notices::add_notice(
                __('Similar products have been recalculated.', 'woocommerce'),
                'success'
            );
        }
    }
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Similar Products', 'woocommerce'); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('wc_recalculate_similarities'); ?>
                <p><?php _e('Click the button below to recalculate similar products for all products in your store.', 'woocommerce'); ?></p>
                <p><?php _e('This process may take some time depending on the number of products in your store.', 'woocommerce'); ?></p>
                <p>
                    <button type="submit" name="wc_recalculate_similarities" class="button button-primary">
                        <?php _e('Recalculate Similar Products', 'woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
}

new WC_Admin_Similar_Products(); 