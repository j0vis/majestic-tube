<?php
/**
 * Smoke test for the random draw in inc/template-tags.php.
 *
 * Three surfaces used to ask MySQL for `ORDER BY RAND()`: the Random listing,
 * the Random setting of the video widget, and the thumbnail a term borrows from
 * one of its own posts. That clause is the classic large-table killer - it reads
 * every matching row, sorts them on a temporary table and throws all but one
 * page away - so all three now draw from a cached pool of IDs instead.
 *
 * Four claims are pinned here, each one a way the replacement has to be right
 * rather than look right:
 *
 *   1. no query the theme builds is ordered by RAND() any more, on any of the
 *      three paths. That is the regression the whole change exists to prevent,
 *      so it is asserted at the end over every argument set the run recorded
 *      rather than over one of them;
 *   2. the pool is cached and keyed by WordPress's own `last_changed` markers
 *      for the posts and terms groups, so a second read inside the window runs
 *      no query at all, two different archives never share a pool, and writing a
 *      post or a term makes the old pool unreachable instead of leaving a stale
 *      draw in place until the TTL runs out. An empty answer is cached the same
 *      way - a term whose posts have no image is a legitimate answer, and
 *      re-asking on every page load is what the cache is there to stop - which
 *      is why the marker, and not the empty answer, is what this suite holds the
 *      cache to;
 *   3. the window a pool is cut from stays inside the archive. An unbounded
 *      `ORDER BY ID` would answer with the oldest videos the site ever
 *      published, so the offset is drawn inside the real post count, and an
 *      archive smaller than the pool is read from the start rather than from an
 *      offset past its end;
 *   4. a draw respects the archive it is asked on. On a category page the pool
 *      carries that category's taxonomy, on the blog home it carries none, and
 *      the listing hands the drawn IDs to the query as `post__in` with
 *      `orderby => post__in` - never an empty `post__in`, which WordPress reads
 *      as no restriction at all and would print the whole site under a Random
 *      heading.
 *
 * Three shipping files are included, so the code under test is the code that
 * ships: inc/template-tags.php (the pool, the draw and the listing filter),
 * inc/widget-videos.php and inc/term-images.php (the other two call sites).
 *
 * What it is not: a real WordPress, or a real database. The stand-ins for
 * WP_Query and get_posts() record the arguments they are handed and answer from
 * a fixture window, so the SHAPE of every query is pinned while the constraints
 * in it (tax_query, meta_query) are recorded rather than evaluated; WP_Query
 * answers the found-row count and no posts otherwise, so the widget is pinned by
 * the arguments it builds and not by the markup its loop would print. wp_rand()
 * is stubbed so the offset a pool starts at is deterministic.
 *
 * Run:  php tests/random-videos-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

// inc/template-tags.php builds constants at include time from these.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

// inc/term-images.php names these two, which live in inc/pagination.php.
define( 'MAJESTIC_TUBE_TERM_CACHE_GROUP', 'majestic_tube_terms' );
define( 'MAJESTIC_TUBE_TERM_CACHE_TTL', 6 * HOUR_IN_SECONDS );

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = 0;

/* -------------------------------------------------------------------------
 * State the fixtures read and write.
 * ---------------------------------------------------------------------- */

$GLOBALS['cache']          = array();
$GLOBALS['transients']     = array();
$GLOBALS['options']        = array();
$GLOBALS['opts']           = array();
$GLOBALS['_GET']           = array();

$GLOBALS['filters']        = array(); // Registered callbacks, hook => list.
$GLOBALS['queries']        = array(); // Every `new WP_Query()` argument set.
$GLOBALS['get_posts_args'] = array(); // Every get_posts() argument set.
$GLOBALS['sets']           = array(); // Every `$query->set()` pair.
$GLOBALS['set_keys']       = array(); // Every set_transient() key, in order.
$GLOBALS['get_keys']       = array(); // Every get_transient() key, in order.

$GLOBALS['total_posts']    = 0;       // What the found-row count answers with.
$GLOBALS['ids']            = array(); // The fixture archive, in ID order.
$GLOBALS['rand_calls']     = array(); // Every wp_rand() range, in order.
$GLOBALS['rand_answer']    = 0;       // What wp_rand() answers.

/**
 * Put an archive of a given size in place.
 *
 * @param int        $total How many published videos the site has.
 * @param array|null $ids   The IDs themselves; defaults to 1..total.
 * @return void
 */
function mt_archive( $total, $ids = null ) {
	$total = (int) $total;

	$GLOBALS['total_posts'] = $total;
	$GLOBALS['ids']         = null === $ids ? ( $total > 0 ? range( 1, $total ) : array() ) : array_values( $ids );
}

