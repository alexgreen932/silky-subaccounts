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


//debug function
if (!function_exists('dd')) {
    function dd($var, $die=true) {
        echo '<pre>';
            var_dump($var);
        echo '</pre>';
        if ($die) {
            die();
        }
        
    }
}

add_action('plugins_loaded', 'my_custom_plugin_init', 20);

// require plugin_dir_path( __FILE__ ) . 'app/Services/WooCommerce/LoyaltyProgram/unit.php';
// include '/app/Services/WooCommerce/LoyaltyProgram/unit.php';

function my_custom_plugin_init() {
    if (class_exists('WooCommerce')) {
        remove_shortcode('sfwc_add_subaccount_shortcode');
        add_shortcode('sfwc_add_subaccount_shortcode', 'sfwc_add_new_subaccount_form_content');
        error_log('Custom shortcode modification applied after WooCommerce load.');
    } else {
        error_log('WooCommerce not active or loaded.');
    }
}



include 'classes/LoyaltyProgramDiscounts.php';
include 'classes/LoyaltyProgramService.php';
include 'classes/SetLoyaltyLevel.php';
//sibaccounts
include 'classes/CompaniesSubaccounts.php';
include 'classes/CompanyRegister.php';
//include 'classes/dev.php';
