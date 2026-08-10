<?php
/**
 * Template tags.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

/**
 * The brand lockup — a custom logo when one is set, the site title otherwise.
 */
function ys_store_brand() {
	if ( has_custom_logo() ) {
		$logo_id = get_theme_mod( 'custom_logo' );
		$logo    = wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'alt'      => get_bloginfo( 'name' ),
				'loading'  => 'eager',
				'decoding' => 'sync',
			)
		);

		printf(
			'<a class="ys-brand" href="%s" rel="home">%s</a>',
			esc_url( home_url( '/' ) ),
			$logo // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() returns escaped markup.
		);

		return;
	}

	printf(
		'<a class="ys-brand" href="%s" rel="home"><span class="grad">%s</span></a>',
		esc_url( home_url( '/' ) ),
		esc_html( get_bloginfo( 'name' ) )
	);
}

/**
 * A section heading: eyebrow, title, and an optional link on the right.
 *
 * @param array $args {
 *     @type string $eyebrow Small mono label above the title.
 *     @type string $title   The heading text.
 *     @type string $text    Optional supporting paragraph.
 *     @type string $link    Optional URL for the trailing action.
 *     @type string $link_text Label for that action.
 *     @type string $tag     Heading level, default h2.
 * }
 */
function ys_store_section_head( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'eyebrow'   => '',
			'title'     => '',
			'text'      => '',
			'link'      => '',
			'link_text' => __( 'View all', 'ys-store' ),
			'tag'       => 'h2',
		)
	);

	$tag = in_array( $args['tag'], array( 'h1', 'h2', 'h3' ), true ) ? $args['tag'] : 'h2';

	echo '<div class="sec-head">';
	echo '<div class="sec-head__text">';

	if ( $args['eyebrow'] ) {
		echo '<span class="eyebrow" data-anim="up">' . esc_html( $args['eyebrow'] ) . '</span>';
	}

	if ( $args['title'] ) {
		printf(
			'<%1$s class="ys-split" data-anim="up">%2$s</%1$s>',
			esc_attr( $tag ),
			esc_html( $args['title'] )
		);
	}

	if ( $args['text'] ) {
		echo '<p class="lede" data-anim="up">' . esc_html( $args['text'] ) . '</p>';
	}

	echo '</div>';

	if ( $args['link'] ) {
		printf(
			'<a class="btn btn--ghost" href="%s" data-anim="up" data-magnet>%s</a>',
			esc_url( $args['link'] ),
			esc_html( $args['link_text'] )
		);
	}

	echo '</div>';
}

/**
 * A marquee strip.
 *
 * The track is printed twice. The CSS animation translates it by exactly -50%,
 * so the duplicate lands on the origin at the instant the original leaves and
 * the loop has no seam to hide.
 *
 * @param array  $items   Phrases to scroll.
 * @param string $classes Extra classes, e.g. 'ys-marquee--reverse'.
 */
function ys_store_marquee( $items = array(), $classes = '' ) {
	if ( ! $items ) {
		$items = array(
			__( 'Free shipping over the threshold', 'ys-store' ),
			__( '30-day returns', 'ys-store' ),
			__( 'Tracked delivery', 'ys-store' ),
			__( 'Human support', 'ys-store' ),
		);
	}

	printf( '<div class="ys-marquee %s" aria-hidden="true"><div class="ys-marquee__track">', esc_attr( $classes ) );

	for ( $pass = 0; $pass < 2; $pass++ ) {
		foreach ( $items as $item ) {
			echo '<span>' . esc_html( $item ) . '</span>';
		}
	}

	echo '</div></div>';
}

/**
 * Pagination in the house style.
 */
function ys_store_pagination() {
	$links = paginate_links(
		array(
			'type'      => 'array',
			'mid_size'  => 1,
			'prev_text' => '←',
			'next_text' => '→',
		)
	);

	if ( ! $links ) {
		return;
	}

	echo '<nav class="ys-pagination" aria-label="' . esc_attr__( 'Pagination', 'ys-store' ) . '">';
	foreach ( $links as $link ) {
		echo wp_kses_post( $link );
	}
	echo '</nav>';
}

/**
 * Render one product card.
 *
 * Used by the archive loop and by any template part that wants a product tile
 * outside the WooCommerce loop (the front page rails, the "you might also
 * like" rows). Takes a product rather than relying on the `$product` global so
 * it is safe to call anywhere.
 *
 * @param WC_Product|int $product Product or ID.
 */
