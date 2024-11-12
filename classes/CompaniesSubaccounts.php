<?php

class CompaniesSubaccounts
{
    public function __construct()
    {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp', [$this, 'handle_subaccount_form_submission']);
        add_action('show_user_profile', [$this, 'show_custom_fields_in_profile']);
        add_action('edit_user_profile', [$this, 'show_custom_fields_in_profile']);

        // Separate handling for switching accounts
        add_action('wp', [$this, 'switch_to_subaccount']);
        add_action('wp', [$this, 'switch_to_parent']);
    }

    /**
     * Summary of register_shortcodes
     * @return void
     */
    public function register_shortcodes()
    {
        //removes the original shortcode to avoid conflicts
        // remove_shortcode('sfwc_add_subaccount_shortcode');
        //add new shortcode to display the form
        // add_shortcode('sfwc_add_subaccount_shortcode', [$this, 'sfwc_add_new_subaccount_form_content']);
        
        //add extra shortcodes to manage
        add_shortcode('sfwc_list_subaccounts', [$this, 'list_subaccounts_shortcode']);
        add_shortcode('sfwc_edit_subaccount_form', [$this, 'edit_subaccount_form_shortcode']);
        add_shortcode('sfwc_account_switcher', [$this, 'account_switcher_shortcode']);
    }

