<?php
/**
 * Miscellaneous template filters.
 *
 * @package Majestic Tube
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hide the admin bar for non-administrators when the original display option is off.
 */
function majestic_tube_maybe_hide_admin_bar() {
	if ( 'on' === majestic_tube_get_option( 'wpst-options', 'display-admin-bar' ) ) {
		return;
	}

	if ( ! is_admin() && ! current_user_can( 'manage_options' ) ) {
		show_admin_bar( false );
	}
}
add_action( 'get_header', 'majestic_tube_maybe_hide_admin_bar' );

/**
 * Include the featured thumbnail in RSS feeds.
 *
 * @param string $content Feed content.
 * @return string
 */
function majestic_tube_rss_post_thumbnail( $content ) {
	global $post;

	if ( has_post_thumbnail( $post->ID ) ) {
		$content = '<p>' . get_the_post_thumbnail( $post->ID ) . '</p>' . $content;
	}

	return $content;
}
add_filter( 'the_excerpt_rss', 'majestic_tube_rss_post_thumbnail' );
add_filter( 'the_content_feed', 'majestic_tube_rss_post_thumbnail' );

/**
 * Manage excerpt length and more string.
 *
 * @param int $length Excerpt length.
 * @return int
 */
function majestic_tube_excerpt_length( $length ) {
	return 20;
}
add_filter( 'excerpt_length', 'majestic_tube_excerpt_length' );

/**
 * Customize the excerpt more string.
 *
 * @param string $more Excerpt more string.
 * @return string
 */
function majestic_tube_excerpt_more( $more ) {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'majestic_tube_excerpt_more' );