/**
 * Forget the caches and the recorders, leaving the markers in place.
 *
 * Only ever called before a section that has to see its own queries: the final
 * sweep asserts over everything the run recorded, so nothing clears the
 * recorders after the last call site has been exercised.
 *
 * @return void
 */
function mt_forget() {
	$GLOBALS['transients']     = array();
	$GLOBALS['set_keys']       = array();
	$GLOBALS['get_keys']       = array();
	$GLOBALS['queries']        = array();
	$GLOBALS['get_posts_args'] = array();
	$GLOBALS['sets']           = array();
	$GLOBALS['rand_calls']     = array();
	$GLOBALS['cache']          = array(
		'posts' => array( 'last_changed' => '1' ),
		'terms' => array( 'last_changed' => '1' ),
	);
}

/**
 * Bump one of WordPress's own `last_changed` markers.
 *
 * @param string $group Either `posts` or `terms`.
 * @return void
 */
function mt_bump( $group ) {
	$GLOBALS['cache'][ $group ]['last_changed'] = (string) ( (int) $GLOBALS['cache'][ $group ]['last_changed'] + 1 );
}

/**
 * The argument set a recorder last saw.
 *
 * @param array $list Recorded argument sets.
 * @return array
 */
function mt_last( $list ) {
	$last = end( $list );

	return is_array( $last ) ? $last : array();
}

/**
 * The last key a recorder saw, as a string.
 *
 * A key comparison cannot go through mt_last(): its array guard turns every
 * string key into array(), and two array() compare equal, so every "the key
 * moved" check would pass and every "the key did not move" check with it.
 *
 * @param array $list Recorded keys.
 * @return string Last key, or an empty string when nothing was recorded.
 */
function mt_last_key( $list ) {
	$last = end( $list );

	return is_string( $last ) ? $last : '';
}

mt_forget();
mt_archive( 0 );

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as these three modules touch.
 * ---------------------------------------------------------------------- */

function __( $s, $d = null ) {
	return $s;
}
function esc_html__( $s, $d = null ) {
	return $s;
}
function esc_attr__( $s, $d = null ) {
	return $s;
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_text_field( $s ) {
	$s = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', (string) $s );

	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}
function is_admin() {
	return false;
}
function is_home() {
	return ! empty( $GLOBALS['is_home'] );
}

/**
 * The options reader, for the keys these modules ask for.
 *
 * inc/theme-options.php resolves a mapped field against its declared default;
 * the keys read here are read for their value alone.
 *
 * @param string $group   Option group.
 * @param string $field   Field id.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function majestic_tube_get_option( $group, $field, $default = false ) {
	return isset( $GLOBALS['opts'][ $field ] ) ? $GLOBALS['opts'][ $field ] : $default;
}

/**
 * The mobile check, which decides which per-page option is read.
 *
 * @return bool
 */
function majestic_tube_is_mobile() {
	return false;
}

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['options'][ $key ] ) ? $GLOBALS['options'][ $key ] : $default;
}

function wp_cache_get( $key, $group = '' ) {
	return isset( $GLOBALS['cache'][ $group ][ $key ] ) ? $GLOBALS['cache'][ $group ][ $key ] : false;
}

function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
	$GLOBALS['cache'][ $group ][ $key ] = $value;

	return true;
}

function wp_cache_delete( $key, $group = '' ) {
	unset( $GLOBALS['cache'][ $group ][ $key ] );

	return true;
}

function get_transient( $key ) {
	$GLOBALS['get_keys'][] = $key;

	return isset( $GLOBALS['transients'][ $key ] ) ? $GLOBALS['transients'][ $key ] : false;
}

function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['transients'][ $key ] = $value;
	$GLOBALS['set_keys'][]        = $key;

	return true;
}

/**
 * The random source the offset is drawn with.
 *
 * Stubbed so an offset is deterministic: it answers with what the suite last
 * asked for, and records the range it was asked for, which is what the boundary
 * checks turn on.
 *
 * @param int $min Lower bound, inclusive.
 * @param int $max Upper bound, inclusive.
 * @return int
 */
