<?php
/**
 * Smoke test for video reporting: the front-end flag endpoint and button, and
 * the moderation screens the reports feed.
 *
 * Four claims worth pinning, because each is one careless edit away from being
 * untrue:
 *
 *   1. one report is one report. The counter moves by exactly one per filing,
 *      it is read and settled through analytics.php rather than by calling
 *      get_post_meta() from this module, and the aggregate caches the admin
 *      screens read are dropped in both layers the moment it moves - a report
 *      filed a second ago must never be hidden behind a cached total;
 *   2. the visitor is remembered. The de-duplication that keeps a returning
 *      visitor from filing the same report twenty times rides on the reports
 *      meta key, not the vote one, and the endpoint answers the repeat with a
 *      success carrying the current count instead of writing a second report;
 *   3. nothing changes state before the request has been checked. A report
 *      without a nonce, against a post the front end would never show, or with
 *      reporting switched off writes nothing at all, and the clear-reports
 *      action refuses a forged nonce before it ever asks what the current user
 *      is allowed to do;
 *   4. the screens can be trusted with what is stored. A report carries visitor
 *      text, so a title or a message reaches the moderation table and the
 *      dashboard widget escaped, and a row whose post was deleted in the
 *      window its ID list is cached for is skipped rather than fatal.
 *
 * inc/reports.php is included for real. The pieces it leans on - inc/analytics.php,
 * inc/ajax.php, the option and icon helpers, the meta, transient and object
 * cache layers - are stubs whose contracts mirror the shipping ones, so what is
 * pinned here is this module's half of every call. Two boundaries are stated
 * rather than tested:
 *
 *   - the successful branch of majestic_tube_handle_clear_reports() ends in
 *     wp_safe_redirect() and exit, which cannot run in this process. Its guards
 *     are pinned here, and the state change it performs is pinned through
 *     majestic_tube_clear_reports(), the function it calls once those guards
 *     pass;
 *   - mb_substr() is declared below only when the PHP running the suite has no
 *     mbstring. The shipping module calls it unguarded, so the fallback is what
 *     keeps this file runnable there, not a suggestion that the call is safe.
 *
 * Run:  php tests/reports-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

// The time constants the module and these stubs count in.
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'DATE_W3C' ) || define( 'DATE_W3C', 'Y-m-d\TH:i:sP' );

/*
 * The group the report aggregates are cached in. inc/analytics.php defines it in
 * the shipping theme; that file is not included here, so the name it defines is
 * declared with the same value.
 */
const MAJESTIC_TUBE_ANALYTICS_GROUP = 'majestic_tube_analytics';

/* -------------------------------------------------------------------------
 * Fixture state
 * ---------------------------------------------------------------------- */

$GLOBALS['posts']              = array();  // Post fixtures, by ID.
$GLOBALS['meta']               = array();  // post_id => meta_key => value.
$GLOBALS['meta_reads']         = array();  // Every keyed get_post_meta() read.
$GLOBALS['meta_writes']        = array();  // Every update_post_meta() write.
$GLOBALS['caps']               = array();  // Capabilities current_user_can() grants.
$GLOBALS['caps_asked']         = array();  // Every capability asked for, in order.
$GLOBALS['calls']              = array();  // Named call log.
$GLOBALS['transients']         = array();
$GLOBALS['transient_ttls']     = array();
$GLOBALS['ocache']             = array();  // group => key => value.
$GLOBALS['acache']             = array();  // post_id => analytics bundle.
$GLOBALS['reported_ids']       = array();  // What get_posts() answers with.
$GLOBALS['found_posts']        = 0;        // What WP_Query reports.
$GLOBALS['posts_queries']      = array();
$GLOBALS['wp_queries']         = array();
$GLOBALS['ip_history_writes']  = array();
$GLOBALS['analytics_keys']     = array();  // Meta keys the IP history was asked about.
$GLOBALS['json']               = null;
$GLOBALS['died']               = null;
$GLOBALS['redirect']           = null;
$GLOBALS['fired']              = array();
$GLOBALS['submenus']           = array();
$GLOBALS['dashboard_widgets']  = array();
$GLOBALS['hooks']              = array();
$GLOBALS['queried_id']         = 0;
$GLOBALS['report_enabled']     = true;
$GLOBALS['ip']                 = '203.0.113.7';
$GLOBALS['domains']            = array();
$_POST                         = array();
$_GET                          = array();

/* -------------------------------------------------------------------------
 * WordPress stubs
 * ---------------------------------------------------------------------- */

/**
 * Record a call and what it was asked for.
 *
 * @param string $name Call name.
 * @param mixed  $args Optional argument.
 * @return void
 */
function mt_call( $name, $args = null ) {
	$GLOBALS['calls'][] = array( $name, $args );
}

/**
 * Whether a named call was made.
 *
 * @param string $name Call name.
 * @return bool
 */
function mt_called( $name ) {
	foreach ( $GLOBALS['calls'] as $call ) {
		if ( $name === $call[0] ) {
			return true;
		}
	}

	return false;
}

/**
 * Every argument a named call was made with.
 *
 * @param string $name Call name.
 * @return array
 */
function mt_call_args( $name ) {
	$found = array();

	foreach ( $GLOBALS['calls'] as $call ) {
		if ( $name === $call[0] ) {
			$found[] = $call[1];
		}
	}

	return $found;
}

/**
 * How many calls were made before a named one, or -1 when it was never made.
 *
 * Used to pin the order two checks happen in rather than only that both did.
 *
 * @param string $name Call name.
 * @return int
 */
function mt_call_index( $name ) {
	foreach ( $GLOBALS['calls'] as $index => $call ) {
		if ( $name === $call[0] ) {
			return (int) $index;
		}
	}

	return -1;
}

/**
 * Register a post fixture.
 *
 * @param int    $post_id Post ID.
 * @param string $title   Post title.
 * @param string $status  Post status.
 * @return int
 */
function mt_post( $post_id, $title, $status = 'publish' ) {
	$post               = new stdClass();
	$post->ID           = (int) $post_id;
	$post->post_title   = $title;
	$post->post_status  = $status;

	$GLOBALS['posts'][ (int) $post_id ] = $post;

	return (int) $post_id;
}

/**
 * Read one stored meta value, or null when the row is absent.
 *
 * Unlike get_post_meta() this records nothing, which is how the analytics stubs
 * read the store without every one of their reads looking like a read made by
 * the module under test.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return mixed
 */
function mt_meta_value( $post_id, $key ) {
	return isset( $GLOBALS['meta'][ (int) $post_id ][ $key ] ) ? $GLOBALS['meta'][ (int) $post_id ][ $key ] : null;
}

/**
 * Seed one stored meta value, and drop the cached counter bundle for the post.
 *
 * A real write invalidates that cache through the meta hooks in
 * inc/analytics.php, so a fixture that skipped it would read as stale.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 * @return void
 */
function mt_meta( $post_id, $key, $value ) {
	$GLOBALS['meta'][ (int) $post_id ][ $key ] = $value;

	unset( $GLOBALS['acache'][ (int) $post_id ] );
}

/**
 * Read one integer counter out of the fixture store.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return int
 */
function mt_meta_int( $post_id, $key ) {
	$value = mt_meta_value( $post_id, $key );

	return null === $value ? 0 : (int) $value;
}

/**
 * The visitor fingerprint the IP-history stubs key their rows with.
 *
 * @return string
 */
function mt_fingerprint() {
	return '' === $GLOBALS['ip'] ? '' : md5( $GLOBALS['ip'] . '|mt-test-salt' );
}

/**
 * Reset every piece of mutable state a section needs to start from zero.
 *
 * @return void
 */
