<?php
/**
 * Sales queries.
 *
 * Every order read here goes through wc_get_orders() and the order CRUD, which
 * is what makes the plugin correct under both the legacy post storage and HPOS.
 * Raw SQL appears in exactly one place — the events table this plugin owns.
 *
 * @package YS_Insights
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads sales figures out of WooCommerce.
 */
class YS_Insights_Data {

	/**
	 * Order statuses that count as revenue.
	 *
	 * `processing` and `completed` are money taken. `on-hold` is not: a bank
	 * transfer that never arrives would inflate every figure on the dashboard.
	 * Filterable, because a store with a custom fulfilment status will disagree.
	 *
	 * @return array
	 */
	public static function paid_statuses() {
		return (array) apply_filters( 'ys_insights_paid_statuses', array( 'wc-processing', 'wc-completed' ) );
	}

	/**
	 * How long a computed range stays cached.
	 *
	 * Short, because the whole point of the dashboard is that today's number is
	 * today's number. Ranges that end in the past are cached far longer by
	 * self::cache_ttl() since they cannot change.
	 */
	const CACHE_TTL = 300;

	/**
	 * Resolve a range keyword into a pair of UTC datetimes.
	 *
	 * All arithmetic is done in the site's timezone and then converted, so
	 * "today" means the store owner's today rather than UTC's.
	 *
	 * @param string $range One of: today, yesterday, 7d, 30d, 90d, mtd, ytd, all.
	 * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
	 */
	public static function resolve_range( $range ) {
		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );

		$start_of_today = $now->setTime( 0, 0, 0 );

