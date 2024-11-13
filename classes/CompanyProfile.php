<?php

class CompanyProfile
{
    public function __construct()
    {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('show_user_profile', [$this, 'show_custom_fields_in_profile']);
        add_action('edit_user_profile', [$this, 'show_custom_fields_in_profile']);
    }

    public function register_shortcodes()
    {
        add_shortcode('sfwc_list_subaccounts', [$this, 'list_subaccounts_shortcode']);
    }

    public function list_subaccounts_shortcode()
    {
        $parent_user_id = get_current_user_id();
        $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true) ?: [];

        if (empty($subaccounts)) {
            return '<p>' . __('You have no registered companies yet.', 'subaccounts-for-woocommerce') . ' <a href="#">' . __('Create new', 'subaccounts-for-woocommerce') . '</a></p>';
        }

        ob_start();
        echo '<h3>' . __('My Subaccounts', 'subaccounts-for-woocommerce') . '</h3><ul>';
        foreach ($subaccounts as $subaccount_id) {
            $subaccount = get_userdata($subaccount_id);
            if ($subaccount) {
                echo '<li>' . esc_html($subaccount->user_login);
                echo '<button onclick="confirmDeletion()">Delete</button>';
                echo '<a href="' . esc_url(add_query_arg(['subaccount_id' => $subaccount_id], site_url('/edit-subaccount'))) . '">Edit</a></li>';
            }
        }
        echo '</ul>';
        return ob_get_clean();
    }

    public function show_custom_fields_in_profile($user)
    {
        ?>
        <h3><?php _e('Subaccount Information', 'subaccounts-for-woocommerce'); ?></h3>
        <table class="form-table">
            <tr><th><label><?php _e('Company', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'company', true)); ?></td></tr>
            <tr><th><label><?php _e('Email', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'email', true)); ?></td></tr>
            <!-- Hidden custom fields -->
            <div style="display: none">
                <?php for ($i = 1; $i <= 8; $i++) { ?>
                    <tr><th><label><?php echo 'Custom Field ' . $i; ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'custom_' . $i, true)); ?></td></tr>
                <?php } ?>
            </div>
        </table>
        <?php
    }
}

new CompanyProfile();
?>
