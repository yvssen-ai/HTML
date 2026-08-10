<?php
/**
 * Cart drawer shell.
 *
 * The shell is printed once and never replaced. Only the inner
 * `.ys-drawer__contents` div is swapped by WooCommerce's fragment refresh,
 * which is what keeps the open/close transform from restarting every time a
 * quantity changes.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ys-drawer" id="ys-drawer" role="dialog" aria-modal="true"
	 aria-label="<?php esc_attr_e( 'Your cart', 'ys-store' ); ?>" hidden>

	<div class="ys-drawer__scrim" data-ys-drawer-close></div>

	<div class="ys-drawer__panel">
		<div class="ys-drawer__head">
			<h2><?php esc_html_e( 'Your cart', 'ys-store' ); ?></h2>
			<button type="button" class="ys-icon-btn" data-ys-drawer-close
					aria-label="<?php esc_attr_e( 'Close cart', 'ys-store' ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
		</div>

		<div class="ys-drawer__contents">
			<?php get_template_part( 'template-parts/cart-drawer-contents' ); ?>
		</div>
	</div>
</div>
