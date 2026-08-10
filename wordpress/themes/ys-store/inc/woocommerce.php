<?php
/**
 * WooCommerce integration.
 *
 * Only loaded when WooCommerce is active — see functions.php.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

/* =============================================================================
   LAYOUT WRAPPERS
   Woo expects the theme to open and close a content wrapper around every one of
   its pages. Ours is a <main> that the skip link can target.
   ========================================================================== */

remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

/**
 * Open the content wrapper.
 */
function ys_store_wrapper_start() {
	echo '<main id="content" class="ys-main">';
}
add_action( 'woocommerce_before_main_content', 'ys_store_wrapper_start', 10 );

/**
 * Close the content wrapper.
 */
function ys_store_wrapper_end() {
	echo '</main>';
}
add_action( 'woocommerce_after_main_content', 'ys_store_wrapper_end', 10 );

/*
 * The default sidebar is removed outright. Filters live in the shop toolbar,
 * where they are visible on a phone without a customer opening a drawer first.
 */
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/* =============================================================================
   ARCHIVE
   Woo's default loop hooks are all removed; content-product.php prints the card
   in one pass instead. Hook-by-hook assembly is what makes a Woo card hard to
   restyle — every element arrives from a different callback with no shared
   wrapper to hang a grid on.
   ========================================================================== */

remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

// The breadcrumb is printed by the archive template, inside the page header.
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

/**
 * Products per page.
 *
 * @return int
 */
function ys_store_loop_columns() {
	return (int) get_theme_mod( 'ys_shop_columns', 4 );
}
add_filter( 'loop_shop_columns', 'ys_store_loop_columns' );

/**
 * @return int
 */
function ys_store_products_per_page() {
	return (int) get_theme_mod( 'ys_products_per_page', 12 );
}
add_filter( 'loop_shop_per_page', 'ys_store_products_per_page', 20 );

/**
 * Sale badge text.
 *
 * A percentage is more persuasive than the word "Sale" and it is information
 * rather than decoration — the customer can decide without opening the product.
 *
 * @param string     $html    Default markup.
 * @param WP_Post    $post    Post object.
 * @param WC_Product $product Product.
 * @return string
 */
function ys_store_sale_flash( $html, $post, $product ) {
	$percentage = ys_store_sale_percentage( $product );

	if ( ! $percentage ) {
		return '<span class="chip chip--sale">' . esc_html__( 'Sale', 'ys-store' ) . '</span>';
	}

	return '<span class="chip chip--sale">' . sprintf(
		/* translators: %d: discount percentage. */
		esc_html__( '-%d%%', 'ys-store' ),
		absint( $percentage )
	) . '</span>';
}
add_filter( 'woocommerce_sale_flash', 'ys_store_sale_flash', 10, 3 );

/**
 * The largest discount across a product's prices.
 *
 * A variable product has one regular/sale pair per variation, so the honest
 * headline is the biggest saving available — that is the one the customer will
 * find if they go looking.
 *
 * @param WC_Product $product Product.
 * @return int Percentage, or 0 when nothing is discounted.
 */
function ys_store_sale_percentage( $product ) {
	if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
		return 0;
	}

	$pairs = array();

	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );

			if ( $child ) {
				$pairs[] = array( (float) $child->get_regular_price(), (float) $child->get_sale_price() );
			}
		}
	} else {
		$pairs[] = array( (float) $product->get_regular_price(), (float) $product->get_sale_price() );
	}

	$best = 0;

	foreach ( $pairs as list( $regular, $sale ) ) {
		if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
			continue;
		}

		$best = max( $best, (int) round( ( ( $regular - $sale ) / $regular ) * 100 ) );
	}

	return $best;
}

/**
 * A stock line the customer can act on.
 *
 * "In stock" tells nobody anything. A low count is a real fact about
 * availability and is worth surfacing; the threshold is WooCommerce's own
 * low-stock setting, so it is never invented for urgency.
 *
 * @param WC_Product $product Product.
 * @return string HTML, or '' when stock is not managed.
 */