function mt_reset_runtime() {
	$GLOBALS['posts']             = array();
	$GLOBALS['meta']              = array();
	$GLOBALS['meta_reads']        = array();
	$GLOBALS['meta_writes']       = array();
	$GLOBALS['caps']              = array();
	$GLOBALS['caps_asked']        = array();
	$GLOBALS['calls']             = array();
	$GLOBALS['transients']        = array();
	$GLOBALS['transient_ttls']    = array();
	$GLOBALS['ocache']            = array();
	$GLOBALS['acache']            = array();
	$GLOBALS['reported_ids']      = array();
	$GLOBALS['found_posts']       = 0;
	$GLOBALS['posts_queries']     = array();
	$GLOBALS['wp_queries']        = array();
	$GLOBALS['ip_history_writes'] = array();
	$GLOBALS['analytics_keys']    = array();
	$GLOBALS['json']              = null;
	$GLOBALS['died']              = null;
	$GLOBALS['redirect']          = null;
	$GLOBALS['fired']             = array();
	$GLOBALS['submenus']          = array();
	$GLOBALS['dashboard_widgets'] = array();
	$GLOBALS['queried_id']        = 0;
	$GLOBALS['report_enabled']    = true;
	$_POST                        = array();
	$_GET                         = array();

	return;
}

/**
 * Capture what a callback prints.
 *
 * @param callable $callback Callback to run.
 * @return string
 */
function capture( $callback ) {
	ob_start();
	call_user_func( $callback );

	return (string) ob_get_clean();
}

/**
 * Run something that ends its own request the way WordPress does.
 *
 * wp_send_json_*() and wp_die() both stop the response; the stubs below stop it
 * here by throwing, so a handler that would have ended is not continued into
 * code the real request never reaches.
 *
 * @param callable $callback Callback to run.
 * @return void
 */
function run_endpoint( $callback ) {
	try {
		call_user_func( $callback );
	} catch ( RuntimeException $e ) {
		// The request ended; the stub has already recorded how.
	}

	return;
}

/* -- Escaping, translation and formatting --------------------------------- */

/**
 * Escape for an HTML context, the way core's esc_* do it.
 *
 * The last argument is false because _wp_specialchars() runs with
 * $double_encode off: escaping an already-escaped value a second time leaves
 * entities alone rather than turning them into &amp; copies of themselves.
 *
 * @param string $text Raw text.
 * @return string
 */
function mt_escape( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
}

function __( $text, $domain = null ) {
	$GLOBALS['domains'][] = $domain;

	return $text;
}

function _n( $single, $plural, $number, $domain = null ) {
	$GLOBALS['domains'][] = $domain;

	return 1 === (int) $number ? $single : $plural;
}

function esc_html__( $text, $domain = null ) {
	return mt_escape( __( $text, $domain ) );
}

function esc_html_e( $text, $domain = null ) {
	echo mt_escape( __( $text, $domain ) );
}

function esc_html( $text ) {
	return mt_escape( $text );
}

function esc_attr( $text ) {
	return mt_escape( $text );
}

function esc_url( $url ) {
	return mt_escape( $url );
}

function esc_attr_e( $text, $domain = null ) {
	echo mt_escape( __( $text, $domain ) );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_text_field( $text ) {
	return trim( (string) $text );
}

function sanitize_textarea_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}

/**
 * wp_trim_words(), tag-stripping included.
 *
 * Core strips every tag before it counts anything (wp_strip_all_tags()), and
 * the moderation screen leans on that half of the call: a report message is
 * stored as the visitor typed it, so whatever markup it carries has to be gone
 * before the line is printed beside esc_html(). A stub that only counted words
 * left the tags in the text.
 *
 * @param string $text      Text to trim.
 * @param int    $num_words Word limit.
 * @return string
 */
function wp_trim_words( $text, $num_words = 55, $more = '&hellip;' ) {
	$text  = trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text ) ) );
	$words = preg_split( '/\s+/', $text );

	if ( ! is_array( $words ) || count( $words ) <= $num_words ) {
		return $text;
	}

	return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
}

function apply_filters( $hook, $value ) {
	return $value;
}

if ( ! function_exists( 'mb_substr' ) ) {
	/**
	 * Byte-based stand-in, so the suite runs on a PHP built without mbstring.
	 *
	 * @param string   $string Subject.
	 * @param int      $start  Offset.
	 * @param int|null $length Length.
	 * @return string
	 */
	function mb_substr( $string, $start, $length = null ) {
		return null === $length ? substr( (string) $string, $start ) : substr( (string) $string, $start, $length );
	}
}

/* -- Options and users ---------------------------------------------------- */

