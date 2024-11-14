<?php

namespace SilkyDrum\WooCommerce;

class LoyaltyProgramService extends LoyaltyProgramDiscounts
{
    // public function __construct()
    // {
    //     add_action('woocommerce_cart_calculate_fees', [$this, 'apply_loyalty_discount']);
    //     add_action('woocommerce_order_status_completed', [$this, 'update_loyalty_level'], 10, 1);
    //     add_filter('woocommerce_email_order_meta', [$this, 'add_loyalty_info_to_email'], 10, 3);
    //     add_filter('woocommerce_product_get_price', [$this, 'change_price'], 10, 2);
    //     add_filter('woocommerce_product_get_sale_price', [$this, 'change_price'], 10, 2);
    //     add_filter('woocommerce_get_price_html', [$this, 'change_price_html'], 10, 2);
    //     // Apply discount for variations
    //     add_filter('woocommerce_variation_prices_price', [$this, 'apply_discount_to_variation'], 10, 3);
    //     add_filter('woocommerce_variation_prices_sale_price', [$this, 'apply_discount_to_variation'], 10, 3);


    //     // Ensure WooCommerce recalculates variation prices when cache hash changes
    //     add_filter('woocommerce_get_variation_prices_hash', [$this, 'update_variation_prices_hash'], 10, 3);
    //     // Force WooCommerce to always fetch all variation prices to ensure discounts show up
    //     add_filter('woocommerce_ajax_variation_threshold', function () {
    //         return 100;
    //     });

    //     add_filter('woocommerce_cart_item_price', [$this, 'apply_cart_discount'], 10, 3);
    //     add_filter('woocommerce_cart_item_subtotal', [$this, 'apply_cart_discount'], 10, 3);


    // }


    public function __construct()
    {
        add_action('init', [$this, 'initialize_hooks']);
    }

