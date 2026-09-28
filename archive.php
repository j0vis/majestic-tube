<?php
/**
 * The generic archive template.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<?php
		/*
		 * Category and tag descriptions can be moved below the listing with the
		 * original cat-desc-position / tag-desc-position options.
		 */
		$description = get_the_archive_description();

		if ( is_category() ) {
			$desc_position = majestic_tube_get_option( 'wpst-options', 'cat-desc-position', 'top' );
		} elseif ( is_tag() ) {
			$desc_position = majestic_tube_get_option( 'wpst-options', 'tag-desc-position', 'top' );
		} else {
			$desc_position = 'top';
		}

		$desc_below = ( 'bottom' === $desc_position );
		?>

		<header class="page-header">
			<h1 class="page-title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
			<?php
			if ( $description && ! $desc_below ) {
				echo '<div class="archive-description">' . wp_kses_post( $description ) . '</div>';
			}
			?>
		</header>

		<?php if ( have_posts() ) : ?>

			<?php
			/*
			 * The standard video grid, exactly as index.php, the video branch
			 * of search.php and taxonomy-actors.php render it.
			 *
			 * This template used to ask for the excerpt part inside a
			 * .post-list instead, which is a one-across column of full-width
			 * images with the excerpt underneath. Every archive a video site
			 * serves - category, tag, actor, author, date - is videos, so that
			 * layout only ever produced an overblown single column on top of
			 * losing the card's duration, rating and duration badges. The
			 * excerpt part is still what search.php uses for page results,
			 * which is the one listing that genuinely has no thumbnail.
			 */
			?>
			<div class="video-grid">
				<?php majestic_tube_render_post_grid(); ?>
			</div>

			<?php if ( $description && $desc_below ) : ?>
				<div class="archive-description archive-description-bottom"><?php echo wp_kses_post( $description ); ?></div>
			<?php endif; ?>

			<?php majestic_tube_the_pagination(); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();