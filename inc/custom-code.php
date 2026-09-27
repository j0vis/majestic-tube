<?php
/**
 * Head / footer output driven by the original theme options.
 *
 * The original settings screen let administrators paste a favicon, search-engine
 * verification tags, analytics snippets and extra JS. Majestic Tube exposes
 * those same fields natively and prints them from wp_head and wp_footer.
 *
 * Output is administrator-supplied markup: it is sanitized on input (unfiltered
 * for users with unfiltered_html, wp_kses_post otherwise) and printed verbatim,
 * exactly like the original theme and like the ad zones.
 *
 * @package Majestic Tube
 * @version 2.1.7
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
 * Print the favicon, verification tags and analytics code in <head>.
 *
 * @return void
 */
function majestic_tube_output_head_code() {
	$favicon = (string) majestic_tube_get_option( 'wpst-options', 'favicon', '' );

	if ( $favicon ) {
		printf( '<link rel="icon" href="%s" />' . "\n", esc_url( $favicon ) );
	}

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
 * Turn the brand options into CSS custom properties.
 *
 * Printing a small variable block keeps the main stylesheet cacheable while
 * still honouring main-color / logo-* / thumbnails-fit / videos-per-row.
 *
 * @return void
 */
function majestic_tube_output_brand_css() {
	$declarations = array();

	$color = (string) majestic_tube_get_option( 'wpst-options', 'main-color', '#0f8a99' );
	$color = sanitize_hex_color( $color );

	if ( $color ) {
		$declarations[] = '--mt-accent:' . $color;

		$hex = ltrim( $color, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$red   = hexdec( substr( $hex, 0, 2 ) );
		$green = hexdec( substr( $hex, 2, 2 ) );
		$blue  = hexdec( substr( $hex, 4, 2 ) );

		$declarations[] = '--mt-accent-rgb:' . $red . ',' . $green . ',' . $blue;
		$declarations[] = '--mt-accent-dark:#' . sprintf(
			'%02x%02x%02x',
			floor( $red * 0.8 ),
			floor( $green * 0.8 ),
			floor( $blue * 0.8 )
		);
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
	$icon      = (string) majestic_tube_get_option( 'wpst-options', 'icon-logo', 'play-circle' );
	$text      = (string) majestic_tube_get_option( 'wpst-options', 'text-logo', '' );
	$use_image = majestic_tube_option_is_on( 'use-logo-image' );
	$image     = (string) majestic_tube_get_option( 'wpst-options', 'image-logo-file', '' );

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
