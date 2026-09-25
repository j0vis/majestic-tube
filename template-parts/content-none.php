<?php
/**
 * Template part for displaying a message when no posts are found.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */
?>
<section class="no-results not-found">

	<header class="page-header">
		<h2 class="page-title"><?php esc_html_e( 'Nothing Found', 'majestic-tube' ); ?></h2>
	</header>

	<div class="page-content">
		<?php if ( is_search() ) : ?>
			<p><?php esc_html_e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'majestic-tube' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'majestic-tube' ); ?></p>
		<?php endif; ?>
		<?php get_search_form(); ?>
	</div>

</section>