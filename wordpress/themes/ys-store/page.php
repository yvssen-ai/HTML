<?php
/**
 * A single page.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="content" class="ys-main">
	<section class="section" style="padding-top:calc(var(--nav-h) + var(--section-y))">
		<div class="wrap wrap--narrow">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class(); ?>>
					<h1 class="ys-split" data-anim="up"><?php the_title(); ?></h1>

					<?php if ( has_post_thumbnail() ) : ?>
						<div data-anim="up" style="margin:2.5rem 0;border-radius:var(--radius-lg);overflow:hidden">
							<?php the_post_thumbnail( 'ys-wide' ); ?>
						</div>
					<?php endif; ?>

					<div class="ys-prose" data-anim="up">
						<?php
						the_content();

						wp_link_pages(
							array(
								'before' => '<nav class="ys-pagination">',
								'after'  => '</nav>',
							)
						);
						?>
					</div>
				</article>
				<?php
				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
			endwhile;
			?>
		</div>
	</section>
</main>

<?php
get_footer();
