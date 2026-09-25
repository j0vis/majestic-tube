<?php
/**
 * Template Name: Categories
 *
 * Lists all video categories with images and counts.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

get_header();

// Categories per page comes from the original categories-per-page option.
$per_page        = majestic_tube_terms_per_page( 'categories-per-page', 20 );
$categories_page = majestic_tube_get_paged();

// One cached call for both the page of terms and the total count.
$directory       = majestic_tube_get_term_directory( 'category', $per_page, $categories_page );
$categories      = $directory['terms'];
$categories_total = $directory['total'];
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<header class="page-header">
			<?php the_title( '<h1 class="page-title">', '</h1>' ); ?>
		</header>

		<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>

			<div class="video-grid categories-grid">
				<?php
				foreach ( $categories as $category ) {
					majestic_tube_render_term_card( $category, 'category' );
				}
				?>
			</div>

			<?php majestic_tube_term_pagination( $categories_total, $per_page ); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();
