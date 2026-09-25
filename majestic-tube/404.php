<?php
/**
 * The 404 template.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<section class="error-404 not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Page not found', 'majestic-tube' ); ?></h1>
			</header>

			<div class="page-content">
				<p><?php esc_html_e( 'It looks like nothing was found at this location. Maybe try a search?', 'majestic-tube' ); ?></p>

				<?php get_search_form(); ?>
			</div>
		</section>

	</main>
</div>

<?php
get_footer();