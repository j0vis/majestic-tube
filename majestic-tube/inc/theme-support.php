<?php
/**
 * Theme support and core compatibility helpers.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set up theme defaults and register feature support.
 */
function majestic_tube_setup() {
	// Theme support for core features.
	//
	// Note: the 'script' and 'style' HTML5 features were deprecated in 6.9 and
	// removed in WordPress 7.0 (Trac #64442) - Core always emits HTML5-style
	// script/style tags now, so passing them would raise a deprecation notice.
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
		)
	);

	// Required by WordPress (and Theme Check): Core prints <title> and the
	// feed links through wp_head(). The original theme declares both too.
	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );

	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'post-formats', array( 'video' ) );

	// Featured image sizes for the video theme (original dimensions).
	//
	// The wpst_thumb_* names are the original theme's, and WP-Script plugins
	// (for example the paywall) ask for wpst_thumb_large by name, so they must
	// stay registered. The majestic-tube-thumb-* names are our own aliases.
	set_post_thumbnail_size( 320, 180, true );
	add_image_size( 'wpst_thumb_large', 640, 360, true );
	add_image_size( 'wpst_thumb_medium', 320, 180, true );
	add_image_size( 'wpst_thumb_small', 150, 84, true );
	add_image_size( 'majestic-tube-thumb-large', 640, 360, true );
	add_image_size( 'majestic-tube-thumb-medium', 320, 180, true );
	add_image_size( 'majestic-tube-thumb-small', 150, 84, true );

	// Navigation menus.
	register_nav_menus(
		array(
			'majestic_tube_main_menu'   => __( 'Main Menu', 'majestic-tube' ),
			'majestic_tube_footer_menu' => __( 'Footer Menu', 'majestic-tube' ),
		)
	);

	// Custom logo.
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	// Customizer selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	// Wide alignment for block editor.
	add_theme_support( 'align-wide' );

	// Responsive embeds.
	add_theme_support( 'responsive-embeds' );

	// Editor styles matching the front-end tokens.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );

	// Content width used by wide/full alignments.
	$GLOBALS['content_width'] = isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : 1200;
}
add_action( 'after_setup_theme', 'majestic_tube_setup' );

/**
 * Body classes like the original (group-blog, hfeed).
 *
 * @param array $classes Body classes.
 * @return array
 */
function majestic_tube_body_classes( $classes ) {
	if ( is_multi_author() ) {
		$classes[] = 'group-blog';
	}

	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}

	return $classes;
}
add_filter( 'body_class', 'majestic_tube_body_classes' );

/**
 * Body classes driven by the original wpst-options keys.
 *
 * The original theme printed the `custom-background` class from header.php and
 * let the viewer's device drive the layout classes; keeping the same class
 * names means any custom CSS written for the original theme still applies.
 *
 * @param array $classes Body classes.
 * @return array
 */
function majestic_tube_legacy_body_classes( $classes ) {
	if ( 'on' === majestic_tube_get_option( 'wpst-options', 'custom-background', 'off' ) ) {
		$classes[] = 'custom-background';
	}

	if ( majestic_tube_is_mobile() ) {
		$classes[] = 'wpst-mobile';
	} else {
		$classes[] = 'wpst-desktop';
	}

	if ( 1 === (int) majestic_tube_get_option( 'wpst-options', 'videos-per-row-mobile', 2 ) ) {
		$classes[] = 'wpst-one-per-row-mobile';
	}

	return $classes;
}
add_filter( 'body_class', 'majestic_tube_legacy_body_classes' );

/**
 * Mobile detection.
 *
 * This is a deliberate one-line wrapper around WordPress core's wp_is_mobile().
 *
 * It used to carry a user-agent fallback for installations where core's
 * function might be absent, but the theme requires WordPress 6.5 and
 * wp_is_mobile() has been unconditionally available for many releases, so that
 * branch was unreachable in practice. Worse, it was a second device list that
 * had to be kept in step with core's: it drifted once already, matching only
 * the literal substring "mobile" and so misclassifying iPads, Android tablets
 * and BlackBerries. A dead branch that can silently disagree with core is
 * worse than no branch at all, so it is gone.
 *
 * The wrapper itself is kept rather than calling wp_is_mobile() at each of the
 * ten call sites, because it is the single seam this theme tests and filters
 * through, and because the wpst_is_mobile() compatibility shim delegates here.
 *
 * @return bool
 */
function majestic_tube_is_mobile() {
	return wp_is_mobile();
}

/**
 * Whether a plugin is active, without loading wp-admin/includes/plugin.php.
 *
 * @param string $plugin Plugin basename, e.g. akismet/akismet.php.
 * @return bool
 */
function majestic_tube_is_plugin_active( $plugin ) {
	$active = (array) get_option( 'active_plugins', array() );

	if ( in_array( $plugin, $active, true ) ) {
		return true;
	}

	if ( ! is_multisite() ) {
		return false;
	}

	$network = (array) get_site_option( 'active_sitewide_plugins', array() );

	return isset( $network[ $plugin ] );
}

/**
 * Legacy guard: WP-Script plugins may call wpst_is_mobile(). Provide it.
 *
 * @return bool
 */
if ( ! function_exists( 'wpst_is_mobile' ) ) {
	function wpst_is_mobile() {
		return majestic_tube_is_mobile();
	}
}

/**
 * Pingback header for singular posts.
 */
function majestic_tube_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'majestic_tube_pingback_header' );