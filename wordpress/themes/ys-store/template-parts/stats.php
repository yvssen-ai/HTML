<?php
/**
 * Stats strip.
 *
 * Every number here is read from the store, not typed into a settings field.
 * A figure a shopper can check is worth showing; one the theme invented is not,
 * so the strip hides itself entirely on a catalogue with nothing to report.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/*
 * Counting products and orders on every front-page view would be two
 * uncached queries on the hottest page of the site. A day of cache is well
 * inside the resolution these numbers are read at.
 */
$stats = get_transient( 'ys_store_stats' );

if ( false === $stats ) {
	$product_count = (int) wp_count_posts( 'product' )->publish;

	$orders = function_exists( 'wc_get_orders' )
		? wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => array( 'wc-completed', 'wc-processing' ),
				'return'     => 'ids',
				'date_after' => '-365 days',
			)
		)
		: array();

	$reviews = get_comments(
		array(
			'post_type' => 'product',
			'status'    => 'approve',
			'count'     => true,
			'type'      => 'review',
		)
	);

	$stats = array(
		'products' => $product_count,
		'orders'   => count( $orders ),
		'reviews'  => (int) $reviews,
	);

	set_transient( 'ys_store_stats', $stats, DAY_IN_SECONDS );
}

$tiles = array();

if ( $stats['products'] > 0 ) {
	$tiles[] = array( ys_store_compact_number( $stats['products'] ), __( 'Products in stock', 'ys-store' ) );
}

if ( $stats['orders'] > 0 ) {
	$tiles[] = array( ys_store_compact_number( $stats['orders'] ), __( 'Orders shipped this year', 'ys-store' ) );
}

if ( $stats['reviews'] > 0 ) {
	$tiles[] = array( ys_store_compact_number( $stats['reviews'] ), __( 'Customer reviews', 'ys-store' ) );
}

if ( count( $tiles ) < 2 ) {
	return;
}
?>
<section class="ys-stats" aria-label="<?php esc_attr_e( 'Store at a glance', 'ys-store' ); ?>">
	<?php foreach ( $tiles as $tile ) : ?>
		<div class="ys-stat" data-anim="up">
			<div class="ys-stat__num" data-count-to="<?php echo esc_attr( preg_replace( '/[^0-9.]/', '', $tile[0] ) ); ?>">
				<?php echo esc_html( $tile[0] ); ?>
			</div>
			<div class="ys-stat__label"><?php echo esc_html( $tile[1] ); ?></div>
		</div>
	<?php endforeach; ?>
</section>
