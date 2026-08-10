<?php
/**
 * Theme supports, menus, sidebars, image sizes.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register everything WordPress and WooCommerce need to know about the theme.
 */
function ys_store_setup() {
	load_theme_textdomain( 'ys-store', YS_STORE_DIR . 'languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/tokens.css' );

	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);

	add_theme_support(
		'custom-logo',
		array(
			'height'      => 60,
			'width'       => 220,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	/*
	 * WooCommerce.
	 *
	 * Declaring gallery support is what turns the single-product images into a
	 * slider with zoom and a lightbox. Without these three the gallery renders
	 * as a plain stack of <img>, which on a store selling anything visual is a
	 * conversion problem, not a cosmetic one.
	 */
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 600,
			'single_image_width'    => 1200,
			'product_grid'          => array(
				'default_rows'    => 3,
				'min_rows'        => 1,
				'default_columns' => 4,
				'min_columns'     => 2,
				'max_columns'     => 5,
			),
		)
	);
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary (header)', 'ys-store' ),
			'shop'    => __( 'Shop categories (footer column 1)', 'ys-store' ),
			'help'    => __( 'Help & policies (footer column 2)', 'ys-store' ),
			'legal'   => __( 'Legal (footer bottom bar)', 'ys-store' ),
		)
	);

	/*
	 * A 4:5 portrait crop for editorial rows. WooCommerce owns the square
	 * catalogue thumbnail, so this one is only used by template parts that lay
	 * products out as a tall rail.
	 */
	add_image_size( 'ys-portrait', 720, 900, true );
	add_image_size( 'ys-wide', 1600, 900, true );
}
add_action( 'after_setup_theme', 'ys_store_setup' );

/**
 * Content width, used by WordPress when it sizes embeds.
 */
function ys_store_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'ys_store_content_width', 1320 );
}
add_action( 'after_setup_theme', 'ys_store_content_width', 0 );

/**
 * Widget areas.
 *
 * The shop sidebar is the only one that matters commercially — it carries the
 * layered-nav and price-filter widgets that let a customer narrow a catalogue.
 */
function ys_store_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Shop filters', 'ys-store' ),
			'id'            => 'shop-filters',
			'description'   => __( 'Shown in the shop sidebar. Add WooCommerce filter widgets here.', 'ys-store' ),
			'before_widget' => '<section id="%1$s" class="ys-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h4 class="ys-widget__title">',
			'after_title'   => '</h4>',
		)
	);

	register_sidebar(
		array(
			'name'          => __( 'Footer extra', 'ys-store' ),
			'id'            => 'footer-extra',
			'description'   => __( 'An optional fourth footer column.', 'ys-store' ),
			'before_widget' => '<section id="%1$s" class="ys-footer__col %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h4>',
			'after_title'   => '</h4>',
		)
	);
}
add_action( 'widgets_init', 'ys_store_widgets_init' );

/**
 * Body classes the CSS and JS key off.
 *
 * @param array $classes Existing classes.
 * @return array
 */
function ys_store_body_classes( $classes ) {
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}

	/*
	 * Checkout and cart opt out of the scroll-reveal layer. A customer part-way
	 * through paying should not be waiting on an intersection observer, and the
	 * cart drawer would fight the cart page for the same fragments.
	 */
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) {
		$classes[] = 'ys-no-reveal';
	}

	if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
		$classes[] = 'ys-shop-context';
	}

	return $classes;
}
add_filter( 'body_class', 'ys_store_body_classes' );

/**
 * Trim the excerpt to something a product card can hold, and end it with a
 * character rather than the "[...]" WordPress defaults to.
 *
 * @param string $more Existing more string.
 * @return string
 */
function ys_store_excerpt_more( $more ) {
	return is_admin() ? $more : '…';
}
add_filter( 'excerpt_more', 'ys_store_excerpt_more' );

/**
 * @param int $length Existing length.
 * @return int
 */
function ys_store_excerpt_length( $length ) {
	return is_admin() ? $length : 22;
}
add_filter( 'excerpt_length', 'ys_store_excerpt_length' );

/**
 * Preconnect to the font host.
 *
 * `wp_resource_hints` is the supported way to do this; hand-printing <link>
 * tags in header.php would duplicate whatever a caching plugin already adds.
 *
 * @param array  $urls          URLs to print.
 * @param string $relation_type The relation type.
 * @return array
 */
function ys_store_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && wp_style_is( 'ys-fonts', 'queue' ) ) {
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
			'crossorigin',
		);
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'ys_store_resource_hints', 10, 2 );
