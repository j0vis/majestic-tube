<?php
/**
 * Head / footer output driven by the original theme options.
 *
 * The original settings screen let administrators paste a favicon, search-engine
 * verification tags, analytics snippets and extra JS. Majestic Tube exposes
 * those same fields natively and prints them from wp_head and wp_footer —
 * except the favicon, which now belongs to WordPress's own Site Icon setting.
 *
 * Output is administrator-supplied markup: it is sanitized on input (unfiltered
 * for users with unfiltered_html, wp_kses_post otherwise) and printed verbatim,
 * exactly like the original theme and like the ad zones.
 *
 * @package Majestic Tube
 * @version 2.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print one administrator-provided code option in a marked block.
 *
 * @param string $option_key Legacy option key.
 * @param string $label      Static marker label.
 * @return void
 */
function majestic_tube_output_code_option( $option_key, $label ) {
	$code = (string) majestic_tube_get_option( 'wpst-options', $option_key, '' );

	if ( '' === trim( $code ) ) {
		return;
	}

	echo "\n<!-- Majestic Tube: " . esc_html( $label ) . " -->\n";
	echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- administrator-supplied markup.
	echo "\n";
}

/**
 * Print the search-engine verification tags and analytics code in <head>.
 *
 * The retired favicon field is deliberately not printed. It was a second
 * source of truth for the browser-tab icon, competing with WordPress's own
 * Site Icon (Appearance -> Customize -> Site Identity -> Site Icon); core
 * prints that one from wp_head, and the theme stepping aside is what makes it
 * the winner. A stored value is left untouched in the database, so nothing an
 * administrator entered is destroyed by this.
 *
 * @return void
 */
function majestic_tube_output_head_code() {
	majestic_tube_output_code_option( 'meta-verification', 'search engine verification' );
	majestic_tube_output_code_option( 'google-analytics', 'analytics' );
}
add_action( 'wp_head', 'majestic_tube_output_head_code', 20 );

/**
 * Print the extra footer scripts, plus the mobile-only snippet on phones.
 *
 * @return void
 */
function majestic_tube_output_footer_code() {
	majestic_tube_output_code_option( 'other-scripts', 'custom scripts' );

	if ( ! majestic_tube_is_mobile() ) {
		return;
	}

	majestic_tube_output_code_option( 'mobile-scripts', 'mobile scripts' );
}
add_action( 'wp_footer', 'majestic_tube_output_footer_code' );

/**
 * Font family bundled with the theme.
 *
 * Kept in one place so the enqueue, the preload and the option values all
 * agree on the name: if the bundled file is ever swapped for another family,
 * this is the only string that has to change alongside assets/css/fonts.css.
 *
 * @return string
 */
function majestic_tube_bundled_font_family() {
	return 'Inter';
}

/**
 * Font stacks the site font option can emit.
 *
 * The bundled family comes first and always ends in a real system stack, so a
 * visitor whose browser never downloads the file (or whose request fails)
 * still reads a designed page instead of a serif default.
 *
 * @return array<string, string> Choice label => CSS font stack.
 */
function majestic_tube_site_font_stacks() {
	$stacks = array(
		'Inter'     => '"' . majestic_tube_bundled_font_family() . '",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif',
		'System UI' => 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif',
	);

	/**
	 * Filter the font stacks offered for the site font option.
	 *
	 * @param array<string, string> $stacks Choice label => CSS font stack.
	 */
	return apply_filters( 'majestic_tube_site_font_stacks', $stacks );
}

/**
 * Font stacks the logo font option can emit.
 *
 * Unlike the site font, a text logo may deliberately use a serif or monospace
 * face. Every choice is a family the operating system already provides - apart
 * from the bundled one - and ends in a generic keyword, so no choice can render
 * as an invisible no-change.
 *
 * The map is the single source of truth: the Customizer choices and the emitted
 * --mt-logo-font-family value both come from here, so a choice can never name a
 * stack the theme does not know.
 *
 * @return array<string, string> Choice label => CSS font stack.
 */
