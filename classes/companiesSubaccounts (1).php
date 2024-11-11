<?php

remove_shortcode('sfwc_add_subaccount_shortcode');

/**
 * Shortcode to display the subaccount creation form.
 */
function sfwc_add_new_subaccount_form_content() {
    // Get the current user and any necessary options
    $parent_user_id = get_current_user_id();
    $sfwc_options = get_option('sfwc_options', []);
    $sfwc_option_display_name = $sfwc_options['sfwc_option_display_name'] ?? 'username';
    $sfwc_option_subaccounts_number_limit = $sfwc_options['sfwc_option_subaccounts_number_limit'] ?? 10;

    // Display a message if subaccount limit is reached
    if (sfwc_subaccount_limit_reached($parent_user_id, $sfwc_option_subaccounts_number_limit)) {
        return wc_print_notice(__('Maximum number of subaccounts reached.', 'subaccounts-for-woocommerce'), 'error');
    }

    // Pre-populate form data from $_POST if available
    $form_data = sfwc_get_subaccount_form_data();

    // Render the form HTML
    ob_start();
    ?>
    <form id="sfwc_form_add_subaccount_frontend" method="post">
        <?php wp_nonce_field('sfwc_add_subaccount_frontend_action', 'sfwc_add_subaccount_frontend'); ?>
        
        <?php sfwc_render_form_field('user_login', 'Username (Company)', true, $form_data); ?>
        <?php sfwc_render_form_field('email', 'Email', true, $form_data); ?>
        <?php sfwc_render_form_field('company', 'Company', false, $form_data); ?>
        <?php sfwc_render_form_field('billing_tax_info', 'Tax Info', false, $form_data); ?>
        <?php sfwc_render_form_field('billing_bank_id', 'Bank ID', false, $form_data); ?>
        <?php sfwc_render_form_field('billing_accaunt_id', 'Account ID', false, $form_data); ?>
        
        <input type="submit" value="<?php echo esc_attr__('Add Subaccount', 'subaccounts-for-woocommerce'); ?>" style="padding:10px 40px;">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('sfwc_add_subaccount_shortcode', 'sfwc_add_new_subaccount_form_content');





/**
 * Helper function to check if the subaccount limit is reached.
 */
function sfwc_subaccount_limit_reached($parent_user_id, $limit) {
    $existing_subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true) ?: [];
    return count($existing_subaccounts) >= $limit;
}

/**
 * Helper function to retrieve form data or set defaults.
 */
function sfwc_get_subaccount_form_data() {
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
 * Helper function to render a form field.
 */
function sfwc_render_form_field($name, $label, $required = false, $form_data = []) {
    $value = esc_attr($form_data[$name] ?? '');
    $required_attr = $required ? 'required' : '';
    $required_marker = $required ? '<span style="color:red;">*</span>' : '';

    echo "<div style='margin-bottom:20px;'><label for='{$name}'>{$label} {$required_marker}</label>";
    echo "<input type='text' name='{$name}' id='{$name}' value='{$value}' {$required_attr} style='width:100%;'></div>";
}


/**
 * Handles form submission and saves subaccount data.
 */
function sfwc_handle_subaccount_form_submission() {
    if (!isset($_POST['sfwc_add_subaccount_frontend']) || !wp_verify_nonce($_POST['sfwc_add_subaccount_frontend'], 'sfwc_add_subaccount_frontend_action')) {
        return;
    }

    $user_data = [
        'user_login' => sanitize_user($_POST['user_login']),
        'user_email' => sanitize_email($_POST['email']),
        'role' => 'subscriber',
    ];

    $user_id = wp_insert_user($user_data);
    if (is_wp_error($user_id)) {
        wc_add_notice($user_id->get_error_message(), 'error');
        return;
    }

    // Save custom fields to user meta
    update_user_meta($user_id, 'company', sanitize_text_field($_POST['company']));
    update_user_meta($user_id, 'billing_tax_info', sanitize_text_field($_POST['billing_tax_info']));
    update_user_meta($user_id, 'billing_bank_id', sanitize_text_field($_POST['billing_bank_id']));
    update_user_meta($user_id, 'billing_accaunt_id', sanitize_text_field($_POST['billing_accaunt_id']));

    // Link the new subaccount to the parent account
    $parent_user_id = get_current_user_id();
    $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true) ?: [];
    $subaccounts[] = $user_id;
    update_user_meta($parent_user_id, 'sfwc_children', $subaccounts);

    wc_add_notice(__('Subaccount created successfully!', 'subaccounts-for-woocommerce'), 'success');
}
add_action('wp', 'sfwc_handle_subaccount_form_submission');



/**
 * Show additional fields for subaccounts in the backend user profile.
 */
function sfwc_show_custom_fields_in_profile($user) {
    if (in_array('subscriber', $user->roles)) {
        ?>
        <h3><?php _e('Subaccount Information', 'subaccounts-for-woocommerce'); ?></h3>

        <table class="form-table">
            <tr>
                <th><label for="company"><?php _e('Company', 'subaccounts-for-woocommerce'); ?></label></th>
                <td><?php echo esc_attr(get_user_meta($user->ID, 'company', true)); ?></td>
            </tr>
            <tr>
                <th><label for="billing_tax_info"><?php _e('Tax Info', 'subaccounts-for-woocommerce'); ?></label></th>
                <td><?php echo esc_attr(get_user_meta($user->ID, 'billing_tax_info', true)); ?></td>
            </tr>
            <tr>
                <th><label for="billing_bank_id"><?php _e('Bank ID', 'subaccounts-for-woocommerce'); ?></label></th>
                <td><?php echo esc_attr(get_user_meta($user->ID, 'billing_bank_id', true)); ?></td>
            </tr>
            <tr>
                <th><label for="billing_accaunt_id"><?php _e('Account ID', 'subaccounts-for-woocommerce'); ?></label></th>
                <td><?php echo esc_attr(get_user_meta($user->ID, 'billing_accaunt_id', true)); ?></td>
            </tr>
        </table>
        <?php
    }
}
add_action('show_user_profile', 'sfwc_show_custom_fields_in_profile');
add_action('edit_user_profile', 'sfwc_show_custom_fields_in_profile');