function ys_store_product_card( $product ) {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( $product );
	}

	if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
		return;
	}

	$id       = $product->get_id();
	$gallery  = $product->get_gallery_image_ids();
	$terms    = get_the_terms( $id, 'product_cat' );
	$category = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
	?>
	<li <?php wc_product_class( 'ys-product-card', $product ); ?>
		data-anim="up"
		data-ys-product="<?php echo esc_attr( $id ); ?>"
		data-ys-name="<?php echo esc_attr( $product->get_name() ); ?>"
		data-ys-price="<?php echo esc_attr( wc_get_price_to_display( $product ) ); ?>"
		data-ys-category="<?php echo esc_attr( $category ); ?>">

		<a class="ys-product-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php
			echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) );

			// The hover image. Only rendered when there is a second photograph
			// to show; otherwise the primary image simply stays put.
			if ( ! empty( $gallery[0] ) ) {
				echo wp_get_attachment_image(
					$gallery[0],
					'woocommerce_thumbnail',
					false,
					array(
						'class'   => 'ys-alt',
						'alt'     => '',
						'loading' => 'lazy',
					)
				);
			}
			?>
		</a>

		<div class="ys-product-card__badges">
			<?php
			/*
			 * The filter is applied directly rather than through
			 * wc_get_template( 'loop/sale-flash.php' ): that template reads the
			 * `$product` global, and this card is also rendered outside the
			 * WooCommerce loop — on the front page rails and the empty cart —
			 * where the global belongs to a different product.
			 */
			if ( $product->is_on_sale() ) {
				echo wp_kses_post(
					apply_filters(
						'woocommerce_sale_flash',
						'<span class="chip chip--sale">' . esc_html__( 'Sale', 'ys-store' ) . '</span>',
						get_post( $product->get_id() ),
						$product
					)
				);
			}

			if ( ! $product->is_in_stock() ) {
				echo '<span class="chip chip--out">' . esc_html__( 'Sold out', 'ys-store' ) . '</span>';
			} elseif ( ys_store_is_new( $product ) ) {
				echo '<span class="chip chip--new">' . esc_html__( 'New', 'ys-store' ) . '</span>';
			}
			?>
		</div>

		<div class="ys-product-card__body">
			<?php if ( $category ) : ?>
				<span class="ys-product-card__cat"><?php echo esc_html( $category ); ?></span>
			<?php endif; ?>

			<h3 class="ys-product-card__title">
				<a href="<?php the_permalink(); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
			</h3>

			<?php if ( $product->get_average_rating() > 0 ) : ?>
				<div class="ys-product-card__rating">
					<?php echo wp_kses_post( wc_get_rating_html( $product->get_average_rating() ) ); ?>
					<span><?php echo esc_html( number_format_i18n( (float) $product->get_average_rating(), 1 ) ); ?></span>
				</div>
			<?php endif; ?>

			<div class="ys-product-card__foot">
				<span class="price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
				<?php woocommerce_template_loop_add_to_cart(); ?>
			</div>
		</div>
	</li>
	<?php
}

/**
 * Has this product been published recently enough to badge as new?
 *
 * @param WC_Product $product Product.
 * @return bool
 */
function ys_store_is_new( $product ) {
	$days = (int) apply_filters( 'ys_store_new_product_days', 21 );

	if ( $days < 1 ) {
		return false;
	}

	$created = $product->get_date_created();

	if ( ! $created ) {
		return false;
	}

	return ( time() - $created->getTimestamp() ) < ( $days * DAY_IN_SECONDS );
}

/**
 * Print a nav menu, falling back to a link to the shop when none is assigned.
 *
 * A brand-new install with no menus should still show a way into the catalogue
 * rather than an empty header.
 *
 * @param string $location Menu location slug.
 * @param string $classes  Wrapper classes.
 */
function ys_store_menu( $location, $classes = '' ) {
	if ( has_nav_menu( $location ) ) {
		wp_nav_menu(
			array(
				'theme_location' => $location,
				'container'      => false,
				'menu_class'     => $classes,
				'depth'          => 2,
				'fallback_cb'    => false,
				// The walker marks parents that own a sub-menu and keeps
				// target="_blank" links from being handed window.opener.
				'walker'         => new YS_Nav_Walker(),
			)
		);

		return;
	}

	if ( 'primary' !== $location || ! function_exists( 'wc_get_page_id' ) ) {
		return;
	}

	$shop = wc_get_page_id( 'shop' );

	printf( '<ul class="%s">', esc_attr( $classes ) );

	if ( $shop > 0 ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( get_permalink( $shop ) ),
			esc_html__( 'Shop', 'ys-store' )
		);
	}

	printf(
		'<li><a href="%s">%s</a></li>',
		esc_url( wc_get_cart_url() ),
		esc_html__( 'Cart', 'ys-store' )
	);

	echo '</ul>';
}

/**
 * Format a number the way the insights tiles and the stats strip both want it:
 * 1200 → 1.2K, 3400000 → 3.4M.
 *
 * @param float $number Number.
 * @return string
 */
function ys_store_compact_number( $number ) {
	$number = (float) $number;

	if ( abs( $number ) >= 1000000 ) {
		return number_format_i18n( $number / 1000000, 1 ) . 'M';
	}

	if ( abs( $number ) >= 1000 ) {
		return number_format_i18n( $number / 1000, 1 ) . 'K';
	}

	return number_format_i18n( $number );
}
