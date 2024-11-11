<?php
/**
 * @package Silky Subaccounts
 * @version 1.0.0
 */
/*
Plugin Name: Silky Subaccounts
Plugin URI: http://wordpress.org/plugins/hello-dolly/
Description: lorem.
Author: Alex
Version: 1.0.0
Author URI: http://al.ex/
*/

add_action('plugins_loaded', 'my_custom_plugin_init', 20);

function my_custom_plugin_init() {
    if (class_exists('WooCommerce')) {
        remove_shortcode('sfwc_add_subaccount_shortcode');
        add_shortcode('sfwc_add_subaccount_shortcode', 'sfwc_add_new_subaccount_form_content');
        error_log('Custom shortcode modification applied after WooCommerce load.');
    } else {
        error_log('WooCommerce not active or loaded.');
    }
}


include 'classes/companiesSubaccounts.php';
