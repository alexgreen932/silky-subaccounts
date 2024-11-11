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
add_action('wp', 'sfwc_handle_subaccount_form_submission');

function sfwc_handle_subaccount_form_submission() {
    // Verify nonce to ensure the form was submitted from the correct source
    if (!isset($_POST['sfwc_add_subaccount_frontend']) || !wp_verify_nonce($_POST['sfwc_add_subaccount_frontend'], 'sfwc_add_subaccount_frontend_action')) {
        return;
    }

    // Sanitize and prepare basic user data
    $user_login = sanitize_user($_POST['user_login'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $user_pass = $_POST['user_pass'] ?? wp_generate_password(); // Generate a random password if not provided

    // Prepare the user data array for registration
    $user_data = [
        'user_login' => $user_login,
        'user_email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => 'subscriber',
        'user_pass' => $user_pass
    ];

    // Insert the new user
    $user_id = wp_insert_user($user_data);

    // Check if user creation was successful
    if (is_wp_error($user_id)) {
        wc_add_notice($user_id->get_error_message(), 'error');
        return;
    }

    // Additional custom fields to save as user meta
    $company = sanitize_text_field($_POST['company'] ?? '');
    $billing_tax_info = sanitize_text_field($_POST['billing_tax_info'] ?? '');
    $billing_bank_id = sanitize_text_field($_POST['billing_bank_id'] ?? '');
    $billing_account_id = sanitize_text_field($_POST['billing_accaunt_id'] ?? ''); // Correct typo to 'billing_account_id'

    // Save additional fields to user meta
    update_user_meta($user_id, 'company', $company);
    update_user_meta($user_id, 'billing_tax_info', $billing_tax_info);
    update_user_meta($user_id, 'billing_bank_id', $billing_bank_id);
    update_user_meta($user_id, 'billing_account_id', $billing_account_id);

    // Link this subaccount to the parent account
    $parent_user_id = get_current_user_id();
    $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true) ?: [];
    $subaccounts[] = $user_id;
    update_user_meta($parent_user_id, 'sfwc_children', $subaccounts);

    // Success message
    wc_add_notice(__('Subaccount created successfully!', 'subaccounts-for-woocommerce'), 'success');
}




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

//list of accounts
/**
 * Shortcode to display a list of subaccounts for the current user, with an Edit button.
 */
function sfwc_list_subaccounts_shortcode() {
    // Ensure the user is logged in
    if (!is_user_logged_in()) {
        return __('You need to be logged in to view this content.', 'subaccounts-for-woocommerce');
    }

    // Get the current user's ID
    $parent_user_id = get_current_user_id();

    // Retrieve subaccounts associated with the current user
    $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true);

    // If no subaccounts, display a message
    if (empty($subaccounts)) {
        return __('No subaccounts found.', 'subaccounts-for-woocommerce');
    }

    // Start output buffering for the list HTML
    ob_start();

    echo '<h3>' . __('My Subaccounts', 'subaccounts-for-woocommerce') . '</h3>';
    echo '<ul>';

    // Loop through each subaccount and display basic information with an edit button
    foreach ($subaccounts as $subaccount_id) {
        $subaccount = get_userdata($subaccount_id);

        // Ensure the subaccount still exists
        if ($subaccount) {
            echo '<li>';
            echo '<strong>' . esc_html($subaccount->display_name) . '</strong><br>';
            echo 'Username: ' . esc_html($subaccount->user_login) . '<br>';
            echo 'Email: ' . esc_html($subaccount->user_email) . '<br>';

            // Fetch and display custom meta fields if they exist
            $company = get_user_meta($subaccount_id, 'company', true);
            $tax_info = get_user_meta($subaccount_id, 'billing_tax_info', true);
            $bank_id = get_user_meta($subaccount_id, 'billing_bank_id', true);
            $account_id = get_user_meta($subaccount_id, 'billing_account_id', true);

            if ($company) {
                echo 'Company: ' . esc_html($company) . '<br>';
            }
            if ($tax_info) {
                echo 'Tax Info: ' . esc_html($tax_info) . '<br>';
            }
            if ($bank_id) {
                echo 'Bank ID: ' . esc_html($bank_id) . '<br>';
            }
            if ($account_id) {
                echo 'Account ID: ' . esc_html($account_id) . '<br>';
            }

            // Update this link to point to the actual "Edit Subaccount" page you created
            $edit_url = add_query_arg(['subaccount_id' => $subaccount_id], site_url('/edit-subaccount'));
            echo '<a href="' . esc_url($edit_url) . '" class="button">' . __('Edit', 'subaccounts-for-woocommerce') . '</a>';

            echo '</li><hr>';
        }
    }

    echo '</ul>';

    // End output buffering and return the content
    return ob_get_clean();
}
add_shortcode('sfwc_list_subaccounts', 'sfwc_list_subaccounts_shortcode');


//edit form
/**
 * Shortcode to display the edit form for a subaccount.
 */
