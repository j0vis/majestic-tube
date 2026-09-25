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

		$title_position = majestic_tube_get_option( 'wpst-options', 'homepage-title-desc-position', 'bottom' );
		$title_above    = ( 'top' === $title_position );

		// SEO text: the original prints it beside the homepage title, in the
		// same top/bottom slot, as a paragraph under the heading.
		$seo_text = (string) majestic_tube_get_option( 'wpst-options', 'seo-footer-text', '' );
		?>

		<?php if ( $title_above && ( $homepage_title || '' !== trim( $seo_text ) ) ) : ?>
			<header class="page-header">
				<?php if ( $homepage_title ) : ?>
					<h1 class="homepage-title"><?php echo esc_html( $homepage_title ); ?></h1>
				<?php endif; ?>

				<?php if ( '' !== trim( $seo_text ) ) : ?>
					<p class="archive-description"><?php echo wp_kses_post( $seo_text ); ?></p>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php majestic_tube_filter_nav(); ?>

		<?php if ( have_posts() ) : ?>

			<div class="video-grid">
				<?php majestic_tube_render_post_grid(); ?>
			</div>

			<?php if ( ! $title_above && ( $homepage_title || '' !== trim( $seo_text ) ) ) : ?>
				<header class="page-header homepage-title-block">
					<?php if ( $homepage_title ) : ?>
						<h2 class="homepage-title"><?php echo esc_html( $homepage_title ); ?></h2>
					<?php endif; ?>

					<?php if ( '' !== trim( $seo_text ) ) : ?>
						<p class="archive-description"><?php echo wp_kses_post( $seo_text ); ?></p>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<?php majestic_tube_the_pagination(); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>
</div>

<?php
// The video homepage is intentionally full-width; KingTube-style listings do
// not reserve a widget column beside the grid.
get_footer();