function wp_rand( $min = 0, $max = 0 ) {
	$GLOBALS['rand_calls'][] = array( $min, $max );

	return (int) $GLOBALS['rand_answer'];
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	add_filter( $hook, $callback, $priority, $accepted_args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['filters'][ $hook ][] = $callback;

	return true;
}

/**
 * Register a filter from inside the suite.
 *
 * @param string   $hook     Filter name.
 * @param callable $callback Callback.
 * @return void
 */
function mt_add_filter( $hook, $callback ) {
	add_filter( $hook, $callback );
}

/**
 * Take every callback off a hook again, so one section's filter cannot answer
 * for the next one.
 *
 * @param string $hook Filter name.
 * @return void
 */
function mt_remove_filter( $hook ) {
	unset( $GLOBALS['filters'][ $hook ] );
}

/**
 * apply_filters() with a registry behind it.
 *
 * The theme's own pool size and window filters have to be exercisable, so this
 * is a real (if minimal) filter pass rather than the identity the other suites
 * use.
 *
 * @param string $hook  Filter name.
 * @param mixed  $value Value being filtered.
 * @param mixed  ...$rest The rest of the arguments WordPress passes.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$rest ) {
	$args = array_merge( array( $value ), $rest );

	foreach ( isset( $GLOBALS['filters'][ $hook ] ) ? $GLOBALS['filters'][ $hook ] : array() as $callback ) {
		$args[0] = call_user_func_array( $callback, $args );
	}

	return $args[0];
}

/**
 * A term, as `instanceof WP_Term` in the pool arguments reads it.
 */
class WP_Term {
	public $term_id;
	public $taxonomy;
	public $name;
	public $slug;

	/**
	 * Constructor.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $name     Term name.
	 * @param string $slug     Term slug.
	 */
	public function __construct( $term_id, $taxonomy, $name = '', $slug = '' ) {
		$this->term_id  = (int) $term_id;
		$this->taxonomy = $taxonomy;
		$this->name     = $name;
		$this->slug     = $slug;
	}
}

/**
 * A post, for the case where a query's queried object is not a term.
 */
class WP_Post {
	public $ID;
	public $post_title;

	/**
	 * Constructor.
	 *
	 * @param int    $id    Post ID.
	 * @param string $title Post title.
	 */
	public function __construct( $id = 0, $title = '' ) {
		$this->ID         = (int) $id;
		$this->post_title = $title;
	}
}

/**
 * The window of the fixture archive a set of arguments asks for.
 *
 * The constraints in the arguments (tax_query, meta_query) are not evaluated:
 * they travel with the recorded ids, which is what these checks are about.
 *
 * @param array $args Query arguments.
 * @return int[] Post IDs.
 */
function mt_window( $args ) {
	$per_page = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 10;
	$offset   = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

	if ( $per_page < 0 ) {
		$per_page = count( $GLOBALS['ids'] );
	}

	return array_map( 'absint', array_slice( $GLOBALS['ids'], $offset, $per_page ) );
}

/**
 * get_posts(), recording the arguments and answering from the fixture window.
 *
 * @param array $args Query arguments.
 * @return int[] Post IDs.
 */
function get_posts( $args = array() ) {
	$GLOBALS['get_posts_args'][] = $args;

	return mt_window( $args );
}

/**
 * WP_Query(), recording the arguments.
 *
 * The count query - the only one that asks for found rows - is answered with
 * the fixture total. Every other query is answered with no posts at all, so the
 * widget stops before its loop and what is asserted about it is the arguments it
 * builds rather than markup this suite would have to stub a whole loop for.
 */
class WP_Query {
	public $args = array();
	public $posts = array();
	public $found_posts = 0;

	/**
	 * Constructor.
	 *
	 * @param array $args Query arguments.
	 */
	public function __construct( $args = array() ) {
		$this->args = $args;

		$GLOBALS['queries'][] = $args;

		if ( array_key_exists( 'no_found_rows', $args ) && ! $args['no_found_rows'] ) {
			$this->found_posts = (int) $GLOBALS['total_posts'];
		}
	}

	public function have_posts() {
		return ! empty( $this->posts );
	}

	public function the_post() {}

	public function rewind_posts() {}

	public function get( $key, $default = '' ) {
		return isset( $this->args[ $key ] ) ? $this->args[ $key ] : $default;
	}

	public function set( $key, $value ) {
		$this->args[ $key ] = $value;
	}
}

/**
 * A main query, with the archive flags and the arguments the filter reads.
 */
class MT_Query {
	public $vars = array();
	public $flags = array();
	public $object = null;
	public $main = true;

	/**
	 * Constructor.
	 *
	 * @param array    $vars   Query vars.
	 * @param array    $flags  Archive flags, e.g. array( 'category' => true ).
	 * @param WP_Term|WP_Post|null $object Queried object.
	 * @param bool     $main   Whether this is the main query.
	 */
	public function __construct( $vars = array(), $flags = array(), $object = null, $main = true ) {
		$this->vars   = $vars;
		$this->flags  = $flags;
		$this->object = $object;
		$this->main   = $main;
	}

	public function is_main_query() {
		return (bool) $this->main;
	}

	public function is_home() {
		return ! empty( $this->flags['home'] );
	}

	public function is_category() {
		return ! empty( $this->flags['category'] );
	}

	public function is_tag() {
		return ! empty( $this->flags['tag'] );
	}

	public function is_search() {
		return ! empty( $this->flags['search'] );
	}

	public function is_tax( $taxonomy = '' ) {
		if ( empty( $this->flags['tax'] ) ) {
			return false;
		}

		return '' === $taxonomy || $taxonomy === $this->flags['tax'];
	}

	public function get( $key, $default = '' ) {
		return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : $default;
	}

	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;

		$GLOBALS['sets'][] = array( $key => $value );
	}

	public function get_queried_object() {
		return $this->object;
	}
}

