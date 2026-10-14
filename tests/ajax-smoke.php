<?php
/**
 * Smoke test for inc/ajax.php.
 *
 * The module is the front end's write path: three endpoints under the original
 * theme's action names (post-views, post-like, get-post-data), each answering
 * both a signed-in and a signed-out visitor. The names are the contract -
 * main.js posts to them and WP-Script tooling calls them by name - so they are
 * pinned rather than assumed.
 *
 * Five claims are held down here:
 *
 *   1. the names and the pairs. Every action is registered for wp_ajax_ and
 *      wp_ajax_nopriv_ with one callback, so a member and a visitor cannot end
 *      up in different code paths;
 *   2. nothing happens without a nonce, and nothing runs behind the refusal.
 *      The nonce is read from $_POST, unslashed and sanitized, and verified
 *      against the 'ajax-nonce' action main.js was handed at render time;
 *   3. the request has to be about a post the front end would show. A missing,
 *      unusable, draft, private or nonexistent id is refused, and no endpoint
 *      counts, votes or reads counters for it;
 *   4. each endpoint answers with the shape main.js reads: the flat original
 *      payload (wp_send_json() does not wrap it), `counted` for a view, the
 *      seven like keys the vote handler branches on, and the refresh keys
 *      applyStats() paints from;
 *   5. the counters are reached through inc/analytics.php rather than by
 *      calling get_post_meta() here, and a vote settles the cached copy, drops
 *      the site-wide "this site has likes" answer, and records the visitor
 *      under the same meta key the check before it consulted.
 *
 * inc/analytics.php's own behavior is NOT covered: the counter layer is stubbed
 * with the signatures and return shapes the module calls, so what is pinned
 * here is what the endpoints do with it. That module has its own suite.
 *
 * Two notes on the harness, so a failure is read correctly. wp_send_json() and
 * wp_send_json_error() end the request in WordPress (they call wp_die()), so
 * the stubs here throw and mt_request() catches; that is what makes "nothing
 * behind the refusal ran" an assertion rather than a coincidence. And the stubs
 * record the array and status a handler asked WordPress to send rather than
 * re-implementing wp_json_encode()'s output.
 *
 * Run:  php tests/ajax-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = 0;

/* -------------------------------------------------------------------------
 * State the fixtures read and write.
 * ---------------------------------------------------------------------- */

$GLOBALS['hooks']          = array(); // Every add_action() call, in order.
$GLOBALS['good_nonce']     = 'a1b2c3d4e5';

$GLOBALS['posts']          = array(); // Post ID => object.
$GLOBALS['meta']           = array(); // Post ID => meta key => value.
$GLOBALS['counters']       = array(); // Post ID => views/likes/dislikes, as analytics reports them.
$GLOBALS['can']            = array(); // Capability => bool.
$GLOBALS['voted']          = false;   // Whether this visitor already voted.
$GLOBALS['already_viewed'] = false;   // Whether this visitor's view was already counted.

$GLOBALS['responses']      = array(); // What each request asked WordPress to send.
$GLOBALS['errors']         = array(); // Uncaught PHP errors a request died with.
$GLOBALS['ended']          = false;   // Whether the request ended by sending a response.

$GLOBALS['post_reads']     = array();
$GLOBALS['meta_reads']     = array();
$GLOBALS['meta_writes']    = array();
$GLOBALS['meta_deleted']   = array();
$GLOBALS['transient_del']  = array();
$GLOBALS['nonce_checks']   = array();
$GLOBALS['dedup_checks']   = array();
$GLOBALS['increments']     = array();
$GLOBALS['rate_calls']     = array();
$GLOBALS['analytics_gets'] = array();
$GLOBALS['analytics_sets'] = array();
$GLOBALS['ip_checks']      = array();
$GLOBALS['ip_history']     = array();
$GLOBALS['requests']       = 0;
$GLOBALS['unanswered']     = 0;

/**
 * The posts and counters each scenario starts from.
 *
 * One published video, one draft, one page, one private post and a second
 * published video whose two vote counters are 1/1 - the ratio that tells
 * round() from ceil(): 1 of 3 votes is 33.33%, which round() reports as 33 and
 * ceil() as 34.
 *
 * @return void
 */
function mt_world() {
	$GLOBALS['posts'] = array(
		7  => (object) array( 'ID' => 7, 'post_type' => 'post', 'post_status' => 'publish' ),
		8  => (object) array( 'ID' => 8, 'post_type' => 'post', 'post_status' => 'draft' ),
		9  => (object) array( 'ID' => 9, 'post_type' => 'page', 'post_status' => 'publish' ),
		10 => (object) array( 'ID' => 10, 'post_type' => 'post', 'post_status' => 'private' ),
		11 => (object) array( 'ID' => 11, 'post_type' => 'post', 'post_status' => 'publish' ),
	);

	$GLOBALS['meta'] = array(
		7  => array(),
		8  => array(),
		9  => array(),
		10 => array(),
		11 => array(),
	);

	$GLOBALS['counters'] = array(
		7  => array( 'views' => 10, 'likes' => 3, 'dislikes' => 1 ),
		8  => array( 'views' => 1, 'likes' => 0, 'dislikes' => 0 ),
		9  => array( 'views' => 0, 'likes' => 0, 'dislikes' => 0 ),
		10 => array( 'views' => 0, 'likes' => 0, 'dislikes' => 0 ),
		11 => array( 'views' => 2, 'likes' => 1, 'dislikes' => 1 ),
	);

	$GLOBALS['can']            = array( 'edit_posts' => false );
	$GLOBALS['voted']          = false;
	$GLOBALS['already_viewed'] = false;
}

