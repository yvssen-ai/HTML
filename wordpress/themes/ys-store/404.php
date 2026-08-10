<?php
/**
 * 404.
 *
 * A dead end on a store is a lost sale, so this page does not stop at an
 * apology: it offers the search box and the four best-selling products, which
 * are the two routes back into the catalogue most likely to be taken.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="content" class="ys-main">
	<section class="section" style="padding-top:calc(var(--nav-h) + var(--section-y))">
		<div class="wrap">
			<span class="eyebrow" data-anim="up"><?php esc_html_e( 'Error 404', 'ys-store' ); ?></span>
			<h1 class="grad ys-split" data-anim="up" style="margin:1rem 0">
				<?php esc_html_e( 'This page has moved on', 'ys-store' ); ?>
			</h1>
			<p class="lede" data-anim="up" style="margin-bottom:2rem">
				<?php esc_html_e( 'The link is dead, but the catalogue is not. Try a search.', 'ys-store' ); ?>
			</p>

			<div data-anim="up" style="max-width:520px"><?php get_search_form(); ?></div>

			<?php
			if ( class_exists( 'WooCommerce' ) ) :
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
						<?php esc_html_e( 'Popular right now', 'ys-store' ); ?>
					</h2>

					<ul class="products columns-4" data-ys-list="404 recovery">
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
					<?php
				endif;
			endif;
			?>
		</div>
	</section>
</main>

<?php
get_footer();
