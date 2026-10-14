<?php
/**
 * Site-wide icons.
 *
 * The theme draws its own icons rather than loading an icon font. A font means
 * another request, a flash of unstyled text, and a page that changes shape
 * whenever the font is blocked or substituted - and the shapes a font gives you
 * are generic in a way that a hand-picked set is not. Every icon here is a
 * masked SVG in the theme's own stylesheet, so it paints in whatever color
 * surrounds it, in either skin, with nothing to load.
 *
 * Templates print `majestic_tube_icon( 'search' )` and get back
 * `<i class="mt-icon mt-icon-search" aria-hidden="true"></i>`. The name is
 * checked against the list below rather than interpolated blindly, so a name
 * that reaches this function from a filter, a widget or a stored option cannot
 * put arbitrary class names into the page.
 *
 * A child theme adds its own shapes with majestic_tube_register_icons() rather
 * than by appending to the stylesheet, which the icon generator replaces
 * wholesale. See that function for what it accepts.
 *
 * @package Majestic Tube
 * @version 2.5.0
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
 * The literal list below is deliberately kept as literals and not generated:
 * it stays readable as text, so the masks in main.css can be held against it,
 * and a name added here without a shape behind it - an empty box on a live
 * page - is caught rather than shipped.
 *
 * @return string[]
 */