function get_option( $key, $default = false ) {
	$options = array(
		'date_format' => 'F j, Y',
		'time_format' => 'g:i a',
	);

	return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

/**
 * Whether reporting is switched on, read the way the theme reads it.
 *
 * @param string $option Option key.
 * @return bool
 */
function majestic_tube_option_is_on( $option ) {
	mt_call( 'option_is_on', $option );

	return (bool) $GLOBALS['report_enabled'];
}

/**
 * The front-end icon helper.
 *
 * @param string $name        Icon name.
 * @param string $extra_class Extra class.
 * @return void
 */
function majestic_tube_icon( $name, $extra_class = '' ) {
	echo '[icon:' . $name . ']';
}

function current_user_can( $capability, $object_id = null ) {
	$GLOBALS['caps_asked'][] = $capability;

	return in_array( $capability, $GLOBALS['caps'], true );
}

/* -- Posts ---------------------------------------------------------------- */

function get_post( $post_id ) {
	return isset( $GLOBALS['posts'][ (int) $post_id ] ) ? $GLOBALS['posts'][ (int) $post_id ] : null;
}

function get_the_ID() {
	return (int) $GLOBALS['queried_id'];
}

function get_the_title( $post_id = 0 ) {
	$post = get_post( $post_id );

	return $post ? $post->post_title : '';
}

function get_permalink( $post_id = 0 ) {
	$post = get_post( $post_id );

	return $post ? 'https://example.test/watch/' . $post->ID . '/' : false;
}

function get_edit_post_link( $post_id = 0 ) {
	return 'https://example.test/wp-admin/post.php?post=' . (int) $post_id . '&action=edit';
}

/**
 * Whether a post is one the front end would show.
 *
 * @param object $post Post object.
 * @return bool
 */
function is_post_publicly_viewable( $post ) {
	return $post && isset( $post->post_status ) && 'publish' === $post->post_status;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	$store = isset( $GLOBALS['meta'][ (int) $post_id ] ) ? $GLOBALS['meta'][ (int) $post_id ] : array();

	if ( '' === $key ) {
		$all = array();

		foreach ( $store as $meta_key => $value ) {
			$all[ $meta_key ] = array( $value );
		}

		return $all;
	}

	$GLOBALS['meta_reads'][] = $key;

	if ( ! array_key_exists( $key, $store ) ) {
		// Core answers with an empty string for a missing single value.
		return $single ? '' : array();
	}

	return $single ? $store[ $key ] : array( $store[ $key ] );
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['meta_writes'][] = $key;
	$GLOBALS['meta'][ (int) $post_id ][ $key ] = $value;
	unset( $GLOBALS['acache'][ (int) $post_id ] );

	return true;
}

function delete_post_meta( $post_id, $key ) {
	$GLOBALS['meta_writes'][] = 'delete:' . $key;
	unset( $GLOBALS['meta'][ (int) $post_id ][ $key ] );
	unset( $GLOBALS['acache'][ (int) $post_id ] );

	return true;
}

function get_posts( $args = array() ) {
	mt_call( 'get_posts', $args );
	$GLOBALS['posts_queries'][] = $args;

	$ids = $GLOBALS['reported_ids'];

	if ( isset( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ) {
		$ids = array_slice( $ids, 0, (int) $args['posts_per_page'] );
	}

	return $ids;
}

/**
 * The one shape this module asks of WP_Query: the count of a filtered set.
 */
class WP_Query {
	/**
	 * Found rows.
	 *
	 * @var int
	 */
	public $found_posts = 0;

	/**
	 * Query arguments.
	 *
	 * @var array
	 */
	public $args = array();

	/**
	 * Record the query and answer the count.
	 *
	 * @param array $args Query arguments.
	 */
	public function __construct( $args = array() ) {
		$this->args                 = $args;
		$GLOBALS['wp_queries'][]    = $args;
		$this->found_posts          = (int) $GLOBALS['found_posts'];
	}

	/**
	 * Whether the query answered anything.
	 *
	 * @return bool
	 */
	public function have_posts() {
		return $this->found_posts > 0;
	}
}

/* -- Caches --------------------------------------------------------------- */

function get_transient( $key ) {
	mt_call( 'get_transient', $key );

	return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false;
}

function set_transient( $key, $value, $ttl = 0 ) {
	mt_call( 'set_transient', $key );
	$GLOBALS['transient_ttls'][ $key ] = (int) $ttl;
	$GLOBALS['transients'][ $key ]     = $value;

	return true;
}

function delete_transient( $key ) {
	mt_call( 'delete_transient', $key );
	unset( $GLOBALS['transients'][ $key ] );

	return true;
}

function wp_cache_get( $key, $group = '' ) {
	mt_call( 'wp_cache_get', $key );

	return isset( $GLOBALS['ocache'][ $group ][ $key ] ) ? $GLOBALS['ocache'][ $group ][ $key ] : false;
}

function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
	mt_call( 'wp_cache_set', $key );
	$GLOBALS['ocache'][ $group ][ $key ] = $value;

	return true;
}

function wp_cache_delete( $key, $group = '' ) {
	mt_call( 'wp_cache_delete', $key );
	unset( $GLOBALS['ocache'][ $group ][ $key ] );

	return true;
}

/* -- Requests, hooks and screens ------------------------------------------ */

/**
 * Verify a nonce, as core does.
 *
 * The contract the suite drives is exact: a nonce is valid when it is the one
 * wp_create_nonce() would have produced for that action, which this spells as
 * `nonce-<md5 of the action>`.
 *
 * @param string $nonce  Nonce value.
 * @param string $action Action name.
 * @return bool
 */
function wp_verify_nonce( $nonce, $action = -1 ) {
	return (string) $nonce === 'nonce-' . md5( (string) $action );
}

/**
 * The nonce a valid request carries for an action.
 *
 * @param string $action Action name.
 * @return string
 */
function mt_nonce( $action ) {
	return 'nonce-' . md5( $action );
}

/**
 * add_query_arg(), in both shapes WordPress accepts.
 *
 * Core takes `add_query_arg( $key, $value, $url )` and `add_query_arg( $args,
 * $url )`. wp_nonce_url() calls the three argument form; reading those
 * arguments through the array shape put the nonce where the URL belonged, so
 * the clear link under test pointed at the nonce and carried nothing else.
 *
 * @param string|array $key   Query key, or a map of them.
 * @param mixed        $value Value for the key, or the URL in the array form.
 * @param string       $url   URL to add to.
 * @return string
 */
function add_query_arg( $key, $value = null, $url = '' ) {
	if ( is_array( $key ) ) {
		$args = $key;
		$url  = is_string( $value ) ? $value : (string) $url;
	} else {
		$args = array( $key => $value );
	}

	$fragment = '';
	$hash     = strpos( (string) $url, '#' );

	if ( false !== $hash ) {
		$fragment = substr( (string) $url, $hash );
		$url      = substr( (string) $url, 0, $hash );
	}

	$query = array();

	foreach ( $args as $key => $value ) {
		$query[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}

	$separator = false === strpos( (string) $url, '?' ) ? '?' : '&';

	return $url . ( $query ? $separator . implode( '&', $query ) : '' ) . $fragment;
}

function wp_nonce_url( $url, $action = -1 ) {
	$url = str_replace( '&amp;', '&', (string) $url );

	return esc_html( add_query_arg( '_wpnonce', mt_nonce( $action ), $url ) );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

function wp_safe_redirect( $url, $status = 302 ) {
	mt_call( 'wp_safe_redirect', $url );
	$GLOBALS['redirect'] = $url;

	return true;
}

function wp_die( $message = '', $title = '', $args = array() ) {
	$GLOBALS['died'] = array(
		'message' => $message,
		'title'   => $title,
		'args'    => $args,
	);

	throw new RuntimeException( 'die' );
}

function wp_send_json_error( $data = null, $status = 200 ) {
	$GLOBALS['json'] = array(
		'type'   => 'error',
		'data'   => $data,
		'status' => $status,
	);

	throw new RuntimeException( 'json' );
}

function wp_send_json_success( $data = null, $status = 200 ) {
	$GLOBALS['json'] = array(
		'type'   => 'success',
		'data'   => $data,
		'status' => $status,
	);

	throw new RuntimeException( 'json' );
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = $hook . '::' . ( is_string( $callback ) ? $callback : 'closure' );
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = $hook . '::' . ( is_string( $callback ) ? $callback : 'closure' );
}

function do_action( $hook ) {
	$args = func_get_args();

	array_shift( $args );

	$GLOBALS['fired'][] = array( $hook, $args );
}

function wp_add_dashboard_widget( $widget_id, $widget_name, $callback ) {
	$GLOBALS['dashboard_widgets'][] = array(
		'id'       => $widget_id,
		'name'     => $widget_name,
		'callback' => $callback,
	);
}

function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
	$GLOBALS['submenus'][] = array(
		'parent'     => $parent_slug,
		'page_title' => $page_title,
		'menu_title' => $menu_title,
		'capability' => $capability,
		'slug'       => $menu_slug,
		'callback'   => $callback,
	);
}

function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}

/* -------------------------------------------------------------------------
 * Stubs of the theme modules this one calls
 * ---------------------------------------------------------------------- */

/*
 * inc/ajax.php. majestic_tube_verify_ajax_nonce() is copied from there so the
 * endpoint's ordering can be driven: ajax.php's own suite covers that module.
 */

function majestic_tube_verify_ajax_nonce() {
	mt_call( 'verify_ajax_nonce' );

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ajax-nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'majestic-tube' ) ), 403 );
	}
}

/**
 * The shared post-id check, mirroring inc/ajax.php.
 *
 * @param string $missing_message Message when post_id is absent.
 * @param string $invalid_message Message when the post does not exist or is not public.
 * @param int    $status          HTTP status for the JSON error.
 * @return int
 */
function majestic_tube_require_ajax_post_id( $missing_message, $invalid_message, $status = 400 ) {
	mt_call( 'require_ajax_post_id', array( $missing_message, $invalid_message, $status ) );

	if ( ! isset( $_POST['post_id'] ) ) {
		wp_send_json_error( array( 'message' => $missing_message ), $status );
	}

	$post_id = absint( wp_unslash( $_POST['post_id'] ) );
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( ! $post || ! is_post_publicly_viewable( $post ) ) {
		wp_send_json_error( array( 'message' => $invalid_message ), $status );
	}

	return $post_id;
}

/*
 * inc/analytics.php. The counters have one home in the shipping theme; these
 * stubs answer from the fixture store with the same shape and the same
 * memoisation, so a write this module makes and forgets to settle shows up as
 * the stale read it would be.
 */

function majestic_tube_analytics_get( $post_id ) {
	$post_id = absint( $post_id );

	mt_call( 'analytics_get', $post_id );

	if ( isset( $GLOBALS['acache'][ $post_id ] ) ) {
		return $GLOBALS['acache'][ $post_id ];
	}

	$bundle = array(
		'views'    => mt_meta_int( $post_id, 'post_views_count' ),
		'likes'    => mt_meta_int( $post_id, 'likes_count' ),
		'dislikes' => mt_meta_int( $post_id, 'dislikes_count' ),
		'rate'     => mt_meta_int( $post_id, 'rate' ),
		'reported' => mt_meta_int( $post_id, 'reported_count' ),
	);

	$GLOBALS['acache'][ $post_id ] = $bundle;

	return $bundle;
}

function majestic_tube_analytics_get_field( $post_id, $field ) {
	$data = majestic_tube_analytics_get( $post_id );

	return isset( $data[ $field ] ) ? (int) $data[ $field ] : 0;
}

function majestic_tube_analytics_set( $post_id, $data ) {
	$post_id = absint( $post_id );

	mt_call( 'analytics_set', array( $post_id, $data ) );

	if ( ! is_array( $data ) ) {
		return;
	}

	$current = majestic_tube_analytics_get( $post_id );
	$merged  = array_merge( $current, array_intersect_key( $data, $current ) );

	foreach ( $merged as $field => $value ) {
		$merged[ $field ] = (int) $value;
	}

	$GLOBALS['acache'][ $post_id ] = $merged;
}

