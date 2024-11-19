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

        // Clear transients when product is updated
        add_action('woocommerce_update_product', function ($product_id) {
            wc_delete_product_transients($product_id);
        });

        // Temporarily disable product transients to avoid conflicts
        add_filter('woocommerce_enable_product_transients', '__return_false');
    }

    public function price_for_variations($price, $variation, $product)
    {
        // Clear transients to avoid caching issues
        wc_delete_product_transients($variation->get_id());

        // Calculate discount for variations
        $discount = $this->get_discount($variation);
        // dd($variation, false);
        // error_log('Applying dropdown or range discount for variation: ' . $variation->get_id() . ', Discount: ' . $discount);

        return max(0, $price - $discount);
    }

    public function price_for_select_and_simple($price, $product)
    {
        // Clear transients
        wc_delete_product_transients($product->get_id());

        // Calculate discount for simple or parent products
        $discount = $this->get_discount($product);
        // error_log('Applying discount for product: ' . $product->get_id() . ', Discount: ' . $discount);

        return max(0, $price - $discount);
    }

    private function get_discount($product)
    {
        // dd(77, false);
        $attribute = $product->get_attribute(self::ATTRIBUTE); // e.g., '200gr' or '1kg'
        // dd($attribute, false);
        $categories = $this->prod_categories($product->get_id());
        // dd($categories);
        $user_id = get_current_user_id();
        $level = 1; // Replace with loyalty level logic
        // dd(self::DISCOUNT_CATEGORIES);

        foreach (self::DISCOUNT_CATEGORIES as $category) {
            // dd($categories, false);
            // dd($category, false);
            if (in_array($category, $categories)) {
                $discount_key = "_lp_discount_{$category}_{$attribute}_level_{$level}";
                $discount = (float) get_option(
                    $discount_key,
                    $this->default_discounts[$level][($attribute === self::VAR_1) ? 0 : 1]
                );
                // error_log("Discount Found: $discount for Category: $category, Attribute: $attribute");
                return $discount;
            }
        }

        // error_log('No discount found for product: ' . $product->get_id());
        return 0;
    }

    private function prod_categories($product_id)
    {
        $product = wc_get_product($product_id);

        // If product is a variation, get the parent product ID
        if ($product->is_type('variation')) {
            $product_id = $product->get_parent_id();
        }

        $prod_cats = get_the_terms($product_id, 'product_cat');
        if (!$prod_cats) {
            return [];
        }

        // Return category slugs
        return array_map(static function ($cat) {
            return $cat->slug;
        }, $prod_cats);
    }
}
