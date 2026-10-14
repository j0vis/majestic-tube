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

	/*
	 * Featured image sizes for the video theme (original dimensions).
	 *
	 * One registration per file. The original theme's wpst_thumb_* names used
	 * to be registered beside these at identical dimensions, so WordPress
	 * resized and re-encoded every upload twice for a file it had already
	 * written, and stored seven metadata entries for five files. The extra
	 * names are not registered any more; they are answered from the attachment
	 * metadata instead - see majestic_tube_image_size_aliases() - which is
	 * where WordPress itself looks a size up (image_get_intermediate_size()
	 * reads $metadata['sizes'][ $size ] and nothing else) and which can only
	 * ever point at a file that was really generated.
	 *
	 * The names that live on that way are the theme's public interface: the
	 * `main-thumbnail-quality` option stores wpst_thumb_small,
	 * wpst_thumb_medium or wpst_thumb_large, and WP-Script plugins (the
	 * paywall, for one) ask for them by name.
	 */
	set_post_thumbnail_size( 320, 180, true );
	add_image_size( 'majestic-tube-thumb-large', 640, 360, true );
	add_image_size( 'majestic-tube-thumb-small', 150, 84, true );

	/*
	 * Two sizes the original theme never had.
	 *
	 * `majestic-tube-thumb-card` is the middle step of the card srcset. Without
	 * it a card had a choice of 320 or 640 pixels wide, so a card drawn between
	 * those widths downloaded an image that was either soft or twice the size it
	 * needed.
	 *
	 * `majestic-tube-poster` is what the <video> poster attribute asks for. The
	 * largest 16:9 file the theme used to register is 640x360, which a player
	 * column over 640 CSS pixels upscales - the picture is soft on every laptop
	 * and on every phone with a high-density screen. Sizes are recorded in the
	 * attachment metadata when an image is uploaded, so existing libraries keep
	 * the old behavior and new uploads get the sharp one; a site that wants the
	 * new files for its old videos can regenerate the thumbnails.
	 */
	add_image_size( 'majestic-tube-thumb-card', 480, 270, true );
	add_image_size( 'majestic-tube-poster', 1280, 720, true );

	// Navigation menus.
	register_nav_menus(
		array(
			'majestic_tube_main_menu'   => __( 'Main Menu', 'majestic-tube' ),
			'majestic_tube_footer_menu' => __( 'Footer Menu', 'majestic-tube' ),
			'majestic_tube_links_menu'  => __( 'Friends & Ad Links', 'majestic-tube' ),
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

	/*
	 * Editor styles matching the front-end tokens.
	 *
	 * The font faces are listed first so the iframe parses the @font-face rules
	 * before the editor stylesheet names the family; without that the editor
	 * previews a fallback and the page keeps Inter, so the same heading wraps in
	 * two different places.
	 */
	add_theme_support( 'editor-styles' );
	add_editor_style(
		array(
			'assets/css/fonts.css',
			'assets/css/editor.css',
		)
	);

	// Content width used by wide/full alignments.
	$GLOBALS['content_width'] = isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : 1200;

	/*
	 * Translations.
	 *
	 * Every string in the theme already passes the 'majestic-tube' text
	 * domain, but the domain was never bound to a folder, so a .mo/.l10n
	 * file placed in languages/ would have been ignored. WordPress.org
	 * language packs carry the domain's translations through their own
	 * delivery mechanism, which is why the omission went unnoticed; a site
	 * translating the theme itself, or a child theme shipping its own
	 * overrides, had no way in.
	 */
	load_theme_textdomain( 'majestic-tube', MAJESTIC_TUBE_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'majestic_tube_setup' );

/**
 * Image size names answered from the attachment metadata.
 *
 * A registered size is a promise to generate a file; two names for one set of
 * dimensions is not, and WordPress has no concept of an alias that would avoid
 * it. Resolving the second name from the metadata costs nothing - a size is
 * looked up in `$metadata['sizes']` and nowhere else - and it can only ever
 * answer with a file that exists, so an image too small to produce the size
 * falls back to the original upload exactly as an ungenerated registered size
 * does. Nothing is lost, including for a site whose library was uploaded while
 * both names were registered: that metadata already carries all of them.
 *
 * @return array<string, string> Aliased size name => the size that made the file.
 */
function majestic_tube_image_size_aliases() {
	/**
	 * Filter the image size names answered from the attachment metadata.
	 *
	 * @param array<string, string> $aliases Aliased name => registered size name.
	 */
	return (array) apply_filters(
		'majestic_tube_image_size_aliases',
		array(
			'wpst_thumb_large'           => 'majestic-tube-thumb-large',
			'wpst_thumb_small'           => 'majestic-tube-thumb-small',
			// The 320x180 size is core's featured-image size, which core and
			// third-party code ask for as `post-thumbnail`.
			'wpst_thumb_medium'          => 'post-thumbnail',
			'majestic-tube-thumb-medium' => 'post-thumbnail',
		)
	);
}

/**
 * Write the aliased size names into an attachment's metadata.
 *
 * Hooked to both ends of that metadata's life: the write end covers a new
 * upload, so the stored record names every size the theme can ask for, and the
 * read end covers an attachment whose record was written elsewhere - a library
 * carried over from the original theme, an importer, or a regeneration with
 * this filter not yet in place.
 *
 * @param array $metadata Attachment metadata.
 * @return array
 */
function majestic_tube_alias_image_sizes( $metadata ) {
	if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
		return $metadata;
	}

	foreach ( majestic_tube_image_size_aliases() as $alias => $source ) {
		if ( isset( $metadata['sizes'][ $source ] ) && ! isset( $metadata['sizes'][ $alias ] ) ) {
			$metadata['sizes'][ $alias ] = $metadata['sizes'][ $source ];
		}
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'majestic_tube_alias_image_sizes' );
add_filter( 'wp_get_attachment_metadata', 'majestic_tube_alias_image_sizes' );

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

	/*
	 * One class for the whole icon set. Doing it here rather than in each
	 * template means the switch is read once per page, and turning it off
	 * hides every icon at the same instant instead of leaving one surface
	 * behind that forgot to ask.
	 */
	if ( ! majestic_tube_icons_enabled() ) {
		$classes[] = 'no-icons';
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