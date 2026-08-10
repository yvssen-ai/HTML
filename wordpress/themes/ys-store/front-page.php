<?php
/**
 * Front page.
 *
 * The storefront: hero, promise strip, categories, a best-sellers rail, a
 * proof block and a newsletter. Every product query here is a live
 * WooCommerce query, so the page reflects the catalogue rather than a set of
 * placeholders someone has to remember to update.
 *
 * A site whose front page is set to a static page in Settings → Reading gets
 * that page instead; this template only runs when the front page is set to
 * "your latest posts" or explicitly assigned.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

get_header();

$has_woo = class_exists( 'WooCommerce' );
$shop_url = $has_woo ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>

<main id="content" class="ys-main">

	<?php get_template_part( 'template-parts/hero' ); ?>

	<?php
	if ( get_theme_mod( 'ys_marquee', true ) ) {
		$phrases = array_filter( array_map( 'trim', explode( ',', (string) get_theme_mod( 'ys_marquee_text', '' ) ) ) );
		ys_store_marquee( $phrases );
	}
	?>

	<?php if ( $has_woo ) : ?>

		<?php
		/* ---- Categories ------------------------------------------------- */
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 6,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
			)
		);

		if ( $categories && ! is_wp_error( $categories ) ) :
			?>
			<section class="section" id="categories">
				<div class="wrap">
					<?php
					ys_store_section_head(
						array(
							'eyebrow'   => __( 'Browse', 'ys-store' ),
							'title'     => __( 'Where to start', 'ys-store' ),
							'link'      => $shop_url,
							'link_text' => __( 'Everything', 'ys-store' ),
						)
					);
					?>

					<div class="grid grid--3">
						<?php
						foreach ( $categories as $index => $category ) :
							$thumb_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
							?>
							<article class="card" data-anim="up">
								<?php if ( $thumb_id ) : ?>
									<div class="card__media">
										<?php echo wp_get_attachment_image( $thumb_id, 'ys-portrait', false, array( 'alt' => '' ) ); ?>
									</div>
								<?php endif; ?>

								<span class="card__index"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
								<h3><?php echo esc_html( $category->name ); ?></h3>
								<p>
									<?php
									printf(
										/* translators: %d: number of products. */
										esc_html( _n( '%d product', '%d products', $category->count, 'ys-store' ) ),
										absint( $category->count )
									);
									?>
								</p>

								<a class="card__link" href="<?php echo esc_url( get_term_link( $category ) ); ?>"
								   data-cursor="<?php esc_attr_e( 'Open', 'ys-store' ); ?>">
									<span class="screen-reader-text"><?php echo esc_html( $category->name ); ?></span>
								</a>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php
		/* ---- Best sellers -------------------------------------------------
		 * `best_selling_products` is a Woo shortcode-backed query that reads the
		 * `total_sales` meta Woo maintains on every purchase. It is the one
		 * ordering on this page that is derived from real sales rather than
		 * from an editorial choice.
		 */
		$best = wc_get_products(
			array(
				'limit'      => 8,
				'orderby'    => 'meta_value_num',
				'meta_key'   => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery -- indexed by Woo.
				'order'      => 'DESC',
				'status'     => 'publish',
				'visibility' => 'catalog',
			)
		);

		if ( $best ) :
			?>
			<section class="section" id="best-sellers">
				<div class="wrap">
					<?php
					ys_store_section_head(
						array(
							'eyebrow'   => __( 'Moving fast', 'ys-store' ),
							'title'     => __( 'Best sellers', 'ys-store' ),
							'text'      => __( 'Ranked by what people actually buy, updated with every order.', 'ys-store' ),
							'link'      => $shop_url,
							'link_text' => __( 'Shop all', 'ys-store' ),
						)
					);
					?>

					<ul class="products columns-4" data-ys-list="Best sellers">
						<?php
						global $post;

						foreach ( $best as $product ) {
							// ys_store_product_card() calls the_permalink(), which
							// reads the global post — so it has to be set up here.
							$post = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
							setup_postdata( $post );
							ys_store_product_card( $product );
						}

						wp_reset_postdata();
						?>
					</ul>
				</div>
			</section>
		<?php endif; ?>

		<?php
		/* ---- On sale ------------------------------------------------------ */
		$sale_ids = wc_get_product_ids_on_sale();

		if ( $sale_ids ) :
			$on_sale = wc_get_products(
				array(
					'limit'      => 4,
					'include'    => $sale_ids,
					'orderby'    => 'date',
					'order'      => 'DESC',
					'status'     => 'publish',
					'visibility' => 'catalog',
				)
			);
			?>
			<?php if ( $on_sale ) : ?>
				<section class="section zone-light" id="on-sale">
					<div class="wrap">
						<?php
						ys_store_section_head(
							array(
								'eyebrow' => __( 'Reduced', 'ys-store' ),
								'title'   => __( 'On sale now', 'ys-store' ),
							)
						);
						?>

						<ul class="products columns-4" data-ys-list="On sale">
							<?php
							foreach ( $on_sale as $product ) {
								$post = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
								setup_postdata( $post );
								ys_store_product_card( $product );
							}

							wp_reset_postdata();
							?>
						</ul>
					</div>
				</section>
			<?php endif; ?>
		<?php endif; ?>

	<?php else : ?>

		<section class="section">
			<div class="wrap">
				<div class="ys-notice">
					<?php esc_html_e( 'Activate WooCommerce to turn this front page into a storefront. Until then the theme runs as a normal site.', 'ys-store' ); ?>
				</div>
			</div>
		</section>

	<?php endif; ?>

	<?php get_template_part( 'template-parts/stats' ); ?>
	<?php get_template_part( 'template-parts/newsletter' ); ?>

</main>

<?php
get_footer();