/**
 * A stand-in for the widget base class, which is all the widget needs from it.
 */
class WP_Widget {
	public $id_base;
	public $name;
	public $widget_options;

	/**
	 * Constructor.
	 *
	 * @param string $id_base        Widget id.
	 * @param string $name           Widget name.
	 * @param array  $widget_options Widget options.
	 * @param array  $control_options Control options.
	 */
	public function __construct( $id_base, $name, $widget_options = array(), $control_options = array() ) {
		$this->id_base        = $id_base;
		$this->name           = $name;
		$this->widget_options = $widget_options;
	}
}

require_once MAJESTIC_TUBE_DIR . '/inc/template-tags.php';
require_once MAJESTIC_TUBE_DIR . '/inc/widget-videos.php';
require_once MAJESTIC_TUBE_DIR . '/inc/term-images.php';

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
 * Every recorded argument set whose orderby is still a random draw.
 *
 * Covers the three recorders, because the three call sites reach MySQL by three
 * different routes: the pool and the borrowed thumbnail through get_posts(), the
 * widget through WP_Query, and the listing by setting the clause on a query it
 * did not build.
 *
 * @return array[] The offending argument sets.
 */
function mt_rand_offenders() {
	$offenders = array();

	foreach ( array_merge( $GLOBALS['queries'], $GLOBALS['get_posts_args'] ) as $args ) {
		if ( isset( $args['orderby'] ) && 'rand' === $args['orderby'] ) {
			$offenders[] = $args;
		}
	}

	foreach ( $GLOBALS['sets'] as $set ) {
		if ( isset( $set['orderby'] ) && 'rand' === $set['orderby'] ) {
			$offenders[] = $set;
		}
	}

	return $offenders;
}

/**
 * Whether a list holds whole numbers and holds each of them once.
 *
 * @param array $values Values.
 * @return bool
 */
function mt_distinct_ints( $values ) {
	$values = array_values( (array) $values );

	foreach ( $values as $value ) {
		if ( ! is_int( $value ) ) {
			return false;
		}
	}

	return array_values( array_unique( $values ) ) === $values;
}

/* -------------------------------------------------------------------------
 * The pool
 * ---------------------------------------------------------------------- */

echo "\nThe pool of IDs a draw comes from\n";

mt_forget();
mt_archive( 5000 );

$GLOBALS['rand_answer'] = 0;

$pool      = majestic_tube_random_video_pool();
$pool_args = mt_last( $GLOBALS['get_posts_args'] );
$count_args = mt_last( $GLOBALS['queries'] );

check( 'a pool is cut in ID order instead of a random one', 'ID' === ( $pool_args['orderby'] ?? '' ), var_export( $pool_args, true ) );
check( 'ascending, over published videos', 'ASC' === ( $pool_args['order'] ?? '' ) && 'publish' === ( $pool_args['post_status'] ?? '' ) );
check( 'asking for IDs alone', 'ids' === ( $pool_args['fields'] ?? '' ) );
check( 'and not paying for a found-row count of its own', true === ( $pool_args['no_found_rows'] ?? null ) );
check( 'the pool is capped rather than the whole archive', 500 === ( $pool_args['posts_per_page'] ?? null ), 'got ' . var_export( $pool_args['posts_per_page'] ?? null, true ) );
check( 'so a pool of 500 five-thousand-video IDs comes back full', 500 === count( $pool ) );
check( 'with every ID in it a whole number', mt_distinct_ints( $pool ) );
check( 'the window starts somewhere inside the archive', array( 0, 4500 ) === mt_last( $GLOBALS['rand_calls'] ), var_export( $GLOBALS['rand_calls'], true ) );
check( 'and the archive size came from its own query', 1 === ( $count_args['posts_per_page'] ?? null ) && false === ( $count_args['no_found_rows'] ?? null ) );
check( 'so the window spans exactly the pool it asked for', $pool[0] === 1 && 500 === count( array_intersect( $pool, range( 1, 500 ) ) ) );

// The other end of the same boundary: the last window that still fits.
mt_forget();
mt_archive( 5000 );

