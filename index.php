<?php
/**
 * The main template file (video homepage).
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<?php
		/*
		 * Homepage title: the original shows the homepage-title option (falling
		 * back to the posts-page title) above or below the grid depending on
		 * homepage-title-desc-position.
		 */
		$homepage_title = (string) majestic_tube_get_option( 'wpst-options', 'homepage-title', '' );

		if ( ! $homepage_title && is_home() && ! is_front_page() ) {
			$homepage_title = get_the_title( get_option( 'page_for_posts' ) );
		}

		// Default mirrors homepage-title-desc-position in inc/theme-options.php.
		$title_position = majestic_tube_get_option( 'wpst-options', 'homepage-title-desc-position', 'top' );
		$title_above    = ( 'top' === $title_position );

		// SEO text: printed both under the homepage title and again at the foot
		// of the page, so the description is present in the markup whichever
		// end a reader or a crawler reaches first.
		$seo_text = trim( (string) majestic_tube_get_option( 'wpst-options', 'seo-footer-text', '' ) );
		?>

		<?php if ( $title_above && ( $homepage_title || '' !== $seo_text ) ) : ?>
			<header class="page-header">
				<?php if ( $homepage_title ) : ?>
					<h1 class="homepage-title"><?php echo esc_html( $homepage_title ); ?></h1>
				<?php endif; ?>

				<?php if ( '' !== $seo_text ) : ?>
					<p class="archive-description"><?php echo wp_kses_post( $seo_text ); ?></p>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php
		majestic_tube_filter_nav();
		majestic_tube_tags_slider();
		?>

		<?php if ( have_posts() ) : ?>

			<div class="video-grid">
				<?php majestic_tube_render_post_grid(); ?>
			</div>

			<?php majestic_tube_the_pagination(); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

		<?php
		/*
		 * The title block, when the site has chosen to put the title at the
		 * bottom of the page.
		 *
		 * This sits outside the have_posts() branch on purpose. A site that
		 * has not published its first video yet still wants a title and a
		 * description on its front page - that is precisely the moment the
		 * description is doing the most work, because it is all the page has
		 * to say. Inside the branch, an empty homepage printed neither.
		 */
		if ( ! $title_above && ( $homepage_title || '' !== $seo_text ) ) :
			?>
			<header class="page-header homepage-title-block">
				<?php if ( $homepage_title ) : ?>
					<h2 class="homepage-title"><?php echo esc_html( $homepage_title ); ?></h2>
				<?php endif; ?>

				<?php if ( '' !== $seo_text ) : ?>
					<p class="archive-description"><?php echo wp_kses_post( $seo_text ); ?></p>
				<?php endif; ?>
			</header>
			<?php
		endif;
		?>

		<?php
		/*
		 * The same description again, at the very foot of the homepage and
		 * below the pagination, so it is the last thing in the page content.
		 *
		 * Search engines weigh a description that bookends the listing: one
		 * near the title tells a crawler what the page is before it reaches
		 * the grid, and one at the end restates it after the links it has to
		 * crawl. It is the same field, so the two copies cannot drift apart.
		 *
		 * Only needed when the title block sits at the top. When the title is
		 * set to the bottom of the page, the description under that heading is
		 * already the last thing in the content, and printing it again a few
		 * lines later would read as a mistake rather than as intent.
		 */
		if ( '' !== $seo_text && $title_above ) :
			?>
			<footer class="homepage-seo-footer">
				<p class="archive-description"><?php echo wp_kses_post( $seo_text ); ?></p>
			</footer>
			<?php
		endif;
		?>

	</main>
</div>

<?php
// The video homepage is intentionally full-width; KingTube-style listings do
// not reserve a widget column beside the grid.
get_footer();