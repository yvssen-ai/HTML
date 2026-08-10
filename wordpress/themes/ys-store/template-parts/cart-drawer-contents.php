<?php
/**
 * Cart drawer contents.
 *
 * Re-rendered on every fragment refresh, so it must be cheap and it must never
 * assume it is running inside a page request — an AJAX add-to-cart reaches it
 * with no queried object at all.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

$cart = WC()->cart;

if ( ! $cart || $cart->is_empty() ) :
	?>
	<div class="ys-drawer__body">
		<div class="ys-drawer__empty">
			<p><?php esc_html_e( 'Nothing here yet.', 'ys-store' ); ?></p>
			<a class="btn btn--primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
				<?php esc_html_e( 'Browse the catalogue', 'ys-store' ); ?>
			</a>
		</div>
	</div>
	<?php
	return;
endif;

$threshold = ys_store_free_shipping_threshold();
$subtotal  = (float) $cart->get_displayed_subtotal();
$remaining = $threshold > 0 ? max( 0, $threshold - $subtotal ) : 0;
?>

<div class="ys-drawer__body">
	<?php if ( $threshold > 0 ) : ?>
		<div class="ys-freeship">
			<?php if ( $remaining > 0 ) : ?>
				<span>
					<?php
					printf(
						/* translators: %s: formatted amount remaining. */
						esc_html__( '%s away from free shipping', 'ys-store' ),
						wp_kses_post( wc_price( $remaining ) )
					);
					?>
				</span>
			<?php else : ?>
				<span><?php esc_html_e( 'Free shipping unlocked', 'ys-store' ); ?></span>
			<?php endif; ?>
			<div class="ys-freeship__bar">
				<i style="width:<?php echo esc_attr( min( 100, ( $subtotal / $threshold ) * 100 ) ); ?>%"></i>
			</div>
		</div>
	<?php endif; ?>

	<?php
	foreach ( $cart->get_cart() as $key => $item ) :
		$product = $item['data'];

		/*
		 * A line can outlive its product: an item is removed from the catalogue
		 * while it sits in someone's session. Woo's own template checks the same
		 * three conditions before rendering, and skipping is correct — the line
		 * is dropped from the totals too.
		 */
		if ( ! $product || ! $product->exists() || $item['quantity'] <= 0 ) {
			continue;
		}

		$permalink = $product->is_visible() ? $product->get_permalink( $item ) : '';
		?>
		<div class="ys-line" data-key="<?php echo esc_attr( $key ); ?>">
			<div class="ys-line__media">
				<?php
				$thumb = $product->get_image( 'woocommerce_thumbnail' );

				if ( $permalink ) {
					printf( '<a href="%s" tabindex="-1" aria-hidden="true">%s</a>', esc_url( $permalink ), wp_kses_post( $thumb ) );
				} else {
					echo wp_kses_post( $thumb );
				}
				?>
			</div>

			<div>
				<?php if ( $permalink ) : ?>
					<a class="ys-line__name" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
				<?php else : ?>
					<span class="ys-line__name"><?php echo esc_html( $product->get_name() ); ?></span>
				<?php endif; ?>

				<?php
				$meta = wc_get_formatted_cart_item_data( $item, true );

				if ( $meta ) {
					echo '<div class="ys-line__variation">' . esc_html( $meta ) . '</div>';
				}
				?>

				<div class="ys-line__controls">
					<div class="quantity">
						<button type="button" class="ys-qty-btn" data-qty="-1"
								aria-label="<?php esc_attr_e( 'Decrease quantity', 'ys-store' ); ?>">−</button>
						<input type="number" class="qty" value="<?php echo esc_attr( $item['quantity'] ); ?>"
							   min="0" step="1"
							   aria-label="<?php esc_attr_e( 'Quantity', 'ys-store' ); ?>">
						<button type="button" class="ys-qty-btn" data-qty="1"
								aria-label="<?php esc_attr_e( 'Increase quantity', 'ys-store' ); ?>">+</button>
					</div>
					<button type="button" class="ys-line__remove" data-ys-remove>
						<?php esc_html_e( 'Remove', 'ys-store' ); ?>
					</button>
				</div>
			</div>

			<div class="ys-line__price">
				<?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_subtotal', $cart->get_product_subtotal( $product, $item['quantity'] ), $item, $key ) ); ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="ys-drawer__foot">
	<div class="ys-drawer__row">
		<span><?php esc_html_e( 'Subtotal', 'ys-store' ); ?></span>
		<span><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></span>
	</div>

	<?php if ( $cart->get_cart_discount_total() > 0 ) : ?>
		<div class="ys-drawer__row">
			<span><?php esc_html_e( 'Discount', 'ys-store' ); ?></span>
			<span>−<?php echo wp_kses_post( wc_price( $cart->get_cart_discount_total() ) ); ?></span>
		</div>
	<?php endif; ?>

	<div class="ys-drawer__row ys-drawer__row--total">
		<span><?php esc_html_e( 'Total', 'ys-store' ); ?></span>
		<span><?php echo wp_kses_post( $cart->get_total() ); ?></span>
	</div>

	<p class="mono" style="color:var(--fg-faint);margin:0">
		<?php esc_html_e( 'Shipping and taxes calculated at checkout', 'ys-store' ); ?>
	</p>

	<a class="btn btn--primary btn--block" href="<?php echo esc_url( wc_get_checkout_url() ); ?>" data-ys-checkout>
		<?php esc_html_e( 'Checkout', 'ys-store' ); ?>
	</a>
	<a class="btn btn--ghost btn--block btn--sm" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<?php esc_html_e( 'View full cart', 'ys-store' ); ?>
	</a>
</div>
