<?php
/**
 * Template Name: Actors
 *
 * Lists all actors with portraits and video counts.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

get_header();

// Actors per page comes from the original actors-per-page option.
$per_page    = majestic_tube_terms_per_page( 'actors-per-page', 20 );
$actors_page = majestic_tube_get_paged();

// One cached call for both the page of terms and the total count.
$directory   = majestic_tube_get_term_directory( 'actors', $per_page, $actors_page );
$actors      = $directory['terms'];
$actors_total = $directory['total'];
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<header class="page-header">
			<?php the_title( '<h1 class="page-title">', '</h1>' ); ?>
		</header>

		<?php if ( ! is_wp_error( $actors ) && $actors ) : ?>

			<div class="video-grid actors-grid">
				<?php
				foreach ( $actors as $actor ) {
					majestic_tube_render_term_card( $actor, 'actors' );
				}
				?>				</div>

				<?php majestic_tube_term_pagination( $actors_total, $per_page ); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();