/**
 * Begin a request: its body, and the record of what it asked for.
 *
 * The world (posts, meta, counters, capabilities) is left alone, so a scenario
 * can send a vote and then ask for the stats, which is the sequence the front
 * end itself sends.
 *
 * @param array $post Body, as $_POST.
 * @return void
 */
function mt_reset( $post = array() ) {
	$_POST = $post;

	$GLOBALS['responses']      = array();
	$GLOBALS['ended']          = false;
	$GLOBALS['post_reads']     = array();
	$GLOBALS['meta_reads']     = array();
	$GLOBALS['meta_writes']    = array();
	$GLOBALS['meta_deleted']   = array();
	$GLOBALS['transient_del']  = array();
	$GLOBALS['nonce_checks']   = array();
	$GLOBALS['dedup_checks']   = array();
	$GLOBALS['increments']     = array();
	$GLOBALS['rate_calls']     = array();
	$GLOBALS['analytics_gets'] = array();
	$GLOBALS['analytics_sets'] = array();
	$GLOBALS['ip_checks']      = array();
	$GLOBALS['ip_history']     = array();
}

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as this module touches.
 * ---------------------------------------------------------------------- */

function __( $text, $domain = null ) {
	return $text;
}

function apply_filters( $hook, $value ) {
	return $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return is_string( $value ) ? stripslashes( $value ) : $value;
}

/**
 * The text sanitizer, faithful on the one case this suite needs it to be.
 *
 * WordPress returns an empty string for an array or an object rather than
 * casting it, which is what makes "a nonce that arrives as an array" a
 * rejection rather than the string "Array".
 *
 * @param mixed $str Raw value.
 * @return string
 */
function sanitize_text_field( $str ) {
	if ( is_object( $str ) || is_array( $str ) ) {
		return '';
	}

	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
}

/**
 * The key sanitizer, with WordPress's scalar guard.
 *
 * A value that is not a scalar sanitizes to an empty string, so the vote word
 * cannot be coerced into one of the two accepted words by sending something
 * shaped like an array. Note that it lowercases: LIKE votes like.
 *
 * @param mixed $key Raw value.
 * @return string
 */
function sanitize_key( $key ) {
	if ( ! is_scalar( $key ) ) {
		return '';
	}

	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hooks'][] = array(
		'hook'     => $hook,
		'callback' => $callback,
		'priority' => $priority,
	);

	return true;
}

/**
 * The nonce verifier, keyed to the one action the front end is handed.
 *
 * inc/assets.php creates the front-end nonce with wp_create_nonce( 'ajax-nonce' ),
 * so a nonce minted for any other action has to fail here - which is the point
 * of recording the action alongside the value.
 *
 * @param string $nonce  Nonce as received.
 * @param string $action Action it is claimed to be for.
 * @return bool
 */
function wp_verify_nonce( $nonce, $action = -1 ) {
	$GLOBALS['nonce_checks'][] = array( 'nonce' => $nonce, 'action' => $action );

	return is_string( $nonce ) && $GLOBALS['good_nonce'] === $nonce && 'ajax-nonce' === $action;
}

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['can'][ $capability ] );
}

function get_post( $post_id ) {
	$GLOBALS['post_reads'][] = (int) $post_id;

	return isset( $GLOBALS['posts'][ $post_id ] ) ? $GLOBALS['posts'][ $post_id ] : null;
}

/**
 * Core's own answer to "would the front end show this?".
 *
 * A publicly queryable post type and a public post status: published posts and
 * pages pass, drafts and private posts do not. The page fixture is deliberate -
 * the module's docblock claims this rule rather than a post-type check, so the
 * suite pins the rule it claims.
 *
 * @param mixed $post Post object.
 * @return bool
 */
function is_post_publicly_viewable( $post ) {
	if ( ! $post || ! isset( $post->post_type, $post->post_status ) ) {
		return false;
	}

	return in_array( $post->post_type, array( 'post', 'page' ), true ) && 'publish' === $post->post_status;
}

function get_post_meta( $post_id, $meta_key = '', $single = false ) {
	$GLOBALS['meta_reads'][] = array( (int) $post_id, $meta_key );

	if ( '' === $meta_key ) {
		return array();
	}

	return isset( $GLOBALS['meta'][ $post_id ][ $meta_key ] ) ? $GLOBALS['meta'][ $post_id ][ $meta_key ] : '';
}

function update_post_meta( $post_id, $meta_key, $meta_value ) {
	$GLOBALS['meta_writes'][] = array( (int) $post_id, $meta_key, $meta_value );

	$GLOBALS['meta'][ $post_id ][ $meta_key ] = $meta_value;

	/*
	 * WordPress fires updated_post_meta here, and inc/analytics.php hangs the
	 * counter cache's invalidation on that hook. The one stub has to reproduce
	 * it: without it the bundle the vote handler read before writing is still
	 * cached, and the percentage it works out afterwards describes the vote it
	 * has just replaced.
	 */
	mt_flush_counters( $post_id );

	return true;
}