function ys_store_stock_line( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	if ( ! $product->is_in_stock() ) {
		return '<span class="ys-stock ys-stock--out">' . esc_html__( 'Out of stock', 'ys-store' ) . '</span>';
	}

	if ( ! $product->managing_stock() ) {
		return '<span class="ys-stock ys-stock--in">' . esc_html__( 'In stock', 'ys-store' ) . '</span>';
	}

	$qty       = (int) $product->get_stock_quantity();
	$threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );

	if ( $qty > 0 && $qty <= $threshold ) {
		return '<span class="ys-stock ys-stock--low">' . sprintf(
			/* translators: %d: units remaining. */
			esc_html( _n( 'Only %d left', 'Only %d left', $qty, 'ys-store' ) ),
			$qty
		) . '</span>';
	}

	return '<span class="ys-stock ys-stock--in">' . esc_html__( 'In stock', 'ys-store' ) . '</span>';
}

/* =============================================================================
   SINGLE PRODUCT
   ========================================================================== */

/**
 * Trust points under the buy button.
 *
 * Pulled from the Customiser so a store owner can state their own terms rather
 * than inherit a set of claims the theme invented.
 */
function ys_store_product_trust() {
	$items = array_filter(
		array(
			get_theme_mod( 'ys_trust_1', __( 'Free delivery over the free-shipping threshold', 'ys-store' ) ),
			get_theme_mod( 'ys_trust_2', __( '30-day returns, no questions', 'ys-store' ) ),
			get_theme_mod( 'ys_trust_3', __( '2-year warranty on every item', 'ys-store' ) ),
		)
	);

	if ( ! $items ) {
		return;
	}

	echo '<ul class="ys-trust">';
	foreach ( $items as $item ) {
		echo '<li>' . esc_html( $item ) . '</li>';
	}
	echo '</ul>';
}
add_action( 'woocommerce_single_product_summary', 'ys_store_product_trust', 35 );

/**
 * Stock line in the product summary, just above the add-to-cart form.
 */
function ys_store_single_stock() {
	global $product;

	// Variable products report stock per variation; Woo prints that itself.
	if ( $product && ! $product->is_type( 'variable' ) ) {
		echo wp_kses_post( ys_store_stock_line( $product ) );
	}
}
add_action( 'woocommerce_single_product_summary', 'ys_store_single_stock', 25 );

/**
 * Quantity input with plus/minus buttons.
 *
 * A bare number spinner is a 12px hit target on a phone. The buttons are wired
 * in cart.js; without JS the field is still a working number input.
 */
function ys_store_quantity_minus() {
	echo '<button type="button" class="ys-qty-btn" data-qty="-1" aria-label="' . esc_attr__( 'Decrease quantity', 'ys-store' ) . '">−</button>';
}
add_action( 'woocommerce_before_quantity_input_field', 'ys_store_quantity_minus' );

/**
 * Quantity plus button.
 */
function ys_store_quantity_plus() {
	echo '<button type="button" class="ys-qty-btn" data-qty="1" aria-label="' . esc_attr__( 'Increase quantity', 'ys-store' ) . '">+</button>';
}
add_action( 'woocommerce_after_quantity_input_field', 'ys_store_quantity_plus' );

/**
 * Give Woo's single add-to-cart button the house classes.
 *
 * @return string
 */
function ys_store_single_add_to_cart_class() {
	return 'single_add_to_cart_button btn btn--primary btn--lg';
}
add_filter( 'woocommerce_order_button_class', 'ys_store_single_add_to_cart_class' );

/**
 * Loop add-to-cart button attributes.
 *
 * The data-* pairs are what analytics.js reads to build a GA4 `add_to_cart`
 * event without a second round trip to the server.
 *
 * @param array      $args    Existing args.
 * @param WC_Product $product Product.
 * @return array
 */