function majestic_tube_theme_icon_names() {
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
 * Every icon name that can be printed: the theme's, plus any a child theme
 * has registered.
 *
 * @return string[]
 */
function majestic_tube_icon_names() {
	return array_values(
		array_unique(
			array_merge( majestic_tube_theme_icon_names(), array_keys( majestic_tube_registered_icons() ) )
		)
	);
}

/**
 * The icons a child theme has registered, keyed by name.
 *
 * Reading and writing share one static so there is no global and no by
 * reference return. Passing an array registers; passing nothing reads.
 *
 * @param array|null $add Icons to merge in, or null to read.
 * @return array Registered icons: name => array( 'path' => ..., 'viewbox' => ... ).
 */
function majestic_tube_registered_icons( $add = null ) {
	static $registry = array();

	if ( is_array( $add ) ) {
		foreach ( $add as $name => $icon ) {
			$clean = majestic_tube_sanitise_icon( $name, $icon );

			if ( null !== $clean ) {
				$registry[ $clean['name'] ] = $clean;
			}
		}
	}

	return $registry;
}

/**
 * Register extra icons, or replace ones the theme already ships.
 *
 * The shapes in the stylesheet are machine-written and replaced wholesale when
 * the set is regenerated, so a child theme cannot add to them by appending CSS.
 * This is the supported way in: supply the shape, and the theme generates the
 * mask and the paint to match everything else in the set.
 *
 *     add_action( 'after_setup_theme', function () {
 *         majestic_tube_register_icons( array(
 *             'brand-mark' => 'M12 2 2 22h20L12 2zm0 5 5 11H7l5-11z',
 *             'wide-wordmark' => array(
 *                 'path'    => 'M2 10h4v8H2v-8zM10 10h4v8h-4v-8zM18 10h4v8h-4v-8z',
 *                 'viewbox' => '0 0 24 24',
 *             ),
 *         ) );
 *     } );
 *
 * Then `majestic_tube_icon( 'brand-mark' )` prints it, and it is laid out,
 * sized and colored exactly like a theme icon because it is the same rules.
 *
 * Registering a name the theme already ships replaces that shape everywhere it
 * is used, including the legacy `fa fa-*` classes that point at the same
 * masks. That is how a child theme restyles a mark it does not like, and it is
 * why the name is matched exactly rather than by prefix.
 *
 * Anything the validator rejects is skipped silently rather than fatally: a
 * child theme with one malformed shape should still load, and the icon in
 * question degrades to the text beside it, which is always readable.
 *
 * @param array $icons Map of name => SVG path data, or name => array(
 *                     'path' => SVG path data, 'viewbox' => optional viewBox.
 * @return array The whole registry after the call, keyed by name.
 */
function majestic_tube_register_icons( $icons ) {
	return majestic_tube_registered_icons( $icons );
}

/**
 * Check one proposed icon and reduce it to the two values the rest needs.
 *
 * The path ends up inside a `url("data:image/svg+xml,...")` in generated CSS,
 * so it is held to the characters SVG path data is actually made of. That is
 * not fussiness: without it, a path reaching this function from a filter, a
 * widget or a stored setting could close the url() token and append rules of
 * its own. A theme is trusted code, but a child theme's options are not.
 *
 * @param mixed $name Icon name.
 * @param mixed $icon Path data, or an array with 'path' and optional 'viewbox'.
 * @return array|null array( 'name', 'path', 'viewbox' ), or null if rejected.
 */
function majestic_tube_sanitise_icon( $name, $icon ) {
	// The theme's own names are all lowercase and hyphenated, so a name that
	// needs reformatting to fit is a mistake rather than a variant to guess at.
	if ( ! is_string( $name ) || ! preg_match( '/^[a-z][a-z0-9-]*$/', $name ) ) {
		return null;
	}

	$path    = '';
	$viewbox = '0 0 24 24';

	if ( is_array( $icon ) ) {
		$path = isset( $icon['path'] ) ? $icon['path'] : '';

		if ( isset( $icon['viewbox'] ) ) {
			$viewbox = $icon['viewbox'];
		}
	} elseif ( is_string( $icon ) ) {
		$path = $icon;
	}

	if ( ! is_string( $path ) || '' === trim( $path ) ) {
		return null;
	}

	$path = trim( $path );

	/*
	 * Path data only: command letters, digits, separators and whitespace. No
	 * quotes, brackets, colons or slashes can appear, so the string cannot
	 * leave the CSS url() token it is written into.
	 */
	if ( ! preg_match( '/^[MmZzLlHhVvCcSsQqTtAaEe0-9\s.,+\-]+$/', $path ) ) {
		return null;
	}

	// A path with no move to start from draws nothing.
	if ( ! preg_match( '/^[Mm]/', $path ) || ! preg_match( '/[0-9]/', $path ) ) {
		return null;
	}

	if ( ! is_string( $viewbox ) ) {
		return null;
	}

	$box = preg_split( '/[\s,]+/', trim( $viewbox ), -1, PREG_SPLIT_NO_EMPTY );

	if ( 4 !== count( $box ) ) {
		return null;
	}

	foreach ( $box as $number ) {
		if ( ! is_numeric( $number ) ) {
			return null;
		}
	}

	// A zero or negative extent masks nothing, which is the empty box this
	// whole set exists to avoid.
	if ( (float) $box[2] <= 0 || (float) $box[3] <= 0 ) {
		return null;
	}

	return array(
		'name'    => $name,
		'path'    => $path,
		'viewbox' => implode( ' ', $box ),
	);
}

/**
 * Wrap one shape in a masked SVG data URI.
 *
 * Only the characters that actually break a CSS url() token are escaped.
 * Escaping everything turns the payload unreadable in devtools for no gain, and
 * the theme's own icon set is built by the same rule - two implementations of
 * one rule, which have to stay in step.
 *
 * @param string $path    SVG path data.
 * @param string $viewbox Four numbers.
 * @return string A url("data:...") value, quotes included.
 */
function majestic_tube_icon_mask_url( $path, $viewbox = '0 0 24 24' ) {
	$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='" . $viewbox . "'>"
		. "<path d='" . $path . "'/></svg>";

	$escaped = str_replace(
		array( '%', '<', '>', '#', '"' ),
		array( '%25', '%3C', '%3E', '%23', '%22' ),
		$svg
	);

	return 'url("data:image/svg+xml,' . $escaped . '")';
}

/**
 * The stylesheet for the icons a child theme registered.
 *
 * Deliberately the same shape as the generated block in main.css: one custom
 * property per icon and one rule that points the mask at it. Emitting it from
 * the theme rather than asking the child to write mask CSS by hand is the
 * point - mask-repeat, mask-position and mask-size are what make an icon line
 * up with its label, and a child theme that had to repeat them would get one
 * wrong sooner or later.
 *
 * @return string CSS, or an empty string when nothing is registered.
 */
function majestic_tube_registered_icons_css() {
	$registered = majestic_tube_registered_icons();

	if ( ! $registered ) {
		return '';
	}

	$css = "/*\n * Icons registered by a child theme.\n *\n"
		. " * Generated by majestic_tube_register_icons(). This block is printed\n"
		. " * after main.css, so registering a name the theme also ships replaces\n"
		. " * that shape everywhere it is used.\n */\n";

	foreach ( $registered as $name => $icon ) {
		$css .= ':root {' . "\n";
		$css .= "\t--mt-icon-$name: " . majestic_tube_icon_mask_url( $icon['path'], $icon['viewbox'] ) . ";\n";
		$css .= "}\n\n";
		$css .= ".mt-icon-$name {\n";
		$css .= "\tbackground-color: currentColor;\n";
		$css .= "\t-webkit-mask-image: var(--mt-icon-$name);\n";
		$css .= "\tmask-image: var(--mt-icon-$name);\n";
		$css .= "}\n\n";
	}

	return $css;
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
