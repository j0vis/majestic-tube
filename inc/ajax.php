<?php
/**
 * AJAX handlers - WP-Script compatible action names and contracts.
 *
 * Mirrors the original theme's endpoints so WP-Script tooling and templates
 * that call post-views / post-like keep working, with proper sanitization.
 *
 * @package Majestic Tube
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verify the shared ajax-nonce sent by the front-end, original style.
 */
function majestic_tube_verify_ajax_nonce() {
	if ( ! isset( $_POST['nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ajax-nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'majestic-tube' ) ), 403 );
	}
}

/**
 * Require a valid post ID from an AJAX request.
 *
 * @param string $missing_message Message when post_id is absent.
 * @param string $invalid_message Message when the post does not exist.
 * @param int    $status          HTTP status for the JSON error.
 * @return int
 */
function majestic_tube_require_ajax_post_id( $missing_message, $invalid_message, $status = 400 ) {
	if ( ! isset( $_POST['post_id'] ) ) {
		wp_send_json_error( array( 'message' => $missing_message ), $status );
	}

	$post_id = absint( wp_unslash( $_POST['post_id'] ) );

	if ( ! $post_id || ! get_post( $post_id ) ) {
		wp_send_json_error( array( 'message' => $invalid_message ), $status );
	}

	return $post_id;
}

/**
 * Build the flat post-like response shared by success and read-only branches.
 *
 * @param int                $post_id Post ID.
 * @param array              $stats   Current likes, dislikes and rate data.
 * @param bool               $already Whether the visitor was already rated.
 * @param string             $button     Button text.
 * @param int|null           $percentage Optional already-calculated percentage.
 * @return array<string, int|string|bool>
 */
function majestic_tube_post_like_payload( $post_id, $stats, $already, $button = '', $percentage = null ) {
	$likes       = (int) $stats['likes'];
	$dislikes    = (int) $stats['dislikes'];
	$total       = $likes + $dislikes;
	$percentage  = null === $percentage ? majestic_tube_get_post_like_rate( $post_id ) : (int) $percentage;

	return array(
		'alreadyrate' => (bool) $already,
		'percentage'  => (int) $percentage,
		'button'      => $button,
		'nbrates'     => (int) $total,
		'likes'       => $likes,
		'dislikes'    => $dislikes,
		'progressbar' => (int) $percentage,
	);
}

/**
 * Handle post view counting (original action: post-views).
 */
function majestic_tube_set_post_views() {
	majestic_tube_verify_ajax_nonce();

	$post_id = majestic_tube_require_ajax_post_id(
		__( 'Post id required!', 'majestic-tube' ),
		__( 'Post id required!', 'majestic-tube' )
	);

	// Editors and administrators do not inflate their own statistics.
	if ( current_user_can( 'edit_posts' ) ) {
		wp_send_json(
			array(
				'views'   => (int) majestic_tube_get_post_views( $post_id ),
				'counted' => false,
			)
		);
	}

	/*
	 * De-duplicate server side. main.js already guards the call with a
	 * per-post sessionStorage key, but that is a client-side convenience: it
	 * does not survive a cleared browser, a second device, or a request sent
	 * without JavaScript. The authoritative check lives here so the counter
	 * cannot be inflated by refreshing.
	 *
	 * A repeat view is not an error - the response is still a success carrying
	 * the current count, so the visitor's number stays correct and the original
	 * { views: N } contract is untouched. 'counted' is additive and ignored by
	 * consumers that predate it.
	 */
	$counted = ! majestic_tube_visitor_already_counted_view( $post_id );
	$views   = $counted ? majestic_tube_increment_post_views( $post_id ) : majestic_tube_get_post_views( $post_id );

	// Flat payload, exactly like the original endpoint.
	wp_send_json(
		array(
			'views'   => (int) $views,
			'counted' => (bool) $counted,
		)
	);
}
add_action( 'wp_ajax_post-views', 'majestic_tube_set_post_views' );
add_action( 'wp_ajax_nopriv_post-views', 'majestic_tube_set_post_views' );

/**
 * Check whether the current IP has voted on a post recently (original keys).
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function majestic_tube_has_already_voted( $post_id ) {
	// Original bug kept intentionally? No: (now - time)/60 vs 86400 was wrong
	// in the original (compared minutes against a day). The shared helper keeps
	// the corrected seconds-based 24h window in one place.
	return majestic_tube_ip_was_recorded_recently( $post_id, 'voted_IP', DAY_IN_SECONDS );
}

/**
 * Handle post likes/dislikes (original action: post-like).
 */
function majestic_tube_post_like() {
	majestic_tube_verify_ajax_nonce();

	if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['post_like'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Missing parameters.', 'majestic-tube' ) ), 400 );
	}

	$post_id = majestic_tube_require_ajax_post_id(
		__( 'Missing parameters.', 'majestic-tube' ),
		__( 'Invalid request.', 'majestic-tube' )
	);
	$post_like = sanitize_key( wp_unslash( $_POST['post_like'] ) );

	if ( ! in_array( $post_like, array( 'like', 'dislike' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'majestic-tube' ) ), 400 );
	}

	$stats    = majestic_tube_analytics_get( $post_id );
	$likes    = $stats['likes'];
	$dislikes = $stats['dislikes'];

	// Payload keys are the original ones, flat (front-end scripts read
	// data.percentage / data.button / data.likes directly). Editors do not
	// vote on content they are allowed to edit.
	if ( current_user_can( 'edit_posts' ) || majestic_tube_has_already_voted( $post_id ) ) {
		wp_send_json( majestic_tube_post_like_payload( $post_id, $stats, true ) );
	}

	majestic_tube_record_ip_history( $post_id, 'voted_IP' );

	if ( 'like' === $post_like ) {
		++$likes;
	} else {
		++$dislikes;
	}

	update_post_meta( $post_id, 'likes_count', $likes );
	update_post_meta( $post_id, 'dislikes_count', $dislikes );

	$percentage  = majestic_tube_get_post_like_rate( $post_id );

	update_post_meta( $post_id, 'rate', $percentage );

	// Settle the cached copy now rather than leaving the meta hooks to
	// invalidate it, so a follow-up read in this same request is correct.
	majestic_tube_analytics_set(
		$post_id,
		array(
			'likes'    => $likes,
			'dislikes' => $dislikes,
			'rate'     => $percentage,
		)
	);

	wp_send_json(
		majestic_tube_post_like_payload(
			$post_id,
			array(
				'likes'    => $likes,
				'dislikes' => $dislikes,
			),
			false,
			__( 'Thank you!', 'majestic-tube' ),
			$percentage
		)
	);
}
add_action( 'wp_ajax_post-like', 'majestic_tube_post_like' );
add_action( 'wp_ajax_nopriv_post-like', 'majestic_tube_post_like' );

/**
 * Async stats refresh endpoint (original action: get-post-data).
 * Returns updated views, likes, dislikes, and rate for a post.
 */
function majestic_tube_get_post_data() {
	majestic_tube_verify_ajax_nonce();

	$post_id = majestic_tube_require_ajax_post_id(
		__( 'Post id required!', 'majestic-tube' ),
		__( 'Invalid post.', 'majestic-tube' )
	);

	$stats    = majestic_tube_analytics_get( $post_id );
	$views    = $stats['views'];
	$likes    = $stats['likes'];
	$dislikes = $stats['dislikes'];
	$total    = $likes + $dislikes;
	$rate     = $stats['rate'];

	// Flat payload: the original contract is { views, likes } at the top
	// level. The extra keys are additive and ignored by older consumers.
	wp_send_json(
		array(
			'views'       => (int) $views,
			'likes'       => (int) $likes,
			'dislikes'    => (int) $dislikes,
			'total'       => (int) $total,
			'rate'        => (int) $rate,
			'progressbar' => (int) $rate,
		)
	);
}
add_action( 'wp_ajax_get-post-data', 'majestic_tube_get_post_data' );
add_action( 'wp_ajax_nopriv_get-post-data', 'majestic_tube_get_post_data' );