function majestic_tube_ip_was_recorded_recently( $post_id, $meta_key, $window = 0 ) {
	mt_call( 'ip_recorded', $meta_key );
	$GLOBALS['analytics_keys'][] = $meta_key;

	$fingerprint = mt_fingerprint();

	if ( '' === $fingerprint ) {
		return false;
	}

	$history = mt_meta_value( absint( $post_id ), $meta_key );

	if ( ! is_array( $history ) || ! isset( $history[ $fingerprint ] ) ) {
		return false;
	}

	$window = $window > 0 ? absint( $window ) : DAY_IN_SECONDS;

	return ( time() - (int) $history[ $fingerprint ] ) < $window;
}

function majestic_tube_record_ip_history( $post_id, $meta_key, $window = 0 ) {
	mt_call( 'record_ip_history', $meta_key );
	$GLOBALS['ip_history_writes'][] = $meta_key;

	$post_id     = absint( $post_id );
	$fingerprint = mt_fingerprint();

	if ( ! $post_id || '' === $fingerprint ) {
		return false;
	}

	$stored                 = mt_meta_value( $post_id, $meta_key );
	$history                = is_array( $stored ) ? $stored : array();
	$history[ $fingerprint ] = time();

	update_post_meta( $post_id, $meta_key, $history );

	return true;
}

/*
 * The module under test.
 */
require_once MAJESTIC_TUBE_DIR . '/inc/reports.php';

// Captured before any section resets the log: the wiring the file registers.
$wiring = $GLOBALS['hooks'];

/* -------------------------------------------------------------------------
 * Check harness
 * ---------------------------------------------------------------------- */

$passed = 0;
$failed = 0;

/**
 * Record one assertion.
 *
 * @param string $label   What is being asserted.
 * @param bool   $ok      Whether it held.
 * @param string $context What was seen, printed on failure.
 * @return void
 */
function check( $label, $ok, $context = '' ) {
	global $passed, $failed;

	if ( $ok ) {
		++$passed;
		printf( "  [PASS] %s\n", $label );

		return;
	}

	++$failed;
	printf( "  [FAIL] %s\n", $label );

	if ( '' !== $context ) {
		printf( "         %s\n", str_replace( "\n", "\n         ", substr( (string) $context, 0, 1200 ) ) );
	}
}

/* -------------------------------------------------------------------------
 * What the file wires up
 * ---------------------------------------------------------------------- */

echo "\nThe endpoints and screens this module registers\n";

check( 'the report endpoint answers a signed-in visitor', in_array( 'wp_ajax_report-video::majestic_tube_report_video', $wiring, true ), implode( ', ', $wiring ) );
check( 'and a signed-out one, because reporting is a public action', in_array( 'wp_ajax_nopriv_report-video::majestic_tube_report_video', $wiring, true ) );
check( 'the moderation screen is added to the admin menu', in_array( 'admin_menu::majestic_tube_register_reports_page', $wiring, true ) );
check( 'clearing reports is an admin-post action', in_array( 'admin_post_majestic_tube_clear_reports::majestic_tube_handle_clear_reports', $wiring, true ) );
check( 'the dashboard widget is registered on the dashboard', in_array( 'wp_dashboard_setup::majestic_tube_register_reports_widget', $wiring, true ) );

/* -------------------------------------------------------------------------
 * The reasons on offer
 * ---------------------------------------------------------------------- */

echo "\nThe reasons a visitor can pick\n";

$reasons = majestic_tube_report_reasons();

check( 'four reasons are offered', 4 === count( $reasons ), count( $reasons ) . ' offered' );
check( 'under the slugs the front end posts', array( 'broken', 'wrong', 'spam', 'other' ) === array_keys( $reasons ), implode( ', ', array_keys( $reasons ) ) );
check( 'every label is a non-empty string', count( array_filter( $reasons, 'is_string' ) ) === 4 && '' !== implode( '', $reasons ) );
check( 'and every one is translatable in the theme domain', ! in_array( null, $GLOBALS['domains'], true ) && ! in_array( 'default', $GLOBALS['domains'], true ), implode( ', ', array_map( function ( $d ) { return var_export( $d, true ); }, array_unique( $GLOBALS['domains'] ) ) ) );

/* -------------------------------------------------------------------------
 * The query behind the moderation screens
 * ---------------------------------------------------------------------- */

echo "\nThe shared reported-video query\n";

$args = majestic_tube_reported_post_query_args( 50 );

check( 'it looks at videos', 'post' === $args['post_type'] );
check( 'a report on an unpublished video still counts', array( 'publish', 'draft', 'pending', 'private' ) === $args['post_status'], implode( ', ', (array) ( $args['post_status'] ?? array() ) ) );
check( 'it asks for ids only', 'ids' === $args['fields'] );
check( 'the caller decides how many', 50 === $args['posts_per_page'] );
check( 'a negative count is made positive', 3 === majestic_tube_reported_post_query_args( -3 )['posts_per_page'] );
check( 'found rows follow the caller', true === $args['no_found_rows'] && false === majestic_tube_reported_post_query_args( 1, false )['no_found_rows'] );
check( 'filters are left in place for a site to hook', false === $args['suppress_filters'] );
check( 'the meta filter is one named clause', array( 'reported' ) === array_keys( $args['meta_query'] ), implode( ', ', array_keys( $args['meta_query'] ) ) );

$reported_clause = $args['meta_query']['reported'];

check( 'on the stored report counter', 'reported_count' === $reported_clause['key'] );
check( 'counting a video with at least one report', '>=' === $reported_clause['compare'] && 1 === $reported_clause['value'] );
check( 'compared as a number', 'NUMERIC' === $reported_clause['type'] );
check( 'and no bare meta_key beside the clause, which would join postmeta twice', ! isset( $args['meta_key'] ) );
check( 'no order unless one is asked for', ! isset( $args['orderby'] ) );
check( 'an order is passed through as given', array( 'reported' => 'DESC' ) === majestic_tube_reported_post_query_args( 5, true, array( 'reported' => 'DESC' ) )['orderby'] );

/* -------------------------------------------------------------------------
 * The two aggregate caches
 * ---------------------------------------------------------------------- */

echo "\nThe aggregate caches\n";

mt_reset_runtime();

$GLOBALS['ocache'][ MAJESTIC_TUBE_ANALYTICS_GROUP ]['reported_ids'] = array( 9 );

check( 'the object cache answers first', array( 9 ) === majestic_tube_report_cache_get( 'reported_ids' ) );
check( 'without reading the transient behind it', ! mt_called( 'get_transient' ), implode( ', ', array_map( function ( $c ) { return $c[0]; }, $GLOBALS['calls'] ) ) );

$GLOBALS['transients']['majestic_tube_reported_count'] = 7;

check( 'a transient answers a cold object cache', 7 === majestic_tube_report_cache_get( 'reported_count' ) );
check( 'and is copied into the object cache on the way', 7 === $GLOBALS['ocache'][ MAJESTIC_TUBE_ANALYTICS_GROUP ]['reported_count'] );
check( 'the transient is named under the theme prefix', array_key_exists( 'majestic_tube_reported_count', $GLOBALS['transients'] ) );

mt_reset_runtime();

check( 'a key in neither layer is a miss, not a zero', false === majestic_tube_report_cache_get( 'reported_count' ), var_export( majestic_tube_report_cache_get( 'reported_count' ), true ) );

majestic_tube_report_cache_set( 'reported_count', 3 );

check( 'setting one aggregate writes the durable copy', 3 === $GLOBALS['transients']['majestic_tube_reported_count'] );
check( 'and the object cache in front of it', 3 === $GLOBALS['ocache'][ MAJESTIC_TUBE_ANALYTICS_GROUP ]['reported_count'] );
check( 'with the documented ten-minute life', 10 * MINUTE_IN_SECONDS === $GLOBALS['transient_ttls']['majestic_tube_reported_count'] );
check( 'the two aggregates stay separate keys', false === majestic_tube_report_cache_get( 'reported_ids' ) );

/* -------------------------------------------------------------------------
 * One report
 * ---------------------------------------------------------------------- */