function ys_store_loop_add_to_cart_args( $args, $product ) {
	$args['class'] = 'btn btn--ghost btn--sm ys-add ' . ( $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '' );

	$args['attributes']['data-ys-name']     = $product->get_name();
	$args['attributes']['data-ys-price']    = wc_get_price_to_display( $product );
	$args['attributes']['data-ys-currency'] = get_woocommerce_currency();
	$args['attributes']['data-cursor']      = __( 'Add', 'ys-store' );

	return $args;
}
add_filter( 'woocommerce_loop_add_to_cart_args', 'ys_store_loop_add_to_cart_args', 10, 2 );

/* =============================================================================
   CART FRAGMENTS
   Every AJAX add-to-cart response carries these back, which is how the header
   count and the drawer contents stay correct without a page load.
   ========================================================================== */

/**
 * @param array $fragments Existing fragments.
 * @return array
 */
function ys_store_cart_fragments( $fragments ) {
	ob_start();
	ys_store_cart_count();
	$fragments['span.ys-cart-count'] = ob_get_clean();

	/*
	 * A fragment replaces the element the selector matched, so its value has to
	 * carry that element itself — returning only the inner markup would leave
	 * the drawer without the wrapper the next refresh looks for, and the second
	 * update of a session would silently do nothing.
	 */
	ob_start();
	echo '<div class="ys-drawer__contents">';
	get_template_part( 'template-parts/cart-drawer-contents' );
	echo '</div>';
	$fragments['div.ys-drawer__contents'] = ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'ys_store_cart_fragments' );

/**
 * Remember that a non-AJAX add happened, so the drawer can open after the
 * reload that follows it.
 *
 * The single-product form is a real POST — it has to be, because a variable
 * product's validation lives server side. Without this the customer submits the
 * form, the page reloads, and the only sign anything happened is a notice above
 * the fold they may already have scrolled past.
 *
 * @param string $cart_key   Cart item key.
 * @param int    $product_id Product id.
 */
function ys_store_flag_add_to_cart( $cart_key, $product_id ) {
	if ( wp_doing_ajax() || ! WC()->session ) {
		return;
	}

	WC()->session->set( 'ys_open_drawer', 1 );
}
add_action( 'woocommerce_add_to_cart', 'ys_store_flag_add_to_cart', 10, 2 );

/**
 * Print the one-shot flag for cart.js.
 *
 * Priority 5 so it lands before wp_print_footer_scripts (priority 20) puts
 * cart.js on the page.
 */
function ys_store_maybe_open_drawer() {
	if ( ! WC()->session || ! WC()->session->get( 'ys_open_drawer' ) ) {
		return;
	}

	// One shot. Left set, every subsequent page view would reopen the drawer.
	WC()->session->set( 'ys_open_drawer', null );

	echo '<script>window.ysOpenCartOnLoad=true;</script>';
}
add_action( 'wp_footer', 'ys_store_maybe_open_drawer', 5 );

/**
 * The header cart badge.
 */
function ys_store_cart_count() {
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;

	printf(
		'<span class="ys-cart-count" data-count="%1$d" aria-hidden="true">%1$d</span>',
		absint( $count )
	);
}

/* =============================================================================
   CART & CHECKOUT WRAPPERS
   These two pages get their layout from wrappers printed around Woo's own
   templates rather than from template overrides. cart.php and form-checkout.php
   change between WooCommerce releases and carry the logic that actually takes
   money; a copy in the theme would silently go stale. Wrapping leaves Woo's
   markup alone and still gives the CSS the two grid parents it needs.
   ========================================================================== */

/**
 * Open the cart page layout.
 */
function ys_store_cart_wrapper_start() {
	echo '<div class="ys-cart"><div class="wrap"><h1 class="ys-split" data-anim="up">' .
		esc_html__( 'Your cart', 'ys-store' ) .
		'</h1><div class="ys-cart__layout">';
}
add_action( 'woocommerce_before_cart', 'ys_store_cart_wrapper_start', 5 );

/**
 * Close the cart page layout.
 */