$GLOBALS['rand_answer'] = 4500;

$tail = majestic_tube_random_video_pool();

check( 'and the last window that fits is reachable', 4500 === ( mt_last( $GLOBALS['get_posts_args'] )['offset'] ?? null ) );
check( 'which ends on the last video in the archive', 5000 === $tail[ count( $tail ) - 1 ] );

// An archive smaller than the pool is read from the start.
mt_forget();
mt_archive( 300 );

majestic_tube_random_video_pool();

$small_args = mt_last( $GLOBALS['get_posts_args'] );

check( 'an archive smaller than the pool is not offset past its end', ! isset( $small_args['offset'] ), var_export( $small_args, true ) );
check( 'and no random number is drawn for it at all', array() === $GLOBALS['rand_calls'] );
check( 'so the pool is the whole archive', 300 === count( mt_window( $small_args ) ) );

/* The pool is cached, and the key is what keeps it honest. */
mt_forget();
mt_archive( 5000 );

$first = majestic_tube_random_video_pool();
$key   = mt_last_key( $GLOBALS['get_keys'] );
$reads = count( $GLOBALS['get_posts_args'] );
$counts = count( $GLOBALS['queries'] );

$second = majestic_tube_random_video_pool();

check( 'a second read inside the window runs no query at all', $reads === count( $GLOBALS['get_posts_args'] ) && $counts === count( $GLOBALS['queries'] ) );
check( 'and is served the same pool', $first === $second );
check( 'the copy is stored under a key named for the pool', is_string( $key ) && false !== strpos( $key, 'majestic_tube_random_pool_' ) );

// A pool cut for one archive cannot be served to another.
majestic_tube_random_video_pool(
	array(
		'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => 7,
			),
		),
	)
);

check( 'a pool cut for one archive is not served to another', '' !== $key && $key !== mt_last_key( $GLOBALS['get_keys'] ) );

mt_bump( 'posts' );

majestic_tube_random_video_pool();

$after_posts = mt_last_key( $GLOBALS['get_keys'] );

check( 'publishing a video makes the old pool unreachable', '' !== $after_posts && $key !== $after_posts );

$reads_after = count( $GLOBALS['get_posts_args'] );

mt_bump( 'terms' );

majestic_tube_random_video_pool();

check( 'and so does a term change', $after_posts !== mt_last_key( $GLOBALS['get_keys'] ) );
check( 'each of which cuts the window again', $reads_after < count( $GLOBALS['get_posts_args'] ) );

// The pool is sized by the filter the site gets to set.
mt_forget();
mt_archive( 5000 );

mt_add_filter(
	'majestic_tube_random_pool_size',
	static function ( $size, $args ) {
		$GLOBALS['pool_size_args'] = $args;

		return 25;
	}
);

majestic_tube_random_video_pool( array( 'post_type' => 'post' ) );

check( 'the pool size can be filtered', 25 === ( mt_last( $GLOBALS['get_posts_args'] )['posts_per_page'] ?? null ) );
check( 'and the filter is handed the arguments it is sizing for', array( 'post_type' => 'post', 'post_status' => 'publish' ) === $GLOBALS['pool_size_args'], var_export( $GLOBALS['pool_size_args'], true ) );
check( 'a smaller cap moves the window inside the smaller set', array( 0, 4975 ) === mt_last( $GLOBALS['rand_calls'] ), var_export( mt_last( $GLOBALS['rand_calls'] ), true ) );

mt_remove_filter( 'majestic_tube_random_pool_size' );

// An archive with nothing in it.
mt_forget();
mt_archive( 0 );

$empty = majestic_tube_random_video_pool();

check( 'an empty archive gives an empty pool', array() === $empty );
check(
	'and is asked for from the start, with no random order and no offset',
	array( 'post_type' => 'post', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 500, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'orderby' => 'ID', 'order' => 'ASC' ) === mt_last( $GLOBALS['get_posts_args'] ),
	var_export( mt_last( $GLOBALS['get_posts_args'] ), true )
);

$empty_reads = count( $GLOBALS['get_posts_args'] );

majestic_tube_random_video_pool();

check( 'the empty answer is cached too, so nothing is re-asked per page load', $empty_reads === count( $GLOBALS['get_posts_args'] ) );

mt_bump( 'posts' );

majestic_tube_random_video_pool();

check( 'but one write is enough to ask again', $empty_reads < count( $GLOBALS['get_posts_args'] ) );

/* -------------------------------------------------------------------------
 * The draw
 * ---------------------------------------------------------------------- */

echo "\nThe draw\n";

mt_forget();
mt_archive( 5000 );

$GLOBALS['rand_answer'] = 0;