echo "\nFiling one report\n";

mt_reset_runtime();

mt_post( 21, 'A reported clip' );
mt_meta( 21, 'reported_count', 0 );
mt_meta( 21, 'reported_reasons', array( array( 'reason' => 'spam', 'message' => 'older', 'time' => time() - 60 ) ) );

check( 'the counter starts where the meta says', 0 === majestic_tube_get_report_count( 21 ) );
check( 'read through analytics rather than out of meta directly', mt_called( 'analytics_get' ) && ! in_array( 'reported_count', $GLOBALS['meta_reads'], true ), 'meta reads: ' . implode( ', ', $GLOBALS['meta_reads'] ) );

$count = majestic_tube_add_report( 21, 'broken', 'it stops at 0:30' );

check( 'the first report is number one', 1 === $count, 'got ' . var_export( $count, true ) );
check( 'the stored counter moves by exactly one', 1 === (int) $GLOBALS['meta'][21]['reported_count'] );
check( 'and the counter the screens read is settled with it', 1 === majestic_tube_get_report_count( 21 ) );
check( 'the counter is written once', 1 === count( array_keys( $GLOBALS['meta_writes'], 'reported_count', true ) ), implode( ', ', $GLOBALS['meta_writes'] ) );

$writes = mt_call_args( 'analytics_set' );

check( 'analytics is told the new value', isset( $writes[0][1]['reported'] ) && 1 === $writes[0][1]['reported'], var_export( $writes, true ) );

$entries = $GLOBALS['meta'][21]['reported_reasons'];

check( 'the report carries the reason', 'broken' === $entries[0]['reason'] );
check( 'the message as typed', 'it stops at 0:30' === $entries[0]['message'] );
check( 'and the time it was filed', is_int( $entries[0]['time'] ) && abs( time() - $entries[0]['time'] ) < 5 );
check( 'the new entry leads the list', 'spam' === $entries[1]['reason'], var_export( $entries, true ) );
check( 'the visitor is remembered against this video', array( 'reported_ips' ) === $GLOBALS['ip_history_writes'], implode( ', ', $GLOBALS['ip_history_writes'] ) );
check( 'both aggregate caches are dropped', ! isset( $GLOBALS['transients']['majestic_tube_reported_ids'] ) && ! isset( $GLOBALS['ocache'][ MAJESTIC_TUBE_ANALYTICS_GROUP ]['reported_count'] ) );
check( 'and the site is told', isset( $GLOBALS['fired'][0] ) && array( 'majestic_tube_video_reported', 21, 1 ) === array( $GLOBALS['fired'][0][0], $GLOBALS['fired'][0][1][0], $GLOBALS['fired'][0][1][2] ), var_export( $GLOBALS['fired'], true ) );

// A second video, seeded with a full list, to watch the cap and the sanitizing.
mt_post( 24, 'A heavily reported clip' );

$seeded = array();

for ( $i = 0; $i < 50; $i++ ) {
	$seeded[] = array( 'reason' => 'spam', 'message' => 'oldest ' . $i, 'time' => time() - 3600 - $i );
}

mt_meta( 24, 'reported_count', 4 );
mt_meta( 24, 'reported_reasons', $seeded );

$count = majestic_tube_add_report( 24, 'BrOken !!', '<script>alert(1)</script>hi' );

check( 'the counter continues from where it was', 5 === $count, 'got ' . var_export( $count, true ) );

$entries = $GLOBALS['meta'][24]['reported_reasons'];

check( 'the stored list is capped at fifty', 50 === count( $entries ), count( $entries ) . ' entries' );
check( 'the newest report is the one that survives at the front', 'broken' === $entries[0]['reason'] );
check( 'the oldest entry is the one that falls off', ! in_array( 'oldest 49', array_column( $entries, 'message' ), true ) );
check( 'and the report reason is reduced to a slug before it is stored', preg_match( '/^[a-z0-9_\-]*$/', $entries[0]['reason'] ), $entries[0]['reason'] );
check( 'a reason that is not a slug cannot smuggle markup in', false === strpos( $entries[0]['reason'], '<' ), $entries[0]['reason'] );
check( 'the message is stored without tags', false === strpos( $entries[0]['message'], '<' ), $entries[0]['message'] );

mt_reset_runtime();
mt_post( 25, 'A video with unreadable stored data' );
mt_meta( 25, 'reported_reasons', 'not an array' );

check( 'a meta row that is not a list does not fatal', 1 === majestic_tube_add_report( 25, 'other', '' ) );
check( 'and the report is stored as the first entry', is_array( $GLOBALS['meta'][25]['reported_reasons'] ) && 'other' === $GLOBALS['meta'][25]['reported_reasons'][0]['reason'] );

mt_reset_runtime();
mt_post( 26, 'A video with no report meta at all' );

$last = majestic_tube_get_last_report( 26 );

check( 'a video nobody reported has no last report', false === $last, var_export( $last, true ) );

majestic_tube_add_report( 26, 'wrong', '' );

$last = majestic_tube_get_last_report( 26 );

check( 'and the last report is the newest entry', is_array( $last ) && 'wrong' === $last['reason'] );
check( 'with the message it was filed with', isset( $last['message'] ) && '' === $last['message'] );

/* -------------------------------------------------------------------------
 * The duplicate guard
 * ---------------------------------------------------------------------- */

echo "\nThe visitor cannot report twice\n";

mt_reset_runtime();
mt_post( 31, 'A clip' );
mt_meta( 31, 'reported_count', 0 );

check( 'a first-time visitor is not yet recorded', false === majestic_tube_has_already_reported( 31 ) );
check( 'and the reports key is the one asked about, not the vote key', array( 'reported_ips' ) === $GLOBALS['analytics_keys'], implode( ', ', $GLOBALS['analytics_keys'] ) );

// Through the endpoint, twice, with the history written for real between them.
$_POST = array(
	'nonce'   => mt_nonce( 'ajax-nonce' ),
	'post_id' => '31',
	'reason'  => 'spam',
	'message' => 'first',
);

run_endpoint( 'majestic_tube_report_video' );

check( 'the first report is accepted', isset( $GLOBALS['json']['type'] ) && 'success' === $GLOBALS['json']['type'], var_export( $GLOBALS['json'], true ) );
check( 'and answered with a fresh, not-already-reported payload', false === $GLOBALS['json']['data']['alreadyreported'] );
check( 'carrying the new count', 1 === $GLOBALS['json']['data']['count'] );
check( 'the visitor is now on the video history', true === majestic_tube_has_already_reported( 31 ) );

$settled = count( mt_call_args( 'analytics_set' ) );
$written = count( $GLOBALS['ip_history_writes'] );

$_POST['message'] = 'second';

run_endpoint( 'majestic_tube_report_video' );

check( 'the second attempt is answered, not refused', isset( $GLOBALS['json']['type'] ) && 'success' === $GLOBALS['json']['type'], var_export( $GLOBALS['json'], true ) );
check( 'flagged as already reported', true === $GLOBALS['json']['data']['alreadyreported'] );
check( 'the count stays where it was', 1 === $GLOBALS['json']['data']['count'] );
check( 'the counter was not incremented again', 1 === (int) $GLOBALS['meta'][31]['reported_count'] );
check( 'no second entry was stored', 1 === count( $GLOBALS['meta'][31]['reported_reasons'] ), var_export( $GLOBALS['meta'][31]['reported_reasons'], true ) );
check(
	'and nothing was written the second time: no counter settled, no history recorded',
	$settled === count( mt_call_args( 'analytics_set' ) ) && $written === count( $GLOBALS['ip_history_writes'] ),
	count( mt_call_args( 'analytics_set' ) ) . ' analytics writes, ' . count( $GLOBALS['ip_history_writes'] ) . ' history writes'
);

/* -------------------------------------------------------------------------
 * What the endpoint refuses
 * ---------------------------------------------------------------------- */

echo "\nWhat the report endpoint refuses\n";

mt_reset_runtime();
mt_post( 41, 'A published clip' );
mt_meta( 41, 'reported_count', 0 );

$_POST = array( 'post_id' => '41', 'reason' => 'broken' );
$GLOBALS['json'] = null;

