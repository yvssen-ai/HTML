<?php
/**
 * REST API.
 *
 * The dashboard is a static shell that fetches from here, so changing the range
 * never reloads wp-admin — and the same endpoint can back a mobile view or an
 * external report without a second implementation.
 *
 * @package YS_Insights
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only reporting routes.
 */
class YS_Insights_REST {

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Routes.
	 */
	public function register_routes() {
		register_rest_route(
			'ys-insights/v1',
			'/report',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'report' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'range' => array(
						'type'              => 'string',
						'default'           => '30d',
						'enum'              => array( 'today', 'yesterday', '7d', '30d', '90d', 'mtd', 'ytd', 'all' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Who may read the numbers.
	 *
	 * `view_woocommerce_reports` rather than `manage_options`: a shop manager
	 * needs the dashboard and does not need the keys to the whole site.
	 *
	 * @return bool
	 */
	public function can_read() {
		return current_user_can( 'view_woocommerce_reports' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * The whole dashboard payload in one request.
	 *
	 * One round trip rather than six: the panels are read together, and six
	 * parallel requests would each pay the cost of booting WordPress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function report( $request ) {
		$range = $request->get_param( 'range' );

		list( $start, $end )             = YS_Insights_Data::resolve_range( $range );
		list( $prev_start, $prev_end )   = YS_Insights_Data::previous_range( $start, $end );

		$current  = YS_Insights_Data::summary( $start, $end );
		$previous = YS_Insights_Data::summary( $prev_start, $prev_end );

		$funnel      = YS_Insights_Collector::funnel( $start, $end );
		$prev_funnel = YS_Insights_Collector::funnel( $prev_start, $prev_end );

		$response = array(
			'range'    => array(
				'key'   => $range,
				'start' => $start->format( 'c' ),
				'end'   => $end->format( 'c' ),
				'label' => $this->range_label( $start, $end ),
			),
			'currency' => array(
				'code'      => get_woocommerce_currency(),
				'symbol'    => html_entity_decode( get_woocommerce_currency_symbol() ),
				'decimals'  => wc_get_price_decimals(),
				'position'  => get_option( 'woocommerce_currency_pos', 'left' ),
				'thousands' => wc_get_price_thousand_separator(),
				'decimal'   => wc_get_price_decimal_separator(),
			),
			'kpis'     => array(
				'revenue'   => $this->kpi( $current['revenue'], $previous['revenue'] ),
				'net'       => $this->kpi( $current['net'], $previous['net'] ),
				'orders'    => $this->kpi( $current['orders'], $previous['orders'] ),
				'aov'       => $this->kpi( $current['aov'], $previous['aov'] ),
				'items'     => $this->kpi( $current['items'], $previous['items'] ),
				'refunds'   => $this->kpi( $current['refunds'], $previous['refunds'] ),
				'customers' => $this->kpi( $current['customers'], $previous['customers'] ),
				'cvr'       => $this->kpi( $funnel['conversion_rate'], $prev_funnel['conversion_rate'] ),
			),
			'series'   => YS_Insights_Data::series( $start, $end ),
			'funnel'   => $funnel,
			'top'      => YS_Insights_Data::top_products( $start, $end ),
			'lowStock' => YS_Insights_Data::low_stock(),
			'recent'   => YS_Insights_Data::recent_orders(),
			'statuses' => YS_Insights_Data::status_breakdown( $start, $end ),
			'sources'  => YS_Insights_Collector::sources( $start, $end ),
			'tracking' => array(
				'collecting' => (bool) get_option( 'ys_insights_collect', 1 ),
				'hasData'    => YS_Insights_Collector::has_data(),
			),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * A value with its change against the previous period.
	 *
	 * `change` is null rather than 0 when the previous period was zero: a jump
	 * from nothing to something is not "+100%", it is a first, and the UI says
	 * so instead of printing a number that means nothing.
	 *
	 * @param float $now  Current value.
	 * @param float $then Previous value.
	 * @return array
	 */
	private function kpi( $now, $then ) {
		$now  = (float) $now;
		$then = (float) $then;

		return array(
			'value'    => $now,
			'previous' => $then,
			'change'   => $then > 0 ? ( ( $now - $then ) / $then ) * 100 : null,
		);
	}

	/**
	 * @param DateTimeImmutable $start Range start.
	 * @param DateTimeImmutable $end   Range end.
	 * @return string
	 */
	private function range_label( $start, $end ) {
		$format = get_option( 'date_format' );

		return sprintf(
			/* translators: 1: start date, 2: end date. */
			__( '%1$s — %2$s', 'ys-insights' ),
			wp_date( $format, $start->getTimestamp() ),
			wp_date( $format, $end->getTimestamp() )
		);
	}
}
