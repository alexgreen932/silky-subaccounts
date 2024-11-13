<?php

class LoyaltyProgramDiscounts
{
    const DISCOUNT_CATEGORIES = ['filtr', 'espresso'];
    const LEVELS = [
        1 => ['orders' => 'from 9', 'duration' => '4 months'],
        2 => ['orders' => 'from 17', 'duration' => '9 months'],
        3 => ['orders' => 'from 25', 'duration' => '12 months']
    ];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_submenu'], 99);
        add_action('admin_post_save_loyalty_discounts', [$this, 'save_loyalty_discounts']);
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
        <h1><?php _e('Manage discounts for loyalty program:', 'textdomain'); ?></h1>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="save_loyalty_discounts">
            <?php $this->render_discount_table(); ?>
            <p><button type="submit" class="button button-primary"><?php _e('Save Changes', 'textdomain'); ?></button></p>
        </form>
        <?php
    }

    public function render_discount_table()
    {
        ?>
        <div class="wrap">
            <table id="lp-table" cellspacing="2" cellpadding="2" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td rowspan="2"><?php _e('Level', 'textdomain'); ?></td>
                        <td colspan="2"><?php _e('Terms', 'textdomain'); ?></td>
                        <?php foreach (self::DISCOUNT_CATEGORIES as $category): ?>
                            <td colspan="2"><?php echo ucfirst($category); ?> <?php _e('Discount', 'textdomain'); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><?php _e('Orders', 'textdomain'); ?></td>
                        <td><?php _e('Duration', 'textdomain'); ?></td>
                        <?php foreach (self::DISCOUNT_CATEGORIES as $category): ?>
                            <td><?php _e('200gr', 'textdomain'); ?></td>
                            <td><?php _e('1kg', 'textdomain'); ?></td>
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
                                $meta_key_200gr = "_lp_discount_{$category}_200gr_{$level}";
                                $meta_key_1kg = "_lp_discount_{$category}_1kg_{$level}";
                                $value_200gr = get_option($meta_key_200gr, '');
                                $value_1kg = get_option($meta_key_1kg, '');
                                ?>
                                <td><input type="number" name="discounts[<?php echo $category; ?>][200gr][<?php echo $level; ?>]" value="<?php echo esc_attr($value_200gr); ?>" class="small-text" /></td>
                                <td><input type="number" name="discounts[<?php echo $category; ?>][1kg][<?php echo $level; ?>]" value="<?php echo esc_attr($value_1kg); ?>" class="small-text" /></td>
                            <?php endforeach; ?>
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

        if (isset($_POST['discounts'])) {
            foreach ($_POST['discounts'] as $category => $weights) {
                foreach ($weights as $weight => $levels) {
                    foreach ($levels as $level => $value) {
                        $meta_key = "_lp_discount_{$category}_{$weight}_{$level}";
                        update_option($meta_key, sanitize_text_field($value));
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
