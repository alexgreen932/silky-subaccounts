<?php

namespace SilkyDrum\WooCommerce;
class LoyaltyProgramDiscounts
{
    const ATTRIBUTE = 'weight';
    const VAR_1 = '200gr';
    const VAR_2 = '1kg';
    const DISCOUNT_CATEGORIES = ['filtr', 'espresso'];
    const LEVELS = [
        1 => ['orders' => 'from 9', 'duration' => '4 months'],
        2 => ['orders' => 'from 17', 'duration' => '9 months'],
        3 => ['orders' => 'from 25', 'duration' => '12 months']
    ];

    // Default discounts if none are saved
    protected $default_discounts = [
        1 => [6, 30],
        2 => [13, 60],
        3 => [24, 100]
    ];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_submenu'], 99);
        add_action('admin_post_save_loyalty_discounts', [$this, 'save_loyalty_discounts']);
    }

    public function getDiscountCategories()
    {
        return self::DISCOUNT_CATEGORIES;
    }
    public function add_submenu()
    {
        add_submenu_page(
            'themes.php',               // Parent menu slug (Appearance)
            'Loyalty Program',           // Page title
            'Loyalty Program',           // Menu title
            'manage_options',            // Capability
            'lp-submenu-page',           // Menu slug
            [$this, 'lp_submenu_page']   // Callback function
        );
    }
    

    public function lp_submenu_page()
    {
        ?>
        <h1><?php _e('Manage discounts for loyalty program:', 'textdomain'); ?></h1>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="save_loyalty_discounts">
            <?php echo $this->renderDiscountTable(); ?>
            <p><button type="submit" class="button button-primary"><?php _e('Save Changes', 'textdomain'); ?></button></p>
        </form>
        <?php
    }

    /**
     * Render the discount table with editable fields.
     *
     * @return string
     */
    public function renderDiscountTable()
    {
        ob_start();
        ?>
        <div class="wrap">
            <h2><?php _e('Loyalty Program Discount Table', 'textdomain'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Level', 'textdomain'); ?></th>
                        <th><?php _e('Orders', 'textdomain'); ?></th>
                        <th><?php _e('Duration', 'textdomain'); ?></th>
                        <?php foreach (self::DISCOUNT_CATEGORIES as $category): ?>
                            <th><?php echo ucfirst($category) . ' ' . self::VAR_1; ?></th>
                            <th><?php echo ucfirst($category) . ' ' . self::VAR_2; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (self::LEVELS as $level => $terms): ?>
                        <tr>
                            <td><?php echo sprintf(__('Level %d', 'textdomain'), $level); ?></td>
                            <td><?php echo $terms['orders']; ?></td>
                            <td><?php echo $terms['duration']; ?></td>
                            <?php foreach (self::DISCOUNT_CATEGORIES as $category): ?>
                                <?php
                                // Retrieve saved values, or use defaults if none are found
                                $var_1_discount = get_option("_lp_discount_{$category}_" . self::VAR_1 . "_level_$level", $this->default_discounts[$level][0]);
                                $var_2_discount = get_option("_lp_discount_{$category}_" . self::VAR_2 . "_level_$level", $this->default_discounts[$level][1]);
                                ?>
                                <td>
                                    <input type="number" name="discounts[<?php echo $category; ?>][<?php echo self::VAR_1; ?>][<?php echo $level; ?>]" value="<?php echo esc_attr($var_1_discount); ?>" class="small-text" />%
                                </td>
                                <td>
                                    <input type="number" name="discounts[<?php echo $category; ?>][<?php echo self::VAR_2; ?>][<?php echo $level; ?>]" value="<?php echo esc_attr($var_2_discount); ?>" class="small-text" />%
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Save loyalty discount values.
     */
    public function save_loyalty_discounts()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.'));
        }

        if (isset($_POST['discounts']) && is_array($_POST['discounts'])) {
            foreach ($_POST['discounts'] as $category => $weights) {
                foreach ($weights as $weight => $levels) {
                    foreach ($levels as $level => $value) {
                        $meta_key = "_lp_discount_{$category}_{$weight}_level_{$level}";
                        update_option($meta_key, sanitize_text_field($value));
                    }
                }
            }
        }

        wp_redirect(admin_url('admin.php?page=lp-submenu-page&message=1'));
        exit;
    }

    function deb(){
        // return ;
    }
}

new LoyaltyProgramDiscounts();