$source = majestic_tube_random_video_pool();
$drawn  = majestic_tube_random_video_ids( 4 );

check( 'a draw of four returns four', 4 === count( $drawn ), var_export( $drawn, true ) );
check( 'with no ID twice', mt_distinct_ints( $drawn ) );
check( 'and nothing that was not in the pool', array() === array_diff( $drawn, $source ), var_export( $drawn, true ) );
check( 'a draw of one is one ID rather than a bare key', 1 === count( majestic_tube_random_video_ids( 1 ) ) );
check( 'and asks for a smaller set than the pool holds', count( $drawn ) < count( $source ) );

mt_forget();
mt_archive( 5000 );

$GLOBALS['rand_answer'] = 0;

check( 'asking for none still draws one rather than the whole archive', 1 === count( majestic_tube_random_video_ids( 0 ) ) );
// absint() is how the pool size is read too, so a negative asks for three - 
// never for the whole archive, which is what the check is guarding against.
check( 'and a negative count is read through absint, not treated as a request for everything', 3 === count( majestic_tube_random_video_ids( -3 ) ) );

mt_forget();
mt_archive( 3 );

check( 'a pool smaller than the count is handed over whole', array( 1, 2, 3 ) === majestic_tube_random_video_ids( 5 ) );

mt_forget();
mt_archive( 0 );

check( 'and an empty pool draws nothing at all', array() === majestic_tube_random_video_ids( 3 ), 'an empty pool that fell back to the archive would print the site' );

/* -------------------------------------------------------------------------
 * The constraints a draw respects
 * ---------------------------------------------------------------------- */

echo "\nWhat a draw has to stay inside\n";

$category      = new MT_Query( array(), array( 'category' => true ), new WP_Term( 7, 'category', 'HD' ) );
$category_args = majestic_tube_random_pool_args( $category );

check( 'a category page carries that category into the pool', isset( $category_args['tax_query'][0] ) && 'category' === $category_args['tax_query'][0]['taxonomy'] && 7 === $category_args['tax_query'][0]['terms'], var_export( $category_args, true ) );
check( 'matched by term ID rather than by slug', 'term_id' === ( $category_args['tax_query'][0]['field'] ?? '' ) );
check( 'and still videos only', 'post' === ( $category_args['post_type'] ?? '' ) && 'publish' === ( $category_args['post_status'] ?? '' ) );

$tag = new MT_Query( array(), array( 'tag' => true ), new WP_Term( 3, 'post_tag', 'Action' ) );

check( 'a tag page carries its tag', 'post_tag' === ( majestic_tube_random_pool_args( $tag )['tax_query'][0]['taxonomy'] ?? '' ) );

$actors = new MT_Query( array(), array( 'tax' => 'actors' ), new WP_Term( 11, 'actors', 'Jane Doe' ) );

check( 'an actor page carries its actor', 'actors' === ( majestic_tube_random_pool_args( $actors )['tax_query'][0]['taxonomy'] ?? '' ) );

$home = new MT_Query( array(), array( 'home' => true ), new WP_Post( 12, 'Videos' ) );

check( 'the blog home carries no taxonomy at all', ! isset( majestic_tube_random_pool_args( $home )['tax_query'] ), var_export( majestic_tube_random_pool_args( $home ), true ) );

$filtered = new MT_Query(
	array(
		'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => 9,
			),
		),
	),
	array( 'category' => true ),
	new WP_Term( 7, 'category', 'HD' )
);

check( 'a constraint the query already carried is kept', 2 === count( majestic_tube_random_pool_args( $filtered )['tax_query'] ) );

$windowed = new MT_Query( array( 'date_query' => array( array( 'after' => '30 days ago' ) ) ), array( 'home' => true ) );

check( 'a date window travels with the draw', array( array( 'after' => '30 days ago' ) ) === ( majestic_tube_random_pool_args( $windowed )['date_query'] ?? null ) );
check( 'and a query without one does not invent it', ! isset( majestic_tube_random_pool_args( $home )['date_query'] ) );

/* -------------------------------------------------------------------------
 * The Random listing
 * ---------------------------------------------------------------------- */

echo "\nThe Random listing\n";

mt_forget();
mt_archive( 600 );

$GLOBALS['opts']    = array( 'videos-per-page' => 12 );
$GLOBALS['_GET']    = array( 'filter' => 'random' );
$GLOBALS['is_home'] = true;

$listing = new MT_Query( array(), array( 'home' => true ) );

majestic_tube_filter_query( $listing );

$drawn_ids = $listing->vars['post__in'] ?? null;

