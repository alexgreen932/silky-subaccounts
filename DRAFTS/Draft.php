<?php

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
        if ($this->is_subaccount()) {
            return '<a href="' . esc_url($this->get_parent_account_link()) . '">' . __('Go back to parent account', 'subaccounts-for-woocommerce') . '</a>';
        }

        $parent_user_id = get_current_user_id();
        $limit = get_option('sfwc_option_subaccounts_number_limit', 10);

        if ($this->subaccount_limit_reached($parent_user_id, $limit)) {
            return wc_print_notice(__('Maximum number of subaccounts reached.', 'subaccounts-for-woocommerce'), 'error');
        }

        $form_data = $this->get_subaccount_form_data();

        ob_start();
        ?>
        <form id="sfwc_form_add_subaccount_frontend" method="post">
            <?php wp_nonce_field('sfwc_add_subaccount_frontend_action', 'sfwc_add_subaccount_frontend'); ?>

            <?php $this->render_form_field('user_login', 'Username (Company)', true, $form_data); ?>
            <?php $this->render_form_field('email', 'Email (Hidden)', true, ['value' => $this->get_fake_email(), 'type' => 'hidden']); ?>
            <?php $this->render_form_field('master_email', 'Master Email', true, ['value' => $this->get_real_email(), 'type' => 'hidden']); ?>
            <?php $this->render_form_field('company', 'Company', false, $form_data); ?>
            <?php $this->render_form_field('billing_tax_info', 'Tax Info', false, $form_data); ?>
            <?php $this->render_form_field('billing_bank_id', 'Bank ID', false, $form_data); ?>
            <?php $this->render_form_field('billing_accaunt_id', 'Account ID', false, $form_data); ?>
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

    // Method definitions for get_real_email, get_fake_email, subaccount_limit_reached, and others as shown previously
}

new CompanyRegister();
?>
<?php

class CompaniesSubaccounts
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
        add_shortcode('sfwc_edit_subaccount_form', [$this, 'edit_subaccount_form_shortcode']);
        add_shortcode('sfwc_account_switcher', [$this, 'account_switcher_shortcode']);
    }

    public function show_custom_fields_in_profile($user)
    {
        if (in_array('subscriber', $user->roles)) {
            ?>
            <h3><?php _e('Subaccount Information', 'subaccounts-for-woocommerce'); ?></h3>
            <table class="form-table">
                <tr><th><label><?php _e('Company', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'company', true)); ?></td></tr>
                <tr><th><label><?php _e('Tax Info', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_tax_info', true)); ?></td></tr>
                <tr><th><label><?php _e('Bank ID', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_bank_id', true)); ?></td></tr>
                <tr><th><label><?php _e('Account ID', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_accaunt_id', true)); ?></td></tr>
                <tr><th><label><?php _e('Display Name', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'account_display_name', true)); ?></td></tr>
                <tr><th><label><?php _e('Billing Address', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_address_1', true)); ?></td></tr>
                <tr><th><label><?php _e('City', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_city', true)); ?></td></tr>
                <tr><th><label><?php _e('State', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_state', true)); ?></td></tr>
                <tr><th><label><?php _e('Postcode', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_postcode', true)); ?></td></tr>
                <tr><th><label><?php _e('Phone', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_phone', true)); ?></td></tr>
                <tr><th><label><?php _e('Last Name', 'subaccounts-for-woocommerce'); ?></label></th><td><?php echo esc_attr(get_user_meta($user->ID, 'billing_last_name', true)); ?></td></tr>
            </table>
            <?php
        }
    }

    // Additional methods for the 3 remaining shortcodes and their functionality
}

new CompaniesSubaccounts();
?>