    /**
     * Summary of sfwc_add_new_subaccount_form_content
     * @return mixed
     */
    public function sfwc_add_new_subaccount_form_content()
    {
        // dd($this->is_subaccount());//todo rm
        if ($this->is_subaccount()) {
            return '<a href="' . esc_url($this->get_parent_account_link()) . '">' . __('Go back to parent account', 'subaccounts-for-woocommerce') . '</a>';
        }

        $parent_user_id = get_current_user_id();
        $sfwc_options = get_option('sfwc_options', []);
        $limit = $sfwc_options['sfwc_option_subaccounts_number_limit'] ?? 10;

        if ($this->subaccount_limit_reached($parent_user_id, $limit)) {
            return wc_print_notice(__('Maximum number of subaccounts reached.', 'subaccounts-for-woocommerce'), 'error');
        }

        $form_data = $this->get_subaccount_form_data();

        ob_start();
        ?>
        <form id="sfwc_form_add_subaccount_frontend" method="post">
            <?php wp_nonce_field('sfwc_add_subaccount_frontend_action', 'sfwc_add_subaccount_frontend'); ?>

            <?php $this->render_form_field('user_login', 'Username (Company)', true, $form_data); ?>
            <?php $this->render_form_field('email', 'Email', true, $form_data); ?>
            <?php $this->render_form_field('company', 'Company', false, $form_data); ?>
            <?php $this->render_form_field('billing_tax_info', 'Tax Info', false, $form_data); ?>
            <?php $this->render_form_field('billing_bank_id', 'Bank ID', false, $form_data); ?>
            <?php $this->render_form_field('billing_accaunt_id', 'Account ID', false, $form_data); ?>

            <input type="submit" value="<?php echo esc_attr__('Add Subaccount', 'subaccounts-for-woocommerce'); ?>"
                style="padding:10px 40px;">
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Summary of list_subaccounts_shortcode
     * @return mixed
     */
    private function subaccount_limit_reached($user_id, $limit)
    {
        $existing_subaccounts = get_user_meta($user_id, 'sfwc_children', true) ?: [];
        return count($existing_subaccounts) >= $limit;
    }

    /**
     * Summary of list_subaccounts_shortcode
     * @return mixed
     */
    private function get_subaccount_form_data()
    {
        return [
            'user_login' => sanitize_text_field($_POST['user_login'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'company' => sanitize_text_field($_POST['company'] ?? ''),
            'billing_tax_info' => sanitize_text_field($_POST['billing_tax_info'] ?? ''),
            'billing_bank_id' => sanitize_text_field($_POST['billing_bank_id'] ?? ''),
            'billing_accaunt_id' => sanitize_text_field($_POST['billing_accaunt_id'] ?? ''),
        ];
    }


    /**
     * Summary of list_subaccounts_shortcode
     * @return mixed
     */
    private function render_form_field($name, $label, $required = false, $form_data = [])
    {
        $value = esc_attr($form_data[$name] ?? '');
        $required_attr = $required ? 'required' : '';
        $required_marker = $required ? '<span style="color:red;">*</span>' : '';

        echo "<div style='margin-bottom:20px;'><label for='{$name}'>{$label} {$required_marker}</label>";
        echo "<input type='text' name='{$name}' id='{$name}' value='{$value}' {$required_attr} style='width:100%;'></div>";
    }

    /**
     * Summary of handle_subaccount_form_submission
     * @return void
     */
    public function handle_subaccount_form_submission()
    {
        if (!isset($_POST['sfwc_add_subaccount_frontend']) || !wp_verify_nonce($_POST['sfwc_add_subaccount_frontend'], 'sfwc_add_subaccount_frontend_action')) {
            return;
        }

        $user_data = [
            'user_login' => sanitize_user($_POST['user_login'] ?? ''),
            'user_email' => sanitize_email($_POST['email'] ?? ''),
            'role' => 'subscriber',
            'user_pass' => $_POST['user_pass'] ?? wp_generate_password()
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            wc_add_notice($user_id->get_error_message(), 'error');
            return;
        }

        update_user_meta($user_id, 'company', sanitize_text_field($_POST['company'] ?? ''));
        update_user_meta($user_id, 'billing_tax_info', sanitize_text_field($_POST['billing_tax_info'] ?? ''));
        update_user_meta($user_id, 'billing_bank_id', sanitize_text_field($_POST['billing_bank_id'] ?? ''));
        update_user_meta($user_id, 'billing_account_id', sanitize_text_field($_POST['billing_accaunt_id'] ?? ''));

        $parent_user_id = get_current_user_id();
        $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true) ?: [];
        $subaccounts[] = $user_id;
        update_user_meta($parent_user_id, 'sfwc_children', $subaccounts);

        wc_add_notice(__('Subaccount created successfully!', 'subaccounts-for-woocommerce'), 'success');
    }

    /**
     * Summary of is_subaccount
     * @return bool
     */
    public function show_custom_fields_in_profile($user)
    {
        if (in_array('subscriber', $user->roles)) {
            ?>
            <h3><?php _e('Subaccount Information', 'subaccounts-for-woocommerce'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Company', 'subaccounts-for-woocommerce'); ?></label></th>
                    <td><?php echo esc_attr(get_user_meta($user->ID, 'company', true)); ?></td>
                </tr>
                <tr>
                    <th><label><?php _e('Tax Info', 'subaccounts-for-woocommerce'); ?></label></th>
                    <td><?php echo esc_attr(get_user_meta($user->ID, 'billing_tax_info', true)); ?></td>
                </tr>
                <tr>
                    <th><label><?php _e('Bank ID', 'subaccounts-for-woocommerce'); ?></label></th>
                    <td><?php echo esc_attr(get_user_meta($user->ID, 'billing_bank_id', true)); ?></td>
                </tr>
                <tr>
                    <th><label><?php _e('Account ID', 'subaccounts-for-woocommerce'); ?></label></th>
                    <td><?php echo esc_attr(get_user_meta($user->ID, 'billing_accaunt_id', true)); ?></td>
                </tr>
            </table>
            <?php
        }
    }

    /**
     * Summary of get_parent_account_link
     * @return string
     */
    public function list_subaccounts_shortcode()
    {
        // dd($this->is_subaccount());//todo rm
        if ($this->is_subaccount()) {
            return '<a href="' . esc_url($this->get_parent_account_link()) . '">' . __('Go back to parent account', 'subaccounts-for-woocommerce') . '</a>';
        }

        if (!is_user_logged_in()) {
            return __('You need to be logged in to view this content.', 'subaccounts-for-woocommerce');
        }

        $parent_user_id = get_current_user_id();
        $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true);

        if (empty($subaccounts)) {
            return __('No subaccounts found.', 'subaccounts-for-woocommerce');
        }

        ob_start();
        echo '<h3>' . __('My Subaccounts', 'subaccounts-for-woocommerce') . '</h3><ul>';
        foreach ($subaccounts as $subaccount_id) {
            $subaccount = get_userdata($subaccount_id);
            if ($subaccount) {
                echo '<li><strong>' . esc_html($subaccount->display_name) . '</strong><br>';
                echo 'Username: ' . esc_html($subaccount->user_login) . '<br>';
                echo 'Email: ' . esc_html($subaccount->user_email) . '<br>';
                $company = get_user_meta($subaccount_id, 'company', true);
                $tax_info = get_user_meta($subaccount_id, 'billing_tax_info', true);
                $bank_id = get_user_meta($subaccount_id, 'billing_bank_id', true);
                $account_id = get_user_meta($subaccount_id, 'billing_account_id', true);

                if ($company)
                    echo 'Company: ' . esc_html($company) . '<br>';
                if ($tax_info)
                    echo 'Tax Info: ' . esc_html($tax_info) . '<br>';
                if ($bank_id)
                    echo 'Bank ID: ' . esc_html($bank_id) . '<br>';
                if ($account_id)
                    echo 'Account ID: ' . esc_html($account_id) . '<br>';

                $edit_url = add_query_arg(['subaccount_id' => $subaccount_id], site_url('/edit-subaccount'));
                echo '<a href="' . esc_url($edit_url) . '" class="button">' . __('Edit', 'subaccounts-for-woocommerce') . '</a><hr></li>';
            }
        }
        echo '</ul>';
        return ob_get_clean();
    }

    /**
     * Summary of get_parent_account_link
     * @return string
     */
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

        if ($is_subaccount) {
            // Link to switch back to the parent account
            echo '<a href="' . esc_url($this->get_parent_account_link()) . '">' . __('Go back to parent account', 'subaccounts-for-woocommerce') . '</a>';
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


    /**
     * Summary of is_subaccount
     * @return bool
     */
    private function is_subaccount()
    {
        $current_user_id = get_current_user_id();

        // Check if this user ID appears in any other user's 'sfwc_children' meta
        $args = [
            'meta_key' => 'sfwc_children',
            'meta_value' => '"' . $current_user_id . '"', // Meta query will look for serialized value in array
            'meta_compare' => 'LIKE',
            'number' => 1, // Only need to find one match
            'fields' => 'ID',
        ];

        $parent_query = new WP_User_Query($args);
        $parent_users = $parent_query->get_results();

        // If any parent user is found, then this is a subaccount
        return !empty($parent_users);
    }


    /**
     * Summary of get_parent_account_link
     * @return mixed
     */
    private function get_parent_account_link()
    {
        $current_user_id = get_current_user_id();

        // Query for the parent user of this subaccount
        $args = [
            'meta_key' => 'sfwc_children',
            'meta_value' => '"' . $current_user_id . '"',
            'meta_compare' => 'LIKE',
            'number' => 1,
            'fields' => 'ID',
        ];

        $parent_query = new WP_User_Query($args);
        $parent_users = $parent_query->get_results();

        if (!empty($parent_users)) {
            $parent_user_id = $parent_users[0];

            // Add the redirect_to parameter to return to the same page
            $redirect_url = esc_url_raw((is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            return add_query_arg(['user_id' => $parent_user_id, 'redirect_to' => urlencode($redirect_url)], wc_get_page_permalink('myaccount'));
        }

        return '';
    }

    /**
     * Summary of switch_to_subaccount
     * @return void
     */
    public function switch_to_subaccount()
    {
        if (isset($_POST['switch_to_subaccount']) && isset($_POST['sfwc_frontend_children'])) {
            $target_user_id = intval($_POST['sfwc_frontend_children']);
            $current_user_id = get_current_user_id();

            // Verify the target user is a valid child account
            if (in_array($target_user_id, get_user_meta($current_user_id, 'sfwc_children', true) ?: [])) {
                // Log in as the target subaccount user
                wp_clear_auth_cookie();
                wp_set_current_user($target_user_id);
                wp_set_auth_cookie($target_user_id);

                // Redirect to the current page or My Account if no redirect is provided
                $redirect_url = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : wc_get_page_permalink('myaccount');
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }

    /**
     * Summary of switch_to_parent
     * @return void
     */
    public function switch_to_parent()
    {
        if (isset($_GET['user_id']) && $this->is_subaccount()) {
            $target_user_id = intval($_GET['user_id']);
            $current_user_id = get_current_user_id();

            // Verify the target user is the parent of the current subaccount
            if (in_array($current_user_id, get_user_meta($target_user_id, 'sfwc_children', true) ?: [])) {
                // Log in as the parent account
                wp_clear_auth_cookie();
                wp_set_current_user($target_user_id);
                wp_set_auth_cookie($target_user_id);

                // Redirect to the specified page or My Account if not provided
                $redirect_url = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : wc_get_page_permalink('myaccount');
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }

}

new CompaniesSubaccounts();
?>