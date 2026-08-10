<?php
/**
 * Styles and scripts.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where GSAP comes from.
 *
 * The CDN default matches how the source sites load it. A store that must not
 * make third-party requests — a strict CSP, or an EU deployment that would
 * rather not hand jsDelivr its visitors' IPs — drops the three files into
 * assets/js/vendor/ and filters this to 'local'. Nothing else changes.
 *
 * @return string 'cdn' | 'local' | 'none'
 */
function ys_store_gsap_source() {
	$source = apply_filters( 'ys_store_gsap_source', 'cdn' );

	return in_array( $source, array( 'cdn', 'local', 'none' ), true ) ? $source : 'cdn';
}

/**
 * Front-end assets.
 */
function ys_store_enqueue() {
	$v = YS_STORE_VERSION;

	/* ---- Fonts ---------------------------------------------------------- */
	wp_enqueue_style(
		'ys-fonts',
		'https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap',
		array(),
		null // phpcs:ignore -- a version query string on a Google Fonts URL breaks their cache.
	);

	/* ---- Styles, in cascade order --------------------------------------- */
	wp_enqueue_style( 'ys-store', get_stylesheet_uri(), array(), $v );
	wp_enqueue_style( 'ys-tokens', YS_STORE_URI . 'assets/css/tokens.css', array( 'ys-store' ), $v );
	wp_enqueue_style( 'ys-base', YS_STORE_URI . 'assets/css/base.css', array( 'ys-tokens' ), $v );
	wp_enqueue_style( 'ys-components', YS_STORE_URI . 'assets/css/components.css', array( 'ys-base' ), $v );
	wp_enqueue_style( 'ys-animations', YS_STORE_URI . 'assets/css/animations.css', array( 'ys-components' ), $v );

	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style( 'ys-woocommerce', YS_STORE_URI . 'assets/css/woocommerce.css', array( 'ys-components' ), $v );
	}

	/* ---- GSAP ------------------------------------------------------------ */
	$gsap_deps = array();
	$source     = ys_store_gsap_source();

	if ( 'none' !== $source ) {
		$base = 'cdn' === $source
			? 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/'
			: YS_STORE_URI . 'assets/js/vendor/';

		wp_enqueue_script( 'gsap', $base . 'gsap.min.js', array(), '3.13.0', true );
		wp_enqueue_script( 'gsap-scrolltrigger', $base . 'ScrollTrigger.min.js', array( 'gsap' ), '3.13.0', true );

		$gsap_deps = array( 'gsap', 'gsap-scrolltrigger' );
	}

	/* ---- App ------------------------------------------------------------- */
	wp_enqueue_script( 'ys-app', YS_STORE_URI . 'assets/js/app.js', $gsap_deps, $v, true );

	wp_localize_script(
		'ys-app',
		'ysStore',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'restUrl'    => esc_url_raw( rest_url() ),
			'nonce'      => wp_create_nonce( 'ys_store' ),
			'isShop'     => function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() ),
			'openDrawer' => (bool) get_theme_mod( 'ys_open_drawer_on_add', true ),
			'preloader'  => ys_store_should_preload(),
			'i18n'       => array(
				'added'   => __( 'Added to cart', 'ys-store' ),
				'error'   => __( 'Something went wrong. Please try again.', 'ys-store' ),
				'cart'    => __( 'Cart', 'ys-store' ),
				'remove'  => __( 'Remove', 'ys-store' ),
				'loading' => __( 'Loading', 'ys-store' ),
			),
		)
	);

	/*
	 * The cart drawer is a separate file with a jQuery dependency, because Woo's
	 * fragment refresh is a jQuery event and there is no non-jQuery hook for it.
	 * Loading it only where a cart can exist keeps jQuery off a blog post.
	 */
	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_script(
			'ys-cart',
			YS_STORE_URI . 'assets/js/cart.js',
			array( 'jquery', 'ys-app' ),
			$v,
			true
		);

		wp_localize_script(
			'ys-cart',
			'ysCart',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'ys_cart' ),
				'cartUrl'      => wc_get_cart_url(),
				'checkoutUrl'  => wc_get_checkout_url(),
				'freeShipping' => ys_store_free_shipping_threshold(),
				'currency'     => get_woocommerce_currency_symbol(),
			)
		);
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'ys_store_enqueue' );

