<?php
/**
 * Single product.
 *
 * Overrides woocommerce/templates/single-product.php. The two-column layout is
 * built here rather than through hooks so the gallery and the summary sit in
 * sibling grid cells — which is what lets the summary be sticky on desktop.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

do_action( 'woocommerce_before_main_content' );

while ( have_posts() ) :
	the_post();
	global $product;
	?>

	<div class="ys-product">
		<div class="wrap">
			<?php woocommerce_breadcrumb(); ?>

			<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'ys-product__layout', $product ); ?>
				 data-ys-product="<?php echo esc_attr( $product->get_id() ); ?>"
				 data-ys-name="<?php echo esc_attr( $product->get_name() ); ?>"
				 data-ys-price="<?php echo esc_attr( wc_get_price_to_display( $product ) ); ?>">

				<div class="ys-product__gallery" data-anim="up">
					<?php
					/**
					 * Prints the sale flash and the gallery.
					 *
					 * @hooked woocommerce_show_product_sale_flash - 10
					 * @hooked woocommerce_show_product_images - 20
					 */
					do_action( 'woocommerce_before_single_product_summary' );
					?>
				</div>

				<div class="summary entry-summary ys-product__summary" data-anim="up">
					<?php
					/**
					 * The title, price, excerpt, add-to-cart form, meta and the
					 * theme's own stock line and trust list.
					 *
					 * @hooked woocommerce_template_single_title - 5
					 * @hooked woocommerce_template_single_rating - 10
					 * @hooked woocommerce_template_single_price - 10
					 * @hooked woocommerce_template_single_excerpt - 20
					 * @hooked ys_store_single_stock - 25
					 * @hooked woocommerce_template_single_add_to_cart - 30
					 * @hooked ys_store_product_trust - 35
					 * @hooked woocommerce_template_single_meta - 40
					 * @hooked woocommerce_template_single_sharing - 50
					 */
					do_action( 'woocommerce_single_product_summary' );
					?>
				</div>
			</div>

			<?php
			/**
			 * Tabs, upsells and related products.
			 *
			 * @hooked woocommerce_output_product_data_tabs - 10
			 * @hooked woocommerce_upsell_display - 15
			 * @hooked woocommerce_output_related_products - 20
			 */
			do_action( 'woocommerce_after_single_product_summary' );
			?>
		</div>
	</div>

	<?php
endwhile;

do_action( 'woocommerce_after_main_content' );

get_footer( 'shop' );