function sfwc_edit_subaccount_form_shortcode() {
    // Ensure the user is logged in and has permission to edit this subaccount
    if (!is_user_logged_in() || !isset($_GET['subaccount_id'])) {
        return __('You do not have permission to access this page.', 'subaccounts-for-woocommerce');
    }

    // Get the subaccount ID from the URL
    $subaccount_id = intval($_GET['subaccount_id']);
    $subaccount = get_userdata($subaccount_id);

    // Ensure the subaccount exists and belongs to the current user
    $parent_user_id = get_current_user_id();
    $subaccounts = get_user_meta($parent_user_id, 'sfwc_children', true);

    if (!$subaccount || !in_array($subaccount_id, $subaccounts)) {
        return __('This subaccount does not exist or you do not have access.', 'subaccounts-for-woocommerce');
    }

    // Retrieve the current values for the custom fields
    $company = get_user_meta($subaccount_id, 'company', true);
    $billing_tax_info = get_user_meta($subaccount_id, 'billing_tax_info', true);
    $billing_bank_id = get_user_meta($subaccount_id, 'billing_bank_id', true);
    $billing_account_id = get_user_meta($subaccount_id, 'billing_account_id', true);

    // Render the edit form
    ob_start();
    ?>
    <form method="post">
        <label for="company"><?php _e('Company', 'subaccounts-for-woocommerce'); ?></label>
        <input type="text" name="company" id="company" value="<?php echo esc_attr($company); ?>" />

        <label for="billing_tax_info"><?php _e('Tax Info', 'subaccounts-for-woocommerce'); ?></label>
        <input type="text" name="billing_tax_info" id="billing_tax_info" value="<?php echo esc_attr($billing_tax_info); ?>" />

        <label for="billing_bank_id"><?php _e('Bank ID', 'subaccounts-for-woocommerce'); ?></label>
        <input type="text" name="billing_bank_id" id="billing_bank_id" value="<?php echo esc_attr($billing_bank_id); ?>" />

        <label for="billing_account_id"><?php _e('Account ID', 'subaccounts-for-woocommerce'); ?></label>
        <input type="text" name="billing_account_id" id="billing_account_id" value="<?php echo esc_attr($billing_account_id); ?>" />

        <input type="hidden" name="subaccount_id" value="<?php echo esc_attr($subaccount_id); ?>" />
        <input type="submit" name="sfwc_update_subaccount" value="<?php _e('Update Subaccount', 'subaccounts-for-woocommerce'); ?>" />
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('sfwc_edit_subaccount_form', 'sfwc_edit_subaccount_form_shortcode');

function sfwc_account_switcher_shortcode() {
    // Check if the user is logged in
    if (!is_user_logged_in()) {
        return __('You need to be logged in to view this content.', 'subaccounts-for-woocommerce');
    }

    // Retrieve the current user data
    $current_user = wp_get_current_user();
    $user_id = get_current_user_id();

    // Retrieve child accounts associated with the current user
    $children_ids = get_user_meta($user_id, 'sfwc_children', true) ?: [];
    $existing_children_ids = array_filter($children_ids, function($child_id) {
        return get_userdata($child_id) !== false;
    });

    // Return early if no children accounts exist
    if (empty($existing_children_ids)) {
        return __('No subaccounts available to switch.', 'subaccounts-for-woocommerce');
    }

    // Prepare styling options
    $sfwc_switcher_pane_bg_color = '#def6ff';
    $sfwc_switcher_pane_headline_color = '#0088cc';
    $sfwc_switcher_pane_text_color = '#3b3b3b';
    $sfwc_switcher_pane_select_bg_color = '#0088cc';
    $sfwc_switcher_pane_select_text_color = '#ffffff';

    // Begin output buffering for the shortcode output
    ob_start();

    // HTML block for the switcher pane
    echo '<div id="sfwc-user-switcher-pane" style="background-color:' . esc_attr($sfwc_switcher_pane_bg_color) . ';">';
    echo '<h3 style="color:' . esc_attr($sfwc_switcher_pane_headline_color) . ';">' . esc_html__('Switch Accounts', 'subaccounts-for-woocommerce') . '</h3>';

    // Display the current user's name
    echo '<p style="color:' . esc_attr($sfwc_switcher_pane_text_color) . ';">';
    echo '<strong>' . esc_html__('Logged in as: ', 'subaccounts-for-woocommerce') . '</strong>';
    echo esc_html($current_user->user_login) . ' (' . esc_html($current_user->user_email) . ')</p>';

    // Select dropdown for switching accounts
    echo '<form method="post">';
    echo '<select id="sfwc_frontend_children" name="sfwc_frontend_children" onchange="this.form.submit();" style="background-color:' . esc_attr($sfwc_switcher_pane_select_bg_color) . '; color:' . esc_attr($sfwc_switcher_pane_select_text_color) . ';">';
    echo '<option value="" disabled selected>' . esc_html__('Select Account', 'subaccounts-for-woocommerce') . '</option>';

    // Populate options for each child account
    foreach ($existing_children_ids as $child_id) {
        $child_user = get_userdata($child_id);
        echo '<option value="' . esc_attr($child_id) . '">' . esc_html($child_user->user_login) . ' (' . esc_html($child_user->user_email) . ')</option>';
    }

    echo '</select>';
    echo '<input name="setc" value="submit" type="submit" style="display:none;">';
    echo '</form>';
    echo '</div>';

    // jQuery code to initialize Selectize and apply styles
    ?>
    <script>
    jQuery(document).ready(function ($) {
        // Initialize Selectize
        $("#sfwc_frontend_children").selectize();

        // Apply custom styles
        $('body').append('<style>#sfwc-user-switcher-pane .selectize-control.single .selectize-input{background-color: <?php echo esc_attr($sfwc_switcher_pane_select_bg_color); ?>;}</style>');
        $('body').append('<style>#sfwc-user-switcher-pane .selectize-control.single .selectize-input input::placeholder, #sfwc-user-switcher-pane .selectize-control.single .selectize-input .item {color: <?php echo esc_attr($sfwc_switcher_pane_select_text_color); ?>;}</style>');
    });
    </script>
    <?php

    // Return the output buffer contents
    return ob_get_clean();
}
add_shortcode('sfwc_account_switcher', 'sfwc_account_switcher_shortcode');







