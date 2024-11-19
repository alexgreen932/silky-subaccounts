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
    function dd($var, $die = true)
    {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
        if ($die) {
            die();
        }

    }
}

function initialize_silky_subaccounts()
{
    // Autoload classes
    spl_autoload_register(function ($class) {
        $namespace = 'SilkyDrum\\WooCommerce\\';
        $base_dir = plugin_dir_path(__FILE__) . 'classes/';

        // Only load classes in the specified namespace
        if (strncmp($namespace, $class, strlen($namespace)) !== 0) {
            return;
        }

        $relative_class = str_replace($namespace, '', $class);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            include_once $file;
        }
    });

    // Example: Instantiate a class to ensure everything works


    if (class_exists('SilkyDrum\WooCommerce\CompanyRegister')) {
        new \SilkyDrum\WooCommerce\CompanyRegister();
    }
    if (class_exists('SilkyDrum\WooCommerce\CompanyProfile')) {
        new \SilkyDrum\WooCommerce\CompanyProfile();
    }
    if (class_exists('SilkyDrum\WooCommerce\CompanySwitcher')) {
        new \SilkyDrum\WooCommerce\CompanySwitcher();
    }
    if (class_exists('SilkyDrum\WooCommerce\LoyaltyProgramSidebarData')) {
        new \SilkyDrum\WooCommerce\LoyaltyProgramSidebarData();
    }
    if (class_exists('SilkyDrum\WooCommerce\LoyaltyProgramDiscounts')) {
        new \SilkyDrum\WooCommerce\LoyaltyProgramDiscounts();
    }
    if (class_exists('SilkyDrum\WooCommerce\EmailOverrideForSubaccounts')) {
        new \SilkyDrum\WooCommerce\EmailOverrideForSubaccounts();
    }

    if (class_exists('SilkyDrum\WooCommerce\SetLoyaltyLevel')) {
        new \SilkyDrum\WooCommerce\SetLoyaltyLevel();
    }

    // if (class_exists('SilkyDrum\WooCommerce\LoyaltyProgramService')) {
    //     $service = new \SilkyDrum\WooCommerce\LoyaltyProgramService();
    // }

    if (class_exists('SilkyDrum\WooCommerce\LoyaltyProgramCalculator')) {
        new \SilkyDrum\WooCommerce\LoyaltyProgramCalculator();
    }
}


add_action('plugins_loaded', 'initialize_silky_subaccounts');



// include 'dev.php';

