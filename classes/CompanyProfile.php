<?php

namespace SilkyDrum\WooCommerce;

class CompanyProfile
{
    public function __construct()
    {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('show_user_profile', [$this, 'show_custom_fields_in_profile']);
        add_action('edit_user_profile', [$this, 'show_custom_fields_in_profile']);
        
        // Register the AJAX action
        add_action('wp_ajax_delete_subaccount', [$this, 'delete_subaccount']);
    }

    public function register_shortcodes()
    {
        add_shortcode('sfwc_list_subaccounts', [$this, 'list_subaccounts_shortcode']);
        add_shortcode('sfwc_edit_subaccount_form', [$this, 'edit_subaccount_form_shortcode']);
    }

    public function list_subaccounts_shortcode()
    {
        if ($this->is_subaccount()) {
            return __('You cannot view this content as you are a subaccount.', 'subaccounts-for-woocommerce') .
                do_shortcode('[sfwc_account_switcher]');
        }

        if (!is_user_logged_in()) {
            return __('You need to be logged in to view this content.', 'subaccounts-for-woocommerce');
        }

        $parent_user_id = get_current_user_id();
        $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true) ?: [];

        if (empty($subaccounts)) {
            return '<p>' . __('You have no registered companies yet.', 'subaccounts-for-woocommerce') .
                ' <a href="#">' . __('Create new', 'subaccounts-for-woocommerce') . '</a></p>';
        }

        ob_start();
        echo '<h3>' . __('My Subaccounts', 'subaccounts-for-woocommerce') . '</h3><ul>';

        foreach ($subaccounts as $subaccount_id) {
            $subaccount = get_userdata($subaccount_id);
            if ($subaccount) {
                echo '<li>' . esc_html($subaccount->user_login);
                echo ' <button onclick="confirmDeletion(' . $subaccount_id . ')">Delete</button>';
                echo ' <a href="' . esc_url(add_query_arg(['subaccount_id' => $subaccount_id], site_url('/edit-subaccount'))) . '">Edit</a></li>';
            }
        }

        echo '</ul>';
        ?>
        <script>
            function confirmDeletion(subaccountId) {
                if (confirm("Are you sure you want to delete this subaccount?")) {
                    jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>", {
                        action: 'delete_subaccount',
                        subaccount_id: subaccountId,
                        _ajax_nonce: '<?php echo wp_create_nonce("delete_subaccount"); ?>'
                    }, function (response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload(); // Refresh to update the subaccount list
                        } else {
                            alert(response.data.message || "Failed to delete subaccount.");
                        }
                    });
                }
            }
        </script>
        <?php
        return ob_get_clean();
    }

    public function show_custom_fields_in_profile($user)
    {
        ?>
        <h3><?php _e('Subaccount Information', 'subaccounts-for-woocommerce'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Company', 'subaccounts-for-woocommerce'); ?></label></th>
                <td><?php echo esc_attr(get_user_meta($user->ID, 'company', true)); ?></td>
            </tr>
            <tr>
                <th><label><?php _e('Master Email', 'subaccounts-for-woocommerce'); ?></label></th>
                <td><?php echo esc_attr(get_user_meta($user->ID, 'master_email', true)); ?></td>
            </tr>

            <!-- Hidden custom fields -->
            <div style="display: none">
                <?php for ($i = 1; $i <= 8; $i++) { ?>
                    <tr>
                        <th><label><?php echo 'Custom Field ' . $i; ?></label></th>
                        <td><?php echo esc_attr(get_user_meta($user->ID, 'custom_' . $i, true)); ?></td>
                    </tr>
                <?php } ?>
            </div>
        </table>
        <?php
    }

    public function edit_subaccount_form_shortcode()
    {
        return __('Subaccount editing form will be displayed here.', 'subaccounts-for-woocommerce');
    }

    public function delete_subaccount()
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('delete_subaccount', '_ajax_nonce', false)) {
            wp_send_json_error(['message' => 'Nonce verification failed.']);
        }
    
        // Temporarily grant delete_users capability to the parent account
        $parent_user_id = get_current_user_id();
        $parent_user = new WP_User($parent_user_id);
        $parent_user->add_cap('delete_users');
    
        $subaccount_id = intval($_POST['subaccount_id']);
        if ($subaccount_id && get_userdata($subaccount_id)) {
            $deleted = wp_delete_user($subaccount_id);
    
            // Revoke delete_users capability after deletion attempt
            $parent_user->remove_cap('delete_users');
    
            if ($deleted) {
                wp_send_json_success(['message' => 'Subaccount deleted successfully.']);
            } else {
                wp_send_json_error(['message' => 'Failed to delete the subaccount.']);
            }
        } else {
            // Revoke delete_users capability if subaccount_id is invalid
            $parent_user->remove_cap('delete_users');
            wp_send_json_error(['message' => 'Invalid subaccount ID.']);
        }
    }
    

    private function is_subaccount()
    {
        $current_user_id = get_current_user_id();
        $user_query = new WP_User_Query([
            'meta_key' => 'sfwc_children',
            'fields' => 'all_with_meta'
        ]);
        $users = $user_query->get_results();

        foreach ($users as $user) {
            $children = get_user_meta($user->ID, 'sfwc_children', true);
            if (is_array($children) && in_array($current_user_id, $children)) {
                return true;
            }
        }

        return false;
    }
}

new CompanyProfile();
?>