run_endpoint( 'majestic_tube_report_video' );

check( 'a report with no nonce is refused', isset( $GLOBALS['json']['type'] ) && 'error' === $GLOBALS['json']['type'], var_export( $GLOBALS['json'], true ) );
check( 'with a security failure status', 403 === $GLOBALS['json']['status'], var_export( $GLOBALS['json'], true ) );
check( 'the request stops there', ! mt_called( 'require_ajax_post_id' ) );
check( 'and nothing was written', ! isset( $GLOBALS['meta'][41]['reported_reasons'] ) && 0 === (int) $GLOBALS['meta'][41]['reported_count'] );
check( 'no report counter was settled either', ! mt_called( 'analytics_set' ) );

mt_reset_runtime();
mt_post( 42, 'A published clip' );
mt_meta( 42, 'reported_count', 0 );

$_POST = array(
	'nonce'   => mt_nonce( 'ajax-nonce' ),
	'post_id' => '42',
	'reason'  => 'broken',
);

run_endpoint( 'majestic_tube_report_video' );

check( 'a well-formed report is accepted', 1 === (int) $GLOBALS['meta'][42]['reported_count'], var_export( $GLOBALS['json'], true ) );
check(
	'the nonce is checked before the post id is read',
	-1 !== mt_call_index( 'verify_ajax_nonce' ) && mt_call_index( 'verify_ajax_nonce' ) < mt_call_index( 'require_ajax_post_id' ),
	'call order: ' . implode( ', ', array_map( function ( $call ) { return $call[0]; }, $GLOBALS['calls'] ) )
);

// The same video, with reporting switched off.
mt_reset_runtime();
mt_post( 43, 'A published clip' );
mt_meta( 43, 'reported_count', 0 );
$GLOBALS['report_enabled'] = false;

$_POST = array(
	'nonce'   => mt_nonce( 'ajax-nonce' ),
	'post_id' => '43',
	'reason'  => 'broken',
);

run_endpoint( 'majestic_tube_report_video' );

check( 'a site with reporting switched off refuses the report', isset( $GLOBALS['json']['type'] ) && 'error' === $GLOBALS['json']['type'], var_export( $GLOBALS['json'], true ) );
check( 'and stores nothing', ! isset( $GLOBALS['meta'][43]['reported_reasons'] ) );
check( 'the switch is read before anything is counted', ! mt_called( 'analytics_set' ) );

// A draft is not a video the front end would ever show.
mt_reset_runtime();
mt_post( 44, 'A draft clip', 'draft' );
mt_meta( 44, 'reported_count', 0 );

$_POST = array(
	'nonce'   => mt_nonce( 'ajax-nonce' ),
	'post_id' => '44',
	'reason'  => 'broken',
);

run_endpoint( 'majestic_tube_report_video' );

check( 'a report against a video the site does not publish is refused', isset( $GLOBALS['json']['type'] ) && 'error' === $GLOBALS['json']['type'], var_export( $GLOBALS['json'], true ) );
check( 'and stores nothing', ! isset( $GLOBALS['meta'][44]['reported_reasons'] ) );

// A post that does not exist at all.
mt_reset_runtime();

$_POST = array(
	'nonce'   => mt_nonce( 'ajax-nonce' ),
	'post_id' => '9999',
	'reason'  => 'broken',
);

run_endpoint( 'majestic_tube_report_video' );

check( 'a report against a post that is gone is refused', isset( $GLOBALS['json']['type'] ) && 'error' === $GLOBALS['json']['type'], var_export( $GLOBALS['json'], true ) );

// A missing post_id.
mt_reset_runtime();

$_POST = array(
	'nonce'  => mt_nonce( 'ajax-nonce' ),
	'reason' => 'broken',
);

run_endpoint( 'majestic_tube_report_video' );

check( 'a report with no video named is refused', isset( $GLOBALS['json']['type'] ) && 'error' === $GLOBALS['json']['type'], var_export( $GLOBALS['json'], true ) );

// What a well-formed request does to the fields it accepts.
mt_reset_runtime();
mt_post( 45, 'A published clip' );
mt_meta( 45, 'reported_count', 0 );

$long = str_repeat( 'a', 2500 );

$_POST = array(
	'nonce'   => mt_nonce( 'ajax-nonce' ),
	'post_id' => '45',
	'reason'  => 'SPAM <script>',
	'message' => $long,
);

run_endpoint( 'majestic_tube_report_video' );

$entry = $GLOBALS['meta'][45]['reported_reasons'][0];

check( 'a reason that is not on the list falls back to the catch-all', 'other' === $entry['reason'], $entry['reason'] );
check( 'a message longer than the limit is cut to it', 2000 === strlen( $entry['message'] ), (string) strlen( $entry['message'] ) );
check( 'and what survives is the start of what was typed', $entry['message'] === str_repeat( 'a', 2000 ), (string) strlen( $entry['message'] ) );

mt_reset_runtime();
mt_post( 46, 'A published clip' );
mt_meta( 46, 'reported_count', 0 );

// A request carries slashes, the way WordPress adds them to superglobals.
$_POST = array(
	'nonce'   => mt_nonce( 'ajax-nonce' ),
	'post_id' => '46',
	'reason'  => 'wrong',
	'message' => 'O\'Brien asked: what\'s <b>wrong</b>?',
);

run_endpoint( 'majestic_tube_report_video' );

$entry = $GLOBALS['meta'][46]['reported_reasons'][0];

check( 'the message is unslashed before it is sanitized', false === strpos( $entry['message'], "\\'" ), $entry['message'] );
check( 'and reaches storage without tags', false === strpos( $entry['message'], '<b>' ), $entry['message'] );
check( 'with the apostrophes the visitor typed', false !== strpos( $entry['message'], "O'Brien" ), $entry['message'] );

/* -------------------------------------------------------------------------
 * Clearing reports
 * ---------------------------------------------------------------------- */

echo "\nClearing a video's reports\n";

mt_reset_runtime();
mt_post( 51, 'A fixed clip' );
mt_meta( 51, 'reported_count', 4 );
mt_meta( 51, 'reported_ips', array( mt_fingerprint() => time() ) );
mt_meta( 51, 'reported_reasons', array( array( 'reason' => 'spam', 'message' => 'x', 'time' => time() ) ) );
mt_meta( 51, 'likes_count', 5 );
mt_meta( 51, 'post_views_count', 9 );

$GLOBALS['caps'] = array();
$_GET            = array();

run_endpoint( 'majestic_tube_handle_clear_reports' );

check( 'a clear request that names no video does nothing', 4 === (int) $GLOBALS['meta'][51]['reported_count'] );
check( 'and does not consult the visitor at all', array() === $GLOBALS['caps_asked'], implode( ', ', $GLOBALS['caps_asked'] ) );

$GLOBALS['died'] = null;
$_GET            = array( 'post' => '51', '_wpnonce' => 'forged' );

run_endpoint( 'majestic_tube_handle_clear_reports' );

check( 'a forged nonce stops the request', null !== $GLOBALS['died'], var_export( $GLOBALS['died'], true ) );
check( 'with a 403', isset( $GLOBALS['died']['args']['response'] ) && 403 === (int) $GLOBALS['died']['args']['response'] );
check( 'nothing is cleared', 4 === (int) $GLOBALS['meta'][51]['reported_count'] );
check( 'and the capability is not even consulted first', ! in_array( 'edit_others_posts', $GLOBALS['caps_asked'], true ), implode( ', ', $GLOBALS['caps_asked'] ) );

$GLOBALS['died'] = null;
$GLOBALS['caps'] = array();
$_GET            = array( 'post' => '51', '_wpnonce' => mt_nonce( 'majestic_tube_clear_reports_51' ) );

run_endpoint( 'majestic_tube_handle_clear_reports' );

check( 'a signed-in visitor without the capability is refused', null !== $GLOBALS['died'], var_export( $GLOBALS['died'], true ) );
check( 'with a 403 of its own', isset( $GLOBALS['died']['args']['response'] ) && 403 === (int) $GLOBALS['died']['args']['response'] );
check( 'and still nothing is cleared', 4 === (int) $GLOBALS['meta'][51]['reported_count'] );
check( 'the capability asked for is the moderation one', in_array( 'edit_others_posts', $GLOBALS['caps_asked'], true ), implode( ', ', $GLOBALS['caps_asked'] ) );

