<?php
/**
 * No products found.
 *
 * Overrides woocommerce/templates/loop/no-products-found.php.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ys-notice">
	<div>
		<strong><?php esc_html_e( 'Nothing matches that yet.', 'ys-store' ); ?></strong>
		<p class="muted" style="margin-top:.4rem">
			<?php esc_html_e( 'Clear the filters, or browse the full catalogue.', 'ys-store' ); ?>
		</p>
	</div>
	<a class="btn btn--ghost btn--sm" style="margin-left:auto"
	   href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
		<?php esc_html_e( 'All products', 'ys-store' ); ?>
	</a>
</div>
