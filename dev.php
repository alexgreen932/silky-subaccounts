<?php 


add_filter('woocommerce_product_variation_get_regular_price', 'custom_top_price', 99, 2 );
add_filter('woocommerce_product_variation_get_price', 'custom_top_price' , 99, 2 );

// Variations (of a variable product)
add_filter('woocommerce_variation_prices_price', 'custom_variation_select_price', 99, 3 );
add_filter('woocommerce_variation_prices_regular_price', 'custom_variation_select_price', 99, 3 );

function custom_top_price( $price, $product ) {
    // Delete product cached price  (if needed)
    wc_delete_product_transients($product->get_id());

    return $price - 30; 
}

function custom_variation_select_price( $price, $variation, $product ) {
    // Delete product cached price  (if needed)
    wc_delete_product_transients($variation->get_id());

    return $price - 20; 
}



