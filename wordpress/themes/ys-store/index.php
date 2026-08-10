<?php
/**
 * The fallback template.
 *
 * Used for the blog index, archives and search results when no more specific
 * template matches.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="content" class="ys-main">
	<section class="section" style="padding-top:calc(var(--nav-h) + var(--section-y))">
		<div class="wrap">
			<?php if ( have_posts() ) : ?>

				<div class="sec-head">
					<div class="sec-head__text">
						<span class="eyebrow" data-anim="up">
							<?php
							if ( is_search() ) {
								esc_html_e( 'Search', 'ys-store' );
							} elseif ( is_archive() ) {
								esc_html_e( 'Archive', 'ys-store' );
							} else {
								esc_html_e( 'Journal', 'ys-store' );
							}
							?>
						</span>
						<h1 class="ys-split" data-anim="up">
							<?php
							if ( is_search() ) {
								printf(
									/* translators: %s: search query. */
									esc_html__( 'Results for %s', 'ys-store' ),
									esc_html( get_search_query() )
								);
							} elseif ( is_archive() ) {
								the_archive_title();
							} else {
								echo esc_html( get_the_title( (int) get_option( 'page_for_posts' ) ) ?: __( 'Latest', 'ys-store' ) );
							}
							?>
						</h1>
					</div>
				</div>

				<div class="grid grid--3">
					<?php
					while ( have_posts() ) :
						the_post();
						?>
						<article <?php post_class( 'card' ); ?> data-anim="up">
							<?php if ( has_post_thumbnail() ) : ?>
								<div class="card__media">
									<?php the_post_thumbnail( 'ys-wide', array( 'alt' => the_title_attribute( array( 'echo' => false ) ) ) ); ?>
								</div>
							<?php endif; ?>

							<span class="card__index"><?php echo esc_html( get_the_date() ); ?></span>
							<h3><?php the_title(); ?></h3>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>

							<a class="card__link" href="<?php the_permalink(); ?>"
							   data-cursor="<?php esc_attr_e( 'Read', 'ys-store' ); ?>">
								<span class="screen-reader-text"><?php the_title(); ?></span>
							</a>
						</article>
					<?php endwhile; ?>
				</div>

				<?php ys_store_pagination(); ?>

			<?php else : ?>

				<h1><?php esc_html_e( 'Nothing found', 'ys-store' ); ?></h1>
				<p class="lede"><?php esc_html_e( 'Try a different search, or head back to the shop.', 'ys-store' ); ?></p>
				<?php get_search_form(); ?>

			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
