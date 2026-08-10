<?php
/**
 * Newsletter block.
 *
 * The form posts nowhere on its own. A store almost certainly already has a
 * list somewhere — Mailchimp, Brevo, Klaviyo — and every one of them ships a
 * WordPress plugin with a shortcode. Setting that shortcode in the filter below
 * replaces the inert markup with the real form, which is a better outcome than
 * this theme collecting addresses into a table nobody exports.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

$shortcode = apply_filters( 'ys_store_newsletter_shortcode', '' );
?>
<section class="section zone-brand" id="newsletter">
	<div class="wrap wrap--narrow" style="text-align:center">
		<span class="eyebrow" style="justify-content:center" data-anim="up">
			<?php esc_html_e( 'Stay in the loop', 'ys-store' ); ?>
		</span>

		<h2 class="ys-split" data-anim="up" style="margin:1rem 0">
			<?php esc_html_e( 'New drops, first', 'ys-store' ); ?>
		</h2>

		<p class="lede" data-anim="up" style="margin:0 auto 2rem">
			<?php esc_html_e( 'One email when something lands. No daily noise, and unsubscribing takes one click.', 'ys-store' ); ?>
		</p>

		<div data-anim="up" style="max-width:520px;margin-inline:auto">
			<?php if ( $shortcode ) : ?>
				<?php echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput -- shortcode output. ?>
			<?php else : ?>
				<p class="mono" style="color:var(--fg-dim)">
					<?php
					printf(
						/* translators: %s: filter name, in code formatting. */
						esc_html__( 'Connect a mailing list by returning its shortcode from the %s filter.', 'ys-store' ),
						'<code>ys_store_newsletter_shortcode</code>' // phpcs:ignore WordPress.Security.EscapeOutput
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</section>
