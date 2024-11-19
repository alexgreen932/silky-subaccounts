<?php

namespace SilkyDrum\WooCommerce;

class LoyaltyProgramService extends LoyaltyProgramDiscounts
{
	public function __construct()
	{
		add_action('init', [$this, 'initialize_hooks']);
	}

	public function initialize_hooks()
	{
		add_action('woocommerce_cart_calculate_fees', [$this, 'apply_loyalty_discount']);
		add_action('woocommerce_order_status_completed', [$this, 'update_loyalty_level'], 10, 1);
		add_filter('woocommerce_email_order_meta', [$this, 'add_loyalty_info_to_email'], 10, 3);
		add_filter('woocommerce_product_get_price', [$this, 'change_price'], 10, 2);
		add_filter('woocommerce_product_get_sale_price', [$this, 'change_price'], 10, 2);
		add_filter('woocommerce_get_price_html', [$this, 'change_price_html'], 10, 2);
		add_filter('woocommerce_variation_prices_price', [$this, 'apply_discount_to_variation'], 10, 3);
		add_filter('woocommerce_variation_prices_sale_price', [$this, 'apply_discount_to_variation'], 10, 3);
		add_filter('woocommerce_get_variation_prices_hash', [$this, 'update_variation_prices_hash'], 10, 3);
	}

	public function apply_loyalty_discount($cart)
	{
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}

		$user_id = get_current_user_id();
		if (!$user_id) {
			return;
		}

		$loyalty_level = $this->get_loyalty_level($user_id);