/*
 * The branch that succeeds ends in wp_safe_redirect() and exit, which cannot run
 * here; the state change is pinned through the function it calls once both
 * guards have passed.
 */
majestic_tube_clear_reports( 51 );

check( 'clearing removes the counter', ! isset( $GLOBALS['meta'][51]['reported_count'] ) );
check( 'the visitor history', ! isset( $GLOBALS['meta'][51]['reported_ips'] ) );
check( 'and the reasons', ! isset( $GLOBALS['meta'][51]['reported_reasons'] ) );
check( 'the counter reads back as zero', 0 === majestic_tube_get_report_count( 51 ) );
check( 'analytics is told to record zero, not to keep the old value', 0 === (int) $GLOBALS['acache'][51]['reported'] );
check( 'both aggregate caches are dropped', ! isset( $GLOBALS['transients']['majestic_tube_reported_count'] ) && ! isset( $GLOBALS['ocache'][ MAJESTIC_TUBE_ANALYTICS_GROUP ]['reported_ids'] ) );
check( 'the video keeps everything that is not a report', 5 === (int) $GLOBALS['meta'][51]['likes_count'] && 9 === (int) $GLOBALS['meta'][51]['post_views_count'] );
check( 'and the site is told', isset( $GLOBALS['fired'][0] ) && array( 'majestic_tube_reports_cleared', 51 ) === array( $GLOBALS['fired'][0][0], $GLOBALS['fired'][0][1][0] ), var_export( $GLOBALS['fired'], true ) );

/* -------------------------------------------------------------------------
 * The reported list and its exact total
 * ---------------------------------------------------------------------- */

echo "\nThe reported list and the total beside it\n";

mt_reset_runtime();

$GLOBALS['reported_ids'] = array( 5, 7, 9 );

$ids = majestic_tube_get_all_reported_post_ids();

check( 'the list is read whole, once', array( 5, 7, 9 ) === $ids, var_export( $ids, true ) );
check( 'at the cap the module documents', 500 === (int) $GLOBALS['posts_queries'][0]['posts_per_page'], var_export( $GLOBALS['posts_queries'][0], true ) );
check( 'most reported first', array( 'reported' => 'DESC' ) === $GLOBALS['posts_queries'][0]['orderby'] );
check( 'with ids only', 'ids' === $GLOBALS['posts_queries'][0]['fields'] );
check( 'and no found-row count of its own', true === $GLOBALS['posts_queries'][0]['no_found_rows'] );
check( 'over the same population the count uses', array( 'publish', 'draft', 'pending', 'private' ) === $GLOBALS['posts_queries'][0]['post_status'] );

check( 'a second read is served from the cache', array( 5, 7, 9 ) === majestic_tube_get_all_reported_post_ids() && 1 === count( $GLOBALS['posts_queries'] ), count( $GLOBALS['posts_queries'] ) . ' queries' );
check( 'a small widget read does not shrink the cached list', array( 5, 7, 9 ) === majestic_tube_get_reported_post_ids( 50 ) && 1 === count( $GLOBALS['posts_queries'] ) );
check( 'a limit slices the cached list', array( 5, 7 ) === majestic_tube_get_reported_post_ids( 2 ), var_export( majestic_tube_get_reported_post_ids( 2 ), true ) );
check( 'and nothing at all asks for every id', array( 5, 7, 9 ) === majestic_tube_get_reported_post_ids( -1 ) );

$GLOBALS['reported_ids'] = array( 5, 7, 9, 11 );

majestic_tube_flush_report_cache();

check( 'after a flush the list is read again', array( 5, 7, 9, 11 ) === majestic_tube_get_all_reported_post_ids() && 2 === count( $GLOBALS['posts_queries'] ), count( $GLOBALS['posts_queries'] ) . ' queries' );

mt_reset_runtime();

$GLOBALS['found_posts'] = 12;

check( 'the total is counted, not measured from the list', 12 === majestic_tube_count_reported_videos() );
check( 'by asking SQL for it', false === $GLOBALS['wp_queries'][0]['no_found_rows'] && 1 === $GLOBALS['wp_queries'][0]['posts_per_page'], var_export( $GLOBALS['wp_queries'][0], true ) );
check( 'over the reported population', 'reported_count' === $GLOBALS['wp_queries'][0]['meta_query']['reported']['key'] );
check( 'the list was not read to produce it', array() === $GLOBALS['posts_queries'] );
check( 'and a second read is cached rather than counted again', 12 === majestic_tube_count_reported_videos() && 1 === count( $GLOBALS['wp_queries'] ) );

majestic_tube_report_cache_set( 'reported_count', 0 );

$before = count( $GLOBALS['wp_queries'] );

check( 'a cached zero is a hit, not a miss to be recounted', 0 === majestic_tube_count_reported_videos() && $before === count( $GLOBALS['wp_queries'] ) );

/* -------------------------------------------------------------------------
 * The report button
 * ---------------------------------------------------------------------- */

echo "\nThe report button on a video\n";

mt_reset_runtime();
mt_post( 61, 'A clip' );
mt_meta( 61, 'reported_count', 3 );

$GLOBALS['report_enabled'] = false;

check( 'nothing is printed when reporting is switched off', '' === capture( function () { majestic_tube_report_button( 61 ); } ) );

$GLOBALS['report_enabled'] = true;

check( 'and nothing when there is no video to report', '' === capture( function () { majestic_tube_report_button( 0 ); } ) );

$html = capture( function () { majestic_tube_report_button( 61 ); } );

check( 'the toggle is a button, not a link that reloads the page', false !== strpos( $html, '<button type="button" class="video-report-toggle"' ), $html );
check( 'it says whether the panel is open', false !== strpos( $html, 'aria-expanded="false"' ) );
check( 'and names the panel it opens', false !== strpos( $html, 'aria-controls="video-report-panel-61"' ) );
check( 'the panel answers to that name', false !== strpos( $html, 'id="video-report-panel-61"' ) );
check( 'the panel is a form', false !== strpos( $html, 'class="video-report-form"' ) );
check( 'carrying the video the report is about', false !== strpos( $html, 'data-post_id="61"' ) );
check( 'every reason is offered', 4 === substr_count( $html, '<option ' ), substr_count( $html, '<option ' ) . ' options' );
check( 'with the slug as value and the label as text', false !== strpos( $html, '<option value="broken">The video does not play</option>' ), $html );
check( 'each label names the field it belongs to', false !== strpos( $html, 'for="video-report-reason-61"' ) && false !== strpos( $html, 'id="video-report-reason-61"' ) );
check( 'the visitor gets somewhere to say more', false !== strpos( $html, 'id="video-report-message-61"' ) );
check( 'there is a submit button', false !== strpos( $html, 'class="video-report-submit"' ) );
check( 'and a live region for the answer', false !== strpos( $html, 'aria-live="polite"' ) );
check( 'the flag icon comes from the icon set', false !== strpos( $html, '[icon:flag]' ) );

check( 'a report count is not shown to someone who cannot moderate', false === strpos( $html, 'video-report-count' ), $html );

$GLOBALS['caps'] = array( 'edit_post' );
$html            = capture( function () { majestic_tube_report_button( 61 ); } );

check( 'and is shown to one who can, asking about this post', false !== strpos( $html, 'video-report-count' ) && in_array( 'edit_post', $GLOBALS['caps_asked'], true ), $html );
check( 'with the number, not the raw counter', false !== strpos( $html, '>3</span>' ), $html );

/* -------------------------------------------------------------------------
 * The moderation screen
 * ---------------------------------------------------------------------- */

echo "\nThe moderation screen\n";

mt_reset_runtime();
$GLOBALS['caps'] = array();

check( 'the screen prints nothing to someone who cannot moderate', '' === capture( 'majestic_tube_render_reports_page' ) );

$GLOBALS['caps']      = array( 'edit_others_posts' );
$GLOBALS['reported_ids'] = array();

