<?php
/**
 * Footer.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;
?>

<footer class="ys-footer">
	<div class="wrap">
		<div class="ys-footer__grid">
			<div class="ys-footer__col">
				<?php ys_store_brand(); ?>
				<p class="muted" style="margin-top:1rem;max-width:32ch">
					<?php echo esc_html( get_bloginfo( 'description' ) ); ?>
				</p>

				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<div class="ys-payments" style="margin-top:1.4rem">
						<?php
						/*
						 * The gateways the store has actually enabled. A row of
						 * card logos a store cannot accept is a promise it
						 * breaks at the payment step.
						 *
						 * `payment_gateways()` rather than
						 * `get_available_payment_gateways()`: the latter runs
						 * every gateway's availability check against the current
						 * cart, which is work worth doing at checkout and not in
						 * the footer of a blog post.
						 */
						foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
							if ( 'yes' !== $gateway->enabled ) {
								continue;
							}

							echo '<span>' . esc_html( $gateway->get_title() ) . '</span>';
						}
						?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( has_nav_menu( 'shop' ) ) : ?>
				<div class="ys-footer__col">
					<h4><?php esc_html_e( 'Shop', 'ys-store' ); ?></h4>
					<?php ys_store_menu( 'shop', '' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( has_nav_menu( 'help' ) ) : ?>
				<div class="ys-footer__col">
					<h4><?php esc_html_e( 'Help', 'ys-store' ); ?></h4>
					<?php ys_store_menu( 'help', '' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( is_active_sidebar( 'footer-extra' ) ) : ?>
				<?php dynamic_sidebar( 'footer-extra' ); ?>
			<?php endif; ?>
		</div>

		<div class="ys-footer__wordmark" aria-hidden="true"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>

		<div class="ys-footer__legal">
			<span>
				<?php
				printf(
					/* translators: 1: year, 2: site name. */
					esc_html__( '© %1$s %2$s', 'ys-store' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</span>
			<?php if ( has_nav_menu( 'legal' ) ) : ?>
				<nav aria-label="<?php esc_attr_e( 'Legal', 'ys-store' ); ?>" class="row">
					<?php ys_store_menu( 'legal', 'row' ); ?>
				</nav>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php if ( class_exists( 'WooCommerce' ) ) : ?>
	<?php get_template_part( 'template-parts/cart-drawer' ); ?>
<?php endif; ?>

<div class="ys-toast" id="ys-toast" role="status" aria-live="polite"></div>

<?php wp_footer(); ?>
</body>
</html>
