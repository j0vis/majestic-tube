<?php
/**
 * The page template.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main">

			<?php majestic_tube_breadcrumbs(); ?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				</header>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="post-thumbnail">
						<?php the_post_thumbnail( 'majestic-tube-thumb-large' ); ?>
					</div>
				<?php endif; ?>

				<div class="entry-content">
					<?php
					the_content();

					wp_link_pages(
						array(
							'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'majestic-tube' ),
							'after'  => '</div>',
						)
					);
					?>
				</div>

			</article>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>

		</main>
	</div>

	<?php
endwhile;

get_footer();