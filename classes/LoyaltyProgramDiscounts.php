<?php 

/**
 * Summary of LoyaltyProgramDiscounts
 */
class LoyaltyProgramDiscounts
{
    public function __construct()
    {
        // Add custom tab to product data panel
        add_filter('woocommerce_product_data_tabs', [$this, 'add_loyalty_program_tab'], 99);
        // Display content in the custom tab
        add_action('woocommerce_product_data_panels', [$this, 'loyalty_program_tab_content']);
        // Save custom fields in product page
        add_action('woocommerce_process_product_meta', [$this, 'save_loyalty_discount_fields']);
        // Add submenu for loyalty program management
        add_action('admin_menu', [$this, 'add_submenu'], 99);
        // Handle save action in submenu
        add_action('admin_post_save_loyalty_discounts', [$this, 'save_loyalty_discounts']);
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

    // Save custom discount fields in the product page
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

    // Add submenu page for mass editing discounts
    public function add_submenu()
    {
        add_submenu_page(
            'woocommerce',
            'Loyalty Program',
            'Loyalty Program',
            'manage_options',
            'lp-submenu-page',
            [$this, 'lp_submenu_page']
        );
    }

    // Display the Loyalty Program discount management page
    public function lp_submenu_page()
    {
        ?>
        <h1>Discounts</h1>
        <p>Manage discounts for all products below:</p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="save_loyalty_discounts">
            <?php
            // Display product discounts for editing
            $this->render_product_discounts();
            ?>
            <p>
                <button type="submit" class="button button-primary">Save Changes</button>
            </p>
        </form>
        <?php
    }

    // Display discounts for each product in the submenu
    public function render_product_discounts()
    {
        $products = wc_get_products(['limit' => -1]); // Get all products

        foreach ($products as $product) {
            echo '<h2>' . esc_html($product->get_name()) . '</h2>';
            $product_id = $product->get_id();

            if ($product->is_type('variable')) {
                echo '<h4>Variable Product Discounts</h4>';
                for ($i = 1; $i <= 3; $i++) {
                    $value = get_post_meta($product_id, "_lp_discount_variative_product_{$i}", true);
                    echo '<input type="number" name="lp_variative_product[' . esc_attr($product_id) . '][' . $i . ']" value="' . esc_attr($value) . '" step="1" />';
                }
            } else {
                echo '<h4>Common Product Discounts</h4>';
                for ($i = 1; $i <= 3; $i++) {
                    $value = get_post_meta($product_id, "_lp_discount_common_product_{$i}", true);
                    echo '<input type="number" name="lp_common_product[' . esc_attr($product_id) . '][' . $i . ']" value="' . esc_attr($value) . '" step="1" />';
                }
            }
        }
    }

    // Handle saving loyalty discounts from the mass edit submenu
    public function save_loyalty_discounts()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.'));
        }

        // Check if discount values are set, then save them
        if (isset($_POST['lp_common_product'])) {
            foreach ($_POST['lp_common_product'] as $product_id => $discounts) {
                foreach ($discounts as $level => $value) {
                    update_post_meta($product_id, "_lp_discount_common_product_{$level}", sanitize_text_field($value));
                }
            }
        }

        if (isset($_POST['lp_variative_product'])) {
            foreach ($_POST['lp_variative_product'] as $product_id => $discounts) {
                foreach ($discounts as $level => $value) {
                    update_post_meta($product_id, "_lp_discount_variative_product_{$level}", sanitize_text_field($value));
                }
            }
        }

        // Redirect back to the submenu page after saving
        wp_redirect(admin_url('admin.php?page=lp-submenu-page&message=1'));
        exit;
    }
}

new LoyaltyProgramDiscounts();
