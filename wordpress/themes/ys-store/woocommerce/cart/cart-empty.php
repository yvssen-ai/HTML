<?php
/**
 * Empty cart.
 *
 * Overrides woocommerce/templates/cart/cart-empty.php. Woo's version is a
 * notice and a "Return to shop" button. An empty cart is the cheapest place on
 * the site to recover a sale, so this one also shows what is selling.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ys-cart">
	<div class="wrap">
		<h1 class="ys-split" data-anim="up"><?php esc_html_e( 'Your cart is empty', 'ys-store' ); ?></h1>

		<p class="lede" data-anim="up" style="margin:1rem 0 2rem">
			<?php esc_html_e( 'Nothing in here yet. Here is what other people are buying.', 'ys-store' ); ?>
		</p>

		<?php
		/**
		 * Woo prints the "your cart is empty" notice here.
		 *
		 * @hooked wc_empty_cart_message - 10
		 */
		do_action( 'woocommerce_cart_is_empty' );
		?>

		<a class="btn btn--primary btn--lg" data-anim="up" data-magnet
		   href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
			<?php esc_html_e( 'Browse the catalogue', 'ys-store' ); ?>
		</a>

		<?php
		$best = wc_get_products(
			array(
				'limit'      => 4,
				'orderby'    => 'meta_value_num',
				'meta_key'   => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery
				'order'      => 'DESC',
				'status'     => 'publish',
				'visibility' => 'catalog',
			)
		);

		if ( $best ) :
			?>
			<h2 style="margin:4rem 0 1.5rem;font-size:var(--t-xl)">
				<?php esc_html_e( 'Best sellers', 'ys-store' ); ?>
			</h2>

			<ul class="products columns-4" data-ys-list="Empty cart recovery">
				<?php
				global $post;

				foreach ( $best as $product ) {
					$post = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
					setup_postdata( $post );
					ys_store_product_card( $product );
				}

				wp_reset_postdata();
				?>
			</ul>
		<?php endif; ?>
	</div>
</div>
