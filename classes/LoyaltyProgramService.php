<?php

// namespace App\Services\WooCommerce;

// use WC_Order;

class LoyaltyProgramService
{

    public function __construct()
    {
        // Hook to apply discount based on loyalty level during cart calculations
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_loyalty_discount']);

        // Hook to update loyalty level after order is completed
        add_action('woocommerce_order_status_completed', [$this, 'update_loyalty_level'], 10, 1);

        // Hook to add loyalty level info in emails
        add_filter('woocommerce_email_order_meta', [$this, 'add_loyalty_info_to_email'], 10, 3);

        // Hook to modify product price based on loyalty level
        add_filter('woocommerce_product_get_price', [$this, 'change_price'], 10, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'change_price'], 10, 2);


        add_filter('woocommerce_get_price_html', [$this, 'change_price_html'], 10, 2);// Additional hook to ensure the displayed price is modified on product pages
    }



    /**
     * Applies the loyalty discount to the cart based on the user's loyalty level.
     *
     * @param WC_Cart $cart
     */
    public function apply_loyalty_discount($cart)
    {
        if (is_admin() && !defined('DOING_AJAX'))
            return;

        $user_id = get_current_user_id();
        if (!$user_id)
            return;

        $loyalty_level = $this->get_loyalty_level($user_id);
        $discount = $this->calculate_discount($loyalty_level, $cart);

        if ($discount > 0) {
            $cart->add_fee(__('Loyalty Program Discount', 'textdomain'), -$discount);
        }
    }

    /**
     * Updates the user's loyalty level after an order is completed.
     *
     * @param int $order_id
     */
    public function update_loyalty_level($order_id)
    {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();

        if ($user_id) {
            $new_level = $this->determine_loyalty_level($user_id);
            update_user_meta($user_id, '_loyalty_level', $new_level);
        }
    }

    /**
     * Adds loyalty level information to the order confirmation email.
     *
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function add_loyalty_info_to_email($order, $sent_to_admin, $plain_text)
    {
        $user_id = $order->get_user_id();
        if ($user_id) {
            $level = get_user_meta($user_id, '_loyalty_level', true);
            echo "<p>" . sprintf(__('Your current loyalty level: %s', 'textdomain'), $level) . "</p>";
        }
    }

    /**
     * Gets the user's current loyalty level.
     *
     * @param int $user_id
     * @return int
     */
    //todo change to private
    function get_loyalty_level($user_id)
    {
        $level = get_user_meta($user_id, '_loyalty_level', true);
        // dd($level);
        return $level ? (int) $level : 0;

    }

    /**
     * Calculates the discount amount based on the loyalty level and cart items.
     *
     * @param int $level
     * @param WC_Cart $cart
     * @return float
     */
    //todo change to private
    //todo more remove as it seems not need now, but need to check before rm
    function calculate_discount($level, $cart)
    {
        $discount = 0;
        $discount_structure = $this->get_discount_structure();

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            if (isset($discount_structure[$level])) {
                $discounts = $discount_structure[$level];

                // Assuming we're matching products by SKU for the discount
                if ($product->get_sku() == 'CAPPUCCINO_200G' && isset($discounts['espresso']['200g'])) {
                    $discount += $discounts['espresso']['200g'] * $quantity;
                } elseif ($product->get_sku() == 'CAPPUCCINO_1KG' && isset($discounts['espresso']['1kg'])) {
                    $discount += $discounts['espresso']['1kg'] * $quantity;
                }

                // Add conditions for other products and categories as needed
            }
        }
        return $discount;
    }


    /**
     * Determines the loyalty level based on user's order history.
     *
     * @param int $user_id
     * @return int
     */
    public function determine_loyalty_level($user_id)
    {

        // Check if the user has been manually assigned Level 3
        $is_manual_level_3 = get_user_meta($user_id, '_loyalty_level_3', true);
        if ($is_manual_level_3 == '1') {
            return 3;
        }

        $orders = wc_get_orders(['customer_id' => $user_id, 'status' => 'completed']);
        $order_count = count($orders);

        if ($order_count === 0) {
            return 0; // No orders
        }

        // Calculate months since first order
        $first_order_date = strtotime(end($orders)->get_date_created());
        $months_since_first_order = (time() - $first_order_date) / (30 * 24 * 60 * 60);

        // Define the loyalty levels with order and time requirements

        //dev, test and debug conditions will be removed in production
        $loyalty_levels = [
            3 => ['orders' => 3, 'months' => 0],
            2 => ['orders' => 2, 'months' => 0],
            1 => ['orders' => 1, 'months' => 0],
        ];

        //todo prod conditions      
        // $loyalty_levels = [
        //     3 => ['orders' => 24, 'months' => 12],
        //     2 => ['orders' => 16, 'months' => 9],
        //     1 => ['orders' => 8, 'months' => 4],
        // ];

        // Iterate through levels in descending order to find the highest applicable level
        foreach ($loyalty_levels as $level => $requirements) {
            if ($order_count >= $requirements['orders'] && $months_since_first_order >= $requirements['months']) {
                return $level;
            }
        }

        // Default level if no criteria are met
        return 0;
    }


    /**
     * Summary of get_loyalty_data
     * @param mixed $user_id
     * @return array
     */
    public function get_loyalty_data($user_id)
    {
        // $loyalty_level = $this->get_loyalty_level($user_id);//todo 
        $loyalty_level = $this->determine_loyalty_level($user_id);
        $next_level = $loyalty_level + 1;
        $discounts = $this->get_discount_structure();

        // Example progress data to the next level
        $progress = [
            'months_left' => 0,  // Dynamically calculate in actual use
            'orders_needed' => 1
        ];

        //todo for prod via array for all levels
        // $progress = [
        //     'months_left' => 5,  // Dynamically calculate in actual use
        //     'orders_needed' => 8
        // ];

        return [
            'loyalty_level' => $loyalty_level,
            'discounts' => $discounts[$loyalty_level] ?? [],
            'next_level' => $next_level,
            'progress' => $progress
        ];
    }


    /**
     * Returns the discount structure for each loyalty level.
     *
     * @return array
     */
    //todo remove after changing with new way of storing and retrieving discount data
    private function get_discount_structure()
    {
        return [
            1 => [
                'espresso' => ['200g' => 6, '1kg' => 30],
                'filter' => ['200g' => 20, '1kg' => 100]
            ],
            2 => [
                'espresso' => ['200g' => 10, '1kg' => 60],
                'filter' => ['200g' => 40, '1kg' => 200]
            ],
            3 => [
                'espresso' => ['200g' => 20, '1kg' => 100],
                'filter' => ['200g' => 60, '1kg' => 300]
            ],
        ];

    }



    /**
     * Calculate discounted price based on user's loyalty level.
     *
     * @param float $price The original price.
     * @param WC_Product $product The product object.
     * @param int $loyalty_level The loyalty level of the user.
     * @return float The discounted price.
     */
    //todo change to private
    function get_discounted_price($price, $product, $loyalty_level)
    {
        // Ensure $price is a valid number, fallback to the regular price if it’s not
        if (!is_numeric($price) || $price <= 0) {
            $price = (float) $product->get_regular_price(); // Ensure price is a float
        }

        $discount = 0;

        // Check for simple (non-variable) product discount
        if (!$product->is_type('variable')) {
            if ($loyalty_level > 0) {
                // Retrieve the discount value from product meta for the specified loyalty level
                $discount_meta_key = "_lp_discount_common_product_{$loyalty_level}";
                $discount = $product->get_meta($discount_meta_key);

                // Ensure discount is a valid number, else set it to zero
                if (!is_numeric($discount)) {
                    $discount = 0;
                } else {
                    $discount = (float) $discount; // Cast to float for calculations
                }
            }
        } else {
            // For variable products, handle discount logic for variations if needed
            return $price; // No discount applied for variable products in this example
        }

        // Return the base price if no valid discount was found
        if ($discount <= 0) {
            return $price;
        }

        // Calculate the discounted price, ensuring the final value does not go below zero
        return max(0, $price - $discount);
    }





    /**
     * Apply discounted price in WooCommerce calculations.
     *
     * @param float $price The original price.
     * @param WC_Product $product The WooCommerce product object.
     * @return float The modified price.
     */
    public function change_price($price, $product)
    {
        // return $price; // No discount if not logged in
        $user_id = get_current_user_id();
        //left if acces will be public
        // if (!$user_id) {
        //     return $price; // No discount if user is not logged in
        // }

        $loyalty_level = $this->get_loyalty_level($user_id);

        return $this->get_discounted_price($price, $product, $loyalty_level);
    }

    /**
     * Modify the displayed price HTML for catalog and product pages.
     *
     * @param string $price_html The HTML of the price to display.
     * @param WC_Product $product The WooCommerce product object.
     * @return string The modified HTML price.
     */
    public function change_price_html($price_html, $product)
    {
        return $price_html;
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $price_html; // No discount display if user is not logged in
        }

        $loyalty_level = $this->get_loyalty_level($user_id);
        $discounted_price = $this->get_discounted_price($product->get_price(), $product, $loyalty_level);

        return wc_price($discounted_price);
    }

    //just blank methods for testing purposes
    public function test()
    {
    }


}

$loyalty_program_service = new LoyaltyProgramService();