function majestic_tube_logo_font_stacks() {
	$stacks = array(
		'System UI'        => 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif',
		'System Serif'     => 'Georgia,"Times New Roman","Noto Serif",Cambria,serif',
		'System Monospace' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,"DejaVu Sans Mono","Liberation Mono",monospace',
	);

	$bundled = majestic_tube_bundled_font_family();

	// Prepended so the bundled family stays the first entry in the list, which is
	// the value the Customizer shows as selected for a fresh install.
	$stacks = array_merge(
		array( $bundled => '"' . $bundled . '",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif' ),
		$stacks
	);

	/**
	 * Filter the font stacks offered for the logo font option.
	 *
	 * @param array<string, string> $stacks Choice label => CSS font stack.
	 */
	return apply_filters( 'majestic_tube_logo_font_stacks', $stacks );
}

/**
 * Resolve a stored font-family value to a safe CSS font stack.
 *
 * A known label resolves through the matching map. Anything else - a family
 * saved by a release that bundled other webfonts (Open Sans, Roboto, Lato,
 * Montserrat), a stale value, or a hand-edited one - resolves to the system
 * stack instead of being echoed into the declaration. The unknown path
 * therefore interpolates nothing at all, so no stored value can break out of
 * the style block, and a removed family can never come back as a silent
 * no-change.
 *
 * @param string      $font_family Stored font family.
 * @param array|null  $stacks      Optional map to resolve against.
 * @return string CSS font stack.
 */
function majestic_tube_css_font_stack( $font_family, $stacks = null ) {
	$stacks = is_array( $stacks ) ? $stacks : majestic_tube_logo_font_stacks();
	$label  = trim( preg_replace( '/\s+/', ' ', (string) $font_family ) );

	if ( isset( $stacks[ $label ] ) ) {
		return $stacks[ $label ];
	}

	/*
	 * The maps are filterable, so the system entry is not guaranteed to exist.
	 * Falling back to the first registered stack keeps the declaration valid
	 * even for a filter that replaces the whole list.
	 */
	$fallback = isset( $stacks['System UI'] ) ? $stacks['System UI'] : reset( $stacks );

	return is_string( $fallback ) ? $fallback : 'sans-serif';
}

/**
 * Whether any current option asks for the bundled webfont.
 *
 * The font file is only enqueued when it will actually be used, so a site that
 * picks the system stack makes no font request at all - the choice is real, not
 * cosmetic.
 *
 * @return bool
 */
function majestic_tube_uses_bundled_font() {
	$bundled = majestic_tube_bundled_font_family();

	$site_font = (string) majestic_tube_get_option( 'wpst-options', 'site-font-family', $bundled );
	$logo_font = (string) majestic_tube_get_option( 'wpst-options', 'logo-font-family', $bundled );

	/**
	 * Filter whether the bundled webfont is loaded on this request.
	 *
	 * @param bool $uses Whether any option selects the bundled family.
	 */
	return (bool) apply_filters( 'majestic_tube_uses_bundled_font', $bundled === $site_font || $bundled === $logo_font );
}

/**
 * Split a hex color into its three channels.
 *
 * @param string $hex Color, with or without the leading hash.
 * @return array|null Channel triple, or null when the value is not hex.
 */
