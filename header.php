<?php
/**
 * The header template.
 *
 * @package Majestic Tube
 * @version 2.0.8
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body data-majestic-tube-version="<?php echo esc_attr( MAJESTIC_TUBE_VERSION ); ?>" <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'majestic-tube' ); ?></a>

<header id="masthead" class="site-header">
	<div class="header-inner site-branding row">
		<div class="logo">
			<?php
			// Honours the original use-logo-image / image-logo-file / icon-logo /
			// text-logo options, then the WordPress custom logo, then the title.
			majestic_tube_site_logo();
			?>
		</div>

		<?php if ( majestic_tube_option_is_on( 'show-search-bar' ) ) : ?>
			<div class="header-search small-search">
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>

		<nav id="site-navigation" class="main-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Main menu', 'majestic-tube' ); ?>">
			<div id="head-mobile" aria-hidden="true"></div>
			<button
				type="button"
				class="button-nav menu-toggle"
				aria-controls="primary-menu"
				aria-expanded="false"
				aria-label="<?php esc_attr_e( 'Toggle navigation', 'majestic-tube' ); ?>"
			>
				<span class="screen-reader-text"><?php esc_html_e( 'Toggle navigation', 'majestic-tube' ); ?></span>
			</button>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'majestic_tube_main_menu',
					'menu_id'        => 'primary-menu',
					'menu_class'     => 'menu',
					'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
					'container'      => false,
					'fallback_cb'    => 'majestic_tube_primary_menu_fallback',
				)
			);
			?>
		</nav>

		<div class="clear"></div>
	</div>

	<?php
	// Header content is managed through the Widgets screen.
	majestic_tube_content_location( 'header' );
	?>
</header>

<div id="page" class="site">
