<?php
/**
 * Settings.
 *
 * @package YS_Insights
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen and option registration.
 */
class YS_Insights_Settings {

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
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register the screen, then hide it from the sidebar.
	 *
	 * Registering under WooCommerce and removing the entry keeps the
	 * capability check and the URL that `add_submenu_page` sets up, without
	 * adding a second row to a menu that already has plenty. Passing an empty
	 * or null parent would do the same thing but is deprecated in PHP 8.1+.
	 */
	public function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'YS Insights settings', 'ys-insights' ),
			__( 'YS Insights settings', 'ys-insights' ),
			'manage_woocommerce',
			'ys-insights-settings',
			array( $this, 'render' )
		);

		remove_submenu_page( 'woocommerce', 'ys-insights-settings' );
	}

	/**
	 * Options.
	 */
	public function register() {
		$options = array(
			'ys_insights_collect'        => array( 'boolean', 1 ),
			'ys_insights_retention_days' => array( 'integer', 365 ),
			'ys_insights_ga4'            => array( 'string', '' ),
			'ys_insights_pixel'          => array( 'string', '' ),
			'ys_insights_consent'        => array( 'boolean', 0 ),
		);

		foreach ( $options as $name => list( $type, $default ) ) {
			register_setting(
				'ys_insights',
				$name,
				array(
					'type'              => $type,
					'default'           => $default,
					'sanitize_callback' => $this->sanitizer( $name, $type ),
				)
			);
		}
	}

	/**
	 * @param string $name Option name.
	 * @param string $type Option type.
	 * @return callable
	 */
	private function sanitizer( $name, $type ) {
		if ( 'boolean' === $type ) {
			return function ( $value ) {
				return $value ? 1 : 0;
			};
		}

		if ( 'integer' === $type ) {
			return function ( $value ) {
				// 0 would mean "prune everything on the next sweep", which is
				// never what someone typing a retention period means.
				return max( 1, absint( $value ) );
			};
		}

		if ( 'ys_insights_ga4' === $name ) {
			return function ( $value ) {
				$value = strtoupper( trim( (string) $value ) );

				// A GA4 measurement id, and nothing else. Anything looser here
				// ends up interpolated into a <script src>.
				return preg_match( '/^G-[A-Z0-9]{4,20}$/', $value ) ? $value : '';
			};
		}

		if ( 'ys_insights_pixel' === $name ) {
			return function ( $value ) {
				$value = trim( (string) $value );

				return preg_match( '/^[0-9]{8,20}$/', $value ) ? $value : '';
			};
		}

		return 'sanitize_text_field';
	}

	/**
	 * The form.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'ys-insights' ) );
		}
		?>
		<div class="wrap ys-ins">
			<h1 class="ys-ins__title"><?php esc_html_e( 'YS Insights settings', 'ys-insights' ); ?></h1>

			<form method="post" action="options.php" class="ys-ins__form">
				<?php settings_fields( 'ys_insights' ); ?>

				<h2><?php esc_html_e( 'First-party funnel', 'ys-insights' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Records sessions, product views, cart adds and checkouts into a table on this site so the dashboard can calculate a conversion rate. No IP addresses, user agents or user accounts are stored, the session key is random per visit, and browsers sending Do Not Track are skipped.', 'ys-insights' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Collect funnel data', 'ys-insights' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="ys_insights_collect" value="1"
									<?php checked( get_option( 'ys_insights_collect', 1 ), 1 ); ?>>
								<?php esc_html_e( 'Record visits and cart events on this site', 'ys-insights' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="ys_insights_retention_days"><?php esc_html_e( 'Keep events for', 'ys-insights' ); ?></label>
						</th>
						<td>
							<input type="number" id="ys_insights_retention_days" name="ys_insights_retention_days"
								   min="1" max="3650" class="small-text"
								   value="<?php echo esc_attr( get_option( 'ys_insights_retention_days', 365 ) ); ?>">
							<?php esc_html_e( 'days', 'ys-insights' ); ?>
							<p class="description">
								<?php esc_html_e( 'Older rows are deleted by a daily job. Sales figures are read from WooCommerce orders and are never affected by this.', 'ys-insights' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Third-party tracking', 'ys-insights' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Optional. Leave both blank to send nothing to anyone. When an ID is set, the standard ecommerce events are emitted with correct values — the purchase event is built from the real order rather than from whatever was in the cart when the page unloaded.', 'ys-insights' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ys_insights_ga4"><?php esc_html_e( 'GA4 measurement ID', 'ys-insights' ); ?></label>
						</th>
						<td>
							<input type="text" id="ys_insights_ga4" name="ys_insights_ga4" class="regular-text"
								   placeholder="G-XXXXXXXXXX"
								   value="<?php echo esc_attr( get_option( 'ys_insights_ga4', '' ) ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="ys_insights_pixel"><?php esc_html_e( 'Meta Pixel ID', 'ys-insights' ); ?></label>
						</th>
						<td>
							<input type="text" id="ys_insights_pixel" name="ys_insights_pixel" class="regular-text"
								   placeholder="000000000000000"
								   value="<?php echo esc_attr( get_option( 'ys_insights_pixel', '' ) ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Consent', 'ys-insights' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="ys_insights_consent" value="1"
									<?php checked( get_option( 'ys_insights_consent', 0 ), 1 ); ?>>
								<?php esc_html_e( 'Wait for consent before loading third-party tags', 'ys-insights' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: %s: JavaScript function name. */
									esc_html__( 'With this on, GA4 and the Pixel are held back until something on the page calls %s — which is the call your cookie banner should make when a visitor accepts. The first-party funnel above is unaffected either way.', 'ys-insights' ),
									'<code>window.ysConsentGranted()</code>' // phpcs:ignore WordPress.Security.EscapeOutput
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-insights' ) ); ?>">
					← <?php esc_html_e( 'Back to the dashboard', 'ys-insights' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