function majestic_tube_hex_channels( $hex ) {
	$hex = ltrim( (string) $hex, '#' );

	if ( 3 === strlen( $hex ) && ctype_xdigit( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return null;
	}

	return array(
		'r' => hexdec( substr( $hex, 0, 2 ) ),
		'g' => hexdec( substr( $hex, 2, 2 ) ),
		'b' => hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Render a channel triple back to the #rrggbb form CSS expects.
 *
 * @param array $rgb Channel triple.
 * @return string Hex color.
 */
function majestic_tube_channels_hex( $rgb ) {
	$clamped = array();

	foreach ( array( 'r', 'g', 'b' ) as $channel ) {
		$clamped[ $channel ] = max( 0, min( 255, (int) round( $rgb[ $channel ] ) ) );
	}

	return sprintf( '#%02x%02x%02x', $clamped['r'], $clamped['g'], $clamped['b'] );
}

/**
 * WCAG relative luminance of a color.
 *
 * @param array $rgb Channel triple.
 * @return float Luminance between 0 and 1.
 */
function majestic_tube_luminance( $rgb ) {
	$linear = array();

	foreach ( array( 'r', 'g', 'b' ) as $channel ) {
		$value             = $rgb[ $channel ] / 255;
		$linear[ $channel ] = ( $value <= 0.03928 ) ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	return ( 0.2126 * $linear['r'] ) + ( 0.7152 * $linear['g'] ) + ( 0.0722 * $linear['b'] );
}

/**
 * WCAG contrast ratio between two colors.
 *
 * @param array $rgb_a First color.
 * @param array $rgb_b Second color.
 * @return float Ratio from 1 to 21.
 */
function majestic_tube_contrast_ratio( $rgb_a, $rgb_b ) {
	$a = majestic_tube_luminance( $rgb_a );
	$b = majestic_tube_luminance( $rgb_b );

	return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
}

/**
 * Convert a channel triple to HSL, where lightness is the only dial.
 *
 * @param array $rgb Channel triple.
 * @return array Hue, saturation and lightness, all between 0 and 1.
 */
function majestic_tube_rgb_to_hsl( $rgb ) {
	$red   = $rgb['r'] / 255;
	$green = $rgb['g'] / 255;
	$blue  = $rgb['b'] / 255;

	$max       = max( $red, $green, $blue );
	$min       = min( $red, $green, $blue );
	$lightness = ( $max + $min ) / 2;
	$delta     = $max - $min;

	if ( 0.0 === (float) $delta ) {
		return array(
			'h' => 0.0,
			's' => 0.0,
			'l' => $lightness,
		);
	}

	$saturation = ( $lightness > 0.5 ) ? $delta / ( 2 - $max - $min ) : $delta / ( $max + $min );

	if ( $max === $red ) {
		$hue = ( ( $green - $blue ) / $delta ) + ( $green < $blue ? 6 : 0 );
	} elseif ( $max === $green ) {
		$hue = ( ( $blue - $red ) / $delta ) + 2;
	} else {
		$hue = ( ( $red - $green ) / $delta ) + 4;
	}

	return array(
		'h' => $hue / 6,
		's' => $saturation,
		'l' => $lightness,
	);
}

/**
 * Convert HSL back to a channel triple.
 *
 * @param array $hsl Hue, saturation and lightness between 0 and 1.
 * @return array Channel triple.
 */
function majestic_tube_hsl_to_rgb( $hsl ) {
	$hue        = $hsl['h'];
	$saturation = $hsl['s'];
	$lightness  = $hsl['l'];

	if ( 0.0 === (float) $saturation ) {
		$gray = $lightness * 255;

		return array(
			'r' => $gray,
			'g' => $gray,
			'b' => $gray,
		);
	}

	$q = ( $lightness < 0.5 ) ? $lightness * ( 1 + $saturation ) : $lightness + $saturation - ( $lightness * $saturation );
	$p = ( 2 * $lightness ) - $q;

	$channel = array();

	foreach ( array( 'r' => $hue + ( 1 / 3 ), 'g' => $hue, 'b' => $hue - ( 1 / 3 ) ) as $name => $offset ) {
		$t = $offset;

		if ( $t < 0 ) {
			$t += 1;
		}

		if ( $t > 1 ) {
			$t -= 1;
		}

		if ( $t < ( 1 / 6 ) ) {
			$value = $p + ( ( $q - $p ) * 6 * $t );
		} elseif ( $t < ( 1 / 2 ) ) {
			$value = $q;
		} elseif ( $t < ( 2 / 3 ) ) {
			$value = $p + ( ( $q - $p ) * ( ( 2 / 3 ) - $t ) * 6 );
		} else {
			$value = $p;
		}

		$channel[ $name ] = $value * 255;
	}

	return $channel;
}

/**
 * Move a color's lightness until it clears a contrast ratio against a surface.
 *
 * The hue and the saturation are left alone, so a site's accent stays the same
 * color; only its lightness moves. A color that already clears the ratio is
 * returned untouched, so nothing is distorted that did not need it, and any
 * color can be fed in: the "Accent color" option accepts whatever the
 * administrator picks, including a near-white or near-black one.
 *
 * @param array $rgb    Color to adjust.
 * @param array $surface Color it will be painted on.
 * @param float $target Ratio to clear. 4.5 is the WCAG AA floor for text; the
 *                      accent tokens ask for a little more than the floor so
 *                      the shipped palette is not sitting exactly on the line.
 * @return array Adjusted channel triple.
 */
function majestic_tube_contrast_safe( $rgb, $surface, $target = 5.0 ) {
	if ( majestic_tube_contrast_ratio( $rgb, $surface ) >= $target ) {
		return $rgb;
	}

	// Away from the surface: the darker of the two gets darker, the lighter one
	// gets lighter, until the pair clears the target.
	$delta = ( majestic_tube_luminance( $rgb ) < majestic_tube_luminance( $surface ) ) ? -0.01 : 0.01;
	$hsl   = majestic_tube_rgb_to_hsl( $rgb );
	$last  = $rgb;

	for ( $step = 0; $step < 100; $step++ ) {
		$hsl['l'] = max( 0, min( 1, $hsl['l'] + $delta ) );
		$last     = majestic_tube_hsl_to_rgb( $hsl );

		if ( majestic_tube_contrast_ratio( $last, $surface ) >= $target ) {
			break;
		}

		// Black or white and still short of the target: there is nowhere
		// further to go, so this is the best the color can do.
		if ( $hsl['l'] <= 0 || $hsl['l'] >= 1 ) {
			break;
		}
	}

	return $last;
}

/**
 * Pick the text color for a filled accent surface.
 *
 * White is preferred, because that is what the theme is designed around, but
 * only while it actually reads: a pale accent that cannot carry white gets the
 * theme's near-black instead, and a mid-tone that cannot carry either gets
 * whichever of the two comes closer.
 *
 * @param array $rgb Fill color.
 * @return array Channel triple for the text on that fill.
 */
function majestic_tube_fill_foreground( $rgb ) {
	$white = array(
		'r' => 255,
		'g' => 255,
		'b' => 255,
	);

	$ink = array(
		'r' => 15,
		'g' => 18,
		'b' => 22,
	);

	$on_white = majestic_tube_contrast_ratio( $white, $rgb );
	$on_ink   = majestic_tube_contrast_ratio( $ink, $rgb );

	if ( $on_white >= 4.5 || $on_white >= $on_ink ) {
		return $white;
	}

	return $ink;
}

/**
 * Turn the brand options into CSS custom properties.
 *
 * Printing a small variable block keeps the main stylesheet cacheable while
 * still honoring main-color / logo-* / thumbnails-fit / videos-per-row and
 * categories-per-row.
 *
 * @return void
 */
function majestic_tube_output_brand_css() {
	$declarations = array();

	/*
	 * The accent, in every role it plays.
	 *
	 * The administrator picks one color; the theme derives the rest from it,
	 * each one aiming at a contrast ratio rather than a fixed shade, because a
	 * single value cannot both carry white button text and read as a link on a
	 * near-black page. Deriving them here rather than in the stylesheet is what
	 * keeps a custom accent consistent across the font, the links, the text and
	 * the icons in both skins: before this, only --mt-accent and --mt-accent-dark
	 * followed the option, so a chosen color changed the buttons while every
	 * link, icon and dark-skin hover stayed the theme's own teal - and the flat
	 * --mt-accent-dark printed here also overrode the light/dark pair in
	 * main.css, which made dark-skin link hover unreadable at 2.9:1.
	 *
	 * Targets, per WCAG 2.1: text on a light surface aims at 5:1 (AA asks
	 * 4.5:1, and the margin keeps the palette off the line), text on a dark
	 * surface aims at 7:1 (AAA, which a bright accent on black can afford),
	 * the hovered text asks for 1.2 more than its resting state, and the
	 * hovered fill asks for half a point more than the fill it replaces.
	 */
	$color = (string) majestic_tube_get_option( 'wpst-options', 'main-color', '#0e7b8a' );
	$color = sanitize_hex_color( $color );
	$rgb   = $color ? majestic_tube_hex_channels( $color ) : null;

	if ( $rgb ) {
		// The two surfaces each derived color is measured against: the light
		// page background (--mt-bg) and the dark card (--mt-surface). Both are
		// the stricter of their pair, so a value that clears them clears the
		// white card and the near-black page too.
		$light_page = majestic_tube_hex_channels( '#f4f5f7' );
		$dark_card  = majestic_tube_hex_channels( '#171b21' );

		$target_light = 5.0;
		$target_dark  = 7.0;

		$text_light = majestic_tube_contrast_safe( $rgb, $light_page, $target_light );
		$text_dark  = majestic_tube_contrast_safe( $rgb, $dark_card, $target_dark );

		// The hover target is measured off the resting color, not off the
		// floor: an accent that already clears the floor comfortably still gets
		// a visible step on hover, without ever being pushed below it.
		$hover_light = majestic_tube_contrast_safe(
			$rgb,
			$light_page,
			min( 21, max( $target_light + 1.2, majestic_tube_contrast_ratio( $text_light, $light_page ) + 1.2 ) )
		);
		$hover_dark  = majestic_tube_contrast_safe(
			$rgb,
			$dark_card,
			min( 21, max( $target_dark + 1.2, majestic_tube_contrast_ratio( $text_dark, $dark_card ) + 1.2 ) )
		);

		$foreground = majestic_tube_fill_foreground( $rgb );
		$fill_hover = majestic_tube_contrast_safe(
			$rgb,
			$foreground,
			min( 21, majestic_tube_contrast_ratio( $rgb, $foreground ) + 0.5 )
		);

		$declarations[] = '--mt-accent:' . $color;
		$declarations[] = '--mt-accent-rgb:' . $rgb['r'] . ',' . $rgb['g'] . ',' . $rgb['b'];
		$declarations[] = '--mt-accent-fg:' . majestic_tube_channels_hex( $foreground );
		$declarations[] = '--mt-accent-hover:' . majestic_tube_channels_hex( $fill_hover );

		// One pair for "the accent as text or as an icon", whichever surface it
		// is drawn on, so a link and a button label cannot drift apart.
		$declarations[] = '--mt-accent-text:light-dark(' . majestic_tube_channels_hex( $text_light ) . ',' . majestic_tube_channels_hex( $text_dark ) . ')';
		$declarations[] = '--mt-accent-text-hover:light-dark(' . majestic_tube_channels_hex( $hover_light ) . ',' . majestic_tube_channels_hex( $hover_dark ) . ')';

		// The player chrome is black whatever the skin is, and black is darker
		// than the dark card, so the dark-skin value is the safe one there.
		$declarations[] = '--mt-accent-on-dark:' . majestic_tube_channels_hex( $text_dark );

		// Kept in step for child themes written against the older name.
		$declarations[] = '--mt-accent-dark:light-dark(' . majestic_tube_channels_hex( $hover_light ) . ',' . majestic_tube_channels_hex( $hover_dark ) . ')';
	}

	$ratio = (string) majestic_tube_get_option( 'wpst-options', 'thumbnails-ratio', '16/9' );
	$parts = array_map( 'absint', explode( '/', $ratio ) );

	if ( 2 === count( $parts ) && $parts[0] && $parts[1] ) {
		// A percentage padding-bottom gives an aspect-ratio box that every
		// browser in the WP-Script support range understands.
		$declarations[] = '--mt-thumb-ratio:' . round( $parts[1] / $parts[0] * 100, 4 ) . '%';
	}

	$fit = (string) majestic_tube_get_option( 'wpst-options', 'thumbnails-fit', 'cover' );

	if ( in_array( $fit, array( 'cover', 'contain', 'fill' ), true ) ) {
		$declarations[] = '--mt-thumb-fit:' . $fit;
	}

	$per_row = absint( majestic_tube_get_option( 'wpst-options', 'videos-per-row', 5 ) );

	if ( $per_row > 0 ) {
		$per_row = min( max( $per_row, 2 ), 8 );

		$declarations[] = '--mt-columns:' . $per_row;

		/*
		 * The grid has to give up columns as the viewport narrows, but the
		 * stylesheet cannot compute a clamped column count from a custom
		 * property: repeat() only accepts an <integer>, and min() is not one.
		 * Emitting the two reduced counts here keeps the videos-per-row option
		 * meaningful on tablets instead of silently forcing three columns over
		 * whatever the administrator chose.
		 */
		$declarations[] = '--mt-columns-tablet:' . min( $per_row, 3 );
	}

	$per_row_mobile = absint( majestic_tube_get_option( 'wpst-options', 'videos-per-row-mobile', 2 ) );

	if ( $per_row_mobile > 0 ) {
		$declarations[] = '--mt-columns-mobile:' . min( max( $per_row_mobile, 1 ), 3 );
	}

	/*
	 * Categories per row. The categories listing reuses .video-grid, so without
	 * a token of its own it silently followed videos-per-row and the
	 * categories-per-row control changed nothing. The cap is 8, the maximum
	 * the control accepts.
	 */
	$per_row_categories = absint( majestic_tube_get_option( 'wpst-options', 'categories-per-row', 5 ) );

	if ( $per_row_categories > 0 ) {
		$per_row_categories = min( max( $per_row_categories, 1 ), 8 );

		$declarations[] = '--mt-category-columns:' . $per_row_categories;
		$declarations[] = '--mt-category-columns-tablet:' . min( $per_row_categories, 3 );
		$declarations[] = '--mt-category-columns-mobile:' . min( $per_row_categories, 2 );
	}

	$site_font  = (string) majestic_tube_get_option( 'wpst-options', 'site-font-family', majestic_tube_bundled_font_family() );
	$declarations[] = '--mt-font-family:' . majestic_tube_css_font_stack( $site_font, majestic_tube_site_font_stacks() );

	$font_size = absint( majestic_tube_get_option( 'wpst-options', 'logo-font-size', 36 ) );

	if ( $font_size ) {
		$declarations[] = '--mt-logo-size:' . $font_size . 'px';
	}

	$max_width = absint( majestic_tube_get_option( 'wpst-options', 'logo-max-width', 300 ) );

	if ( $max_width ) {
		$declarations[] = '--mt-logo-max-width:' . $max_width . 'px';
	}

	$max_height = absint( majestic_tube_get_option( 'wpst-options', 'logo-max-height', 120 ) );

	if ( $max_height ) {
		$declarations[] = '--mt-logo-max-height:' . $max_height . 'px';
	}

	// Margins are signed values (negative pulls the logo up), so they are read
	// with intval() rather than absint().
	$margin_top = intval( majestic_tube_get_option( 'wpst-options', 'logo-margin-top', 0 ) );

	if ( $margin_top ) {
		$declarations[] = '--mt-logo-margin-top:' . $margin_top . 'px';
	}

	$margin_left = intval( majestic_tube_get_option( 'wpst-options', 'logo-margin-left', 0 ) );

	if ( $margin_left ) {
		$declarations[] = '--mt-logo-margin-left:' . $margin_left . 'px';
	}

	$font_family = (string) majestic_tube_get_option( 'wpst-options', 'logo-font-family', majestic_tube_bundled_font_family() );

	if ( $font_family ) {
		$declarations[] = '--mt-logo-font-family:' . majestic_tube_css_font_stack( $font_family );
	}

	$watermark_width = absint( majestic_tube_get_option( 'wpst-options', 'logo-watermark-max-width', 200 ) );

	if ( $watermark_width ) {
		$declarations[] = '--mt-watermark-max-width:' . $watermark_width . 'px';
	}

	/**
	 * Filter the CSS custom properties printed for the theme options.
	 *
	 * @param string[] $declarations CSS declarations without the trailing semicolon.
	 */
	$declarations = apply_filters( 'majestic_tube_brand_css', $declarations );

	if ( ! $declarations ) {
		return;
	}

	/*
	 * The values are assembled from sanitize_hex_color(), absint() and
	 * majestic_tube_css_font_stack() above, so they are already safe to print
	 * raw. esc_html() must not be used here: it would turn the font stack's
	 * double quotes into &quot;, which CSS does not decode, silently breaking
	 * --mt-logo-font-family. wp_strip_all_tags() is kept as a guard so a value
	 * injected through the majestic_tube_brand_css filter cannot close the
	 * <style> element.
	 */
	printf(
		"<style id=\"majestic-tube-brand\">:root{%s;}</style>\n",
		wp_strip_all_tags( implode( ';', $declarations ) )
	);
}
/*
 * Printed at priority 10 on purpose. wp_print_styles() runs on wp_head
 * priority 8 and prints every enqueued stylesheet, so a :root block emitted
 * earlier loses the cascade to main.css for every token the stylesheet also
 * declares - which is all of them. At priority 7 that silently disabled the
 * main-color option as well as the font and column tokens, because main.css
 * re-declared --mt-accent and --mt-font-family afterwards.
 */
add_action( 'wp_head', 'majestic_tube_output_brand_css', 10 );

/**
 * Logo markup: the original theme supports an image logo, an icon+text logo
 * and a plain text logo, all driven by the wpst-options keys.
 *
 * Falls back to the WordPress custom logo, then to the site title.
 *
 * @return void
 */
function majestic_tube_site_logo() {
	$icon      = (string) majestic_tube_get_option( 'wpst-options', 'icon-logo', 'film' );
	$text      = (string) majestic_tube_get_option( 'wpst-options', 'text-logo', '' );
	$use_image = majestic_tube_option_is_on( 'use-logo-image' );
	$image     = (string) majestic_tube_get_option( 'wpst-options', 'image-logo-file', '' );

	// "Play circle" is no longer offered, so a value saved before it was
	// dropped is retired here rather than left to render a dangling class.
	if ( 'play-circle' === $icon ) {
		$icon = 'film';
	}

	if ( $use_image && $image ) {
		printf(
			'<a class="site-logo logo-image" href="%1$s" rel="home"><img src="%2$s" alt="%3$s" /></a>',
			esc_url( home_url( '/' ) ),
			esc_url( $image ),
			esc_attr( get_bloginfo( 'name' ) )
		);

		return;
	}

	if ( has_custom_logo() ) {
		the_custom_logo();

		return;
	}

	$name = $text ? $text : get_bloginfo( 'name' );

	printf(
		'<a class="site-logo" href="%1$s" rel="home"><i class="fa fa-%2$s" aria-hidden="true"></i><span class="site-logo-text">%3$s</span></a>',
		esc_url( home_url( '/' ) ),
		esc_attr( $icon ),
		esc_html( $name )
	);
}
