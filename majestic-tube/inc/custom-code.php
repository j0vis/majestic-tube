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
 * @version 2.0.6
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
 * Build a safe CSS font stack from a stored font-family option.
 *
 * The original option holds a single family name (now Inter by default). Every character
 * that is not a letter, digit, space, hyphen or underscore is dropped, which
 * removes quotes, semicolons, braces and comment markers alike. The result is
 * wrapped in double quotes with `sans-serif` appended as the fallback, so the
 * declaration stays valid even if the stored value is empty after filtering.
 *
 * @param string $font_family Stored font family.
 * @return string CSS font stack.
 */
function majestic_tube_css_font_stack( $font_family ) {
	$family = preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) $font_family );
	$family = trim( preg_replace( '/\s+/', ' ', $family ) );

	if ( '' === $family ) {
		return 'sans-serif';
	}

	return '"' . $family . '",sans-serif';
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
		$declarations[] = '--mt-columns:' . $per_row;
	}

	$per_row_mobile = absint( majestic_tube_get_option( 'wpst-options', 'videos-per-row-mobile', 2 ) );

	if ( $per_row_mobile > 0 ) {
		$declarations[] = '--mt-columns-mobile:' . $per_row_mobile;
	}

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

	$font_family = (string) majestic_tube_get_option( 'wpst-options', 'logo-font-family', 'Inter' );

	if ( $font_family && 'System default' !== $font_family ) {
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
	 * raw. esc_html() must not be used here: it would turn the font family's
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
add_action( 'wp_head', 'majestic_tube_output_brand_css', 7 );

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
