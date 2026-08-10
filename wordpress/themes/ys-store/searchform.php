<?php
/**
 * Search form.
 *
 * Scoped to products when WooCommerce is active. A shopper typing into a store's
 * search box is looking for something to buy, not for a blog post; the hidden
 * post_type is what makes the results reflect that.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

$id = 'ys-search-' . wp_unique_id();
?>
<form role="search" method="get" class="ys-search row" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>">
		<?php esc_html_e( 'Search', 'ys-store' ); ?>
	</label>

	<input type="search" id="<?php echo esc_attr( $id ); ?>" name="s"
		   value="<?php echo esc_attr( get_search_query() ); ?>"
		   placeholder="<?php esc_attr_e( 'What are you looking for?', 'ys-store' ); ?>"
		   style="flex:1 1 220px">

	<?php if ( class_exists( 'WooCommerce' ) ) : ?>
		<input type="hidden" name="post_type" value="product">
	<?php endif; ?>

	<button type="submit" class="btn btn--primary"><?php esc_html_e( 'Search', 'ys-store' ); ?></button>
</form>