function delete_post_meta( $post_id, $meta_key ) {
	$GLOBALS['meta_deleted'][] = array( (int) $post_id, $meta_key );

	unset( $GLOBALS['meta'][ $post_id ][ $meta_key ] );

	mt_flush_counters( $post_id );

	return true;
}

/**
 * Reproduce what WordPress does to the cached counters on a counter write.
 *
 * inc/analytics.php registers majestic_tube_analytics_flush_post() on
 * added/updated/deleted_post_meta for exactly the keys below, so writing a
 * counter drops the cached bundle and the next read comes from the row that was
 * just stored. This suite stubs the analytics layer instead of including it, so
 * the flush is modelled by re-deriving $GLOBALS['counters'] from the meta that
 * was written. Without it the module would read the bundle it cached before the
 * vote and answer with the percentage the video already had.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function mt_flush_counters( $post_id ) {
	$post_id = (int) $post_id;
	$map     = array(
		'post_views_count' => 'views',
		'likes_count'      => 'likes',
		'dislikes_count'   => 'dislikes',
	);

	foreach ( $map as $meta_key => $field ) {
		if ( isset( $GLOBALS['meta'][ $post_id ][ $meta_key ] ) ) {
			$GLOBALS['counters'][ $post_id ][ $field ] = (int) $GLOBALS['meta'][ $post_id ][ $meta_key ];
		}
	}
}

function delete_transient( $key ) {
	$GLOBALS['transient_del'][] = $key;

	return true;
}

/*
 * The counter layer, stubbed at the boundary this module calls it through.
 *
 * The readers answer from the counters fixture, so an endpoint that reached for
 * get_post_meta() instead shows up as a recorded meta read; the writers record
 * what they were asked to settle. The rate function is the formula the real one
 * uses (round, not ceil).
 */
function majestic_tube_analytics_get( $post_id ) {
	$GLOBALS['analytics_gets'][] = (int) $post_id;

	$counters = isset( $GLOBALS['counters'][ $post_id ] ) ? $GLOBALS['counters'][ $post_id ] : array( 'views' => 0, 'likes' => 0, 'dislikes' => 0 );

	return array(
		'views'    => (int) $counters['views'],
		'likes'    => (int) $counters['likes'],
		'dislikes' => (int) $counters['dislikes'],
		'rate'     => isset( $GLOBALS['meta'][ $post_id ]['rate'] ) ? (int) $GLOBALS['meta'][ $post_id ]['rate'] : 0,
		'reported' => 0,
	);
}

function majestic_tube_analytics_set( $post_id, $data ) {
	$GLOBALS['analytics_sets'][] = array( (int) $post_id, $data );

	foreach ( (array) $data as $field => $value ) {
		$GLOBALS['counters'][ $post_id ][ $field ] = (int) $value;
	}
}

function majestic_tube_get_post_views( $post_id ) {
	return isset( $GLOBALS['counters'][ $post_id ] ) ? (int) $GLOBALS['counters'][ $post_id ]['views'] : 0;
}

function majestic_tube_increment_post_views( $post_id ) {
	$GLOBALS['increments'][] = (int) $post_id;

	$GLOBALS['counters'][ $post_id ]['views'] = majestic_tube_get_post_views( $post_id ) + 1;

	return $GLOBALS['counters'][ $post_id ]['views'];
}

/**
 * The rating percentage, as inc/video-meta.php delegates it to analytics.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function majestic_tube_get_post_like_rate( $post_id ) {
	$GLOBALS['rate_calls'][] = (int) $post_id;

	$counters = isset( $GLOBALS['counters'][ $post_id ] ) ? $GLOBALS['counters'][ $post_id ] : array( 'likes' => 0, 'dislikes' => 0 );
	$total    = (int) $counters['likes'] + (int) $counters['dislikes'];

	return 0 === $total ? 0 : (int) round( $counters['likes'] / $total * 100 );
}

function majestic_tube_visitor_already_counted_view( $post_id ) {
	$GLOBALS['dedup_checks'][] = (int) $post_id;

	return (bool) $GLOBALS['already_viewed'];
}

function majestic_tube_ip_was_recorded_recently( $post_id, $meta_key, $window = 0 ) {
	$GLOBALS['ip_checks'][] = array( (int) $post_id, $meta_key );

	return (bool) $GLOBALS['voted'];
}

function majestic_tube_record_ip_history( $post_id, $meta_key, $window = 0 ) {
	$GLOBALS['ip_history'][] = array( (int) $post_id, $meta_key );

	return true;
}

/* -------------------------------------------------------------------------
 * The end of a WordPress AJAX request.
 * ---------------------------------------------------------------------- */

/**
 * Stands in for the wp_die() at the end of wp_send_json().
 *
 * Without it a failed nonce check would merely return to its caller and the
 * handler would carry on into the code the check exists to protect, which is
 * the opposite of what WordPress does.
 */
class MT_Ajax_Die extends Exception {}

/**
 * Record the response a handler asked WordPress to send, then end the request.
 *
 * @param string $sent_by  Which wp_send_json*() the module called.
 * @param mixed  $data     The array passed to it.
 * @param mixed  $status   HTTP status passed to it, or null.
 * @return void
 */
