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

			<div class="post-list">
				<?php majestic_tube_render_post_grid( 'excerpt' ); ?>
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