/**
 * Drop WooCommerce's own presentation stylesheets.
 *
 * `woocommerce-layout` and `woocommerce-general` between them set the grid, the
 * buttons, the tables and the notices — all of which assets/css/woocommerce.css
 * defines from scratch. Loading both means every rule here needs a specificity
 * war to win, and the shopper downloads ~40KB of CSS to have it overridden.
 *
 * `woocommerce-smallscreen` is dropped for the same reason: it is a set of
 * max-width-768px table rewrites for a layout this theme does not use.
 *
 * What stays: `photoswipe*` and the inline gallery styles, which carry the
 * lightbox's behaviour rather than its skin.
 *
 * @param array $styles Woo's registered styles.
 * @return array
 */
function ys_store_dequeue_woo_styles( $styles ) {
	unset( $styles['woocommerce-general'], $styles['woocommerce-layout'], $styles['woocommerce-smallscreen'] );

	return $styles;
}
add_filter( 'woocommerce_enqueue_styles', 'ys_store_dequeue_woo_styles' );

/**
 * Should the preloader run for this request?
 *
 * Never on checkout — an animation between a customer and a payment form is a
 * cost with no upside. Never for a logged-in editor, who reloads constantly.
 * Otherwise once per session, tracked by a sessionStorage flag in app.js.
 *
 * @return bool
 */
function ys_store_should_preload() {
	if ( ! get_theme_mod( 'ys_preloader', true ) ) {
		return false;
	}

	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_account_page() ) ) {
		return false;
	}

	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return false;
	}

	return (bool) apply_filters( 'ys_store_should_preload', true );
}

/**
 * The cart subtotal at which shipping becomes free, in store currency.
 *
 * Read from the shipping zones rather than a theme option, so the number the
 * drawer promises is the number checkout will honour. Returns 0 when no zone
 * offers a free-shipping method with a minimum-amount requirement, and the
 * drawer then hides the progress bar entirely instead of promising nothing.
 *
 * @return float
 */
function ys_store_free_shipping_threshold() {
	$cached = get_transient( 'ys_free_shipping_threshold' );

	if ( false !== $cached ) {
		return (float) $cached;
	}

	$threshold = 0.0;

	if ( class_exists( 'WC_Shipping_Zones' ) ) {
		$zones   = WC_Shipping_Zones::get_zones();
		$zones[] = array( 'id' => 0 ); // The "rest of the world" zone is not in get_zones().

		foreach ( $zones as $zone_data ) {
			$zone = WC_Shipping_Zones::get_zone( isset( $zone_data['id'] ) ? $zone_data['id'] : 0 );

			if ( ! $zone ) {
				continue;
			}

			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				if ( 'free_shipping' !== $method->id ) {
					continue;
				}

				$requires = isset( $method->requires ) ? $method->requires : '';

				if ( ! in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
					continue;
				}

				$amount = isset( $method->min_amount ) ? (float) $method->min_amount : 0.0;

				// The lowest qualifying threshold is the one a customer can actually reach.
				if ( $amount > 0 && ( 0.0 === $threshold || $amount < $threshold ) ) {
					$threshold = $amount;
				}
			}
		}
	}

	set_transient( 'ys_free_shipping_threshold', $threshold, HOUR_IN_SECONDS );

	return $threshold;
}

/**
 * Clear the cached threshold whenever shipping settings change.
 */
function ys_store_flush_shipping_cache() {
	delete_transient( 'ys_free_shipping_threshold' );
}
add_action( 'woocommerce_shipping_zone_method_added', 'ys_store_flush_shipping_cache' );
add_action( 'woocommerce_shipping_zone_method_deleted', 'ys_store_flush_shipping_cache' );
add_action( 'woocommerce_shipping_zone_method_status_toggled', 'ys_store_flush_shipping_cache' );
add_action( 'woocommerce_update_options_shipping', 'ys_store_flush_shipping_cache' );

/**
 * Block-editor assets, so a page built in Gutenberg matches the front end.
 */
function ys_store_editor_assets() {
	wp_enqueue_style( 'ys-editor-tokens', YS_STORE_URI . 'assets/css/tokens.css', array(), YS_STORE_VERSION );
}
add_action( 'enqueue_block_editor_assets', 'ys_store_editor_assets' );
