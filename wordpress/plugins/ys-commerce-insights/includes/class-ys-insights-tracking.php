<?php
/**
 * Third-party ecommerce tracking.
 *
 * Optional, off by default, and it sends nothing until a store owner enters an
 * ID. What it is for: the events that matter are the ones a generic tag manager
 * gets wrong. A purchase read from the real WC_Order is correct even when the
 * customer closes the tab before the thank-you page finishes loading, and an
 * item list built server side carries the real prices rather than whatever was
 * on screen.
 *
 * @package YS_Insights
 */

defined( 'ABSPATH' ) || exit;

/**
 * Emits GA4 and Meta Pixel ecommerce events.
 */
class YS_Insights_Tracking {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		if ( ! $this->ga4() && ! $this->pixel() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_head', array( $this, 'print_tags' ), 5 );
		add_action( 'wp_footer', array( $this, 'print_events' ), 20 );
	}

	/**
	 * @return string
	 */
	private function ga4() {
		return (string) get_option( 'ys_insights_ga4', '' );
	}

	/**
	 * @return string
	 */
	private function pixel() {
		return (string) get_option( 'ys_insights_pixel', '' );
	}

	/**
	 * Client-side event bridge.
	 */
	public function assets() {
		wp_enqueue_script(
			'ys-insights-track',
			YS_INSIGHTS_URL . 'assets/track.js',
			array(),
			YS_INSIGHTS_VERSION,
			true
		);

		wp_localize_script(
			'ys-insights-track',
			'ysTrack',
			array(
				'ga4'          => $this->ga4(),
				'pixel'        => $this->pixel(),
				'needsConsent' => (bool) get_option( 'ys_insights_consent', 0 ),
				'currency'     => get_woocommerce_currency(),
			)
		);
	}

	/**
	 * The loader snippets.
	 *
	 * Both are printed with their network request deferred behind a consent
	 * gate when one is configured; track.js is what actually inserts the
	 * vendor script, so nothing leaves the browser before it is allowed to.
	 */
	public function print_tags() {
		// The queue has to exist before anything tries to push to it, which is
		// why this is in the head rather than alongside the loader in track.js.
		echo "<script>window.dataLayer=window.dataLayer||[];window.ysEvents=window.ysEvents||[];</script>\n";
	}

	/**
	 * Page-specific ecommerce events, as data for track.js to send.
	 *
	 * Printed as JSON in a script tag rather than assembled in JS: the prices,
	 * names and category paths are all things the server already knows
	 * correctly, and re-deriving them from the DOM is how tracking drifts out
	 * of step with the catalogue.
	 */
	public function print_events() {
		$events = array();

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );

			if ( $product ) {
				$events[] = array(
					'name'   => 'view_item',
					'params' => array(
						'currency' => get_woocommerce_currency(),
						'value'    => (float) wc_get_price_to_display( $product ),
						'items'    => array( $this->item_from_product( $product ) ),
					),
				);
			}
		}

		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
			$items = array();
			$index = 0;

			/*
			 * The main query has already run by wp_footer, so the products on
			 * this page are the posts it returned — no second query, and no
			 * scraping the DOM for prices that may have been formatted.
			 */
			foreach ( (array) $GLOBALS['wp_query']->posts as $post ) {
				$product = wc_get_product( is_object( $post ) ? $post->ID : $post );

				if ( $product ) {
					$items[] = $this->item_from_product( $product, $index++ );
				}
			}

			if ( $items ) {
				$events[] = array(
					'name'   => 'view_item_list',
					'params' => array(
						'item_list_name' => wp_strip_all_tags( woocommerce_page_title( false ) ),
						'items'          => $items,
					),
				);
			}
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && WC()->cart && ! WC()->cart->is_empty() ) {
			$items = array();

			foreach ( WC()->cart->get_cart() as $line ) {
				if ( ! empty( $line['data'] ) ) {
					$items[] = $this->item_from_product( $line['data'], null, (int) $line['quantity'] );
				}
			}

			$events[] = array(
				'name'   => 'begin_checkout',
				'params' => array(
					'currency' => get_woocommerce_currency(),
					'value'    => (float) WC()->cart->get_total( 'edit' ),
					'items'    => $items,
				),
			);
		}

		$purchase = $this->purchase_event();

		if ( $purchase ) {
			$events[] = $purchase;
		}

		if ( ! $events ) {
			return;
		}

		printf(
			'<script id="ys-insights-events" type="application/json">%s</script>',
			wp_json_encode( $events ) // phpcs:ignore WordPress.Security.EscapeOutput -- JSON in a non-executing script block.
		);
	}

	/**
	 * The purchase event, built from the order.
	 *
	 * Guarded by an order meta flag so a customer who refreshes the thank-you
	 * page — or arrives back on it from an email — does not report the sale a
	 * second time. Double-counted purchases are the most common and most
	 * damaging tracking bug there is: they corrupt ad platform bidding, not
	 * just a report.
	 *
	 * @return array|null
	 */
	private function purchase_event() {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return null;
		}

		$order_id = absint( get_query_var( 'order-received' ) );

		if ( ! $order_id ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return null;
		}

		// The key in the URL is what proves this browser owns the order.
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return null;
		}

		if ( $order->get_meta( '_ys_insights_tracked' ) ) {
			return null;
		}

		$items = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( ! $product ) {
				continue;
			}

			$items[] = $this->item_from_product( $product, null, (int) $item->get_quantity(), (float) $item->get_total() / max( 1, (int) $item->get_quantity() ) );
		}

		$order->update_meta_data( '_ys_insights_tracked', 1 );
		$order->save();

		return array(
			'name'   => 'purchase',
			'params' => array(
				'transaction_id' => $order->get_order_number(),
				'currency'       => $order->get_currency(),
				'value'          => (float) $order->get_total(),
				'tax'            => (float) $order->get_total_tax(),
				'shipping'       => (float) $order->get_shipping_total(),
				'coupon'         => implode( ',', $order->get_coupon_codes() ),
				'items'          => $items,
			),
		);
	}

	/**
	 * One product as a GA4 item.
	 *
	 * @param WC_Product $product  Product.
	 * @param int|null   $index    Position in a list.
	 * @param int        $quantity Quantity.
	 * @param float|null $price    Override price, e.g. the line price actually paid.
	 * @return array
	 */
	private function item_from_product( $product, $index = null, $quantity = 1, $price = null ) {
		$terms      = get_the_terms( $product->get_id(), 'product_cat' );
		$categories = ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'name' ) : array();

		$item = array(
			'item_id'       => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
			'item_name'     => $product->get_name(),
			'price'         => null === $price ? (float) wc_get_price_to_display( $product ) : round( $price, 2 ),
			'quantity'      => max( 1, (int) $quantity ),
			'item_category' => $categories ? $categories[0] : '',
		);

		if ( null !== $index ) {
			$item['index'] = (int) $index;
		}

		return $item;
	}
}