check( 'the listing is ordered by the draw rather than by a random clause', 'post__in' === ( $listing->vars['orderby'] ?? '' ), var_export( $listing->vars, true ) );
check( 'of exactly one page of videos', is_array( $drawn_ids ) && 12 === count( $drawn_ids ), var_export( $drawn_ids, true ) );
check( 'with no repeats', mt_distinct_ints( $drawn_ids ) );
check( 'and a real selection rather than an empty restriction', array() !== $drawn_ids );
check( 'the draw came from the pool, cut in ID order', 1 === count( $GLOBALS['get_posts_args'] ) && 'ID' === ( mt_last( $GLOBALS['get_posts_args'] )['orderby'] ?? '' ) );

// A site that narrows the draw to recent videos still gets its window through
// to the pool, which is the point of keeping the option.
mt_forget();
mt_archive( 600 );

mt_add_filter(
	'majestic_tube_random_window_days',
	static function () {
		return 30;
	}
);

$windowed_listing = new MT_Query( array(), array( 'home' => true ) );

majestic_tube_filter_query( $windowed_listing );

$window_args = mt_last( $GLOBALS['get_posts_args'] );

check( 'a recent-videos window narrows the pool rather than the clause', '30 days ago' === ( $window_args['date_query'][0]['after'] ?? null ), var_export( $window_args, true ) );
check( 'and the listing itself carries the same window', '30 days ago' === ( $windowed_listing->vars['date_query'][0]['after'] ?? null ) );
check( 'while still being ordered by the drawn IDs', 'post__in' === ( $windowed_listing->vars['orderby'] ?? '' ) );

mt_remove_filter( 'majestic_tube_random_window_days' );

// Nothing to draw: the listing has to close rather than open up.
mt_forget();
mt_archive( 0 );

$empty_listing = new MT_Query( array(), array( 'home' => true ) );

majestic_tube_filter_query( $empty_listing );

check( 'an empty draw is the one ID that matches no post', array( 0 ) === ( $empty_listing->vars['post__in'] ?? null ), 'an empty post__in is no restriction at all: ' . var_export( $empty_listing->vars, true ) );
check( 'and the listing still refuses to shuffle the whole archive', 'post__in' === ( $empty_listing->vars['orderby'] ?? '' ) );

// The sort filters are for the video archives.
mt_forget();
mt_archive( 600 );

$search = new MT_Query( array(), array( 'search' => true ) );

majestic_tube_filter_query( $search );

check( 'a search page gets no sort of its own', array( 'posts_per_page' => 12 ) === $search->vars, var_export( $search->vars, true ) );
check( 'and no draw is taken for it', array() === $GLOBALS['get_posts_args'] );

$secondary = new MT_Query( array(), array( 'home' => true ), null, false );

majestic_tube_filter_query( $secondary );

check( 'a secondary query is left alone too', array() === $secondary->vars );

/* -------------------------------------------------------------------------
 * The other two call sites
 * ---------------------------------------------------------------------- */

echo "\nThe Random widget\n";

mt_forget();
mt_archive( 40 );

$GLOBALS['opts'] = array();

$widget = new MajesticTube_Video_Widget();

$widget->widget(
	array(
		'before_widget' => '',
		'after_widget'  => '',
		'before_title'  => '',
		'after_title'   => '',
	),
	array(
		'title'   => 'Random',
		'orderby' => 'rand',
		'count'   => 4,
	)
);

$widget_args = mt_last( $GLOBALS['queries'] );

check( 'the random widget no longer asks for a random order', isset( $widget_args['orderby'] ) && 'rand' !== $widget_args['orderby'], var_export( $widget_args, true ) );
check( 'it is handed a draw of the size it asked for', isset( $widget_args['post__in'] ) && 4 === count( $widget_args['post__in'] ), var_export( $widget_args, true ) );
check( 'ordered as drawn', 'post__in' === ( $widget_args['orderby'] ?? '' ) );
check( 'and still limited to that many videos', 4 === ( $widget_args['posts_per_page'] ?? null ) );

mt_forget();
mt_archive( 0 );

$empty_widget = new MajesticTube_Video_Widget();

$empty_widget->widget(
	array(
		'before_widget' => '',
		'after_widget'  => '',
		'before_title'  => '',
		'after_title'   => '',
	),
	array(
		'orderby' => 'rand',
		'count'   => 4,
	)
);

$empty_widget_args = mt_last( $GLOBALS['queries'] );

check( 'an empty archive does not turn a Random widget into a Latest one', array( 0 ) === ( $empty_widget_args['post__in'] ?? null ), var_export( $empty_widget_args, true ) );

echo "\nThe thumbnail a term borrows\n";

mt_forget();
mt_archive( 40 );

$borrowed    = majestic_tube_get_term_fallback_post_id( 7, 'category' );
$borrow_args = mt_last( $GLOBALS['get_posts_args'] );