		foreach ($cart->get_cart() as $cart_item) {
			$product = $cart_item['data'];
			if ($this->is_discounted_category($product)) {
				$price = $product->get_price();
				$discounted_price = $this->get_discounted_price($price, $product, $loyalty_level);

				if ($discounted_price < $price) {
					$discount_amount = $price - $discounted_price;
					$cart->add_fee(__('Loyalty Discount', 'textdomain'), -$discount_amount * $cart_item['quantity']);
				}
			}
		}
	}


	//todo remove if not used
	private function is_discounted_category($product)
	{
		$categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
		return !empty(array_intersect(self::DISCOUNT_CATEGORIES, $categories));
	}

	private function get_discount_category($product)
	{
		$product_id = $product instanceof WC_Product_Variation
			? $product->get_parent_id()
			: $product->get_id();
		$categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
		return !empty(array_intersect(self::DISCOUNT_CATEGORIES, $categories))
			? reset($categories)
			: null;
	}


	public function get_discounted_price($price, $product, $loyalty_level)
	{
		if (!is_numeric($price) || $price <= 0) {
			$price = (float) $product->get_regular_price();
		}

		$product_id = $product instanceof WC_Product_Variation
			? $product->get_parent_id()
			: $product->get_id();

		$categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
		$discount_category = array_intersect(self::DISCOUNT_CATEGORIES, $categories);
		if (empty($discount_category)) {
			return $price;
		}

		$discount_category = reset($discount_category);
		$product_attribute = $product instanceof WC_Product_Variation
			? wc_get_product($product_id)->get_attribute(self::ATTRIBUTE)
			: $product->get_attribute(self::ATTRIBUTE);

		$discount = 0;

		if (strpos($product_attribute, self::VAR_1) !== false) {
			$discount = (float) get_option("_lp_discount_{$discount_category}_" . self::VAR_1 . "_level_$loyalty_level", 0);
		} elseif (strpos($product_attribute, self::VAR_2) !== false) {
			$discount = (float) get_option("_lp_discount_{$discount_category}_" . self::VAR_2 . "_level_$loyalty_level", 0);
		}

		return max(0, $price - $discount);
	}

	public function apply_discount_to_variation($price, $product, $variation_id)
	{
		$user_id = get_current_user_id();
		$loyalty_level = $this->get_loyalty_level($user_id);

		if (!empty($variation_id)) {
			$variation = wc_get_product($variation_id);
			if ($variation instanceof WC_Product_Variation) {
				$variation_price = $variation->get_regular_price();
				return $this->get_discounted_price($variation_price, $variation, $loyalty_level);
			}
		}

		if (!$product || !$product->is_type('variable')) {
			return $price;
		}

		$variation_ids = $product->get_children();

		foreach ($variation_ids as $id) {
			$variation = wc_get_product($id);
			if ($variation instanceof WC_Product_Variation) {
				$variation_price = $variation->get_regular_price();
				$discounted_price = $this->get_discounted_price($variation_price, $variation, $loyalty_level);

				if ($discounted_price < $variation_price) {
					$variation->set_regular_price($discounted_price);
					$variation->set_sale_price($discounted_price);
					$variation->save();
				}
			}
		}

		return $price;
	}

	public function update_variation_prices_hash($price_hash, $product, $display)
	{
		$user_id = get_current_user_id();
		$loyalty_level = $this->get_loyalty_level($user_id);
		$price_hash['loyalty_level'] = $loyalty_level;
		return $price_hash;
	}

	private function get_loyalty_level($user_id)
	{
		return (int) get_user_meta($user_id, '_loyalty_level', true) ?: 0;
	}

	public function change_price($price, $product)
	{
		$user_id = get_current_user_id();
		$loyalty_level = $this->get_loyalty_level($user_id);

		if ($product->is_type('variable')) {
			return $this->apply_discount_to_variation($price, $product, null);
		}

		return $this->get_discounted_price($price, $product, $loyalty_level);
	}



	public function get_variation_price($product)
	{
		global $product;
		if (!$product->is_type('variable')) {
			//return price of product if not a variable
			return $product->get_price();
			// dd('NOT VARIATION PRICE');
		}
		// if ($product->is_type('variable')) {

		//product is a variable

		// Get all variation IDs
		$variations = $product->get_children();
		// dd($variations);
		$prices = [];


		// global $woocommerce;
		// $product_variation = new $variations;
		// // $regular_price = $product_variation->regular_price;
		// dd($product_variation );

		//loop through all variations and store their prices
		foreach ($variations as $variation_id) {
			$variation = wc_get_product($variation_id);
			//dd($variation);//WC_Product_Variation Object
			// global $woocommerce;
			// $product_variation = new WC_Product_Variation($_POST['variation_id']);
			// $regular_price = $product_variation->regular_price;
			$price = $variation->get_regular_price();
			// dd($price,false);
			$discounted_category = $this->is_discounted_category($product);
			// dd($discounted_category,false);
			$artibute = get_variation_attributes();
			$artibute = wc_get_product_variation_attributes( $variation_id );
			dd($artibute,false);





			// if ($variation instanceof WC_Product_Variation) {
			//     // Get the price of the variation
			//     $price = $variation->get_regular_price();
			//     $prices[$variation_id] = $price; // Store variation prices
			// }
		}

		// foreach ($variations as $variation_id) {
		// 	$variation = wc_get_product($variation_id);
		// 	if ($variation instanceof WC_Product_Variation) {
		// 		// Get the price of the variation
		// 		$price = $variation->get_regular_price();
		// 		$prices[$variation_id] = $price; // Store variation prices
		// 	}
		// }

		// Find the lowest price variation
		if (!empty($prices)) {
			$lowest_variation_id = array_keys($prices, min($prices))[0];
			$lowest_variation = wc_get_product($lowest_variation_id);

			if ($lowest_variation instanceof WC_Product_Variation) {
				// Apply discount logic to the lowest price variation
				$discount = 0;
				$product_attribute = $lowest_variation->get_attribute(self::ATTRIBUTE);
				$discount_category = $this->get_discount_category($product);

				if ($discount_category && strpos($product_attribute, self::VAR_1) !== false) {
					$discount = (float) get_option("_lp_discount_{$discount_category}_" . self::VAR_1 . "_level_1", 0);
				} elseif ($discount_category && strpos($product_attribute, self::VAR_2) !== false) {
					$discount = (float) get_option("_lp_discount_{$discount_category}_" . self::VAR_2 . "_level_1", 0);
				}

				// dd( $discount_category );

				return max(0, $lowest_variation->get_price() - $discount);
			}
		}
		// }

		// For simple products or others, return the product's price
		//return $product->get_price();
	}

	public function change_price_html($price_html, $product)
	{
		$discounted_price = $this->get_variation_price($product);

		// Format the price for WooCommerce display
		return is_numeric($discounted_price) ? wc_price($discounted_price) : $price_html;
	}



}



new LoyaltyProgramService();
