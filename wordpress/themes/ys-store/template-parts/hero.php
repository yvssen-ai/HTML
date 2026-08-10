<?php
/**
 * Hero.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

$shop_url = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

$cta_url  = get_theme_mod( 'ys_hero_cta_url' ) ? get_theme_mod( 'ys_hero_cta_url' ) : $shop_url;
$cta2_url = get_theme_mod( 'ys_hero_cta2_url' );
?>
<section class="ys-hero" id="hero">
	<canvas class="ys-hero__field" id="ys-field" aria-hidden="true"></canvas>
	<div class="ys-hero__glow" aria-hidden="true"></div>

	<div class="wrap ys-hero__inner">
		<?php if ( get_theme_mod( 'ys_hero_eyebrow' ) ) : ?>
			<span class="eyebrow" data-anim="up" data-ys-hero-eyebrow>
				<?php echo esc_html( get_theme_mod( 'ys_hero_eyebrow' ) ); ?>
			</span>
		<?php endif; ?>

		<h1 class="grad ys-split" data-anim="up" data-ys-hero-title>
			<?php
			echo esc_html(
				get_theme_mod( 'ys_hero_title', __( 'Built for people who move fast', 'ys-store' ) )
			);
			?>
		</h1>

		<p class="lede" data-anim="up" data-ys-hero-text>
			<?php
			echo esc_html(
				get_theme_mod( 'ys_hero_text', __( 'A curated catalogue, honest pricing, and delivery that arrives when we said it would.', 'ys-store' ) )
			);
			?>
		</p>

		<div class="ys-hero__cta" data-anim="up">
			<a class="btn btn--primary btn--lg" href="<?php echo esc_url( $cta_url ); ?>" data-magnet
			   data-cursor="<?php esc_attr_e( 'Go', 'ys-store' ); ?>">
				<?php echo esc_html( get_theme_mod( 'ys_hero_cta_text', __( 'Shop the catalogue', 'ys-store' ) ) ); ?>
			</a>

			<?php if ( $cta2_url ) : ?>
				<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( $cta2_url ); ?>" data-magnet>
					<?php echo esc_html( get_theme_mod( 'ys_hero_cta2_text', __( 'Best sellers', 'ys-store' ) ) ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>