function mt_send( $sent_by, $data, $status ) {
	$GLOBALS['responses'][] = array(
		'sent_by' => $sent_by,
		'data'    => $data,
		'status'  => $status,
	);

	$GLOBALS['ended'] = true;

	throw new MT_Ajax_Die();
}

/**
 * wp_send_json() sends its argument as-is: it does not wrap it in success/data.
 *
 * That is why the responses here are flat and why main.js has to unwrap(), so
 * the distinction is recorded rather than flattened away.
 */
function wp_send_json( $response, $status_code = null, $flags = 0 ) {
	mt_send( 'wp_send_json', $response, $status_code );
}

function wp_send_json_error( $data = null, $status_code = null, $flags = 0 ) {
	mt_send( 'wp_send_json_error', $data, $status_code );
}

function wp_send_json_success( $data = null, $status_code = null, $flags = 0 ) {
	mt_send( 'wp_send_json_success', $data, $status_code );
}

require_once MAJESTIC_TUBE_DIR . '/inc/ajax.php';

/* -------------------------------------------------------------------------
 * Harness
 * ---------------------------------------------------------------------- */

function check( $label, $ok, $context = '' ) {
	global $passed, $failed;

	if ( $ok ) {
		++$passed;
		printf( "  [PASS] %s\n", $label );

		return;
	}

	++$failed;
	printf( "  [FAIL] %s%s\n", $label, $context ? "\n         $context" : '' );
}

/**
 * Run one handler the way WordPress runs it, and answer with what it sent.
 *
 * @param callable|string $handler         Handler to run.
 * @param bool            $expects_answer  Whether the handler is one that must
 *                                         answer, rather than a helper called
 *                                         on its own to read what it returns.
 * @return array Recorded responses.
 */
function mt_request( $handler, $expects_answer = true ) {
	++$GLOBALS['requests'];

	try {
		call_user_func( $handler );
	} catch ( MT_Ajax_Die $e ) {
		// Expected: the handler ended by sending its response.
	} catch ( Throwable $e ) {
		$GLOBALS['errors'][] = get_class( $e ) . ': ' . $e->getMessage();
	}

	if ( $expects_answer && ! $GLOBALS['ended'] ) {
		++$GLOBALS['unanswered'];
	}

	return $GLOBALS['responses'];
}

/**
 * A compact rendering of a recorded response, for a failure's detail line.
 *
 * @param array $response One recorded response.
 * @return string
 */
function mt_show( $response ) {
	return $response['sent_by'] . ' ' . var_export( $response['status'], true ) . ' ' . json_encode( $response['data'] );
}

/*
 * Everything below runs against the module that ships. The handlers stop by
 * throwing MT_Ajax_Die, so the response under test is always the first and only
 * one a request recorded.
 */
mt_world();

/* -------------------------------------------------------------------------
 * The names, and the pairs they answer on
 * ---------------------------------------------------------------------- */

echo "\nThe names, and the pairs they answer on\n";

$registered = array();

foreach ( $GLOBALS['hooks'] as $hook ) {
	$registered[ $hook['hook'] ] = $hook['callback'];
}

check( 'three actions are registered for both kinds of visitor, and nothing else', 6 === count( $GLOBALS['hooks'] ), json_encode( array_keys( $registered ) ) );
check( 'post-views answers a visitor under the original name', isset( $registered['wp_ajax_nopriv_post-views'] ) && 'majestic_tube_set_post_views' === $registered['wp_ajax_nopriv_post-views'] );
check( 'and a member under the same handler', isset( $registered['wp_ajax_post-views'] ) && 'majestic_tube_set_post_views' === $registered['wp_ajax_post-views'] );
check( 'post-like answers a visitor under the original name', isset( $registered['wp_ajax_nopriv_post-like'] ) && 'majestic_tube_post_like' === $registered['wp_ajax_nopriv_post-like'] );
check( 'and a member under the same handler', isset( $registered['wp_ajax_post-like'] ) && 'majestic_tube_post_like' === $registered['wp_ajax_post-like'] );
check( 'get-post-data answers a visitor under the original name', isset( $registered['wp_ajax_nopriv_get-post-data'] ) && 'majestic_tube_get_post_data' === $registered['wp_ajax_nopriv_get-post-data'] );
check( 'and a member under the same handler', isset( $registered['wp_ajax_get-post-data'] ) && 'majestic_tube_get_post_data' === $registered['wp_ajax_get-post-data'] );
check(
	'a signed-out visitor and a member run the same code for each action',
	$registered['wp_ajax_post-views'] === $registered['wp_ajax_nopriv_post-views']
		&& $registered['wp_ajax_post-like'] === $registered['wp_ajax_nopriv_post-like']
		&& $registered['wp_ajax_get-post-data'] === $registered['wp_ajax_nopriv_get-post-data'],
	'sign-in would otherwise change which handler answers'
);

/* -------------------------------------------------------------------------
 * The nonce
 * ---------------------------------------------------------------------- */

echo "\nThe nonce\n";

mt_reset( array( 'post_id' => 7 ) );

$sent = mt_request( 'majestic_tube_set_post_views' );