		switch ( $range ) {
			case 'today':
				$start = $start_of_today;
				$end   = $now;
				break;

			case 'yesterday':
				$start = $start_of_today->modify( '-1 day' );
				$end   = $start_of_today->modify( '-1 second' );
				break;

			case '7d':
				$start = $start_of_today->modify( '-6 days' );
				$end   = $now;
				break;

			case '90d':
				$start = $start_of_today->modify( '-89 days' );
				$end   = $now;
				break;

			case 'mtd':
				$start = $now->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), 1 )->setTime( 0, 0, 0 );
				$end   = $now;
				break;

			case 'ytd':
				$start = $now->setDate( (int) $now->format( 'Y' ), 1, 1 )->setTime( 0, 0, 0 );
				$end   = $now;
				break;

			case 'all':
				$start = new DateTimeImmutable( '2000-01-01 00:00:00', $tz );
				$end   = $now;
				break;

			case '30d':
			default:
				$start = $start_of_today->modify( '-29 days' );
				$end   = $now;
				break;
		}

		return array( $start, $end );
	}

	/**
	 * The equivalent range immediately before the given one, for comparisons.
	 *
	 * A 30-day range compares against the 30 days before it. This is what makes
	 * "+12% on the previous period" mean something specific.
	 *
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
	 */
	public static function previous_range( $start, $end ) {
		$length = $end->getTimestamp() - $start->getTimestamp();

		return array(
			$start->modify( '-' . ( $length + 1 ) . ' seconds' ),
			$start->modify( '-1 second' ),
		);
	}

	/**
	 * Headline figures for a range.
	 *
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @return array
	 */
	public static function summary( $start, $end ) {
		$key    = self::cache_key( 'summary', $start, $end );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		$totals = array(
			'revenue'      => 0.0,
			'net'          => 0.0,
			'refunds'      => 0.0,
			'shipping'     => 0.0,
			'tax'          => 0.0,
			'orders'       => 0,
			'items'        => 0,
			'customers'    => 0,
			'aov'          => 0.0,
			'new_customer' => 0,
		);

		$emails = array();

		foreach ( self::each_order( $start, $end ) as $order ) {
			$total    = (float) $order->get_total();
			$refunded = (float) $order->get_total_refunded();

			$totals['revenue']  += $total;
			$totals['refunds']  += $refunded;
			$totals['shipping'] += (float) $order->get_shipping_total();
			$totals['tax']      += (float) $order->get_total_tax();
			$totals['orders']++;

			foreach ( $order->get_items() as $item ) {
				$totals['items'] += (int) $item->get_quantity();
			}

			$email = strtolower( (string) $order->get_billing_email() );

			if ( $email ) {
				$emails[ $email ] = true;
			}
		}

		$totals['net']       = $totals['revenue'] - $totals['refunds'];
		$totals['customers'] = count( $emails );
		$totals['aov']       = $totals['orders'] > 0 ? $totals['revenue'] / $totals['orders'] : 0.0;

		set_transient( $key, $totals, self::cache_ttl( $end ) );

		return $totals;
	}

	/**
	 * Revenue and order count per day across a range.
	 *
	 * Buckets are pre-seeded with zeros so a day with no sales is a point on
	 * the chart at zero rather than a gap the line jumps over — which is the
	 * difference between "we sold nothing on Sunday" and "we have no data".
	 *
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @return array List of { date, revenue, orders }.
	 */
	public static function series( $start, $end ) {
		$key    = self::cache_key( 'series', $start, $end );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		$tz      = wp_timezone();
		$buckets = array();
		$cursor  = $start->setTime( 0, 0, 0 );
		$last    = $end->setTime( 0, 0, 0 );

		// A range of years would produce thousands of points nobody can read.
		$guard = 0;

		while ( $cursor <= $last && $guard < 400 ) {
			$buckets[ $cursor->format( 'Y-m-d' ) ] = array(
				'date'    => $cursor->format( 'Y-m-d' ),
				'revenue' => 0.0,
				'orders'  => 0,
			);

			$cursor = $cursor->modify( '+1 day' );
			$guard++;
		}

		foreach ( self::each_order( $start, $end ) as $order ) {
			$created = $order->get_date_created();

			if ( ! $created ) {
				continue;
			}

			$day = $created->setTimezone( $tz )->format( 'Y-m-d' );

			if ( ! isset( $buckets[ $day ] ) ) {
				continue;
			}

			$buckets[ $day ]['revenue'] += (float) $order->get_total();
			$buckets[ $day ]['orders']++;
		}

		$series = array_values( $buckets );

		set_transient( $key, $series, self::cache_ttl( $end ) );

		return $series;
	}

	/**
	 * Best sellers in a range, by revenue.
	 *
	 * Ranked by revenue rather than units: the product that made the most money
	 * is the one worth restocking first, and a cheap accessory will always win
	 * a unit count.
	 *
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @param int               $limit Rows to return.
	 * @return array
	 */
	public static function top_products( $start, $end, $limit = 8 ) {
		$key    = self::cache_key( 'top' . $limit, $start, $end );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		$rows = array();

		foreach ( self::each_order( $start, $end ) as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();

				if ( ! $product_id ) {
					continue;
				}

				if ( ! isset( $rows[ $product_id ] ) ) {
					$rows[ $product_id ] = array(
						'id'      => $product_id,
						'name'    => $item->get_name(),
						'units'   => 0,
						'revenue' => 0.0,
					);
				}

				$rows[ $product_id ]['units']   += (int) $item->get_quantity();
				$rows[ $product_id ]['revenue'] += (float) $item->get_total();
			}
		}

		usort(
			$rows,
			function ( $a, $b ) {
				return $b['revenue'] <=> $a['revenue'];
			}
		);

		$rows = array_slice( $rows, 0, $limit );

		foreach ( $rows as $i => $row ) {
			$rows[ $i ]['edit'] = get_edit_post_link( $row['id'], 'raw' );
		}

		set_transient( $key, $rows, self::cache_ttl( $end ) );

		return $rows;
	}

	/**
	 * Products at or below WooCommerce's own low-stock threshold.
	 *
	 * The most actionable list on the dashboard: these are sales the store is
	 * about to stop being able to make.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function low_stock( $limit = 8 ) {
		$threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );

		$products = wc_get_products(
			array(
				'limit'        => $limit,
				'status'       => 'publish',
				'stock_status' => array( 'instock', 'outofstock' ),
				'orderby'      => 'meta_value_num',
				'meta_key'     => '_stock', // phpcs:ignore WordPress.DB.SlowDBQuery
				'order'        => 'ASC',
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_manage_stock',
						'value'   => 'yes',
						'compare' => '=',
					),
					array(
						'key'     => '_stock',
						'value'   => $threshold,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$rows = array();

		foreach ( $products as $product ) {
			$rows[] = array(
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'stock' => (int) $product->get_stock_quantity(),
				'edit'  => get_edit_post_link( $product->get_id(), 'raw' ),
			);
		}

		return $rows;
	}

	/**
	 * The most recent orders, whatever their status.
	 *
	 * Unpaid ones are included deliberately — a run of failed orders is the
	 * single most urgent thing a store owner can be shown, and filtering to
	 * paid statuses would hide exactly that.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function recent_orders( $limit = 8 ) {
		$orders = wc_get_orders(
			array(
				'limit'   => $limit,
				'orderby' => 'date',
				'order'   => 'DESC',
				'type'    => 'shop_order',
			)
		);

		$rows = array();

		foreach ( $orders as $order ) {
			$created = $order->get_date_created();

			$rows[] = array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'customer' => trim( $order->get_formatted_billing_full_name() ),
				'total'    => (float) $order->get_total(),
				'status'   => $order->get_status(),
				'label'    => wc_get_order_status_name( $order->get_status() ),
				'date'     => $created ? $created->date_i18n( get_option( 'date_format' ) . ' H:i' ) : '',
				'edit'     => $order->get_edit_order_url(),
			);
		}

		return $rows;
	}

	/**
	 * Order counts by status across a range, for the health strip.
	 *
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @return array status => count.
	 */
	public static function status_breakdown( $start, $end ) {
		$counts = array();

		/*
		 * One paginated query per status, asking for a single row and reading
		 * the total off the result. Fetching every id and counting them in PHP
		 * would pull tens of thousands of rows across the wire to produce eight
		 * integers the database can count itself.
		 */
		foreach ( array_keys( wc_get_order_statuses() ) as $status ) {
			$result = wc_get_orders(
				array(
					'limit'        => 1,
					'paginate'     => true,
					'return'       => 'ids',
					'type'         => 'shop_order',
					'status'       => $status,
					'date_created' => $start->getTimestamp() . '...' . $end->getTimestamp(),
				)
			);

			$total = isset( $result->total ) ? (int) $result->total : 0;

			if ( $total > 0 ) {
				$counts[ str_replace( 'wc-', '', $status ) ] = $total;
			}
		}

		arsort( $counts );

		return $counts;
	}

	/* =========================================================================
	   Internals
	   ====================================================================== */

	/**
	 * Iterate paid orders in a range without loading them all at once.
	 *
	 * A generator rather than an array: a year of orders on a busy store is
	 * tens of thousands of WC_Order objects, and every caller above only ever
	 * needs one at a time. Pages of 200 keep peak memory flat regardless of how
	 * much history the range covers.
	 *
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @return Generator|WC_Order[]
	 */
	private static function each_order( $start, $end ) {
		$page     = 1;
		$per_page = 200;

		do {
			$orders = wc_get_orders(
				array(
					'limit'        => $per_page,
					'page'         => $page,
					'orderby'      => 'date',
					'order'        => 'ASC',
					'type'         => 'shop_order',
					'status'       => self::paid_statuses(),
					// wc_get_orders understands a `from...to` timestamp range on
					// date_created, and it maps to the right column under both
					// the legacy and the HPOS storage.
					'date_created' => $start->getTimestamp() . '...' . $end->getTimestamp(),
				)
			);

			foreach ( $orders as $order ) {
				yield $order;
			}

			$page++;
		} while ( count( $orders ) === $per_page );
	}

	/**
	 * @param string            $prefix Cache namespace.
	 * @param DateTimeImmutable $start  Range start.
	 * @param DateTimeImmutable $end    Range end.
	 * @return string
	 */
	private static function cache_key( $prefix, $start, $end ) {
		return 'ys_ins_' . $prefix . '_' . substr(
			md5( $start->format( 'U' ) . '|' . $end->format( 'U' ) . '|' . implode( ',', self::paid_statuses() ) ),
			0,
			20
		);
	}

	/**
	 * A range that has already ended cannot change, so it can be cached for a
	 * day. A range running up to now gets the short TTL.
	 *
	 * @param DateTimeImmutable $end Range end.
	 * @return int Seconds.
	 */
	private static function cache_ttl( $end ) {
		return ( $end->getTimestamp() < time() - 120 ) ? DAY_IN_SECONDS : self::CACHE_TTL;
	}

	/**
	 * Drop every cached figure.
	 *
	 * Called whenever an order is created or changes status: a dashboard that
	 * shows a five-minute-old total right after the owner marks an order
	 * complete looks broken, even though it is only stale.
	 */
	public static function flush_cache() {
		global $wpdb;

		// Transients have no group to flush, so the option rows are matched by
		// prefix. Both the value and the timeout row have to go.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_ys_ins_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ys_ins_' ) . '%'
			)
		);

		// A persistent object cache still holds the deleted rows under the
		// `options` group. Not every drop-in implements group flushing, hence
		// the capability check rather than a bare call.
		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'options' );
		} else {
			wp_cache_delete( 'alloptions', 'options' );
		}
	}
}

add_action( 'woocommerce_new_order', array( 'YS_Insights_Data', 'flush_cache' ) );
add_action( 'woocommerce_order_status_changed', array( 'YS_Insights_Data', 'flush_cache' ) );
add_action( 'woocommerce_order_refunded', array( 'YS_Insights_Data', 'flush_cache' ) );
