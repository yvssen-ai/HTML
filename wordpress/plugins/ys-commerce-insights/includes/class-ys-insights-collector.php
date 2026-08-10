<?php
/**
 * First-party funnel collector.
 *
 * WooCommerce knows what was bought. It does not know how many people looked
 * and did not buy — and without that there is no conversion rate, which is the
 * single number that tells a store owner whether a change helped.
 *
 * This collector fills that gap without a third-party tracker. What it stores:
 * a random session key, an event name, a product or order id, a value, the
 * traffic source, and a device class. What it deliberately does not store: IP
 * addresses, user agents, user ids, or anything else that identifies a person.
 * The session key is random per visit and expires with the cookie; it cannot be
 * used to recognise the same person on a later visit.
 *
 * @package YS_Insights
 */

defined( 'ABSPATH' ) || exit;

/**
 * Records funnel events into a table this plugin owns.
 */
class YS_Insights_Collector {

	/**
	 * Cookie holding the session key.
	 */
	const COOKIE = 'ys_ins_s';

	/**
	 * A session ends after this much inactivity. Thirty minutes is the
	 * convention every analytics product uses, which makes the numbers here
	 * comparable with whatever else the store runs.
	 */
	const SESSION_TTL = 1800;

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
	 * The events table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'ys_insights_events';
	}

	/**
	 * Does the events table exist?
	 *
	 * A plugin dropped into wp-content/plugins by hand, restored from a partial
	 * backup, or activated on one site of a network can be running with no
	 * table behind it. Every read and write checks first, so the worst case is
	 * a dashboard with a zeroed funnel rather than a fatal on the storefront.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		static $exists = null;

		if ( null !== $exists ) {
			return $exists;
		}

		global $wpdb;

		$table  = self::table();
		$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );

		return $exists;
	}

	/**
	 * Create or migrate the table.
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		/*
		 * `session_key` is CHAR(32) and indexed because every funnel query
		 * counts distinct sessions. `created_at` is stored in UTC — the
		 * dashboard converts on the way out, so a store that changes timezone
		 * does not rewrite its history.
		 */
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_key CHAR(32) NOT NULL DEFAULT '',
			event VARCHAR(32) NOT NULL DEFAULT '',
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			value DECIMAL(18,4) NOT NULL DEFAULT 0,
			currency CHAR(3) NOT NULL DEFAULT '',
			source VARCHAR(100) NOT NULL DEFAULT '',
			medium VARCHAR(60) NOT NULL DEFAULT '',
			campaign VARCHAR(100) NOT NULL DEFAULT '',
			device VARCHAR(10) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY event_time (event, created_at),
			KEY session_event (session_key, event),
			KEY object_time (object_id, created_at)
		) {$collate};";

		dbDelta( $sql );
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp', array( $this, 'record_page_view' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'record_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'record_checkout' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'record_purchase' ) );
		add_action( 'ys_insights_prune', array( $this, 'prune' ) );
	}

	/**
	 * Is collection switched on, and is this visitor one we should record?
	 *
	 * @return bool
	 */
	public function enabled() {
		if ( ! get_option( 'ys_insights_collect', 1 ) || ! self::table_exists() ) {
			return false;
		}

		// Do Not Track. Honouring it is not legally required in most places;
		// it is honoured here because a visitor who has set it has said
		// something unambiguous and the funnel is not worth ignoring that for.
		if ( isset( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'] ) {
			return false;
		}

		// Staff browsing their own store would otherwise dominate the funnel of
		// a small shop and make the conversion rate meaningless.
		if ( is_user_logged_in() && current_user_can( 'edit_shop_orders' ) ) {
			return false;
		}

		if ( wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! wp_doing_ajax() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The current session key, minting one if this is a new visit.
	 *
	 * @return string 32 hex characters, or '' when a key cannot be established.
	 */
	public function session_key() {
		static $key = null;

		if ( null !== $key ) {
			return $key;
		}

		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$candidate = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) );

			if ( 32 === strlen( $candidate ) ) {
				$key = $candidate;
				$this->refresh_cookie( $key );

				return $key;
			}
		}

		$key = md5( wp_generate_password( 32, false ) . microtime( true ) );
		$this->refresh_cookie( $key );

		return $key;
	}

	/**
	 * Write the session cookie with a sliding expiry.
	 *
	 * @param string $key Session key.
	 */
	private function refresh_cookie( $key ) {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$key,
			array(
				'expires'  => time() + self::SESSION_TTL,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Insert one event.
	 *
	 * @param string $event     Event name.
	 * @param int    $object_id Product or order id.
	 * @param float  $value     Monetary value.
	 */
	public function record( $event, $object_id = 0, $value = 0.0 ) {
		global $wpdb;

		if ( ! $this->enabled() ) {
			return;
		}

		$key = $this->session_key();

		if ( ! $key ) {
			return;
		}

		$attribution = $this->attribution();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'session_key' => $key,
				'event'       => substr( $event, 0, 32 ),
				'object_id'   => absint( $object_id ),
				'value'       => (float) $value,
				'currency'    => get_woocommerce_currency(),
				'source'      => $attribution['source'],
				'medium'      => $attribution['medium'],
				'campaign'    => $attribution['campaign'],
				'device'      => $this->device(),
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/* =========================================================================
	   Event hooks
	   ====================================================================== */

	/**
	 * A page view, and a product view when the page is a product.
	 */
	public function record_page_view() {
		if ( is_admin() || ! $this->enabled() ) {
			return;
		}

		// A 404 is not a page view worth counting toward a conversion rate.
		if ( is_404() ) {
			return;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );

			$this->record(
				'product_view',
				get_queried_object_id(),
				$product ? (float) wc_get_price_to_display( $product ) : 0
			);

			return;
		}

		$this->record( 'page_view' );
	}

	/**
	 * @param string $cart_key    Cart item key.
	 * @param int    $product_id  Product id.
	 * @param int    $quantity    Quantity.
	 * @param int    $variation_id Variation id.
	 * @param array  $variation   Variation data.
	 * @param array  $cart_item   Cart item data.
	 */
	public function record_add_to_cart( $cart_key, $product_id, $quantity, $variation_id = 0, $variation = array(), $cart_item = array() ) {
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );

		$this->record(
			'add_to_cart',
			$product_id,
			$product ? (float) wc_get_price_to_display( $product ) * (int) $quantity : 0
		);
	}

	/**
	 * Checkout reached.
	 */
	public function record_checkout() {
		$cart = WC()->cart;

		$this->record( 'begin_checkout', 0, $cart ? (float) $cart->get_total( 'edit' ) : 0 );
	}

	/**
	 * Order placed.
	 *
	 * `woocommerce_checkout_order_processed` fires once, server side, at the
	 * moment the order is created — before any redirect to a payment provider.
	 * That makes it immune to the two ways a thank-you-page pixel loses
	 * purchases: a customer closing the tab, and a gateway that returns them
	 * somewhere else.
	 *
	 * @param int $order_id Order id.
	 */
	public function record_purchase( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$this->record( 'purchase', $order_id, (float) $order->get_total() );
	}

	/* =========================================================================
	   Reporting
	   ====================================================================== */

	/**
	 * The funnel for a range.
	 *
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @return array
	 */
	public static function funnel( $start, $end ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array(
				'sessions'        => 0,
				'viewed'          => 0,
				'added'           => 0,
				'checkout'        => 0,
				'purchased'       => 0,
				'conversion_rate' => 0.0,
				'cart_completion' => 0.0,
			);
		}

		$table = self::table();
		$from  = gmdate( 'Y-m-d H:i:s', $start->getTimestamp() );
		$to    = gmdate( 'Y-m-d H:i:s', $end->getTimestamp() );

		// One grouped query rather than five counts: on a table with a year of
		// traffic the difference is a page that loads and one that times out.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT event, COUNT(DISTINCT session_key) AS sessions, COUNT(*) AS hits
				 FROM {$table}
				 WHERE created_at BETWEEN %s AND %s
				 GROUP BY event",
				$from,
				$to
			),
			ARRAY_A
		);

		$by_event = array();

		foreach ( (array) $rows as $row ) {
			$by_event[ $row['event'] ] = array(
				'sessions' => (int) $row['sessions'],
				'hits'     => (int) $row['hits'],
			);
		}

		$sessions = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_key) FROM {$table} WHERE created_at BETWEEN %s AND %s",
				$from,
				$to
			)
		);

		$steps = array(
			'sessions'  => $sessions,
			'viewed'    => $by_event['product_view']['sessions'] ?? 0,
			'added'     => $by_event['add_to_cart']['sessions'] ?? 0,
			'checkout'  => $by_event['begin_checkout']['sessions'] ?? 0,
			'purchased' => $by_event['purchase']['sessions'] ?? 0,
		);

		$steps['conversion_rate'] = $steps['sessions'] > 0
			? ( $steps['purchased'] / $steps['sessions'] ) * 100
			: 0.0;

		// Of everyone who put something in a basket, how many finished. This is
		// the number a store can move fastest, because the intent is already there.
		$steps['cart_completion'] = $steps['added'] > 0
			? ( $steps['purchased'] / $steps['added'] ) * 100
			: 0.0;

		return $steps;
	}

	/**
	 * Traffic sources ranked by the revenue they produced.
	 *
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @param int               $limit Rows.
	 * @return array
	 */
	public static function sources( $start, $end, $limit = 6 ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array();
		}

		$table = self::table();

		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT
					CASE WHEN source = '' THEN 'direct' ELSE source END AS source,
					COUNT(DISTINCT session_key) AS sessions,
					SUM(CASE WHEN event = 'purchase' THEN value ELSE 0 END) AS revenue,
					SUM(CASE WHEN event = 'purchase' THEN 1 ELSE 0 END) AS orders
				 FROM {$table}
				 WHERE created_at BETWEEN %s AND %s
				 GROUP BY source
				 ORDER BY revenue DESC, sessions DESC
				 LIMIT %d",
				gmdate( 'Y-m-d H:i:s', $start->getTimestamp() ),
				gmdate( 'Y-m-d H:i:s', $end->getTimestamp() ),
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Has the collector recorded anything yet?
	 *
	 * The dashboard uses this to tell "no traffic" apart from "just installed",
	 * which are very different messages to show an owner.
	 *
	 * @return bool
	 */
	public static function has_data() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table = self::table();

		return (bool) $wpdb->get_var( "SELECT id FROM {$table} LIMIT 1" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Delete events past the retention window.
	 *
	 * Runs daily. Keeping raw hits forever is a storage cost with no reporting
	 * benefit past a year, and a smaller table is a smaller thing to breach.
	 */
	public function prune() {
		global $wpdb;

		$days = (int) get_option( 'ys_insights_retention_days', 365 );

		if ( $days < 1 || ! self::table_exists() ) {
			return;
		}

		$table = self::table();

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			)
		);
	}

	/* =========================================================================
	   Internals
	   ====================================================================== */

	/**
	 * Where this visit came from.
	 *
	 * UTM parameters win when present. Otherwise the referrer's host stands in,
	 * with anything from this site treated as no referrer at all — an internal
	 * click is not a traffic source.
	 *
	 * @return array{source:string,medium:string,campaign:string}
	 */
	private function attribution() {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$source   = '';
		$medium   = '';
		$campaign = '';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only attribution of a public page view.
		if ( isset( $_GET['utm_source'] ) ) {
			$source = sanitize_text_field( wp_unslash( $_GET['utm_source'] ) );
		}

		if ( isset( $_GET['utm_medium'] ) ) {
			$medium = sanitize_text_field( wp_unslash( $_GET['utm_medium'] ) );
		}

		if ( isset( $_GET['utm_campaign'] ) ) {
			$campaign = sanitize_text_field( wp_unslash( $_GET['utm_campaign'] ) );
		}
		// phpcs:enable

		if ( ! $source && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$host = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST );
			$self = wp_parse_url( home_url(), PHP_URL_HOST );

			if ( $host && $host !== $self ) {
				$source = $host;
				$medium = 'referral';
			}
		}

		$cached = array(
			'source'   => substr( $source, 0, 100 ),
			'medium'   => substr( $medium, 0, 60 ),
			'campaign' => substr( $campaign, 0, 100 ),
		);

		return $cached;
	}

	/**
	 * A coarse device class.
	 *
	 * Three buckets, derived from WordPress's own detection rather than a stored
	 * user agent string — enough to answer "is mobile converting worse than
	 * desktop", which is the only question the dashboard asks of it.
	 *
	 * @return string
	 */
	private function device() {
		if ( ! function_exists( 'wp_is_mobile' ) ) {
			return 'unknown';
		}

		return wp_is_mobile() ? 'mobile' : 'desktop';
	}
}
