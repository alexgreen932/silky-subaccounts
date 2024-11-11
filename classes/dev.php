<?php
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
