<?php
/**
 * Product archive — the shop, and every category and tag archive.
 *
 * Overrides woocommerce/templates/archive-product.php.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

do_action( 'woocommerce_before_main_content' );
?>

<div class="ys-shop">
	<div class="wrap">

		<header class="ys-shop__head">
			<?php woocommerce_breadcrumb(); ?>

			<h1 data-anim="up" class="ys-split">
				<?php woocommerce_page_title(); ?>
			</h1>

			<?php
			// A category's own description, set in Products → Categories.
			do_action( 'woocommerce_archive_description' );
			?>
		</header>

		<?php
		/*
		 * The toolbar. Woo's `woocommerce_before_shop_loop` carries the result
		 * count and the ordering dropdown; wrapping them rather than removing
		 * and reprinting them means a plugin that hooks in here — a filter
		 * plugin, a per-page selector — still lands in the right place.
		 */
		?>
		<div class="ys-shop__toolbar">
			<?php
			$top_categories = ( is_shop() || is_product_category() )
				? get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'hide_empty' => true,
						'number'     => 8,
						'orderby'    => 'count',
						'order'      => 'DESC',
						'parent'     => 0,
					)
				)
				: array();

			if ( $top_categories && ! is_wp_error( $top_categories ) ) :
				$current = is_product_category() ? get_queried_object_id() : 0;
				?>
				<nav class="ys-shop__filters" aria-label="<?php esc_attr_e( 'Product categories', 'ys-store' ); ?>">
					<a class="chip <?php echo is_shop() ? 'is-active' : ''; ?>"
					   href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
						<?php esc_html_e( 'All', 'ys-store' ); ?>
					</a>

					<?php foreach ( $top_categories as $category ) : ?>
						<a class="chip <?php echo ( $current === $category->term_id ) ? 'is-active' : ''; ?>"
						   href="<?php echo esc_url( get_term_link( $category ) ); ?>">
							<?php echo esc_html( $category->name ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php else : ?>
				<div></div>
			<?php endif; ?>

			<div class="row">
				<?php
				woocommerce_result_count();
				woocommerce_catalog_ordering();
				?>
			</div>
		</div>

		<?php if ( is_active_sidebar( 'shop-filters' ) ) : ?>
			<div class="ys-filters">
				<?php dynamic_sidebar( 'shop-filters' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( woocommerce_product_loop() ) : ?>

			<?php
			woocommerce_product_loop_start();

			if ( wc_get_loop_prop( 'total' ) ) {
				while ( have_posts() ) {
					the_post();

					/**
					 * Fires before a product in the loop.
					 *
					 * @since 1.0.0
					 */
					do_action( 'woocommerce_shop_loop' );

					wc_get_template_part( 'content', 'product' );
				}
			}

			woocommerce_product_loop_end();
			?>

			<?php
			// Woo's own pagination, restyled by .woocommerce-pagination in CSS.
			woocommerce_pagination();
			?>

		<?php else : ?>

			<?php do_action( 'woocommerce_no_products_found' ); ?>

		<?php endif; ?>

	</div>
</div>

<?php
do_action( 'woocommerce_after_main_content' );

get_footer( 'shop' );