check( 'a category borrows from a pool rather than from a random clause', 'ID' === ( $borrow_args['orderby'] ?? '' ), var_export( $borrow_args, true ) );
check( 'which is the pool, not a single-row query', 500 === ( $borrow_args['posts_per_page'] ?? null ) );
check( 'carrying the term it is borrowing for', isset( $borrow_args['tax_query'][0] ) && 'category' === $borrow_args['tax_query'][0]['taxonomy'] && 7 === $borrow_args['tax_query'][0]['terms'] );
check( 'and still only considering videos that have an image', '_thumbnail_id' === ( $borrow_args['meta_query'][0]['key'] ?? '' ) );
check( 'the drawn post is one of that term\'s videos', in_array( $borrowed, $GLOBALS['ids'], true ), 'got ' . var_export( $borrowed, true ) );

$borrow_reads = count( $GLOBALS['get_posts_args'] );

majestic_tube_get_term_fallback_post_id( 7, 'category' );

check( 'the pick is cached, so a directory does not re-draw it per card', $borrow_reads === count( $GLOBALS['get_posts_args'] ) );
check( 'and the cached pick is the one it answered with', $borrowed === majestic_tube_get_term_fallback_post_id( 7, 'category' ) );

mt_forget();
mt_archive( 40 );

$recent      = majestic_tube_get_term_fallback_post_id( 7, 'actors' );
$recent_args = mt_last( $GLOBALS['get_posts_args'] );

check( 'an actor borrows the newest video instead', 'date' === ( $recent_args['orderby'] ?? '' ), var_export( $recent_args, true ) );
check( 'in a single-row query rather than the pool', 1 === ( $recent_args['posts_per_page'] ?? null ) && 1 === count( $GLOBALS['get_posts_args'] ) );
check( 'and gets a video back', in_array( $recent, $GLOBALS['ids'], true ), 'got ' . var_export( $recent, true ) );

/* -------------------------------------------------------------------------
 * The clause that was removed
 *
 * Asserted over every argument set the run recorded - the pool, the draw, the
 * listing, the widget and the borrowed thumbnail - because the point of the
 * change is that no path has a random order left in it.
 * ---------------------------------------------------------------------- */

echo "\nThe clause that was removed\n";

/*
 * One clean record, then the three paths one after another. The sections above
 * each clear the recorders before they run, so a sweep of what is left would
 * describe the last section and not the change; the sweep has to see a query
 * from every path to say anything about any of them.
 */
mt_forget();
mt_archive( 600 );

$GLOBALS['_GET'] = array( 'filter' => 'random' );
$GLOBALS['opts'] = array( 'videos-per-page' => 12 );

/*
 * The three paths have to cut three different windows for the sweep to say
 * anything about all of them: a bare pool and the blog home ask for the same
 * argument set and are meant to share one cached window (the section above
 * pins that), so the listing is run on a category archive instead.
 */
majestic_tube_random_video_pool();
majestic_tube_filter_query( new MT_Query( array(), array( 'category' => true ), new WP_Term( 7, 'category', 'HD' ) ) );

$sweep_widget = new MajesticTube_Video_Widget();

$sweep_widget->widget(
	array(
		'before_widget' => '',
		'after_widget'  => '',
		'before_title'  => '',
		'after_title'   => '',
	),
	array(
		'orderby' => 'rand',
		'count'   => 4,
	)
);

majestic_tube_get_term_fallback_post_id( 7, 'category' );

$offenders = mt_rand_offenders();

check(
	'no query any of the three paths builds is ordered by RAND()',
	array() === $offenders,
	count( $offenders ) . ' argument set(s) still carry it: ' . var_export( $offenders, true )
);

check(
	'and the sweep saw every path',
	array() !== $GLOBALS['queries'] && array() !== $GLOBALS['sets'] && 3 <= count( $GLOBALS['get_posts_args'] ),
	'queries: ' . count( $GLOBALS['queries'] ) . ', sets: ' . count( $GLOBALS['sets'] ) . ', get_posts: ' . count( $GLOBALS['get_posts_args'] )
);

$pool_shapes = array();

foreach ( $GLOBALS['get_posts_args'] as $args ) {
	if ( 'ID' === ( $args['orderby'] ?? '' ) ) {
		$pool_shapes[] = $args;
	}
}

check(
	'every window it cut was cut in ID order',
	3 <= count( $pool_shapes ),
	count( $pool_shapes ) . ' pool window(s) recorded'
);

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $GLOBALS['passed'], $GLOBALS['failed'] );

if ( $GLOBALS['failed'] ) {
	exit( 1 );
}

echo "All checks passed.\n";
