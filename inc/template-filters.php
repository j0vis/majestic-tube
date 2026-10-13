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

/**
 * Reserve the room a spam check would need on the comment form.
 *
 * The room is printed from the submit field's filter rather than from one of
 * the actions inside the form, because this is the one point reached for a
 * signed-in commenter as well as a signed-out one: comment_form_after_fields()
 * lives inside the field loop, and a signed-in commenter's form skips it, so a
 * slot printed there would appear for some visitors and not others. The submit
 * field core hands in is returned untouched, with the room in front of it, so
 * the submit paragraph, the button and the hidden comment fields keep the
 * shape they had.
 *
 * It is the room and not a challenge. The theme asks Turnstile for a token on
 * the sign-up and video submission forms, so nothing on this one is verified
 * and a visitor is never asked to solve a puzzle that decides nothing. The
 * comment form is the same height whether protection is off, on or still
 * loading, and the helpers that own the markup live with the rest of the spam
 * check in inc/ajax-login-register.php.
 *
 * @param string $submit_field Submit field markup, including the button.
 * @return string The room, then the submit field.
 */
function majestic_tube_captcha_comment_room( $submit_field ) {
	ob_start();
	majestic_tube_captcha_reserved_space();

	return (string) ob_get_clean() . $submit_field;
}
add_filter( 'comment_form_submit_field', 'majestic_tube_captcha_comment_room' );