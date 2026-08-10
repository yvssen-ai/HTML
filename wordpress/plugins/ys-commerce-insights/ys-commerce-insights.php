<?php
/**
 * Plugin Name: YS Commerce Insights
 * Plugin URI:  https://github.com/yvssen-ai/HTML
 * Description: Sales analytics for WooCommerce, in the YS Store visual language. A dashboard of revenue, orders, AOV and conversion rate; a first-party funnel that works without any third-party tracker; and correct GA4 / Meta Pixel ecommerce events for the stores that want one.
 * Version:     1.0.0
 * Author:      Yassen Hassan
 * License:     GPL-2.0-or-later
 * Text Domain: ys-insights
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.8
 *
 * @package YS_Insights
 */

defined( 'ABSPATH' ) || exit;

define( 'YS_INSIGHTS_VERSION', '1.0.0' );
define( 'YS_INSIGHTS_FILE', __FILE__ );
define( 'YS_INSIGHTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'YS_INSIGHTS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Tell WooCommerce this plugin is safe with High-Performance Order Storage.
 *
 * Without this declaration WooCommerce refuses to let a store enable HPOS while
 * the plugin is active, and shows the owner an incompatibility warning. Every
 * order read in this plugin goes through wc_get_orders() and the CRUD API, so
 * the declaration is accurate — there is not a single query against wp_posts
 * for order data.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', YS_INSIGHTS_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', YS_INSIGHTS_FILE, true );
		}
	}
);

require_once YS_INSIGHTS_DIR . 'includes/class-ys-insights-data.php';
require_once YS_INSIGHTS_DIR . 'includes/class-ys-insights-collector.php';
require_once YS_INSIGHTS_DIR . 'includes/class-ys-insights-rest.php';
require_once YS_INSIGHTS_DIR . 'includes/class-ys-insights-admin.php';
require_once YS_INSIGHTS_DIR . 'includes/class-ys-insights-settings.php';
require_once YS_INSIGHTS_DIR . 'includes/class-ys-insights-tracking.php';

/**
 * Boot, once WooCommerce is known to be there.
 *
 * `plugins_loaded` at priority 20 is late enough that WooCommerce has defined
 * its classes and early enough to register admin menus and REST routes.
 */
function ys_insights_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ys_insights_missing_woo_notice' );

		return;
	}

	YS_Insights_Collector::instance()->hooks();
	YS_Insights_REST::instance()->hooks();
	YS_Insights_Admin::instance()->hooks();
	YS_Insights_Settings::instance()->hooks();
	YS_Insights_Tracking::instance()->hooks();
}
add_action( 'plugins_loaded', 'ys_insights_boot', 20 );

/**
 * Admin notice when WooCommerce is not active.
 */
function ys_insights_missing_woo_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	esc_html_e( 'YS Commerce Insights needs WooCommerce. It is sitting idle until WooCommerce is activated.', 'ys-insights' );
	echo '</p></div>';
}

/**
 * Activation: create the events table and schedule the retention sweep.
 */
function ys_insights_activate() {
	YS_Insights_Collector::create_table();

	if ( ! wp_next_scheduled( 'ys_insights_prune' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ys_insights_prune' );
	}

	add_option( 'ys_insights_db_version', YS_INSIGHTS_VERSION );
}
register_activation_hook( __FILE__, 'ys_insights_activate' );

/**
 * Deactivation: stop the sweep. The data stays — a store owner who deactivates
 * to debug something should not lose a year of history for it. uninstall.php
 * is where deletion belongs.
 */
function ys_insights_deactivate() {
	$timestamp = wp_next_scheduled( 'ys_insights_prune' );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'ys_insights_prune' );
	}
}
register_deactivation_hook( __FILE__, 'ys_insights_deactivate' );

/**
 * Run the table migration when the plugin is updated in place, which does not
 * fire the activation hook.
 */
function ys_insights_maybe_upgrade() {
	if ( get_option( 'ys_insights_db_version' ) === YS_INSIGHTS_VERSION ) {
		return;
	}

	YS_Insights_Collector::create_table();
	update_option( 'ys_insights_db_version', YS_INSIGHTS_VERSION );
}
add_action( 'admin_init', 'ys_insights_maybe_upgrade' );
