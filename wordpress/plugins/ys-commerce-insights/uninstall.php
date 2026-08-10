<?php
/**
 * Uninstall.
 *
 * Runs only when the plugin is deleted from the Plugins screen — not on
 * deactivation. This is the one place the collected data is removed, and it is
 * removed completely: the events table, the options, the cached figures, and
 * the per-order tracking flags.
 *
 * @package YS_Insights
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$table = $wpdb->prefix . 'ys_insights_events';

$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

$options = array(
	'ys_insights_collect',
	'ys_insights_retention_days',
	'ys_insights_ga4',
	'ys_insights_pixel',
	'ys_insights_consent',
	'ys_insights_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Cached report figures.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_ys_ins_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ys_ins_' ) . '%'
	)
);

/*
 * The "already tracked" flag on orders. Left behind these are harmless, but a
 * store that reinstalls the plugin would find its historic orders marked as
 * reported and quietly skip them — so they go too. Both storage back-ends are
 * covered: HPOS keeps order meta in its own table, legacy storage in postmeta.
 */
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ys_insights_tracked' ) ); // phpcs:ignore WordPress.DB

$hpos_meta = $wpdb->prefix . 'wc_orders_meta';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_meta ) ) === $hpos_meta ) {
	$wpdb->delete( $hpos_meta, array( 'meta_key' => '_ys_insights_tracked' ) ); // phpcs:ignore WordPress.DB
}

$timestamp = wp_next_scheduled( 'ys_insights_prune' );

if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'ys_insights_prune' );
}