    public function initialize_hooks()
    {
        // WooCommerce-specific hooks
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_loyalty_discount']);
        add_action('woocommerce_order_status_completed', [$this, 'update_loyalty_level'], 10, 1);
        add_filter('woocommerce_email_order_meta', [$this, 'add_loyalty_info_to_email'], 10, 3);
        add_filter('woocommerce_product_get_price', [$this, 'change_price'], 10, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'change_price'], 10, 2);
        add_filter('woocommerce_get_price_html', [$this, 'change_price_html'], 10, 2);
        add_filter('woocommerce_variation_prices_price', [$this, 'apply_discount_to_variation'], 10, 3);
        add_filter('woocommerce_variation_prices_sale_price', [$this, 'apply_discount_to_variation'], 10, 3);
        add_filter('woocommerce_get_variation_prices_hash', [$this, 'update_variation_prices_hash'], 10, 3);
        add_filter('woocommerce_ajax_variation_threshold', function () {
            return 100;
        });
        //todo remove then temperary disable cache while debugging
        add_filter('woocommerce_get_variation_prices_hash', function ($hash, $product, $for_display) {
            $hash[] = time(); // Makes the hash unique each time, bypassing cache
            return $hash;
        }, 10, 3);

    }

    public function apply_loyalty_discount($cart)
    {
        if (is_admin() && !defined('DOING_AJAX'))
            return;

        $user_id = get_current_user_id();
        if (!$user_id)
            return;

        $loyalty_level = $this->get_loyalty_level($user_id);

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($this->is_discounted_category($product)) {
                $price = $product->get_price();
                $discounted_price = $this->get_discounted_price($price, $product, $loyalty_level);

                if (is_numeric($price) && is_numeric($discounted_price) && $discounted_price < $price) {
                    $discount_amount = $price - $discounted_price;
                    $cart->add_fee(__('Loyalty Discount', 'textdomain'), -$discount_amount * $cart_item['quantity']);
                }
            }
        }
    }

    private function is_discounted_category($product)
    {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
        return !empty(array_intersect(self::DISCOUNT_CATEGORIES, $categories));
    }

    public function update_loyalty_level($order_id)
    {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        if ($user_id) {
            $new_level = $this->determine_loyalty_level($user_id);
            update_user_meta($user_id, '_loyalty_level', $new_level);
        }
    }

    public function add_loyalty_info_to_email($order, $sent_to_admin, $plain_text)
    {
        $user_id = $order->get_user_id();
        if ($user_id) {
            $level = get_user_meta($user_id, '_loyalty_level', true);
            echo "<p>" . sprintf(__('Your current loyalty level: %s', 'textdomain'), $level) . "</p>";
        }
    }


    //debbb...

    public function get_discounted_price($price, $product, $loyalty_level)
    {
        return $price;//for continue work //
        static $discount_applied = [];
        static $parent_categories_cache = [];
    
        // Ensure $price is a valid number
        if (!is_numeric($price) || $price <= 0) {
            $price = (float) $product->get_regular_price();
        }
    
        // Retrieve parent product details if this is a variation
        if ($product instanceof WC_Product_Variation) {
            $product_id = $product->get_parent_id(); // Parent ID for the variation
            $parent_product = wc_get_product($product_id); // Load the actual parent product
        } else {
            $product_id = $product->get_id();
            $parent_product = $product;
        }
    
        // Check if discount is already applied for this product ID
        if (isset($discount_applied[$product_id])) {
            echo "<pre>Discount already applied for product ID: $product_id</pre>";
            return $price;
        }
    
        // Cache and retrieve categories based on the parent product ID
        if (!isset($parent_categories_cache[$product_id])) {
            $parent_categories_cache[$product_id] = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        }
    
        // Retrieve categories from cache
        $categories = $parent_categories_cache[$product_id];
        echo "<pre>Product Categories for ID {$product_id}: ";
        print_r($categories);
        echo "</pre>";
    
        // Check if the product is in a discounted category
        $discount_category = array_intersect(self::DISCOUNT_CATEGORIES, $categories);
        if (empty($discount_category)) {
            echo "<pre>Product not in discount category</pre>";
            return $price; // No discount if the product is not in a discounted category
        }
    
        // Get the first matching discount category
        $discount_category = reset($discount_category);
    
        // Retrieve discount based on loyalty level and product attribute from the parent
        $product_attribute = $parent_product->get_attribute(self::ATTRIBUTE);
        $discount = 0;
    
        echo "<pre>Product Attribute - " . self::ATTRIBUTE . ": $product_attribute</pre>";
    
        if (strpos($product_attribute, self::VAR_1) !== false) {
            $var_1_discount = get_option("_lp_discount_{$discount_category}_" . self::VAR_1 . "_level_$loyalty_level", 0);
            $discount = (float) $var_1_discount;
            echo "<pre>Discount for " . self::VAR_1 . ": $discount</pre>";
        } elseif (strpos($product_attribute, self::VAR_2) !== false) {
            $var_2_discount = get_option("_lp_discount_{$discount_category}_" . self::VAR_2 . "_level_$loyalty_level", 0);
            $discount = (float) $var_2_discount;
            echo "<pre>Discount for " . self::VAR_2 . ": $discount</pre>";
        } else {
            echo "<pre>No matching attribute for discount</pre>";
        }
    
        // Calculate the discounted price
        $discounted_price = max(0, $price - $discount);
    
        // Mark discount as applied for this product ID
        $discount_applied[$product_id] = true;
    
        echo "<pre>Final Discounted Price for product ID {$product_id}: $discounted_price</pre>";
        return $discounted_price;
    }
    


    public function apply_discount_to_variation($price, $variation, $variable_product)
    {
        // Ensure we have the original price for calculation
        if (empty($price)) {
            $price = $variation->get_regular_price(); // Use regular price if original is missing
        }

        // Get the loyalty level for the current user
        $user_id = get_current_user_id();
        $loyalty_level = (new LoyaltyProgramSidebarData())->get_loyalty_data($user_id)['loyalty_level'] ?? 0;

        // Apply the discount using get_discounted_price
        $discounted_price = $this->get_discounted_price($price, $variation, $loyalty_level);

        // echo "<pre>Discounted Price for variation ID {$variation->get_id()}: $discounted_price</pre>";
        return $discounted_price;
    }



    /**
     * Update the variation prices cache hash to force recalculation.
     */
    public function update_variation_prices_hash($price_hash, $product, $display)
    {
        $user_id = get_current_user_id();
        $loyalty_level = $this->get_loyalty_level($user_id);

        // Modify the hash based on the user's loyalty level
        $price_hash['loyalty_level'] = $loyalty_level;
        return $price_hash;
    }




    private function get_product_category($product)
    {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
        foreach (self::DISCOUNT_CATEGORIES as $discount_category) {
            if (in_array($discount_category, $categories)) {
                return $discount_category;
            }
        }
        return null;
    }

    public function change_price($price, $product)
    {
        // Ensure this discount only applies once
        if ($product->get_meta('_discount_applied', true)) {
            return $price; // Return price if discount has already been applied
        }

        $user_id = get_current_user_id();
        $loyalty_level = $this->get_loyalty_level($user_id);

        // Calculate discounted price
        $discounted_price = $this->get_discounted_price($price, $product, $loyalty_level);

        // Set the flag to prevent further discounts
        $product->update_meta_data('_discount_applied', true);
        $product->save_meta_data();

        return $discounted_price;
    }


    public function change_price_html($price_html, $product)
    {
        $user_id = get_current_user_id();
        $loyalty_level = $this->get_loyalty_level($user_id);
        $discounted_price = $this->get_discounted_price($product->get_price(), $product, $loyalty_level);
        return is_numeric($discounted_price) ? wc_price($discounted_price) : $price_html;
    }

    private function get_loyalty_level($user_id)
    {
        return (int) get_user_meta($user_id, '_loyalty_level', true) ?: 0;
    }

    //debug only
    function deb()
    {
        $rt = $this->is_discounted_category(222);
        return $rt;
    }
}

new LoyaltyProgramService();