check( 'a request with no nonce is refused', 1 === count( $sent ) && 'wp_send_json_error' === $sent[0]['sent_by'], $sent ? mt_show( $sent[0] ) : 'nothing was sent' );
check( 'with the message the front end shows', 'Security check failed.' === $sent[0]['data']['message'], mt_show( $sent[0] ) );
check( 'and the status that tells main.js the request failed', 403 === $sent[0]['status'], mt_show( $sent[0] ) );
check( 'nothing behind the check ran', ! $GLOBALS['increments'] && ! $GLOBALS['dedup_checks'] && ! $GLOBALS['analytics_gets'] );
check( 'and an absent nonce is not even verified', ! $GLOBALS['nonce_checks'], 'the isset() guard short-circuits' );

mt_reset( array( 'post_id' => 7, 'nonce' => 'not-the-nonce' ) );

$sent = mt_request( 'majestic_tube_set_post_views' );

check( 'a wrong nonce is refused', 1 === count( $sent ) && 'Security check failed.' === $sent[0]['data']['message'], mt_show( $sent[0] ) );
check( 'and it was checked against the action main.js was handed', 1 === count( $GLOBALS['nonce_checks'] ) && 'ajax-nonce' === $GLOBALS['nonce_checks'][0]['action'], json_encode( $GLOBALS['nonce_checks'] ) );
check( 'with nothing counted behind it', ! $GLOBALS['increments'] );

mt_reset( array( 'post_id' => 7, 'nonce' => 'a1b2c3d4e5\\' ) );

$sent = mt_request( 'majestic_tube_set_post_views' );

check(
	'a nonce WordPress escaped is unslashed before it is verified',
	1 === count( $GLOBALS['nonce_checks'] ) && 'a1b2c3d4e5' === $GLOBALS['nonce_checks'][0]['nonce'],
	json_encode( $GLOBALS['nonce_checks'] )
);
check( 'so the escaped spelling is accepted', 1 === count( $sent ) && 'wp_send_json' === $sent[0]['sent_by'], $sent ? mt_show( $sent[0] ) : 'nothing was sent' );

mt_reset( array( 'post_id' => 7, 'nonce' => ' <b>a1b2c3d4e5</b> ' ) );

mt_request( 'majestic_tube_set_post_views' );

check(
	'markup and padding around the nonce are stripped rather than verified',
	1 === count( $GLOBALS['nonce_checks'] ) && 'a1b2c3d4e5' === $GLOBALS['nonce_checks'][0]['nonce'],
	json_encode( $GLOBALS['nonce_checks'] )
);

mt_reset( array( 'post_id' => 7, 'nonce' => array( 'a1b2c3d4e5' ) ) );

$sent = mt_request( 'majestic_tube_set_post_views' );

check( 'a nonce that arrives as an array is refused', 1 === count( $sent ) && 'Security check failed.' === $sent[0]['data']['message'], mt_show( $sent[0] ) );
check( 'and it is not silently read as a string', '' === $GLOBALS['nonce_checks'][0]['nonce'], json_encode( $GLOBALS['nonce_checks'] ) );

foreach ( array( 'post-views' => 'majestic_tube_set_post_views', 'post-like' => 'majestic_tube_post_like', 'get-post-data' => 'majestic_tube_get_post_data' ) as $action => $handler ) {
	mt_world();
	mt_reset( array( 'nonce' => 'wrong', 'post_id' => 7, 'post_like' => 'like' ) );

	$sent = mt_request( $handler );

	check(
		$action . ' refuses a bad nonce before it reads the body',
		'Security check failed.' === $sent[0]['data']['message']
			&& ! $GLOBALS['post_reads']
			&& ! $GLOBALS['analytics_gets']
			&& ! $GLOBALS['meta_writes'],
		mt_show( $sent[0] )
	);
}

/* -------------------------------------------------------------------------
 * The post the request is about
 * ---------------------------------------------------------------------- */

echo "\nThe post the request is about\n";

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5' ) );

$sent = mt_request( 'majestic_tube_set_post_views' );

check( 'a request without a post id is refused', 1 === count( $sent ) && 'Post id required!' === $sent[0]['data']['message'], mt_show( $sent[0] ) );
check( 'with a 400', 400 === $sent[0]['status'], mt_show( $sent[0] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5' ) );

$sent = mt_request( 'majestic_tube_post_like' );

check( 'the vote endpoint says it in its own words', 'Missing parameters.' === $sent[0]['data']['message'], mt_show( $sent[0] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 8, 'post_like' => 'like' ) );

$sent = mt_request( 'majestic_tube_post_like' );

check( 'a draft is not a post the front end shows', 'Invalid request.' === $sent[0]['data']['message'], mt_show( $sent[0] ) );
check( 'and the vote is not recorded for it', ! $GLOBALS['meta_writes'] && ! $GLOBALS['ip_history'] );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 10 ) );

$sent = mt_request( 'majestic_tube_set_post_views' );

check( 'a private post is refused too', 'Post id required!' === $sent[0]['data']['message'], mt_show( $sent[0] ) );
check( 'without counting a view on it', ! $GLOBALS['increments'] );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 4242 ) );

$sent = mt_request( 'majestic_tube_set_post_views' );

check( 'a post that does not exist is refused', 'Post id required!' === $sent[0]['data']['message'], mt_show( $sent[0] ) );

