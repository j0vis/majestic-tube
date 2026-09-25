<?php
/**
 * The search results template.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<header class="page-header">
			<h1 class="page-title">
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Search Results for: %s', 'majestic-tube' ),
					'<span>' . esc_html( get_search_query() ) . '</span>'
				);
				?>
			</h1>
		</header>

		<?php
		if ( have_posts() ) :
			/*
			 * Videos are the `post` type in the WP-Script data model (the original
			 * theme relabels posts as "Videos"), so search results are rendered as
			 * video cards, exactly like the original search.php did. Pages keep
			 * the plain excerpt layout.
			 */
			$majestic_tube_is_post_search = ! is_search() || 'page' !== get_query_var( 'post_type' );
			?>

			<?php if ( $majestic_tube_is_post_search ) : ?>
				<div class="video-grid">
					<?php majestic_tube_render_post_grid(); ?>
				</div>
			<?php else : ?>
				<div class="post-list">
					<?php majestic_tube_render_post_grid( 'excerpt' ); ?>
				</div>
			<?php endif; ?>

			<?php majestic_tube_the_pagination(); ?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>
</div>

<?php
get_footer();