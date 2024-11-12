<?php

class LoyaltyProgramDiscounts
{
    const ATTRIBUTE = 'weight';
    const VAR_1 = '200gr';
    const VAR_2 = '1kg';

    public function __construct()
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'add_loyalty_program_tab'], 99);
        add_action('woocommerce_product_data_panels', [$this, 'loyalty_program_tab_content']);
        add_action('woocommerce_process_product_meta', [$this, 'save_loyalty_discount_fields']);
        add_action('admin_menu', [$this, 'add_submenu'], 99);
        add_action('admin_post_save_loyalty_discounts', [$this, 'save_loyalty_discounts']);
    }

    public function add_loyalty_program_tab($tabs)
    {
        $tabs['loyalty_program'] = [
            'label' => __('Loyalty Program Discounts', 'textdomain'),
            'target' => 'loyalty_program_discounts_data',
            'priority' => 99,
        ];
        return $tabs;
    }

    public function loyalty_program_tab_content()
    {
        ?>
        <div id="loyalty_program_discounts_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h2><?php _e('Loyalty Program Discounts', 'textdomain'); ?></h2>

                <div class="is_simple">
                    <h4><?php _e('Common Product Discounts', 'textdomain'); ?></h4>
                    <?php for ($i = 1; $i <= 3; $i++) : ?>
                        <?php woocommerce_wp_text_input([
                            'id' => "_lp_discount_common_product_{$i}",
                            'label' => __("Discount Level $i", 'textdomain'),
                            'type' => 'number',
                            'custom_attributes' => ['step' => '1', 'min' => '0'],
                        ]); ?>
                    <?php endfor; ?>
                </div>

                <div class="is_variable">
                    <h4><?php _e('Variable Product Discounts', 'textdomain'); ?></h4>
                    <?php for ($i = 1; $i <= 3; $i++) : ?>
                        <div>
                            <label><?php echo self::VAR_1; ?></label>
                            <input type="number" name="lp_variative_product[<?php echo esc_attr($i); ?>][200gr]" step="1" class="small-text" />
                        </div>
                        <div>
                            <label><?php echo self::VAR_2; ?></label>
                            <input type="number" name="lp_variative_product[<?php echo esc_attr($i); ?>][1kg]" step="1" class="small-text" />
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <script>
            jQuery(document).ready(function ($) {
                function toggleDiscountFields() {
                    if ($('#product-type').val() === 'variable') {
                        $('.is_simple').hide();
                        $('.is_variable').show();
                    } else {
                        $('.is_simple').show();
                        $('.is_variable').hide();
                    }
                }
                toggleDiscountFields();
                $('#product-type').change(toggleDiscountFields);
            });
        </script>
        <?php
    }

    public function save_loyalty_discount_fields($post_id)
    {
        for ($i = 1; $i <= 3; $i++) {
            $common_discount = sanitize_text_field($_POST["_lp_discount_common_product_{$i}"] ?? '');
            update_post_meta($post_id, "_lp_discount_common_product_{$i}", $common_discount);

            $value200 = sanitize_text_field($_POST['lp_variative_product'][$i]['200gr'] ?? '');
            update_post_meta($post_id, "_lp_discount_variative_product_{$i}_200", $value200);

            $value1000 = sanitize_text_field($_POST['lp_variative_product'][$i]['1kg'] ?? '');
            update_post_meta($post_id, "_lp_discount_variative_product_{$i}_1000", $value1000);
        }
    }

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

    public function lp_submenu_page()
    {
        ?>
        <h1><?php _e('Manage discounts for all products below:', 'textdomain'); ?></h1>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="save_loyalty_discounts">
            <?php $this->render_product_discounts(); ?>
            <p><button type="submit" class="button button-primary"><?php _e('Save Changes', 'textdomain'); ?></button></p>
        </form>
        <?php
    }

    public function render_product_discounts()
    {
        $cat_slugs = ['filtr', 'espresso'];
        $products = wc_get_products([
            'limit' => -1,
            'category' => $cat_slugs
        ]);

        ?>
        <div class="wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-title"><?php _e('Product', 'textdomain') ?></th>
                        <th scope="col" class="manage-column column-level-1"><?php _e('Level 1', 'textdomain') ?></th>
                        <th scope="col" class="manage-column column-level-2"><?php _e('Level 2', 'textdomain') ?></th>
                        <th scope="col" class="manage-column column-level-3"><?php _e('Level 3', 'textdomain') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <?php
                            $product_id = $product->get_id();
                            echo '<td class="title column-title">' . esc_html($product->get_name()) . '</td>';

                            if ($product->is_type('variable')) {
                                for ($i = 1; $i <= 3; $i++) {
                                    $value200 = get_post_meta($product_id, "_lp_discount_variative_product_{$i}_200", true);
                                    $value1000 = get_post_meta($product_id, "_lp_discount_variative_product_{$i}_1000", true);
                                    echo '<td class="column-level-' . esc_attr($i) . '">';
                                    echo '<div><label style="width: 60px; display: inline-block">200gr</label>';
                                    echo '<input type="number" name="lp_variative_product[' . esc_attr($product_id) . '][' . $i . '][200gr]" value="' . esc_attr($value200) . '" step="1" class="small-text" /></div>';
                                    echo '<div><label style="width: 60px; display: inline-block">1kg</label>';
                                    echo '<input type="number" name="lp_variative_product[' . esc_attr($product_id) . '][' . $i . '][1kg]" value="' . esc_attr($value1000) . '" step="1" class="small-text" /></div>';
                                    echo '</td>';
                                }
                            } else {
                                for ($i = 1; $i <= 3; $i++) {
                                    $value = get_post_meta($product_id, "_lp_discount_common_product_{$i}", true);
                                    echo '<td class="column-level-' . esc_attr($i) . '">';
                                    echo '<input type="number" name="lp_common_product[' . esc_attr($product_id) . '][' . $i . ']" value="' . esc_attr($value) . '" step="1" class="small-text" />';
                                    echo '</td>';
                                }
                            }
                            ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function save_loyalty_discounts()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.'));
        }

        if (isset($_POST['lp_common_product'])) {
            foreach ($_POST['lp_common_product'] as $product_id => $discounts) {
                foreach ($discounts as $level => $value) {
                    update_post_meta($product_id, "_lp_discount_common_product_{$level}", sanitize_text_field($value));
                }
            }
        }

        if (isset($_POST['lp_variative_product'])) {
            foreach ($_POST['lp_variative_product'] as $product_id => $discounts) {
                foreach ($discounts as $level => $discount_values) {
                    foreach ($discount_values as $var => $value) {
                        update_post_meta($product_id, "_lp_discount_variative_product_{$level}_{$var}", sanitize_text_field($value));
                    }
                }
            }
        }

        wp_redirect(admin_url('admin.php?page=lp-submenu-page&message=1'));
        exit;
    }
}

new LoyaltyProgramDiscounts();
?>
