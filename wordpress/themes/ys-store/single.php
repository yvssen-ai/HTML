<?php
/**
 * A single post.
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
					<span class="eyebrow" data-anim="up"><?php echo esc_html( get_the_date() ); ?></span>
					<h1 class="ys-split" data-anim="up" style="margin:1rem 0"><?php the_title(); ?></h1>

					<?php if ( has_post_thumbnail() ) : ?>
						<div data-anim="up" style="margin:2.5rem 0;border-radius:var(--radius-lg);overflow:hidden">
							<?php the_post_thumbnail( 'ys-wide' ); ?>
						</div>
					<?php endif; ?>

					<div class="ys-prose" data-anim="up"><?php the_content(); ?></div>

					<footer style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid var(--line)">
						<?php the_tags( '<div class="row">', '', '</div>' ); ?>
					</footer>
				</article>

				<nav class="row" style="justify-content:space-between;margin-top:2.5rem">
					<?php
					previous_post_link( '%link', '← %title' );
					next_post_link( '%link', '%title →' );
					?>
				</nav>

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
