<?php

class CompanySwitcher
{
    public function __construct()
    {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp', [$this, 'switch_to_subaccount']);
        add_action('wp', [$this, 'switch_to_parent']);
    }

    public function register_shortcodes()
    {
        add_shortcode('sfwc_account_switcher', [$this, 'account_switcher_shortcode']);
    }

    public function account_switcher_shortcode()
    {
        if (!is_user_logged_in()) {
            return __('You need to be logged in to view this content.', 'subaccounts-for-woocommerce');
        }

        $current_user = wp_get_current_user();
        $user_id = get_current_user_id();
        $children_ids = get_user_meta($user_id, 'sfwc_children', true) ?: [];
        $is_subaccount = $this->is_subaccount();

        ob_start();
        echo '<div id="sfwc-user-switcher-pane">';
        // dd($is_subaccount);
        if ($is_subaccount) {
      
            // Display link to switch back to the parent account if logged in as a subaccount
            $parent_account_link = $this->get_parent_account_link();
            if ($parent_account_link) {
                echo '<a href="' . esc_url($parent_account_link) . '">' . __('Go back to parent account', 'subaccounts-for-woocommerce') . '</a>';
            }
        } else {
            // Display switch options for the parent account
            echo '<h3>' . esc_html__('Switch Accounts', 'subaccounts-for-woocommerce') . '</h3>';
            echo '<p><strong>' . esc_html__('Logged in as: ', 'subaccounts-for-woocommerce') . '</strong>' . esc_html($current_user->user_login) . ' (' . esc_html($current_user->user_email) . ')</p>';

            $current_url = esc_url_raw((is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

            // Form for switching to a subaccount
            echo '<form method="post">';
            echo '<select name="sfwc_frontend_children" required>';
            echo '<option value="" disabled selected>' . esc_html__('Select Account', 'subaccounts-for-woocommerce') . '</option>';

            foreach ($children_ids as $child_id) {
                $child_user = get_userdata($child_id);
                if ($child_user) {
                    echo '<option value="' . esc_attr($child_id) . '">' . esc_html($child_user->user_login) . ' (' . esc_html($child_user->user_email) . ')</option>';
                }
            }

            echo '</select>';
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr($current_url) . '">';
            echo '<input type="hidden" name="switch_to_subaccount" value="1">';
            echo '<input type="submit" value="' . esc_attr__('Switch Account', 'subaccounts-for-woocommerce') . '">';
            echo '</form>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    private function is_subaccount()
    {
        $current_user_id = get_current_user_id();
        $user_query = new WP_User_Query([
            'meta_key' => 'sfwc_children',
            'fields' => 'all_with_meta'
        ]);
        $users = $user_query->get_results();
    
        // Iterate through each user's sfwc_children metadata to check if the current user ID exists
        foreach ($users as $user) {
            $children = get_user_meta($user->ID, 'sfwc_children', true);
            
            // Ensure $children is an array and check if the current user ID is present
            if (is_array($children) && in_array($current_user_id, $children)) {
                return true;
            }
        }
    
        return false;
    }
    
    

    private function get_parent_account_link()
    {
        $current_user_id = get_current_user_id();
    
        // Retrieve all users with sfwc_children metadata
        $user_query = new WP_User_Query([
            'meta_key' => 'sfwc_children',
            'fields' => 'all_with_meta'
        ]);
        $users = $user_query->get_results();
    
        // Iterate through each user's sfwc_children metadata to find the parent of the current subaccount
        foreach ($users as $user) {
            $children = get_user_meta($user->ID, 'sfwc_children', true);
    
            // Check if current user ID is within this user's sfwc_children array
            if (is_array($children) && in_array($current_user_id, $children)) {
                // Build the parent account link with redirection to the current page
                $redirect_url = esc_url_raw((is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                return add_query_arg(['user_id' => $user->ID, 'redirect_to' => urlencode($redirect_url)], wc_get_page_permalink('myaccount'));
            }
        }
    
        return ''; // Return an empty string if no parent is found
    }
    

    public function switch_to_subaccount()
    {
        if (isset($_POST['switch_to_subaccount']) && isset($_POST['sfwc_frontend_children'])) {
            $target_user_id = intval($_POST['sfwc_frontend_children']);
            $current_user_id = get_current_user_id();

            // Verify the target user is a valid child account
            if (in_array($target_user_id, get_user_meta($current_user_id, 'sfwc_children', true) ?: [])) {
                wp_clear_auth_cookie();
                wp_set_current_user($target_user_id);
                wp_set_auth_cookie($target_user_id);

                $redirect_url = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : wc_get_page_permalink('myaccount');
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }

    public function switch_to_parent()
    {
        if (isset($_GET['user_id']) && $this->is_subaccount()) {
            $target_user_id = intval($_GET['user_id']);
            $current_user_id = get_current_user_id();

            // Verify the target user is the parent of the current subaccount
            if (in_array($current_user_id, get_user_meta($target_user_id, 'sfwc_children', true) ?: [])) {
                wp_clear_auth_cookie();
                wp_set_current_user($target_user_id);
                wp_set_auth_cookie($target_user_id);

                $redirect_url = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : wc_get_page_permalink('myaccount');
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }
}

new CompanySwitcher();
?>
