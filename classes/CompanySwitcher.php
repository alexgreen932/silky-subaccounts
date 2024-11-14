<?php

namespace SilkyDrum\WooCommerce;

class CompanySwitcher
{
    public function __construct()
    {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_ajax_switch_to_account', [$this, 'switch_to_account']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
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
        $is_subaccount = $this->is_subaccount();

        // Fetch parent account ID if subaccount
        $parent_id = $is_subaccount ? $this->get_parent_id() : null;

        // Get subaccounts from the parent account
        $children_ids = get_user_meta($parent_id ?? $user_id, 'sfwc_children', true) ?: [];

        ob_start();
        echo '<div id="sfwc-user-switcher-pane">';

        // Render switcher UI
        echo '<h3>' . esc_html__('Switch Accounts', 'subaccounts-for-woocommerce') . '</h3>';
        echo '<p><strong>' . esc_html__('Logged in as: ', 'subaccounts-for-woocommerce') . '</strong>' . esc_html($current_user->user_login) . ' (' . esc_html($current_user->user_email) . ')</p>';
        echo '<form id="sfwc-switch-form" method="post">';
        echo '<select name="target_account" required>';

        // If subaccount, add "Parent Account" option at the top
        if ($is_subaccount && $parent_id) {
            $parent_user = get_userdata($parent_id);
            echo '<option value="' . esc_attr($parent_id) . '">' . esc_html__('Parent Account', 'subaccounts-for-woocommerce') . ' (' . esc_html($parent_user->user_email) . ')</option>';
        }

        // List subaccounts
        foreach ($children_ids as $child_id) {
            $child_user = get_userdata($child_id);
            if ($child_user) {
                echo '<option value="' . esc_attr($child_id) . '">' . esc_html($child_user->user_login) . ' (' . esc_html($child_user->user_email) . ')</option>';
            }
        }

        echo '</select>';
        echo '<button type="submit">' . esc_attr__('Switch Account', 'subaccounts-for-woocommerce') . '</button>';
        echo '</form>';
        ?>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const switchForm = document.querySelector("#sfwc-switch-form");

                switchForm.addEventListener("submit", function (event) {
                    event.preventDefault();

                    const selectedAccount = switchForm.querySelector("select[name='target_account']").value;
                    const nonce = "<?php echo wp_create_nonce('sfwc_switch_nonce'); ?>";
                    const ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";

                    // Add loader to the page
                    const loader = document.createElement("div");
                    loader.innerHTML = "Moment Switching your Company...";
                    loader.style.cssText = "position:fixed; background:#fff; padding:20px; width:400px; text-align:center; top: 200px; left:50%; margin-left: -200px; z-index: 1000;";
                    document.body.appendChild(loader);

                    jQuery.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "switch_to_account",
                            target_user_id: selectedAccount,
                            security: nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                // Redirect to the same page URL
                                window.location.href = window.location.href.split("#")[0];
                            } else {
                                alert("Switching failed. Please try again.");
                                document.body.removeChild(loader); // Remove loader on error
                            }
                        },
                        error: function () {
                            alert("Switching failed. Please try again.");
                            document.body.removeChild(loader); // Remove loader on error
                        }
                    });
                });
            });

        </script>

        <?php
        echo '</div>';
        return ob_get_clean();
    }
    public function switch_to_account()
    {
        check_ajax_referer('sfwc_switch_nonce', 'security');
    
        if (!isset($_POST['target_user_id'])) {
            wp_send_json_error(['message' => 'No target account specified']);
            return;
        }
    
        $target_user_id = intval($_POST['target_user_id']);
        $current_user_id = get_current_user_id();
        $is_subaccount = $this->is_subaccount();
    
        if ($is_subaccount) {
            // Get the parent ID if user is a subaccount
            $parent_id = $this->get_parent_id();
            if ($parent_id) {
                // If the target account is the parent, switch back to parent
                if ($parent_id === $target_user_id) {
                    wp_clear_auth_cookie();
                    wp_set_current_user($parent_id);
                    wp_set_auth_cookie($parent_id);
                    wp_send_json_success(['redirect' => wc_get_page_permalink('myaccount')]);
                } else {
                    // Allow switching between subaccounts
                    $children = get_user_meta($parent_id, 'sfwc_children', true) ?: [];
                    if (in_array($target_user_id, $children)) {
                        wp_clear_auth_cookie();
                        wp_set_current_user($target_user_id);
                        wp_set_auth_cookie($target_user_id);
                        wp_send_json_success(['redirect' => wc_get_page_permalink('myaccount')]);
                    } else {
                        wp_send_json_error(['message' => 'Invalid subaccount selection']);
                    }
                }
            } else {
                wp_send_json_error(['message' => 'Parent account not found']);
            }
        } else {
            // If user is a parent account, allow switching to subaccounts
            $children = get_user_meta($current_user_id, 'sfwc_children', true) ?: [];
            if (in_array($target_user_id, $children)) {
                wp_clear_auth_cookie();
                wp_set_current_user($target_user_id);
                wp_set_auth_cookie($target_user_id);
                wp_send_json_success(['redirect' => wc_get_page_permalink('myaccount')]);
            } else {
                wp_send_json_error(['message' => 'Invalid subaccount selection']);
            }
        }
    }
    

    public function enqueue_scripts()
    {
        // This method can remain empty if no additional scripts are needed
    }

    private function get_parent_id()
    {
        $current_user_id = get_current_user_id();

        $user_query = new \WP_User_Query([
            'meta_key' => 'sfwc_children',
            'fields' => 'all_with_meta'
        ]);
        $users = $user_query->get_results();

        foreach ($users as $user) {
            $children = get_user_meta($user->ID, 'sfwc_children', true);
            if (is_array($children) && in_array($current_user_id, $children)) {
                return $user->ID;
            }
        }
        return null;
    }

    private function is_subaccount()
    {
        $current_user_id = get_current_user_id();
        $user_query = new \WP_User_Query([
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

new CompanySwitcher();