function ys_store_cart_wrapper_end() {
	echo '</div></div></div>';
}
add_action( 'woocommerce_after_cart', 'ys_store_cart_wrapper_end', 50 );

/**
 * Open the checkout layout, with a three-step indicator.
 */
function ys_store_checkout_wrapper_start() {
	$steps = array(
		__( 'Cart', 'ys-store' )    => false,
		__( 'Details', 'ys-store' ) => true,
		__( 'Payment', 'ys-store' ) => false,
	);

	echo '<div class="ys-checkout"><div class="wrap">';
	echo '<h1>' . esc_html__( 'Checkout', 'ys-store' ) . '</h1>';
	echo '<ol class="ys-checkout__steps">';

	foreach ( $steps as $label => $current ) {
		printf(
			'<li class="%s">%s</li>',
			$current ? 'is-current' : '',
			esc_html( $label )
		);
	}

	echo '</ol>';
}
add_action( 'woocommerce_before_checkout_form', 'ys_store_checkout_wrapper_start', 5 );

/**
 * Close the checkout layout.
 */
function ys_store_checkout_wrapper_end() {
	echo '</div></div>';
}
add_action( 'woocommerce_after_checkout_form', 'ys_store_checkout_wrapper_end', 50 );

/* =============================================================================
   CHECKOUT
   ========================================================================== */

/**
 * Placeholders on checkout fields.
 *
 * Woo labels every field and then leaves the input empty, which on a phone —
 * where the label scrolls out of view above the keyboard — leaves the customer
 * typing into an unmarked box. Mirroring the label into the placeholder costs
 * nothing and removes the ambiguity.
 *
 * @param array $fields Checkout fields.
 * @return array
 */
function ys_store_checkout_placeholders( $fields ) {
	foreach ( $fields as $section => $section_fields ) {
		foreach ( $section_fields as $key => $field ) {
			if ( empty( $field['placeholder'] ) && ! empty( $field['label'] ) && 'select' !== ( $field['type'] ?? '' ) ) {
				$fields[ $section ][ $key ]['placeholder'] = $field['label'];
			}
		}
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'ys_store_checkout_placeholders' );

/**
 * Remove the "Have a coupon?" toggle from checkout and keep coupons on the cart
 * page only.
 *
 * A coupon field at the payment step is a documented abandonment trigger: it
 * tells a customer without a code that they are paying more than someone else,
 * and sends them to a discount-hunting tab they may not come back from.
 * Filterable, because a store running a code-led campaign needs it there.
 */
function ys_store_checkout_coupon() {
	if ( apply_filters( 'ys_store_hide_checkout_coupon', true ) ) {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
	}
}
add_action( 'woocommerce_before_checkout_form', 'ys_store_checkout_coupon', 1 );

/* =============================================================================
   MISC
   ========================================================================== */

/**
 * Related products count, matched to the grid so the row is never ragged.
 *
 * @param array $args Existing args.
 * @return array
 */
function ys_store_related_args( $args ) {
	$args['posts_per_page'] = ys_store_loop_columns();
	$args['columns']        = ys_store_loop_columns();

	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'ys_store_related_args' );

/**
 * Swap Woo's default 300px placeholder for the theme's own, so a catalogue with
 * missing photography still looks deliberate.
 *
 * @param string $src Placeholder URL.
 * @return string
 */
function ys_store_placeholder_img_src( $src ) {
	$local = YS_STORE_DIR . 'assets/img/placeholder.svg';

	return file_exists( $local ) ? YS_STORE_URI . 'assets/img/placeholder.svg' : $src;
}
add_filter( 'woocommerce_placeholder_img_src', 'ys_store_placeholder_img_src' );

/**
 * Message shown after an add-to-cart, when JS is unavailable and Woo falls back
 * to a page reload.
 *
 * @param string $message Default message.
 * @return string
 */
function ys_store_add_to_cart_message( $message ) {
	return $message;
}
add_filter( 'wc_add_to_cart_message_html', 'ys_store_add_to_cart_message' );
