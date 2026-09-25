<?php
/**
 * Template Name: Tags
 *
 * Lists all video tags.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

get_header();

// Tags per page reuses the categories-per-page option, like the original.
$per_page  = majestic_tube_terms_per_page( 'categories-per-page', 20 );
$tags_page = majestic_tube_get_paged();

// One cached call for both the page of terms and the total count.
$directory = majestic_tube_get_term_directory( 'post_tag', $per_page, $tags_page );
$tags      = $directory['terms'];
$tags_total = $directory['total'];
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<header class="page-header">
			<?php the_title( '<h1 class="page-title">', '</h1>' ); ?>
		</header>

		<?php if ( ! is_wp_error( $tags ) && $tags ) : ?>

			<div class="tags-cloud">
				<?php foreach ( $tags as $tag ) : ?>
					<a class="tag-item" href="<?php echo esc_url( get_term_link( $tag ) ); ?>">
						<?php echo esc_html( $tag->name ); ?>
						<span class="tag-count"><?php echo esc_html( number_format_i18n( $tag->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<?php majestic_tube_term_pagination( $tags_total, $per_page ); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();
