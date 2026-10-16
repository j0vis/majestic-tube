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
 * The post is fetched rather than taken from the global: the_excerpt_rss and
 * the_content_feed also fire for a feed item built straight from ID, and a
 * global $post that is null there made $post->ID a fatal error on every item
 * of such a feed instead of one image being left out.
 *
 * @param string $content Feed content.
 * @return string
 */
function majestic_tube_rss_post_thumbnail( $content ) {
	$post = get_post();

	if ( $post && has_post_thumbnail( $post->ID ) ) {
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
 * The spam check's slot on the comment form.
 *
 * It is printed from the submit field's filter rather than from one of the
 * actions inside the form, because this is the one point reached for a
 * signed-in commenter as well as a signed-out one: comment_form_after_fields()
 * lives inside the field loop, and a signed-in commenter's form skips it, so a
 * slot printed there would appear for some visitors and not others. The submit
 * field core hands in is returned untouched, with the slot in front of it, so
 * the submit paragraph, the button and the hidden comment fields keep the
 * shape they had.
 *
 * A signed-out commenter gets the widget, and the token it posts is the one
 * majestic_tube_comment_captcha_check() below verifies. A signed-in commenter
 * gets the reserved room instead and is never challenged: the account is the
 * check, and asking a member to solve a puzzle before every reply is the kind
 * of friction that ends in the challenge being switched off. The room is held
 * either way, so the form is the same height whether protection is off, on or
 * still loading - Turnstile's iframe is 65px tall and arrives after the page
 * has rendered, and a button that moves when it lands is a button that gets
 * missed. The helpers that own this markup live with the rest of the spam
 * check in inc/ajax-login-register.php.
 *
 * @param string $submit_field Submit field markup, including the button.
 * @return string The slot, then the submit field.
 */
function majestic_tube_captcha_comment_slot( $submit_field ) {
	ob_start();

	if ( is_user_logged_in() ) {
		majestic_tube_captcha_reserved_space();
	} else {
		majestic_tube_captcha_widget();
	}

	return (string) ob_get_clean() . $submit_field;
}
add_filter( 'comment_form_submit_field', 'majestic_tube_captcha_comment_slot' );

/**
 * Refuse an anonymous comment whose spam check did not pass.
 *
 * The widget above is the question, and this is the answer being checked. A
 * challenge nobody verifies is worse than none at all: it tells the visitor
 * they have been checked, costs them a tap or a wait, and stops no spam.
 *
 * The hook is `pre_comment_approved` rather than `preprocess_comment` because
 * that is the one WordPress acts on. Returning a WP_Error from this filter
 * short-circuits the insert and hands the error back to wp-comments-post.php,
 * which shows the message and a link back to the form. A WP_Error from
 * `preprocess_comment` is read by nothing: the next thing core does is index
 * the data it was handed as an array, so the refusal never happens and the
 * request dies on the way instead. The error carries 403 as its data because
 * that is what core reads to decide whether to show the failure page: with no
 * data it exits on a blank response instead.
 *
 * Three requests are left alone, on purpose:
 *
 *   - a signed-in commenter, whose account is the check and who is never
 *     offered a widget to solve;
 *   - anything that is not a posted comment form - a plugin calling
 *     wp_new_comment() from PHP, a trackback, an import - which has no token to
 *     offer and no visitor to ask. Those are gated on `$_POST['comment']`, the
 *     field core itself refuses a comment without, so nothing is created from a
 *     web request that skips this check;
 *   - a site with the check switched off or a key missing, which is the state
 *     majestic_tube_captcha_is_configured() describes.
 *
 * @param int|string $approved     Approval status, or a WP_Error to refuse.
 * @param array      $commentdata  Comment data, unused here.
 * @return int|string|WP_Error
 */
function majestic_tube_comment_captcha_check( $approved, $commentdata ) {
	unset( $commentdata );

	if ( ! majestic_tube_captcha_is_configured() || is_user_logged_in() ) {
		return $approved;
	}

	if ( ! isset( $_POST['comment'] ) ) {
		return $approved;
	}

	$field = majestic_tube_captcha_token_field();
	$token = ( isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

	if ( majestic_tube_verify_captcha( $token ) ) {
		return $approved;
	}

	return new WP_Error(
		'majestic_tube_captcha',
		__( 'That comment was not published: the spam check did not pass. Solve the challenge and send it again.', 'majestic-tube' ),
		403
	);
}
add_filter( 'pre_comment_approved', 'majestic_tube_comment_captcha_check', 10, 2 );