foreach ( array( '', '0', 'abc' ) as $value ) {
	mt_world();
	mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => $value ) );

	$sent = mt_request( 'majestic_tube_set_post_views' );

	check(
		'a post id of ' . var_export( $value, true ) . ' is refused',
		1 === count( $sent ) && 'wp_send_json_error' === $sent[0]['sent_by'],
		$sent ? mt_show( $sent[0] ) : 'nothing was sent'
	);
}

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 'abc' ) );

mt_request( 'majestic_tube_set_post_views' );

check( 'and an unusable id is not looked up at all', ! $GLOBALS['post_reads'], json_encode( $GLOBALS['post_reads'] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => '-7' ) );

$sent = mt_request( 'majestic_tube_set_post_views' );

check(
	'a negative id is read as its absolute value, the way absint() reads it',
	11 === $sent[0]['data']['views'] && array( 7 ) === $GLOBALS['increments'],
	'it counts the same video, which is what the front end asks for anyway'
);

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7 ) );

$returned = 0;
$sent     = mt_request(
	static function () use ( &$returned ) {
		$returned = majestic_tube_require_ajax_post_id( 'missing', 'invalid' );
	},
	false
);

check( 'a published video is accepted, and its id comes back', 7 === $returned, var_export( $returned, true ) );
check( 'and a valid request reaches the end without sending an error', ! $sent && ! $GLOBALS['ended'] );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 9 ) );

$returned = 0;
mt_request(
	static function () use ( &$returned ) {
		$returned = majestic_tube_require_ajax_post_id( 'missing', 'invalid' );
	},
	false
);

check(
	'a published page passes as well: the rule is "publicly viewable", not "a video"',
	9 === $returned,
	'the module documents this rule, so the suite pins the rule it claims'
);

mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 8 ) );

// Enough to prove each refusal carries the message and status its caller asked
// it to carry, which is how the three endpoints word the same condition
// differently.
$sent = mt_request(
	static function () {
		$_POST['post_id'] = 8;

		majestic_tube_require_ajax_post_id( 'the missing message', 'the invalid message', 418 );
	}
);

check( 'the caller supplies the refusal the helper sends', 'the invalid message' === $sent[0]['data']['message'] && 418 === $sent[0]['status'], mt_show( $sent[0] ) );

/* -------------------------------------------------------------------------
 * Counting a view
 * ---------------------------------------------------------------------- */

echo "\nCounting a view\n";

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7 ) );

$sent = mt_request( 'majestic_tube_set_post_views' );
$data = $sent[0]['data'];

check( 'a first view raises the counter and says so', 11 === $data['views'] && true === $data['counted'], mt_show( $sent[0] ) );
check( 'the answer is flat, the way the original endpoint answered', 'wp_send_json' === $sent[0]['sent_by'] && ! array_key_exists( 'success', $data ), mt_show( $sent[0] ) );
check( 'and carries exactly views and counted', array( 'views', 'counted' ) === array_keys( $data ), json_encode( array_keys( $data ) ) );
check( 'the number is sent as a number', is_int( $data['views'] ), gettype( $data['views'] ) );
check( 'the count went through the analytics layer, not through meta', array( 7 ) === $GLOBALS['increments'] && ! $GLOBALS['meta_writes'], json_encode( $GLOBALS['meta_writes'] ) );
check( 'and the visitor was checked for a duplicate first', array( 7 ) === $GLOBALS['dedup_checks'], json_encode( $GLOBALS['dedup_checks'] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7 ) );
$GLOBALS['already_viewed'] = true;

$sent = mt_request( 'majestic_tube_set_post_views' );
$data = $sent[0]['data'];

check( 'a repeat view is not counted again', false === $data['counted'], mt_show( $sent[0] ) );
check( 'but the visitor is still given the current number', 10 === $data['views'], mt_show( $sent[0] ) );
check( 'and the counter is left alone', ! $GLOBALS['increments'], json_encode( $GLOBALS['increments'] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7 ) );
$GLOBALS['can']['edit_posts'] = true;

$sent = mt_request( 'majestic_tube_set_post_views' );
$data = $sent[0]['data'];

check( 'an editor sees the count without adding to it', 10 === $data['views'] && false === $data['counted'], mt_show( $sent[0] ) );
check(
	'and is never recorded as this video\'s latest viewer',
	! $GLOBALS['dedup_checks'] && ! $GLOBALS['increments'],
	'the editor branch is checked before the de-duplication write'
);

/* -------------------------------------------------------------------------
 * Voting
 * ---------------------------------------------------------------------- */

echo "\nVoting\n";

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7, 'post_like' => 'like' ) );

$sent = mt_request( 'majestic_tube_post_like' );
$data = $sent[0]['data'];

