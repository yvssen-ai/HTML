<?php
/**
 * AJAX endpoints for the cart drawer.
 *
 * WooCommerce ships an AJAX add-to-cart but nothing for changing a quantity or
 * removing a line without a page load, so the drawer needs these two. Both
 * return the standard fragment payload, which means the header badge, the
 * drawer body and the totals all update from one response.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set the quantity of a cart line.
 *
 * A quantity of 0 removes the line, which is what the drawer's minus button
 * does when it reaches the bottom.
 */
function ys_store_ajax_set_quantity() {
	check_ajax_referer( 'ys_cart', 'nonce' );

	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	$qty = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : null;

	if ( '' === $key || null === $qty || $qty < 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ys-store' ) ), 400 );
	}

	$cart = WC()->cart;
	$item = $cart->get_cart_item( $key );

	if ( ! $item ) {
		wp_send_json_error( array( 'message' => __( 'That item is no longer in your cart.', 'ys-store' ) ), 404 );
	}

	/*
	 * set_quantity() with $refresh_totals = true recalculates before the
	 * fragments are rendered. Without it the drawer would show the new line
	 * against the old total for one frame — the kind of flicker that reads as a
	 * pricing bug on a payment screen.
	 */
	$updated = $cart->set_quantity( $key, $qty, true );

	if ( ! $updated ) {
		wp_send_json_error( array( 'message' => __( 'Could not update that item.', 'ys-store' ) ), 400 );
	}

	ys_store_send_cart_fragments();
}
add_action( 'wp_ajax_ys_set_quantity', 'ys_store_ajax_set_quantity' );
add_action( 'wp_ajax_nopriv_ys_set_quantity', 'ys_store_ajax_set_quantity' );

/**
 * Remove a cart line.
 */
function ys_store_ajax_remove_item() {
	check_ajax_referer( 'ys_cart', 'nonce' );

	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

	if ( '' === $key ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ys-store' ) ), 400 );
	}

	$cart = WC()->cart;
	$item = $cart->get_cart_item( $key );

	if ( ! $item ) {
		// Already gone. Not an error from the customer's point of view — send
		// the current cart so the drawer converges on the truth either way.
		ys_store_send_cart_fragments();
	}

	$cart->remove_cart_item( $key );
	$cart->calculate_totals();

	ys_store_send_cart_fragments(
		array(
			/* translators: %s: product name. */
			'message' => sprintf( __( '%s removed', 'ys-store' ), $item['data'] ? $item['data']->get_name() : __( 'Item', 'ys-store' ) ),
		)
	);
}
add_action( 'wp_ajax_ys_remove_item', 'ys_store_ajax_remove_item' );
add_action( 'wp_ajax_nopriv_ys_remove_item', 'ys_store_ajax_remove_item' );

/**
 * Return the current cart as Woo fragments plus a small summary the drawer uses
 * for its free-shipping bar and for analytics events.
 *
 * @param array $extra Extra keys to merge into the payload.
 */
function ys_store_send_cart_fragments( $extra = array() ) {
	$cart = WC()->cart;

	// Woo builds the fragment array from this filter; calling it directly is
	// what `wc_ajax_get_refreshed_fragments` does internally.
	$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

	$payload = array(
		'fragments' => $fragments,
		'cart_hash' => $cart->get_cart_hash(),
		'count'     => $cart->get_cart_contents_count(),
		'subtotal'  => (float) $cart->get_subtotal(),
		'total'     => (float) $cart->get_total( 'edit' ),
		'currency'  => get_woocommerce_currency(),
	);

	wp_send_json_success( array_merge( $payload, $extra ) );
}
