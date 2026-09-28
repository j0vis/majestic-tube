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

/*
 * Every tag is listed on one page, so this directory has no paginator.
 *
 * A tag is a small label and the cloud is a flat, wrapping list: paging it
 * split a set of related tags across pages, and someone looking for one tag
 * had to page through the rest to find it. The categories and actors
 * directories still paginate, because those are card grids where a page
 * boundary is the natural way to break up a long wall of images.
 *
 * A per_page of 0 tells majestic_tube_get_term_directory() not to limit the
 * query, so no option is read for a setting that no longer applies here.
 */
$per_page  = 0;
$tags_page = 1;
$letter    = majestic_tube_get_requested_letter();

// One cached call for both the terms and the total count.
$directory = majestic_tube_get_term_directory( 'post_tag', $per_page, $tags_page, $letter );
$tags      = $directory['terms'];
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<header class="page-header">
			<?php the_title( '<h1 class="page-title">', '</h1>' ); ?>
		</header>

		<?php majestic_tube_term_letter_nav( 'post_tag' ); ?>

		<?php if ( ! is_wp_error( $tags ) && $tags ) : ?>

			<div class="tags-cloud">
				<?php foreach ( $tags as $tag ) : ?>
					<a class="tag-item" href="<?php echo esc_url( get_term_link( $tag ) ); ?>">
						<?php echo esc_html( $tag->name ); ?>
						<span class="tag-count"><?php echo esc_html( number_format_i18n( $tag->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();
