<?php
/**
 * Admin dashboard.
 *
 * The page itself is a static shell. Everything in it is drawn by admin.js from
 * a single REST call, so switching the range is instant and the page has one
 * rendering path rather than one for PHP and one for JS.
 *
 * @package YS_Insights
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dashboard screen.
 */
class YS_Insights_Admin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Screen hook suffix, so assets load on this page only.
	 *
	 * @var string
	 */
	private $screen = '';

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
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( YS_INSIGHTS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add the page under WooCommerce, where a store owner already looks.
	 */
	public function menu() {
		$this->screen = add_submenu_page(
			'woocommerce',
			__( 'YS Insights', 'ys-insights' ),
			__( 'YS Insights', 'ys-insights' ),
			'view_woocommerce_reports',
			'ys-insights',
			array( $this, 'render' ),
			2
		);
	}

	/**
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=ys-insights' ) ),
				esc_html__( 'Dashboard', 'ys-insights' )
			)
		);

		return $links;
	}

	/**
	 * Dashboard assets.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->screen ) {
			return;
		}

		wp_enqueue_style(
			'ys-insights-admin',
			YS_INSIGHTS_URL . 'assets/admin.css',
			array(),
			YS_INSIGHTS_VERSION
		);

		wp_enqueue_script(
			'ys-insights-admin',
			YS_INSIGHTS_URL . 'assets/admin.js',
			array(),
			YS_INSIGHTS_VERSION,
			true
		);

		wp_localize_script(
			'ys-insights-admin',
			'ysInsights',
			array(
				'root'     => esc_url_raw( rest_url( 'ys-insights/v1/report' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'settings' => esc_url_raw( admin_url( 'admin.php?page=ys-insights-settings' ) ),
				'locale'   => str_replace( '_', '-', get_user_locale() ),
				'ranges'   => array(
					'today'     => __( 'Today', 'ys-insights' ),
					'yesterday' => __( 'Yesterday', 'ys-insights' ),
					'7d'        => __( 'Last 7 days', 'ys-insights' ),
					'30d'       => __( 'Last 30 days', 'ys-insights' ),
					'90d'       => __( 'Last 90 days', 'ys-insights' ),
					'mtd'       => __( 'Month to date', 'ys-insights' ),
					'ytd'       => __( 'Year to date', 'ys-insights' ),
					'all'       => __( 'All time', 'ys-insights' ),
				),
				'i18n'     => array(
					'revenue'        => __( 'Revenue', 'ys-insights' ),
					'net'            => __( 'Net of refunds', 'ys-insights' ),
					'orders'         => __( 'Orders', 'ys-insights' ),
					'aov'            => __( 'Average order', 'ys-insights' ),
					'items'          => __( 'Items sold', 'ys-insights' ),
					'refunds'        => __( 'Refunded', 'ys-insights' ),
					'customers'      => __( 'Customers', 'ys-insights' ),
					'cvr'            => __( 'Conversion rate', 'ys-insights' ),
					'vsPrevious'     => __( 'vs previous period', 'ys-insights' ),
					'noPrevious'     => __( 'no prior data', 'ys-insights' ),
					'revenueOverTime' => __( 'Revenue over time', 'ys-insights' ),
					'funnel'         => __( 'Funnel', 'ys-insights' ),
					'sessions'       => __( 'Sessions', 'ys-insights' ),
					'viewedProduct'  => __( 'Viewed a product', 'ys-insights' ),
					'addedToCart'    => __( 'Added to cart', 'ys-insights' ),
					'reachedCheckout' => __( 'Reached checkout', 'ys-insights' ),
					'purchased'      => __( 'Purchased', 'ys-insights' ),
					'topProducts'    => __( 'Top products by revenue', 'ys-insights' ),
					'lowStock'       => __( 'Running low', 'ys-insights' ),
					'recentOrders'   => __( 'Recent orders', 'ys-insights' ),
					'sources'        => __( 'Where the money came from', 'ys-insights' ),
					'orderStatuses'  => __( 'Order statuses', 'ys-insights' ),
					'units'          => __( 'units', 'ys-insights' ),
					'left'           => __( 'left', 'ys-insights' ),
					'nothing'        => __( 'Nothing in this period.', 'ys-insights' ),
					'loading'        => __( 'Loading…', 'ys-insights' ),
					'error'          => __( 'Could not load the report.', 'ys-insights' ),
					'retry'          => __( 'Try again', 'ys-insights' ),
					'collectorOff'   => __( 'Funnel collection is switched off, so conversion rate cannot be calculated.', 'ys-insights' ),
					'collectorNew'   => __( 'No visits recorded yet. Conversion rate will appear once the store has traffic.', 'ys-insights' ),
					'openSettings'   => __( 'Settings', 'ys-insights' ),
				),
			)
		);
	}

	/**
	 * The shell.
	 */
	public function render() {
		if ( ! current_user_can( 'view_woocommerce_reports' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view store reports.', 'ys-insights' ) );
		}
		?>
		<div class="wrap ys-ins">
			<div class="ys-ins__bar">
				<div>
					<h1 class="ys-ins__title"><?php esc_html_e( 'YS Insights', 'ys-insights' ); ?></h1>
					<p class="ys-ins__sub" id="ys-ins-range-label"></p>
				</div>

				<div class="ys-ins__controls">
					<label class="screen-reader-text" for="ys-ins-range">
						<?php esc_html_e( 'Date range', 'ys-insights' ); ?>
					</label>
					<select id="ys-ins-range"></select>

					<a class="ys-ins__link" href="<?php echo esc_url( admin_url( 'admin.php?page=ys-insights-settings' ) ); ?>">
						<?php esc_html_e( 'Settings', 'ys-insights' ); ?>
					</a>
				</div>
			</div>

			<div id="ys-ins-app" aria-live="polite">
				<p class="ys-ins__loading"><?php esc_html_e( 'Loading…', 'ys-insights' ); ?></p>
			</div>
		</div>
		<?php
	}
}
