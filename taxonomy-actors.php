<?php
/**
 * The actor archive template.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

get_header();

$actor = get_queried_object();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php majestic_tube_breadcrumbs(); ?>

		<header class="page-header actor-header">
			<?php
			$portrait = majestic_tube_get_term_image_url( $actor->term_id, 'actors', 'majestic-tube-thumb-medium' );

			if ( $portrait ) :
				?>
				<img class="actor-portrait" src="<?php echo esc_url( $portrait ); ?>" alt="<?php echo esc_attr( $actor->name ); ?>" width="320" height="180" />
			<?php endif; ?>

			<div class="actor-info">
				<h1 class="page-title"><?php echo esc_html( $actor->name ); ?></h1>

				<?php if ( $actor->description ) : ?>
					<div class="actor-description"><?php echo wp_kses_post( wpautop( $actor->description ) ); ?></div>
				<?php endif; ?>

				<p class="actor-count">
					<?php
					printf(
						/* translators: %s: video count. */
						esc_html( _n( '%s video', '%s videos', $actor->count, 'majestic-tube' ) ),
						esc_html( number_format_i18n( $actor->count ) )
					);
					?>
				</p>
			</div>
		</header>

		<?php majestic_tube_filter_nav(); ?>

		<?php if ( have_posts() ) : ?>

			<div class="video-grid">
				<?php majestic_tube_render_post_grid(); ?>
			</div>

			<?php majestic_tube_the_pagination(); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();