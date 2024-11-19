<?php 

namespace SilkyDrum\WooCommerce;

class LoyaltyProgramCalculator extends LoyaltyProgramDiscounts
{
    public function __construct()
    {
        // Add filters for adjusting variation and product prices
        add_filter('woocommerce_product_variation_get_regular_price', [$this, 'price_for_select_and_simple'], 99, 2);
        add_filter('woocommerce_product_variation_get_price', [$this, 'price_for_select_and_simple'], 99, 2);

        add_filter('woocommerce_variation_prices_price', [$this, 'price_for_variations'], 99, 3);
        add_filter('woocommerce_variation_prices_regular_price', [$this, 'price_for_variations'], 99, 3);

        // Clear transients
        add_action('woocommerce_update_product', function ($product_id) {
            wc_delete_product_transients($product_id);
        });

        // Temporarily disable product transients
        add_filter('woocommerce_enable_product_transients', '__return_false');
    }

    /**
     * Adjust the price for individual variations (used for dropdown and top range).
     *
     * @param float $price
     * @param WC_Product_Variation $variation
     * @param WC_Product $product
     * @return float
     */
    public function price_for_variations($price, $variation, $product)
    {
        wc_delete_product_transients($variation->get_id());

        // Apply a fixed discount for variations only once
        $discount = 20;
        error_log('Applying discount for variation: ' . $variation->get_id() . ', Discount: ' . $discount);
        return max(0, $price - $discount);
    }

    /**
     * Adjust the price for the parent product (from-to range or simple product).
     *
     * @param float $price
     * @param WC_Product $product
     * @return float
     */
    public function price_for_select_and_simple($price, $product)
    {
        // Clear transients
        wc_delete_product_transients($product->get_id());

        if (!$product->is_type('variable')) {
            // Apply discount for simple products
            $discount = 50;
            error_log('Applying discount for simple product: ' . $product->get_id() . ', Discount: ' . $discount);
            return max(0, $price - $discount);
        }

        // Apply discount for parent variable product (from-to range)
        $variations = $product->get_children();
        $min_price = INF;
        $max_price = -INF;

        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);

            // Calculate variation price (without applying discount again)
            $variation_price = $this->price_for_variations($variation->get_price(), $variation, $product);
            $min_price = min($min_price, $variation_price);
            $max_price = max($max_price, $variation_price);
        }

        // Debugging: Log min and max prices for range
        error_log('From-To Range: Min Price = ' . $min_price . ', Max Price = ' . $max_price);

        // Return min or max price for range display
        return $price === $product->get_price() ? $max_price : $min_price;
    }
}
