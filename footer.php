<?php
/**
 * The footer template.
 *
 * Markup follows the original theme: the footer widget area is wrapped in the
 * class stored in the `footer-columns` option, the logo / copyright bar are
 * gated on their own switches, and the back-to-top link closes the page.
 *
 * @package Majestic Tube
 * @version 2.0.6
 */

$footer_columns = (string) majestic_tube_get_option( 'wpst-options', 'footer-columns', 'four-columns-footer' );
$footer_logo    = (string) majestic_tube_get_option( 'wpst-options', 'image-logo-file', '' );
?>
	</div><!-- #page -->

	<footer id="colophon" class="site-footer" role="contentinfo">
		<div class="row">
			<?php
			// Footer content is managed through the Widgets screen.
			majestic_tube_content_location( 'footer' );
			?>

			<?php if ( is_active_sidebar( 'majestic-tube-footer' ) ) : ?>
				<div class="footer-widgets <?php echo esc_attr( $footer_columns ? $footer_columns : 'four-columns-footer' ); ?>">
					<?php dynamic_sidebar( 'majestic-tube-footer' ); ?>
				</div>
			<?php endif; ?>

			<div class="clear"></div>

			<?php
			/*
			 * Footer logo: the original prints the same image used for the
			 * header logo, greyscaled.
			 */
			if ( $footer_logo && majestic_tube_option_is_on( 'logo-footer' ) ) :
				?>
				<div class="logo-footer">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<img class="grayscale" src="<?php echo esc_url( $footer_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					</a>
				</div>
			<?php endif; ?>

			<?php if ( has_nav_menu( 'majestic_tube_footer_menu' ) ) : ?>
				<div class="footer-menu-container">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'majestic_tube_footer_menu',
							'container'      => false,
							'depth'          => 1,
						)
					);
					?>
				</div>
			<?php endif; ?>

			<?php
			/*
			 * Copyright bar. Keep the administrator's text, but remove the
			 * legacy WP-Script attribution that older installs saved in the
			 * option. A fresh install falls back to a simple site copyright.
			 */
			if ( majestic_tube_option_is_on( 'copyright-bar' ) ) :
				$copyright = (string) majestic_tube_get_option( 'wpst-options', 'copyright-text', '' );
				$copyright = str_ireplace(
					array(
						'All rights reserved. Powered by WP-Script.com',
						'Powered by WP-Script.com',
						'All rights reserved.',
					),
					'',
					$copyright
				);
				$copyright = trim( $copyright );
				?>
				<div class="site-info">
					<?php
					if ( '' !== trim( $copyright ) ) {
						echo wp_kses_post( $copyright );
					} else {
						printf(
							/* translators: %1$s: copyright symbol, %2$s: current year, %3$s: site name. */
							esc_html__( '%1$s %2$s %3$s', 'majestic-tube' ),
							'&copy;',
							esc_html( wp_date( 'Y' ) ),
							esc_html( get_bloginfo( 'name' ) )
						);
					}
					?>
				</div><!-- .site-info -->
			<?php endif; ?>
		</div>
	</footer><!-- #colophon -->

	<a href="#" id="back-to-top" class="back-to-top" aria-label="<?php esc_attr_e( 'Back to top', 'majestic-tube' ); ?>">
		<span aria-hidden="true">&uarr;</span>
	</a>

<?php wp_footer(); ?>
</body>
</html>
