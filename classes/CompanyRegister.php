<?php

class CompanyRegister
{
    public function __construct()
    {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp', [$this, 'handle_subaccount_form_submission']);
    }

    public function register_shortcodes()
    {
        remove_shortcode('sfwc_add_subaccount_shortcode');
        add_shortcode('sfwc_add_subaccount_shortcode', [$this, 'sfwc_add_new_subaccount_form_content']);
    }

    public function sfwc_add_new_subaccount_form_content()
    {


        //return if subbaccount
        if ($this->is_subaccount()) {
            return __('You cannot view this content as you are a subaccount.', 'subaccounts-for-woocommerce') .
                do_shortcode('[sfwc_account_switcher]');
        }

        $parent_user_id = get_current_user_id();
        $limit = 99;

        if ($this->subaccount_limit_reached($parent_user_id, $limit)) {
            return wc_print_notice(__('Maximum number of subaccounts reached.', 'subaccounts-for-woocommerce'), 'error');
        }

        $form_data = $this->get_subaccount_form_data();


        ob_start();
        ?>
        <form id="sfwc_form_add_subaccount_frontend" method="post">
            <?php wp_nonce_field('sfwc_add_subaccount_frontend_action', 'sfwc_add_subaccount_frontend'); ?>

            <?php $this->render_form_field('user_login', 'Username (Company)', true, $form_data); ?>
            
            <div style="display: none">
                <?php $this->render_form_field('email', 'Email', true, ['value' => $this->get_fake_email(), 'type' => 'text']); ?>
                <?php $this->render_form_field('master_email', 'Master Email', true, ['value' => $this->get_real_email(), 'type' => 'text']); ?>
                <?php for ($i = 1; $i <= 8; $i++) {
                    $this->render_form_field('custom_' . $i, 'Custom Field ' . $i, false, ['type' => 'text']);
                } ?>
            </div>

            <?php $this->render_form_field('account_display_name', 'Display Name', false, $form_data); ?>
            <?php $this->render_form_field('billing_address_1', 'Billing Address', false, $form_data); ?>
            <?php $this->render_form_field('billing_city', 'City', false, $form_data); ?>
            <?php $this->render_form_field('billing_state', 'State', false, $form_data); ?>
            <?php $this->render_form_field('billing_postcode', 'Postcode', false, $form_data); ?>
            <?php $this->render_form_field('billing_phone', 'Phone', false, $form_data); ?>
            <?php $this->render_form_field('billing_last_name', 'Last Name', false, $form_data); ?>

            <input type="submit" value="<?php echo esc_attr__('Add Subaccount', 'subaccounts-for-woocommerce'); ?>" style="padding:10px 40px;">
        </form>
        <?php
        return ob_get_clean();
    }

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

        // Update meta fields for WooCommerce and custom fields
        update_user_meta($user_id, 'account_display_name', sanitize_text_field($_POST['account_display_name'] ?? ''));
        update_user_meta($user_id, 'billing_address_1', sanitize_text_field($_POST['billing_address_1'] ?? ''));
        update_user_meta($user_id, 'billing_city', sanitize_text_field($_POST['billing_city'] ?? ''));
        update_user_meta($user_id, 'billing_state', sanitize_text_field($_POST['billing_state'] ?? ''));
        update_user_meta($user_id, 'billing_postcode', sanitize_text_field($_POST['billing_postcode'] ?? ''));
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['billing_phone'] ?? ''));
        update_user_meta($user_id, 'billing_last_name', sanitize_text_field($_POST['billing_last_name'] ?? ''));
        update_user_meta($user_id, 'master_email', sanitize_email($this->get_real_email()));

        // Save each custom field
        for ($i = 1; $i <= 8; $i++) {
            update_user_meta($user_id, 'custom_' . $i, sanitize_text_field($_POST['custom_' . $i] ?? ''));
        }

        $parent_user_id = get_current_user_id();
        $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true) ?: [];
        $subaccounts[] = $user_id;
        update_user_meta($parent_user_id, 'sfwc_children', $subaccounts);

        wc_add_notice(__('Subaccount created successfully!', 'subaccounts-for-woocommerce'), 'success');
    }

    private function subaccount_limit_reached($user_id, $limit)
    {
        $existing_subaccounts = get_user_meta($user_id, 'sfwc_children', true) ?: [];
        return count($existing_subaccounts) >= $limit;
    }

    private function render_form_field($name, $label, $required = false, $options = [])
    {
        $value = esc_attr($options['value'] ?? '');
        $type = $options['type'] ?? 'text';
        $required_attr = $required ? 'required' : '';
        $required_marker = $required ? '<span style="color:red;">*</span>' : '';

        echo "<div style='margin-bottom:20px;'><label for='{$name}'>{$label} {$required_marker}</label>";
        echo "<input type='{$type}' name='{$name}' id='{$name}' value='{$value}' {$required_attr} style='width:100%;'></div>";
    }

    private function get_fake_email()
    {
        return substr(bin2hex(random_bytes(8)), 0, 8) . '_' . $this->get_real_email();
    }

    private function get_real_email()
    {
        $user = get_userdata(get_current_user_id());
        return $user->user_email;
    }

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

new CompanyRegister();
?>
