<?php
/**
 * Header.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="profile" href="https://gmpg.org/xfn/11">
<?php wp_head(); ?>
<script>
/* Written before first paint, and deliberately not in an enqueued file.
   animations.css hides every [data-anim] element ONLY under .ys-js, so a
   visitor whose JavaScript never arrives gets the whole page rather than a
   blank one — and a visitor whose JS does arrive never sees the flash of
   already-positioned content that a class added on DOMContentLoaded causes. */
document.documentElement.classList.add('ys-js');

/* Safety net. If app.js never boots — a CDN that times out, a caching plugin
   that mangles the bundle, an extension that blocks it — the class above would
   leave every [data-anim] element at opacity 0 and the store would look empty.
   Four seconds is well past a normal boot and well inside a shopper's patience. */
window.setTimeout(function () {
	if (!window.ysBooted) {
		document.documentElement.classList.remove('ys-js');
	}
}, 4000);
</script>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#content"><?php esc_html_e( 'Skip to content', 'ys-store' ); ?></a>

<?php if ( ys_store_should_preload() ) : ?>
	<div class="ys-preloader" id="ys-preloader" role="status" aria-live="polite">
		<div class="ys-preloader__mark grad" aria-hidden="true">
			<?php
			/*
			 * One span per character so the intro can stagger them. The mark is
			 * aria-hidden and the readable status sits in the counter below, so
			 * a screen reader hears "Loading", not the letters one at a time.
			 */
			$mark = wp_strip_all_tags( get_bloginfo( 'name' ) );
			$mark = $mark ? $mark : 'YS';

			foreach ( preg_split( '//u', mb_substr( $mark, 0, 12 ), -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
				echo '<span>' . esc_html( ' ' === $char ? "\u{00A0}" : $char ) . '</span>';
			}
			?>
		</div>
		<div class="ys-preloader__bar" aria-hidden="true"><i id="ys-preloader-bar"></i></div>
		<p class="ys-preloader__count"><span id="ys-preloader-count">0</span>% · <?php esc_html_e( 'Loading', 'ys-store' ); ?></p>
	</div>
<?php endif; ?>

<div class="ys-progress" aria-hidden="true"><i id="ys-progress-bar"></i></div>
<div class="ys-grain" aria-hidden="true"></div>

<?php if ( get_theme_mod( 'ys_cursor', true ) ) : ?>
	<div class="ys-cursor" id="ys-cursor" aria-hidden="true">
		<div class="ys-cursor__dot"></div>
		<div class="ys-cursor__ring"><span class="ys-cursor__label"></span></div>
	</div>
<?php endif; ?>

<header class="ys-nav" id="ys-nav">
	<div class="ys-nav__inner">
		<?php ys_store_brand(); ?>

		<nav class="ys-nav__nav" aria-label="<?php esc_attr_e( 'Primary', 'ys-store' ); ?>">
			<?php ys_store_menu( 'primary', 'ys-nav__menu' ); ?>
		</nav>

		<div class="ys-nav__actions">
			<button class="ys-icon-btn" id="ys-search-toggle" type="button"
					aria-label="<?php esc_attr_e( 'Search products', 'ys-store' ); ?>"
					aria-expanded="false" aria-controls="ys-search-panel">
				<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			</button>

			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<button class="ys-icon-btn" id="ys-cart-toggle" type="button"
						aria-label="<?php esc_attr_e( 'Open cart', 'ys-store' ); ?>"
						aria-expanded="false" aria-controls="ys-drawer">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l-1.2 12H7.2z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
					<?php ys_store_cart_count(); ?>
				</button>
			<?php endif; ?>

			<button class="ys-burger" id="ys-burger" type="button"
					aria-label="<?php esc_attr_e( 'Menu', 'ys-store' ); ?>"
					aria-expanded="false" aria-controls="ys-mobile-menu">
				<i></i><i></i>
			</button>
		</div>
	</div>

	<?php
	/*
	 * The search panel lives inside the fixed header so it drops out of it
	 * rather than appearing at the top of the document. It is `hidden` until
	 * the toggle opens it, which keeps the field out of the tab order — a
	 * collapsed search box that still catches Tab is a small, constant
	 * annoyance for anyone using a keyboard.
	 */
	?>
	<div class="ys-search-panel" id="ys-search-panel" hidden>
		<div class="wrap"><?php get_search_form(); ?></div>
	</div>
</header>

<div class="ys-mobile-menu" id="ys-mobile-menu">
	<?php ys_store_menu( 'primary', 'ys-mobile-menu__list' ); ?>
	<?php if ( class_exists( 'WooCommerce' ) ) : ?>
		<a class="btn btn--primary" style="margin-top:1.5rem" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
			<?php esc_html_e( 'View cart', 'ys-store' ); ?>
		</a>
	<?php endif; ?>
</div>
