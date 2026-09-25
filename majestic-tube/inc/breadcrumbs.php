<?php
/**
 * Breadcrumb trail.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a simple breadcrumb trail.
 */
function majestic_tube_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}

	$separator = '<span class="breadcrumb-separator" aria-hidden="true">/</span>';

	echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'majestic-tube' ) . '">';
	echo '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'majestic-tube' ) . '</a>';

	if ( is_single() ) {
		echo $separator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static safe string.
		echo '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Videos', 'majestic-tube' ) . '</a>';
		echo $separator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static safe string.
		echo '<span class="breadcrumb-current">' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_singular() ) {
		echo $separator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static safe string.
		echo '<span class="breadcrumb-current">' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_archive() || is_home() ) {
		echo $separator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static safe string.
		echo '<span class="breadcrumb-current">' . esc_html( wp_strip_all_tags( get_the_archive_title() ) ) . '</span>';
	} elseif ( is_search() ) {
		echo $separator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static safe string.
		/* translators: %s: search query. */
		echo '<span class="breadcrumb-current">' . esc_html( sprintf( __( 'Search: %s', 'majestic-tube' ), get_search_query() ) ) . '</span>';
	}

	echo '</nav>';
}