check( 'a like raises the like counter', 4 === $data['likes'] && 1 === $data['dislikes'], mt_show( $sent[0] ) );
check( 'and the total with it', 5 === $data['nbrates'], mt_show( $sent[0] ) );
check( 'the percentage is the live ratio', 80 === $data['percentage'] && 80 === $data['progressbar'], mt_show( $sent[0] ) );
check( 'the vote is not presented as a repeat', false === $data['alreadyrate'], mt_show( $sent[0] ) );
check( 'and the button carries the thank-you text main.js prints', 'Thank you!' === $data['button'], mt_show( $sent[0] ) );
check( 'the new counts land on the original meta keys', array( array( 7, 'likes_count', 4 ), array( 7, 'dislikes_count', 1 ) ) === array_slice( $GLOBALS['meta_writes'], 0, 2 ), json_encode( $GLOBALS['meta_writes'] ) );
check(
	'and the stored rate is the same number as the response',
	3 === count( $GLOBALS['meta_writes'] ) && array( 7, 'rate', 80 ) === $GLOBALS['meta_writes'][2],
	json_encode( $GLOBALS['meta_writes'] )
);
check( 'the rating is asked of the one shared helper', array( 7 ) === $GLOBALS['rate_calls'], json_encode( $GLOBALS['rate_calls'] ) );
check( 'the cached counters are settled for the rest of the request', array( array( 7, array( 'likes' => 4, 'dislikes' => 1, 'rate' => 80 ) ) ) === $GLOBALS['analytics_sets'], json_encode( $GLOBALS['analytics_sets'] ) );
check( 'the visitor is remembered so a second vote is refused', array( array( 7, 'voted_IP' ) ) === $GLOBALS['ip_history'], json_encode( $GLOBALS['ip_history'] ) );
check(
	'the check before the vote consults the same window the vote writes',
	array( array( 7, 'voted_IP' ) ) === $GLOBALS['ip_checks'] && $GLOBALS['ip_checks'] === $GLOBALS['ip_history'],
	json_encode( $GLOBALS['ip_checks'] )
);
check( 'the site-wide "this site has likes" answer is dropped', in_array( 'majestic_tube_site_records_likes', $GLOBALS['transient_del'], true ), json_encode( $GLOBALS['transient_del'] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7, 'post_like' => 'dislike' ) );

$sent = mt_request( 'majestic_tube_post_like' );
$data = $sent[0]['data'];

check( 'a dislike raises the other counter', 3 === $data['likes'] && 2 === $data['dislikes'], mt_show( $sent[0] ) );
check( 'the ratio is rounded, not rounded up', 60 === $data['percentage'] && 60 === $GLOBALS['meta'][7]['rate'], mt_show( $sent[0] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 11, 'post_like' => 'dislike' ) );

$sent = mt_request( 'majestic_tube_post_like' );

check(
	'one vote out of three is reported as 33%, the way every display surface rounds it',
	33 === $sent[0]['data']['percentage'] && 33 === $GLOBALS['meta'][11]['rate'],
	'ceil() would report 34'
);

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7, 'post_like' => 'LIKE' ) );

$sent = mt_request( 'majestic_tube_post_like' );

check( 'the vote word is lowercased, so LIKE votes like', 4 === $sent[0]['data']['likes'], mt_show( $sent[0] ) );

foreach ( array( '', 'up', 'likedislike', 'l1ke' ) as $word ) {
	mt_world();
	mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7, 'post_like' => $word ) );

	$sent = mt_request( 'majestic_tube_post_like' );

	check(
		'a vote of ' . var_export( $word, true ) . ' is refused',
		1 === count( $sent ) && 'Invalid request.' === $sent[0]['data']['message'] && 400 === $sent[0]['status'],
		mt_show( $sent[0] )
	);
	check( 'and nothing is written for it', ! $GLOBALS['meta_writes'] && ! $GLOBALS['analytics_sets'] && ! $GLOBALS['ip_history'] );
}

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7, 'post_like' => array( 'like' ) ) );

$sent = mt_request( 'majestic_tube_post_like' );

check( 'a vote word that arrives as an array is refused', 'Invalid request.' === $sent[0]['data']['message'], mt_show( $sent[0] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7 ) );

$sent = mt_request( 'majestic_tube_post_like' );

check( 'a request with no vote word is refused before the post is read', 'Missing parameters.' === $sent[0]['data']['message'] && ! $GLOBALS['post_reads'], mt_show( $sent[0] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7, 'post_like' => 'like' ) );
$GLOBALS['voted'] = true;

$sent = mt_request( 'majestic_tube_post_like' );
$data = $sent[0]['data'];

check( 'a second vote inside the window is not counted', 3 === $data['likes'] && 1 === $data['dislikes'], mt_show( $sent[0] ) );
check( 'the visitor is told they already voted', true === $data['alreadyrate'], mt_show( $sent[0] ) );
check( 'with no button text, so main.js prints its own thank-you', '' === $data['button'], mt_show( $sent[0] ) );
check(
	'and nothing at all is written',
	! $GLOBALS['meta_writes'] && ! $GLOBALS['meta_deleted'] && ! $GLOBALS['analytics_sets'] && ! $GLOBALS['ip_history'],
	json_encode( array( $GLOBALS['meta_writes'], $GLOBALS['analytics_sets'] ) )
);

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7, 'post_like' => 'like' ) );
$GLOBALS['can']['edit_posts'] = true;

$sent = mt_request( 'majestic_tube_post_like' );

check( 'an editor cannot vote on content they may edit', 3 === $sent[0]['data']['likes'] && true === $sent[0]['data']['alreadyrate'], mt_show( $sent[0] ) );
check( 'and the vote window is not even consulted', ! $GLOBALS['ip_checks'] && ! $GLOBALS['ip_history'], json_encode( $GLOBALS['ip_checks'] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7, 'post_like' => 'like' ) );

