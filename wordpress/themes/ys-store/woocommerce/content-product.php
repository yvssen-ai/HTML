<?php
/**
 * One product in the loop.
 *
 * Overrides woocommerce/templates/content-product.php. inc/woocommerce.php has
 * already removed every default loop hook, so this template is the single place
 * a card is described — see ys_store_product_card().
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

ys_store_product_card( $product );
