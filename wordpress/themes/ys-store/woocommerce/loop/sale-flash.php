<?php
/**
 * Sale badge.
 *
 * Overrides woocommerce/templates/loop/sale-flash.php. The markup itself comes
 * from the `woocommerce_sale_flash` filter in inc/woocommerce.php, which turns
 * the word "Sale" into the actual discount.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

global $post, $product;

if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
	return;
}

echo wp_kses_post(
	apply_filters(
		'woocommerce_sale_flash',
		'<span class="chip chip--sale">' . esc_html__( 'Sale', 'ys-store' ) . '</span>',
		$post,
		$product
	)
);
