<?php
/**
 * Site-wide icons.
 *
 * The theme draws its own icons rather than loading an icon font. A font means
 * another request, a flash of unstyled text, and a page that changes shape
 * whenever the font is blocked or substituted - and the shapes a font gives you
 * are generic in a way that a hand-picked set is not. Every icon here is a
 * masked SVG in the theme's own stylesheet, so it paints in whatever colour
 * surrounds it, in either skin, with nothing to load.
 *
 * Templates print `majestic_tube_icon( 'search' )` and get back
 * `<i class="mt-icon mt-icon-search" aria-hidden="true"></i>`. The name is
 * checked against the list below rather than interpolated blindly, so a name
 * that reaches this function from a filter, a widget or a stored option cannot
 * put arbitrary class names into the page.
 *
 * @package Majestic Tube
 * @version 2.4.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every icon name the theme ships, with the context each one is drawn in.
 *
 * The list is the contract. A name not in it has no shape behind it, so a
 * masked element pointing at nothing would be an empty box - a gap where a
 * control should be. Returning an empty string instead means a missing icon
 * degrades to the text beside it, which is always readable.
 *
 * @return string[]
 */
function majestic_tube_icon_names() {
	return array(
		// Navigation and chrome.
		'search',
		'menu',
		'close',
		'home',
		'chevron-left',
		'chevron-right',
		'arrow-up',
		'external',
		'download',
		'folder',
		'tag',

		// Media and video.
		'film',
		'video',
		'image',
		'play',
		'play-circle',
		'clock',
		'calendar',
		'eye',
		'thumbs-up',
		'thumbs-down',
		'heart',
		'star',
		'share',

		// Player controls.
		'sliders',
		'gauge',
		'expand',

		// Sorting and filtering.
		'sort',
		'random',
		'fire',
		'hourglass',

		// Site features.
		'comment',
		'user',
		'flag',
		'link',
		'settings',
		'check',
		'info',
		'warning',
		'envelope',
		'shield',
		'camera',
		'verified',
	);
}

/**
 * Print one of the theme's icons.
 *
 * The element is always `aria-hidden`, because an icon here is decoration beside
 * a label that already says what it does. Where the icon is the only thing
 * visible - a back-to-top button, a bare chevron in the pagination - the
 * surrounding control carries the accessible name instead, and this function
 * does not try to invent one.
 *
 * @param string $name  Icon name, from majestic_tube_icon_names().
 * @param string $class Extra classes for the same element.
 * @return string Icon markup, or an empty string for an unknown name.
 */
function majestic_tube_get_icon( $name, $class = '' ) {
	$name = sanitize_html_class( $name );

	if ( ! in_array( $name, majestic_tube_icon_names(), true ) ) {
		return '';
	}

	$classes = trim( 'mt-icon mt-icon-' . $name . ' ' . $class );

	return '<i class="' . esc_attr( $classes ) . '" aria-hidden="true"></i>';
}

/**
 * Print one of the theme's icons.
 *
 * @param string $name  Icon name, from majestic_tube_icon_names().
 * @param string $class Extra classes for the same element.
 * @return void
 */
function majestic_tube_icon( $name, $class = '' ) {
	echo majestic_tube_get_icon( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
}

/**
 * Whether site-wide icons are switched on.
 *
 * Icons are on by default. This exists because a site that has leaned on the
 * old `fa fa-*` classes through a child theme or a plugin can be styling those
 * elements today, and turning every one of them into a mask is a visible
 * change. The switch is the way to say no to it.
 *
 * @return bool
 */
function majestic_tube_icons_enabled() {
	/**
	 * Filters whether the theme draws its own icons.
	 *
	 * @param bool $enabled Whether icons are drawn.
	 */
	return (bool) apply_filters( 'majestic_tube_icons_enabled', majestic_tube_option_is_on( 'show-icons' ) );
}

/**
 * The icon for one of the homepage sort tabs.
 *
 * Each sort answers a different question, and a word alone does not say which is
 * which at a glance - "Latest" and "Longest" are both dates on the surface. The
 * icons separate them before anyone reads.
 *
 * @param string $slug Sort slug, from majestic_tube_video_filters().
 * @return string Icon markup, or an empty string when the slug is unknown.
 */
function majestic_tube_sort_icon( $slug ) {
	$icons = array(
		'latest'      => 'clock',
		'most-viewed' => 'eye',
		'longest'     => 'hourglass',
		'popular'     => 'fire',
		'random'      => 'random',
	);

	if ( ! isset( $icons[ $slug ] ) ) {
		return '';
	}

	return majestic_tube_get_icon( $icons[ $slug ], 'filter-nav-icon' );
}