$sent = mt_request( 'majestic_tube_post_like' );

check( 'the vote response is flat too, like the original endpoint', 'wp_send_json' === $sent[0]['sent_by'] && ! array_key_exists( 'success', $sent[0]['data'] ), mt_show( $sent[0] ) );

/* -------------------------------------------------------------------------
 * The like payload on its own
 * ---------------------------------------------------------------------- */

echo "\nThe like payload on its own\n";

mt_world();

// Cleared because the requests above asked the helper for a rating, and this
// check is about whether THIS call asks for one.
$GLOBALS['rate_calls'] = array();

$payload = majestic_tube_post_like_payload( 7, array( 'likes' => 3, 'dislikes' => 1 ), false, 'Thanks', 42 );

check( 'the helper returns the seven original keys, in one flat array', array( 'alreadyrate', 'percentage', 'button', 'nbrates', 'likes', 'dislikes', 'progressbar' ) === array_keys( $payload ), json_encode( array_keys( $payload ) ) );
check( 'the counts come from the stats it was handed', 3 === $payload['likes'] && 1 === $payload['dislikes'] && 4 === $payload['nbrates'], json_encode( $payload ) );
check( 'a percentage the caller calculated is used as given', 42 === $payload['percentage'] && 42 === $payload['progressbar'], json_encode( $payload ) );
check( 'and the live ratio is not asked for behind it', ! $GLOBALS['rate_calls'], json_encode( $GLOBALS['rate_calls'] ) );

$GLOBALS['rate_calls'] = array();

$computed = majestic_tube_post_like_payload( 11, array( 'likes' => 3, 'dislikes' => 1 ), true );

check( 'a null percentage is filled from the shared rating helper', 50 === $computed['percentage'] && array( 11 ) === $GLOBALS['rate_calls'], json_encode( $GLOBALS['rate_calls'] ) );
check( 'the flag is a boolean, since main.js branches on it', true === $computed['alreadyrate'], var_export( $computed['alreadyrate'], true ) );
check( 'and the button defaults to empty text', '' === $computed['button'], var_export( $computed['button'], true ) );

/* -------------------------------------------------------------------------
 * Refreshing the stats
 * ---------------------------------------------------------------------- */

echo "\nRefreshing the stats\n";

mt_world();
$GLOBALS['meta'][7]['rate'] = 0; // A stale stored rating, as an imported post carries.
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 7 ) );

$sent = mt_request( 'majestic_tube_get_post_data' );
$data = $sent[0]['data'];

check( 'the refresh carries every counter applyStats() paints', array( 'views', 'likes', 'dislikes', 'total', 'rate', 'progressbar' ) === array_keys( $data ), json_encode( array_keys( $data ) ) );
check( 'views and likes are numbers', 10 === $data['views'] && 3 === $data['likes'], mt_show( $sent[0] ) );
check( 'the total is the two vote counters together', 4 === $data['total'], mt_show( $sent[0] ) );
check(
	'the rate is the live ratio rather than the stored one',
	75 === $data['rate'] && 75 === $data['progressbar'],
	'the stored rate meta is 0; echoing it would repaint a correct badge as 0%'
);
check( 'the counters are read through the analytics layer', array( 7 ) === $GLOBALS['analytics_gets'], json_encode( $GLOBALS['analytics_gets'] ) );
check( 'and not by reading counter meta here', ! $GLOBALS['meta_reads'], json_encode( $GLOBALS['meta_reads'] ) );
check( 'a refresh changes nothing', ! $GLOBALS['meta_writes'] && ! $GLOBALS['meta_deleted'] && ! $GLOBALS['analytics_sets'], json_encode( $GLOBALS['meta_writes'] ) );
check( 'and does not count a view of its own', ! $GLOBALS['increments'] && ! $GLOBALS['dedup_checks'], json_encode( $GLOBALS['increments'] ) );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 8 ) );

$sent = mt_request( 'majestic_tube_get_post_data' );

check( 'a refresh for a post the front end does not show is refused', 'Invalid post.' === $sent[0]['data']['message'], mt_show( $sent[0] ) );
check( 'with nothing read for it', ! $GLOBALS['analytics_gets'] );

mt_world();
mt_reset( array( 'nonce' => 'a1b2c3d4e5', 'post_id' => 999 ) );

$sent = mt_request( 'majestic_tube_get_post_data' );

check( 'and so is one for a post that does not exist', 'Invalid post.' === $sent[0]['data']['message'], mt_show( $sent[0] ) );

/* -------------------------------------------------------------------------
 * The suite's own footing
 * ---------------------------------------------------------------------- */

echo "\nThe suite's own footing\n";

check( 'no request died with an uncaught PHP error', ! $GLOBALS['errors'], implode( '; ', $GLOBALS['errors'] ) );
check( 'every handler that answers a request answered it', 0 === $GLOBALS['unanswered'], $GLOBALS['unanswered'] . ' of ' . $GLOBALS['requests'] . ' handlers returned without sending anything' );

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $GLOBALS['passed'], $GLOBALS['failed'] );

if ( $GLOBALS['failed'] ) {
	exit( 1 );
}

echo "All checks passed.\n";