check( 'with nothing reported it says so', false !== strpos( capture( 'majestic_tube_render_reports_page' ), 'No video has been reported yet.' ) );

mt_reset_runtime();
$GLOBALS['caps']         = array( 'edit_others_posts' );
$GLOBALS['reported_ids'] = array( 41, 42, 43 );

$hostile = 'Bad <script>alert(1)</script> & "quotes"';

mt_post( 41, $hostile, 'publish' );
mt_post( 43, 'A draft clip', 'draft' );
// 42 is on the cached list and no longer exists: the deleted-in-the-window case.
mt_meta( 41, 'reported_count', 2 );
mt_meta( 41, 'reported_reasons', array( array( 'reason' => 'broken', 'message' => 'stops at <b>0:30</b>', 'time' => 1600000000 ) ) );
mt_meta( 43, 'reported_count', 1 );

$page = capture( 'majestic_tube_render_reports_page' );

check( 'the screen renders at all with a deleted video on the list', '' !== $page );
check( 'the row that vanished is skipped, the others still drawn', 3 === substr_count( $page, '<tr>' ), substr_count( $page, '<tr>' ) . ' rows' );
check(
	'the surviving rows keep the cached order',
	false !== strpos( $page, 'post.php?post=41' ) && false !== strpos( $page, 'post.php?post=43' ) && strpos( $page, 'post.php?post=41' ) < strpos( $page, 'post.php?post=43' ),
	$page
);
check( 'a title carrying markup is escaped', false === strpos( $page, '<script>alert' ) && false !== strpos( $page, '&lt;script&gt;alert(1)&lt;/script&gt;' ) );
check( 'and so is its ampersand', false !== strpos( $page, '&amp;' ) );
check( 'an unpublished video is labeled as such', false !== strpos( $page, '<em>Draft</em>' ), $page );
check( 'the report count is shown', false !== strpos( $page, '>2</td>' ), $page );
check( 'the last reason is spelled out for a reader', false !== strpos( $page, 'The video does not play' ) );
check( 'and the message is shown without its tags', false === strpos( $page, '<b>0:30</b>' ) && false !== strpos( $page, 'stops at 0:30' ), $page );
check( 'the time is machine-readable as well as printed', false !== strpos( $page, '<time datetime="2020-09-13T12:26:40+00:00">' ), $page );
check( 'the clear link carries a nonce', false !== strpos( $page, '_wpnonce=' ) );
check( 'and names the action it performs on this post', false !== strpos( $page, 'action=majestic_tube_clear_reports&amp;post=41' ), $page );
check( 'the row links to the video itself', false !== strpos( $page, 'https://example.test/watch/41/' ) );
check( 'the screen says where reports are kept', false !== strpos( $page, 'reported_count, reported_reasons, reported_ips' ) );

$_GET['cleared'] = '1';

check( 'and confirms a clear that just happened', false !== strpos( capture( 'majestic_tube_render_reports_page' ), 'Reports cleared for that video.' ) );

$_GET = array();

/* -------------------------------------------------------------------------
 * The dashboard widget
 * ---------------------------------------------------------------------- */

echo "\nThe dashboard widget\n";

mt_reset_runtime();

$GLOBALS['caps'] = array();

majestic_tube_register_reports_widget();

check( 'no widget is offered to someone who cannot moderate', array() === $GLOBALS['dashboard_widgets'], var_export( $GLOBALS['dashboard_widgets'], true ) );

$GLOBALS['caps'] = array( 'edit_others_posts' );

majestic_tube_register_reports_widget();

check( 'the widget is registered under the theme ID', isset( $GLOBALS['dashboard_widgets'][0]['id'] ) && 'majestic_tube_reported_videos' === $GLOBALS['dashboard_widgets'][0]['id'], var_export( $GLOBALS['dashboard_widgets'], true ) );
check( 'and draws itself', isset( $GLOBALS['dashboard_widgets'][0]['callback'] ) && 'majestic_tube_render_reports_widget' === $GLOBALS['dashboard_widgets'][0]['callback'] );

$GLOBALS['reported_ids'] = array();
$GLOBALS['found_posts']  = 0;

check( 'with nothing reported it says so', false !== strpos( capture( 'majestic_tube_render_reports_widget' ), 'No video has been reported yet.' ) );

mt_reset_runtime();
$GLOBALS['reported_ids'] = array( 71 );
$GLOBALS['found_posts']  = 1;

mt_post( 71, 'A clip' );
mt_meta( 71, 'reported_count', 1 );
mt_meta( 71, 'reported_reasons', array( array( 'reason' => 'spam', 'message' => '', 'time' => time() ) ) );

$widget = capture( 'majestic_tube_render_reports_widget' );

check( 'one report is described in the singular', false !== strpos( $widget, '1 video has pending reports.' ), $widget );
check( 'the video is listed with its count', false !== strpos( $widget, 'A clip</a> (1)' ), $widget );
check( 'linked to the screen that can act on it', false !== strpos( $widget, 'post.php?post=71&amp;action=edit' ), $widget );
check( 'with the last reason beside it', false !== strpos( $widget, '&ndash; Spam or misleading' ), $widget );
check( 'and a way through to the full list', false !== strpos( $widget, 'edit.php?page=majestic-tube-reports' ), $widget );

mt_reset_runtime();
$GLOBALS['reported_ids'] = array( 81, 82, 83, 84, 85, 86, 87, 88 );
$GLOBALS['found_posts']  = 8;

for ( $i = 81; $i <= 88; $i++ ) {
	mt_post( $i, 'Clip ' . $i );
	mt_meta( $i, 'reported_count', 2 );
}

$widget = capture( 'majestic_tube_render_reports_widget' );

check( 'several reports are described in the plural', false !== strpos( $widget, '8 videos have pending reports.' ), $widget );
check( 'the widget lists five, not the whole backlog', 5 === substr_count( $widget, '<li>' ), substr_count( $widget, '<li>' ) . ' rows' );
check( 'the total is the exact one, not the five on show', false !== strpos( $widget, '8 videos have pending reports.' ) );

/* -------------------------------------------------------------------------
 * The menu entry
 * ---------------------------------------------------------------------- */

echo "\nThe menu entry\n";

mt_reset_runtime();
$GLOBALS['found_posts'] = 0;

majestic_tube_register_reports_page();

$menu = isset( $GLOBALS['submenus'][0] ) ? $GLOBALS['submenus'][0] : array();

check( 'the screen hangs off the Videos menu', isset( $menu['parent'] ) && 'edit.php' === $menu['parent'], var_export( $menu, true ) );
check( 'behind the moderation capability', isset( $menu['capability'] ) && 'edit_others_posts' === $menu['capability'] );
check( 'under a stable slug', isset( $menu['slug'] ) && 'majestic-tube-reports' === $menu['slug'] );
check( 'and draws the theme screen', isset( $menu['callback'] ) && 'majestic_tube_render_reports_page' === $menu['callback'] );
check( 'the menu name is the translated one', isset( $menu['menu_title'] ) && 'Reported Videos' === $menu['menu_title'], var_export( $menu, true ) );

check( 'nothing is added to the name when nothing is pending', '' === majestic_tube_reports_menu_bubble() );

mt_reset_runtime();
$GLOBALS['found_posts'] = 4;

majestic_tube_register_reports_page();

$menu = isset( $GLOBALS['submenus'][0] ) ? $GLOBALS['submenus'][0] : array();

check( 'a pending count is shown beside the name', isset( $menu['menu_title'] ) && false !== strpos( $menu['menu_title'], 'Reported Videos' ) && false !== strpos( $menu['menu_title'], 'plugin-count' ), var_export( $menu, true ) );
check( 'in the markup the admin menu styles', isset( $menu['menu_title'] ) && false !== strpos( $menu['menu_title'], 'update-plugins count-4' ), var_export( $menu, true ) );
check( 'and the number is the count, formatted for reading', isset( $menu['menu_title'] ) && false !== strpos( $menu['menu_title'], '>4</span>' ), var_export( $menu, true ) );

/* -------------------------------------------------------------------------
 * Result
 * ---------------------------------------------------------------------- */

printf( "\n%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";

exit( 0 );
