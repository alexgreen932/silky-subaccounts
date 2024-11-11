<?php

// namespace App\Services\WooCommerce;


class LoyaltyProgramDiscounts
{
    public function __construct()
    {
        // Add custom tab to product data panel
        add_filter('woocommerce_product_data_tabs', [$this, 'add_loyalty_program_tab'], 99);
        // Display content in the custom tab
        add_action('woocommerce_product_data_panels', [$this, 'loyalty_program_tab_content']);
        // Save custom fields
        add_action('woocommerce_process_product_meta', [$this, 'save_loyalty_discount_fields']);        
        // Add submenu for loyalty program management
        add_action('admin_menu', [$this, 'add_submenu'], 99);
    }

    // Add a custom tab to the product data panel
    public function add_loyalty_program_tab($tabs)
    {
        $tabs['loyalty_program'] = [
            'label'    => __('Loyalty Program Discounts', 'woocommerce'),
            'target'   => 'loyalty_program_discounts_data',
            'priority' => 99,
        ];
        return $tabs;
    }

    // Content for the Loyalty Program Discounts tab
    public function loyalty_program_tab_content()
    {
        global $post;
        ?>
        <div id="loyalty_program_discounts_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h2><?php _e('Loyalty Program Discounts', 'woocommerce'); ?></h2>

                <?php
                // Common product discounts
                echo '<h4>Common Product Discounts</h4>';
                for ($i = 1; $i <= 3; $i++) {
                    woocommerce_wp_text_input([
                        'id' => "_lp_discount_common_product_{$i}",
                        'label' => __("Discount Level $i", 'woocommerce'),
                        'desc_tip' => true,
                        'description' => __('Discount for common product', 'woocommerce'),
                        'type' => 'number',
                        'custom_attributes' => ['step' => '1', 'min' => '0']
                    ]);
                }

                // Variable product discounts
                echo '<h4>Variable Product Discounts</h4>';
                for ($i = 1; $i <= 3; $i++) {
                    woocommerce_wp_text_input([
                        'id' => "_lp_discount_variative_product_{$i}",
                        'label' => __("Discount Level $i", 'woocommerce'),
                        'desc_tip' => true,
                        'description' => __('Discount for each variation', 'woocommerce'),
                        'type' => 'number',
                        'custom_attributes' => ['step' => '1', 'min' => '0']
                    ]);
                }
                ?>
            </div>
        </div>
        <?php
    }

    // Save custom discount fields when the product is saved
    public function save_loyalty_discount_fields($post_id)
    {
        for ($i = 1; $i <= 3; $i++) {
            // Save common product discount fields
            $common_discount = isset($_POST["_lp_discount_common_product_{$i}"]) ? sanitize_text_field($_POST["_lp_discount_common_product_{$i}"]) : '';
            update_post_meta($post_id, "_lp_discount_common_product_{$i}", $common_discount);

            // Save variable product discount fields
            $variative_discount = isset($_POST["_lp_discount_variative_product_{$i}"]) ? sanitize_text_field($_POST["_lp_discount_variative_product_{$i}"]) : '';
            update_post_meta($post_id, "_lp_discount_variative_product_{$i}", $variative_discount);
        }
    }

    // Add submenu page for mass changing discounts
    public function add_submenu()
    {
        add_submenu_page(
            'woocommerce', // WooCommerce submenu page
            'Loyalty Program', // Page title
            'Loyalty Program', // Menu title
            'manage_options', // Capability required to access the page
            'lp-submenu-page', // Page slug
            [$this, 'lp_submenu_page'] // Callback function to render the page
        );

        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
    }

    // Placeholder for registering settings (to prevent the fatal error)
    public function register_settings()
    {
        // Settings registration code would go here if needed
    }

    // Display the Loyalty Program discount management page
    public function lp_submenu_page()
    {
        ?>
        <h1>Discounts</h1>
        <p>Manage discounts for all products below:</p>
        <?php
        // Display list of products with discount fields for each
        $this->render_product_discounts();
    }

    // Display discounts for each product in the submenu
    public function render_product_discounts()
    {
        $products = wc_get_products(['limit' => -1]); // Get all products

        foreach ($products as $product) {
            echo '<h2>' . esc_html($product->get_name()) . '</h2>';
            // Render fields depending on product type
            if ($product->is_type('variable')) {
                echo '<h4>Variable Product Discounts</h4>';
                for ($i = 1; $i <= 3; $i++) {
                    echo '<input type="number" name="lp_variative_product[' . esc_attr($product->get_id()) . '][' . $i . ']" value="0" step="1" />';
                }
            } else {
                echo '<h4>Common Product Discounts</h4>';
                for ($i = 1; $i <= 3; $i++) {
                    echo '<input type="number" name="lp_common_product[' . esc_attr($product->get_id()) . '][' . $i . ']" value="0" step="1" />';
                }
            }
        }
    }
}

new LoyaltyProgramDiscounts();



