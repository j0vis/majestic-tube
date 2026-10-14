<?php
/**
 * Smoke test for inc/analytics.php.
 *
 * The module is the one place the theme reads and writes the counters every
 * surface shows - the cards, the single video page, the structured data, the
 * AJAX responses and the admin report screens - plus the per-visitor
 * de-duplication that keeps one reader from counting twice. It is included for
 * real, so the code under test is the code that ships.
 *
 * What is pinned here, in the order the module grows:
 *
 *   1. the stored contract: five meta keys, five field names, and one cache key
 *      per post. The original theme's key names are what importers, plugins and
 *      the WP-Script tooling write, so a rename here is a data-loss bug;
 *   2. one read for five counters: a whole meta row is fetched in a single
 *      get_post_meta( $post_id ) call and memoised, a cache entry that is not
 *      an array is not trusted, and a counter that was never written reads as 0
 *      rather than raising "Undefined array key 0" on PHP 8;
 *   3. the imported rating shape: `thumbs_up` / `thumbs_down` are read as the
 *      starting figures when the native pair is absent, the native pair wins
 *      when it is present, and the two halves fall back independently;
 *   4. a partial write merges over the counters it did not name, and an
 *      unknown field is ignored instead of stored;
 *   5. the view counter stays atomic: with a usable $wpdb the module issues one
 *      parameterised UPDATE and repopulates the cache from what the database
 *      reports, and only falls back to a cached read-modify-write when that
 *      statement is unavailable or fails;
 *   6. every write invalidates: the three meta hooks plus the two post hooks
 *      are registered, only the five counter keys flush, and an unrelated meta
 *      edit does not;
 *   7. the visitor identity is a keyed fingerprint of a validated address: the
 *      raw address is never stored, two spellings of one address collapse into
 *      one identity, and rotating the salt changes every fingerprint;
 *   8. per-visitor history expires, is re-hashed out of the raw addresses an
 *      older release stored, and is capped rather than left to grow;
 *   9. one view per visitor per video inside one window, with the expiry of the
 *      transient set past the window it represents;
 *  10. the daily sweep only runs under cron, only writes rows it changed, and
 *      is scheduled on activation and dropped when the theme is switched away.
 *
 * What it is not: a real WordPress. `time()` is PHP's own function and cannot be
 * stubbed, so every check that depends on "now" uses a timestamp relative to it,
 * and the functions that take an explicit `$now` are the only ones driven with
 * fixed times. The `$wpdb` stub records the statement and its arguments instead
 * of running SQL, so what is pinned about the atomic increment is the shape of
 * the statement and the order of the calls around it, not MySQL's behavior.
 *
 * Run:  php tests/analytics-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

// The module's own windows are built from these at call time.
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = 0;

/* -------------------------------------------------------------------------
 * State the fixtures read and write.
 * ---------------------------------------------------------------------- */

$GLOBALS['meta']            = array();  // post ID => meta key => value, as stored.
$GLOBALS['writes']          = array();  // Every add/update/delete, in order.
$GLOBALS['cache']           = array();  // Object cache, group => key => value.
$GLOBALS['cache_hits']      = 0;
$GLOBALS['cache_misses']    = 0;
$GLOBALS['transients']      = array();
$GLOBALS['transient_sets']  = array();  // array( name, value, expiration ).
$GLOBALS['meta_reads_all']  = 0;        // get_post_meta() calls with one argument.
$GLOBALS['actions']         = array();  // hook => list of array( callback, priority, args ).
$GLOBALS['filters']         = array();  // hook => callable.
$GLOBALS['filters_applied'] = array();  // Every hook name apply_filters() saw.
$GLOBALS['clean_calls']     = array();  // clean_post_cache() arguments.
$GLOBALS['posts_queries']   = array();  // get_posts() argument sets, in order.
$GLOBALS['posts_fixture']   = array();  // Queue of ID lists get_posts() answers with.
$GLOBALS['doing_cron']      = false;
$GLOBALS['scheduled']       = array();  // hook => timestamp.
$GLOBALS['scheduled_calls'] = array();
$GLOBALS['scheduled_clear'] = array();
$GLOBALS['salt']            = 'test-salt-one';

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as this module touches.
 * ---------------------------------------------------------------------- */

function apply_filters( $hook, $value, ...$args ) {
	$GLOBALS['filters_applied'][] = $hook;

	if ( isset( $GLOBALS['filters'][ $hook ] ) ) {
		return call_user_func( $GLOBALS['filters'][ $hook ], $value, ...$args );
	}

	return $value;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['actions'][ $hook ][] = array( 'callback' => $callback, 'priority' => $priority, 'args' => $args );
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['actions'][ $hook ][] = array( 'callback' => $callback, 'priority' => $priority, 'args' => $args );
}

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_text_field( $value ) {
	return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function wp_salt( $scheme = 'auth' ) {
	return $GLOBALS['salt'] . '|' . $scheme;
}

function wp_cache_get( $key, $group = '' ) {
	if ( isset( $GLOBALS['cache'][ $group ][ $key ] ) ) {
		++$GLOBALS['cache_hits'];

		return $GLOBALS['cache'][ $group ][ $key ];
	}

	++$GLOBALS['cache_misses'];

	return false;
}

function wp_cache_set( $key, $value, $group = '', $expire = 0 ) {
	$GLOBALS['cache'][ $group ][ $key ] = $value;

	return true;
}

function wp_cache_delete( $key, $group = '' ) {
	$existed = isset( $GLOBALS['cache'][ $group ][ $key ] );

	unset( $GLOBALS['cache'][ $group ][ $key ] );

	return $existed;
}

function get_transient( $name ) {
	return isset( $GLOBALS['transients'][ $name ] ) ? $GLOBALS['transients'][ $name ] : false;
}

function set_transient( $name, $value, $expiration = 0 ) {
	$GLOBALS['transients'][ $name ] = $value;
	$GLOBALS['transient_sets'][]    = array( $name, $value, $expiration );

	return true;
}

/**
 * Read post meta, in the two shapes the module asks for.
 *
 * A one-argument call is "the whole row" - core's own shape, key => array of
 * values - and the number of those calls is what proves the module batches its
 * five counters into one read. The single-value shape hands back whatever was
 * stored, an array included, exactly as core does.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key, empty for the whole row.
 * @param bool   $single   Whether one value was asked for.
 * @return mixed
 */
function get_post_meta( $post_id, $meta_key = '', $single = false ) {
	$post_id = (int) $post_id;

	if ( 1 === func_num_args() ) {
		++$GLOBALS['meta_reads_all'];

		$row = isset( $GLOBALS['meta'][ $post_id ] ) ? $GLOBALS['meta'][ $post_id ] : array();
		$out = array();

		foreach ( $row as $key => $value ) {
			$out[ $key ] = is_array( $value ) ? $value : array( $value );
		}

		return $out;
	}

	if ( ! isset( $GLOBALS['meta'][ $post_id ][ $meta_key ] ) ) {
		return $single ? '' : array();
	}

	$value = $GLOBALS['meta'][ $post_id ][ $meta_key ];

	if ( $single ) {
		return $value;
	}

	return is_array( $value ) ? $value : array( $value );
}

function update_post_meta( $post_id, $meta_key, $meta_value ) {
	$GLOBALS['meta'][ (int) $post_id ][ $meta_key ] = $meta_value;
	$GLOBALS['writes'][]                           = array( 'update', (int) $post_id, $meta_key, $meta_value );

	return true;
}

function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
	if ( $unique && isset( $GLOBALS['meta'][ (int) $post_id ][ $meta_key ] ) ) {
		return false;
	}

	$GLOBALS['meta'][ (int) $post_id ][ $meta_key ] = $meta_value;
	$GLOBALS['writes'][]                           = array( 'add', (int) $post_id, $meta_key, $meta_value );

	return true;
}

function delete_post_meta( $post_id, $meta_key ) {
	$existed = isset( $GLOBALS['meta'][ (int) $post_id ][ $meta_key ] );

	unset( $GLOBALS['meta'][ (int) $post_id ][ $meta_key ] );
	$GLOBALS['writes'][] = array( 'delete', (int) $post_id, $meta_key, null );

	return $existed;
}

function clean_post_cache( $post_id ) {
	$GLOBALS['clean_calls'][] = (int) $post_id;
}

function get_posts( $args = array() ) {
	$GLOBALS['posts_queries'][] = $args;

	return $GLOBALS['posts_fixture'] ? array_shift( $GLOBALS['posts_fixture'] ) : array();
}

function wp_doing_cron() {
	return (bool) $GLOBALS['doing_cron'];
}

function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['scheduled'][ $hook ] ) ? $GLOBALS['scheduled'][ $hook ] : false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['scheduled'][ $hook ] = $timestamp;
	$GLOBALS['scheduled_calls'][]  = array( $timestamp, $recurrence, $hook );

	return true;
}

function wp_clear_scheduled_hook( $hook ) {
	$GLOBALS['scheduled_clear'][] = $hook;

	unset( $GLOBALS['scheduled'][ $hook ] );

	return 0;
}

/**
 * The database, in the two calls the atomic counter makes.
 *
 * prepare() records the statement and its arguments and hands the statement
 * back unchanged, so an assertion can prove the post ID travelled as a
 * placeholder instead of being interpolated into the SQL. query() and get_var()
 * answer with whatever the test scripted.
 */
class MT_WPDB {

	/** @var string */
	public $postmeta = 'wp_postmeta';

	/** @var array[] Every prepare() call: array( statement, arguments ). */
	public $prepared = array();

	/** @var string[] Every query() statement. */
	public $queries = array();

	/** @var string[] Every get_var() statement. */
	public $lookups = array();

	/** @var array Scripted answers. */
	public $answers = array(
		'query'   => 1,
		'get_var' => null,
	);

	/**
	 * Record a statement and its arguments.
	 *
	 * @param string $query Statement.
	 * @param mixed  ...$args Placeholder values.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$this->prepared[] = array( $query, $args );

		return $query;
	}

	/**
	 * Record and answer an UPDATE.
	 *
	 * @param string $query Statement.
	 * @return mixed
	 */
	public function query( $query ) {
		$this->queries[] = $query;

		return $this->answers['query'];
	}

	/**
	 * Record and answer a SELECT.
	 *
	 * @param string $query Statement.
	 * @return mixed
	 */
	public function get_var( $query ) {
		$this->lookups[] = $query;

		return $this->answers['get_var'];
	}
}

/* -------------------------------------------------------------------------
 * The module under test.
 * ---------------------------------------------------------------------- */

require_once MAJESTIC_TUBE_DIR . '/inc/analytics.php';

$GLOBALS['wpdb'] = new MT_WPDB();

/* -------------------------------------------------------------------------
 * Fixtures and helpers
 * ---------------------------------------------------------------------- */

/**
 * Reset everything a check can move, so one section cannot leak into the next.
 *
 * @return void
 */
function mt_fresh() {
	$GLOBALS['meta']            = array();
	$GLOBALS['writes']          = array();
	$GLOBALS['cache']           = array();
	$GLOBALS['cache_hits']      = 0;
	$GLOBALS['cache_misses']    = 0;
	$GLOBALS['transients']      = array();
	$GLOBALS['transient_sets']  = array();
	$GLOBALS['meta_reads_all']  = 0;
	$GLOBALS['filters']         = array();
	$GLOBALS['filters_applied'] = array();
	$GLOBALS['clean_calls']     = array();
	$GLOBALS['posts_queries']   = array();
	$GLOBALS['posts_fixture']   = array();
	$GLOBALS['doing_cron']      = false;
	$GLOBALS['salt']            = 'test-salt-one';

	unset( $_SERVER['REMOTE_ADDR'] );

	$GLOBALS['wpdb'] = new MT_WPDB();
}

/**
 * Store a post's meta row.
 *
 * @param int   $post_id Post ID.
 * @param array $meta    Meta key => value.
 * @return void
 */
function mt_meta( $post_id, $meta ) {
	$GLOBALS['meta'][ (int) $post_id ] = $meta;
}

/**
 * Set, or clear, the visitor address the module reads.
 *
 * @param string $ip Address. An empty string clears it.
 * @return void
 */
function mt_client( $ip ) {
	if ( '' === $ip ) {
		unset( $_SERVER['REMOTE_ADDR'] );

		return;
	}

	$_SERVER['REMOTE_ADDR'] = $ip;
}

/**
 * Register a filter callback for one hook.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @return void
 */
function mt_filter( $hook, $callback ) {
	$GLOBALS['filters'][ $hook ] = $callback;
}

/**
 * The cached counter array for a post, or null when there is no entry.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function mt_cached( $post_id ) {
	$key = 'post_' . (int) $post_id;

	return isset( $GLOBALS['cache'][ MAJESTIC_TUBE_ANALYTICS_GROUP ][ $key ] )
		? $GLOBALS['cache'][ MAJESTIC_TUBE_ANALYTICS_GROUP ][ $key ]
		: null;
}

/**
 * Every write this suite saw for one meta key.
 *
 * @param string $meta_key Meta key.
 * @return array[]
 */
function mt_writes_for( $meta_key ) {
	$found = array();

	foreach ( $GLOBALS['writes'] as $write ) {
		if ( $write[2] === $meta_key ) {
			$found[] = $write;
		}
	}

	return $found;
}

/**
 * Every write this suite saw for one post.
 *
 * @param int $post_id Post ID.
 * @return array[]
 */
function mt_writes_on( $post_id ) {
	$found = array();

	foreach ( $GLOBALS['writes'] as $write ) {
		if ( $write[1] === (int) $post_id ) {
			$found[] = $write;
		}
	}

	return $found;
}

/**
 * The fingerprint of the visitor whose address is currently set.
 *
 * @return string
 */
function mt_client_fingerprint() {
	return majestic_tube_ip_fingerprint( majestic_tube_get_client_ip() );
}

/**
 * An array key shaped exactly like one of our fingerprints.
 *
 * @param int $seed Distinguishes one from another.
 * @return string
 */
function mt_fake_fingerprint( $seed ) {
	return str_pad( dechex( $seed ), 32, '0', STR_PAD_LEFT );
}

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

/* -------------------------------------------------------------------------
 * The counters it owns
 * ---------------------------------------------------------------------- */

echo "\nThe counters it owns\n";

$map = majestic_tube_analytics_meta_map();

check(
	'the five stored keys are the original theme\'s, in one map',
	array(
		'post_views_count' => 'views',
		'likes_count'      => 'likes',
		'dislikes_count'   => 'dislikes',
		'rate'             => 'rate',
		'reported_count'   => 'reported',
	) === $map,
	'got: ' . var_export( $map, true )
);
check(
	'and the keys are listed in the order they are read',
	array( 'post_views_count', 'likes_count', 'dislikes_count', 'rate', 'reported_count' ) === majestic_tube_analytics_meta_keys()
);
check( 'the cache key names the post', 'post_42' === majestic_tube_analytics_cache_key( 42 ) );
check( 'and a numeric string is normalised', 'post_42' === majestic_tube_analytics_cache_key( '42' ) );
check(
	'the group is the module\'s own',
	'majestic_tube_analytics' === MAJESTIC_TUBE_ANALYTICS_GROUP,
	'a shared group would let one flush evict unrelated entries'
);

/* -------------------------------------------------------------------------
 * One read for five counters
 * ---------------------------------------------------------------------- */

echo "\nOne read for five counters\n";

mt_fresh();
mt_meta(
	7,
	array(
		'post_views_count' => '120',
		'likes_count'      => '30',
		'dislikes_count'   => '10',
		'rate'             => '75',
		'reported_count'   => '2',
	)
);

$data = majestic_tube_analytics_get( 7 );

check( 'a whole row is fetched once', 1 === $GLOBALS['meta_reads_all'], 'reads: ' . $GLOBALS['meta_reads_all'] );
check(
	'and every counter comes out as an integer',
	array( 'views' => 120, 'likes' => 30, 'dislikes' => 10, 'rate' => 75, 'reported' => 2 ) === $data,
	'got: ' . var_export( $data, true )
);
check( 'a string in the database becomes a number', is_int( $data['views'] ) );

$GLOBALS['meta_reads_all'] = 0;

$other_fields = array(
	majestic_tube_analytics_get_field( 7, 'views' ),
	majestic_tube_analytics_get_field( 7, 'dislikes' ),
	majestic_tube_analytics_get_field( 7, 'rate' ),
	majestic_tube_analytics_get_field( 7, 'reported' ),
);

check( 'a single field reads from the memoised row', 30 === majestic_tube_analytics_get_field( 7, 'likes' ) );
check(
	'and the other four add no read at all',
	0 === $GLOBALS['meta_reads_all'] && array( 120, 10, 75, 2 ) === $other_fields,
	'reads: ' . $GLOBALS['meta_reads_all'] . ' got: ' . var_export( $other_fields, true )
);
check( 'an unknown field is 0, not a warning', 0 === majestic_tube_analytics_get_field( 7, 'nonsense' ) );
check(
	'the cached entry is what the read produced',
	array( 'views' => 120, 'likes' => 30, 'dislikes' => 10, 'rate' => 75, 'reported' => 2 ) === mt_cached( 7 )
);

mt_fresh();
mt_meta( 7, array( 'post_views_count' => '120' ) );
$GLOBALS['cache'][ MAJESTIC_TUBE_ANALYTICS_GROUP ]['post_7'] = 'not an array';

check( 'a cache entry that is not an array is not trusted', 120 === majestic_tube_analytics_get_field( 7, 'views' ) );
check( 'and the row is re-read to replace it', 1 === $GLOBALS['meta_reads_all'] );

mt_fresh();

check(
	'a post with no counters at all reads as zero',
	array( 'views' => 0, 'likes' => 0, 'dislikes' => 0, 'rate' => 0, 'reported' => 0 ) === majestic_tube_analytics_get( 999 )
);
check( 'and the row is storable rather than an error', 0 === majestic_tube_analytics_get_field( 999, 'views' ) );

mt_fresh();
mt_meta(
	7,
	array(
		'post_views_count' => array(),
		'likes_count'      => array(),
	)
);

check(
	'a meta row written with no value is 0, not an undefined key',
	0 === majestic_tube_analytics_get_field( 7, 'views' ) && 0 === majestic_tube_analytics_get_field( 7, 'likes' ),
	'reading $meta[ $key ][0] straight off an empty array is the PHP 8 trap this exists for'
);

echo "\nReading one counter out of a raw row\n";

check( 'an absent key falls back', 5 === majestic_tube_analytics_meta_int( array(), 'likes_count', 5 ) );
check( 'and defaults to 0', 0 === majestic_tube_analytics_meta_int( array(), 'likes_count' ) );
check( 'an empty value falls back', 5 === majestic_tube_analytics_meta_int( array( 'likes_count' => array() ), 'likes_count', 5 ) );
check( 'a scalar value is read as a one-element list', 9 === majestic_tube_analytics_meta_int( array( 'likes_count' => '9' ), 'likes_count' ) );
check( 'a negative value is absinted', 4 === majestic_tube_analytics_meta_int( array( 'likes_count' => '-4' ), 'likes_count' ) );
check( 'trailing rubbish is dropped', 12 === majestic_tube_analytics_meta_int( array( 'likes_count' => array( '12abc' ) ), 'likes_count' ) );
check( 'a row that is not an array is tolerated', 0 === majestic_tube_analytics_meta_int( 'nonsense', 'likes_count' ) );

/* -------------------------------------------------------------------------
 * The imported rating shape
 * ---------------------------------------------------------------------- */

echo "\nThe imported rating shape\n";

mt_fresh();
mt_meta( 8, array( 'thumbs_up' => '80', 'thumbs_down' => '20' ) );
$imported = majestic_tube_analytics_get( 8 );

check( 'an imported pair stands in for the native one', 80 === $imported['likes'] && 20 === $imported['dislikes'] );
check( 'and it really reads as a percentage', 80 === majestic_tube_analytics_like_rate( 8 ) );

mt_fresh();
mt_meta(
	8,
	array(
		'thumbs_up'   => '80',
		'thumbs_down' => '20',
		'likes_count' => '3',
	)
);
$mixed = majestic_tube_analytics_get( 8 );

check( 'the native count wins once there is one', 3 === $mixed['likes'] );
check( 'while the half with no native count still falls back', 20 === $mixed['dislikes'] );

mt_fresh();
mt_meta( 8, array( 'likes_count' => '3' ) );
$native = majestic_tube_analytics_get( 8 );

check( 'a lone native count leaves the other half at zero', 3 === $native['likes'] && 0 === $native['dislikes'] );

mt_fresh();
mt_meta(
	8,
	array(
		'likes_count'    => '0',
		'dislikes_count' => '0',
		'thumbs_up'      => '80',
		'thumbs_down'    => '20',
	)
);
$zeroed = majestic_tube_analytics_get( 8 );

check(
	'a native pair of zeros is not replaced by the import',
	0 === $zeroed['likes'] && 0 === $zeroed['dislikes'],
	'isset() is true for zero, which is what keeps the imported pair out'
);

/* -------------------------------------------------------------------------
 * The like percentage
 * ---------------------------------------------------------------------- */

echo "\nThe like percentage\n";

mt_fresh();
mt_meta(
	9,
	array(
		'likes_count'    => '1',
		'dislikes_count' => '0',
	)
);

check( 'one vote out of one is 100%', 100 === majestic_tube_analytics_like_rate( 9 ), 'the ratio is likes over likes plus dislikes' );

mt_fresh();
mt_meta(
	9,
	array(
		'likes_count'    => '1',
		'dislikes_count' => '1',
	)
);

check( 'one of each is an even split', 50 === majestic_tube_analytics_like_rate( 9 ) );

mt_fresh();
mt_meta(
	9,
	array(
		'likes_count'    => '1',
		'dislikes_count' => '2',
	)
);

check( 'one in three is 33%', 33 === majestic_tube_analytics_like_rate( 9 ) );

mt_fresh();
mt_meta(
	9,
	array(
		'likes_count'    => '2',
		'dislikes_count' => '1',
	)
);

check( 'two in three is 67%, rounded rather than floored', 67 === majestic_tube_analytics_like_rate( 9 ) );

mt_fresh();

check( 'no votes at all is 0%, not a division by zero', 0 === majestic_tube_analytics_like_rate( 9 ) );

/* -------------------------------------------------------------------------
 * Writing counters through the cache
 * ---------------------------------------------------------------------- */

echo "\nWriting counters through the cache\n";

mt_fresh();
mt_meta(
	7,
	array(
		'post_views_count' => '10',
		'likes_count'      => '2',
		'dislikes_count'   => '1',
	)
);
majestic_tube_analytics_get( 7 );
majestic_tube_analytics_set( 7, array( 'likes' => 5 ) );

$merged = mt_cached( 7 );

check(
	'a partial write keeps the counters it did not name',
	5 === $merged['likes'] && 10 === $merged['views'] && 1 === $merged['dislikes'],
	'got: ' . var_export( $merged, true )
);
check( 'and casts what it was handed', is_int( $merged['likes'] ) );

majestic_tube_analytics_set(
	7,
	array(
		'views'    => '3',
		'nonsense' => 99,
	)
);

check( 'an unknown field is not stored', ! isset( mt_cached( 7 )['nonsense'] ) );
check( 'while the named one is', 3 === mt_cached( 7 )['views'] );

majestic_tube_analytics_set( 7, 'not an array' );

check( 'a non-array update writes nothing at all', 3 === mt_cached( 7 )['views'] );

majestic_tube_analytics_flush_post( 7 );

check( 'a flush drops that post\'s entry', null === mt_cached( 7 ) );

majestic_tube_analytics_set( 8, array( 'views' => 1 ) );
majestic_tube_analytics_flush_post( 7 );

check( 'and leaves another post\'s entry alone', 1 === mt_cached( 8 )['views'] );

/* -------------------------------------------------------------------------
 * The atomic view increment
 * ---------------------------------------------------------------------- */

echo "\nThe atomic view increment\n";

mt_fresh();
mt_meta(
	7,
	array(
		'post_views_count' => '41',
		'likes_count'      => '3',
	)
);
$GLOBALS['wpdb']->answers['query']   = 1;
$GLOBALS['wpdb']->answers['get_var'] = '42';

$views = majestic_tube_analytics_increment_views( 7 );

check( 'one statement is issued', 1 === count( $GLOBALS['wpdb']->queries ) );
check(
	'it increments in SQL rather than in PHP',
	false !== strpos( $GLOBALS['wpdb']->prepared[0][0], 'CAST( meta_value AS UNSIGNED ) + 1' ),
	$GLOBALS['wpdb']->prepared[0][0]
);
check(
	'it targets the view counter and nothing else',
	false !== strpos( $GLOBALS['wpdb']->prepared[0][0], "'post_views_count'" ) && false === strpos( $GLOBALS['wpdb']->prepared[0][0], 'likes_count' ),
	$GLOBALS['wpdb']->prepared[0][0]
);
check(
	'the post ID travels as a placeholder',
	false !== strpos( $GLOBALS['wpdb']->prepared[0][0], '%d' ) && array( 7 ) === $GLOBALS['wpdb']->prepared[0][1],
	'got: ' . var_export( $GLOBALS['wpdb']->prepared[0], true )
);
check( 'the answer is what the database reports', 42 === $views, 'got: ' . var_export( $views, true ) );
check( 'the cached row is dropped before the value is read back', in_array( 7, $GLOBALS['clean_calls'], true ) );
check( 'and the value read back is past the cache', 1 === count( $GLOBALS['wpdb']->lookups ), 'the new count cannot come from the cached copy' );
check( 'the cache is repopulated with it', 42 === mt_cached( 7 )['views'] );
check( 'without losing the other counters', 3 === mt_cached( 7 )['likes'] );
check( 'and no meta write is issued for the counter', array() === mt_writes_for( 'post_views_count' ) );

/*
 * The create-at-1 branch is for a post with no counter row at all: the UPDATE
 * matched nothing, which is the only way it reports zero affected rows here.
 * The fixture therefore carries no `post_views_count` key - add_post_meta() is
 * unique, so a fixture that pre-set it would model a row that already exists
 * and the row would never be created.
 */
mt_fresh();
$GLOBALS['wpdb']->answers['query']   = 0;
$GLOBALS['wpdb']->answers['get_var'] = '1';

majestic_tube_analytics_increment_views( 7 );

check(
	'no counter row yet: the first view creates it at 1',
	array( 'add', 7, 'post_views_count', 1 ) === $GLOBALS['writes'][0],
	'got: ' . var_export( $GLOBALS['writes'], true )
);
check( 'and only that one write is made', 1 === count( $GLOBALS['writes'] ) );

mt_fresh();
mt_meta( 7, array( 'post_views_count' => '5' ) );
$GLOBALS['wpdb']->answers['query'] = false;

check( 'a failed statement falls back to the meta API', 6 === majestic_tube_analytics_increment_views( 7 ) );
check(
	'which writes the counter it computed',
	array( 'update', 7, 'post_views_count', 6 ) === $GLOBALS['writes'][0],
	'got: ' . var_export( $GLOBALS['writes'], true )
);
check( 'and caches it', 6 === mt_cached( 7 )['views'] );

mt_fresh();
mt_meta( 7, array( 'post_views_count' => '5' ) );
unset( $GLOBALS['wpdb'] );

check( 'without a database object the same fallback runs', 6 === majestic_tube_analytics_increment_views( 7 ) );

mt_fresh();
mt_meta( 7, array( 'post_views_count' => '5' ) );

check( 'a missing post ID counts nothing', 0 === majestic_tube_analytics_increment_views( 0 ) );
check( 'and writes nothing', array() === $GLOBALS['writes'] );

echo "\nReading past a stale cache\n";

mt_fresh();
mt_meta( 7, array( 'post_views_count' => '9' ) );
unset( $GLOBALS['wpdb'] );

check( 'with no database object the cached field answers', 9 === majestic_tube_analytics_field_from_db( 7, 'post_views_count' ) );

$GLOBALS['wpdb']                       = new MT_WPDB();
$GLOBALS['wpdb']->answers['get_var']   = null;

check( 'an absent row reads as 0', 0 === majestic_tube_analytics_field_from_db( 7, 'post_views_count' ) );
check(
	'and the statement is a parameterised read of one key',
	false !== strpos( $GLOBALS['wpdb']->prepared[0][0], 'meta_key = %s' ) && array( 7, 'post_views_count' ) === $GLOBALS['wpdb']->prepared[0][1],
	'got: ' . var_export( $GLOBALS['wpdb']->prepared[0], true )
);
check( 'the meta key maps to its field name', 'likes' === majestic_tube_analytics_field_for_meta( 'likes_count' ) );
check( 'and an unknown key maps to views rather than to nothing', 'views' === majestic_tube_analytics_field_for_meta( 'made_up' ) );

/* -------------------------------------------------------------------------
 * The cache is invalidated by any writer
 * ---------------------------------------------------------------------- */

echo "\nThe cache is invalidated by any writer\n";

foreach ( array( 'updated_post_meta', 'added_post_meta', 'deleted_post_meta' ) as $hook ) {
	$registered = isset( $GLOBALS['actions'][ $hook ][0] ) ? $GLOBALS['actions'][ $hook ][0] : null;

	check(
		'a write through the meta API flushes the cache (' . $hook . ')',
		$registered && 'majestic_tube_analytics_invalidate_on_meta_change' === $registered['callback'] && 10 === $registered['priority'] && 3 === $registered['args'],
		'got: ' . var_export( $registered, true )
	);
}

foreach ( array( 'deleted_post', 'trashed_post' ) as $hook ) {
	$registered = isset( $GLOBALS['actions'][ $hook ][0] ) ? $GLOBALS['actions'][ $hook ][0] : null;

	check(
		'a removed post drops its counters (' . $hook . ')',
		$registered && 'majestic_tube_analytics_invalidate_on_delete' === $registered['callback'],
		'got: ' . var_export( $registered, true )
	);
}

mt_fresh();
majestic_tube_analytics_set( 7, array( 'views' => 5 ) );
majestic_tube_analytics_invalidate_on_meta_change( 1, 7, 'likes_count' );

check( 'a counter written from outside the theme flushes the entry', null === mt_cached( 7 ) );

majestic_tube_analytics_set( 7, array( 'views' => 5 ) );
majestic_tube_analytics_invalidate_on_meta_change( 2, 7, 'thumb' );

check(
	'an unrelated meta edit does not',
	5 === mt_cached( 7 )['views'],
	'flushing on every meta write would undo the caching entirely'
);

majestic_tube_analytics_set( 7, array( 'views' => 5 ) );
majestic_tube_analytics_invalidate_on_delete( 7 );

check( 'deleting the post drops the entry', null === mt_cached( 7 ) );

/* -------------------------------------------------------------------------
 * Visitor identity
 * ---------------------------------------------------------------------- */

echo "\nVisitor identity\n";

mt_fresh();
mt_client( '203.0.113.9' );

check( 'the address is read from REMOTE_ADDR', '203.0.113.9' === majestic_tube_get_client_ip() );

mt_client( '' );

check( 'no address at all is an empty string', '' === majestic_tube_get_client_ip() );

mt_client( 'not an address' );

check( 'a value that is not an address is discarded', '' === majestic_tube_get_client_ip() );

mt_client( ' 203.0.113.9 ' );

check( 'surrounding space is cleaned off', '203.0.113.9' === majestic_tube_get_client_ip() );

mt_client( '203.0.113.9' );
mt_filter(
	'majestic_tube_client_ip',
	function ( $ip ) {
		return '198.51.100.7';
	}
);

check( 'a site behind a trusted proxy can hand over its own value', '198.51.100.7' === majestic_tube_get_client_ip() );

mt_filter(
	'majestic_tube_client_ip',
	function ( $ip ) {
		return 'not an address';
	}
);

check( 'but a filter that hands back rubbish falls back to REMOTE_ADDR', '203.0.113.9' === majestic_tube_get_client_ip() );

mt_fresh();
mt_client( '203.0.113.9' );

$ipv4 = majestic_tube_ip_fingerprint( majestic_tube_get_client_ip() );

check( 'a visitor becomes 32 hex characters', 1 === preg_match( '/^[a-f0-9]{32}$/', $ipv4 ), 'got: ' . $ipv4 );
check( 'the same visitor always gets the same one', $ipv4 === majestic_tube_ip_fingerprint( '203.0.113.9' ) );
check( 'two addresses do not collide', $ipv4 !== majestic_tube_ip_fingerprint( '203.0.113.10' ) );
check( 'and the address is not in the result', false === strpos( $ipv4, '.' ) );
check( 'an empty address has no fingerprint', '' === majestic_tube_ip_fingerprint( '' ) );
check( 'and neither has rubbish', '' === majestic_tube_ip_fingerprint( 'localhost' ) );

check(
	'two spellings of one IPv6 address are one visitor',
	'' !== majestic_tube_ip_fingerprint( '::1' )
		&& majestic_tube_ip_fingerprint( '::1' ) === majestic_tube_ip_fingerprint( '0:0:0:0:0:0:0:1' ),
	'the address is canonicalised through inet_pton(), so a padded form cannot act twice'
);
check(
	'and letter case does not make a second visitor',
	majestic_tube_ip_fingerprint( '2001:DB8::1' ) === majestic_tube_ip_fingerprint( '2001:db8::1' )
);

$before          = majestic_tube_ip_fingerprint( '203.0.113.9' );
$GLOBALS['salt'] = 'test-salt-two';
$after           = majestic_tube_ip_fingerprint( '203.0.113.9' );

check(
	'rotating the salt changes every fingerprint',
	$before !== $after,
	'the hash is keyed with wp_salt(), which is what keeps a database dump from being an address list'
);

$GLOBALS['salt'] = 'test-salt-one';

check( 'one of ours is recognized', majestic_tube_is_fingerprint( $ipv4 ) );
check( 'a raw address is not', ! majestic_tube_is_fingerprint( '203.0.113.9' ) );
check( 'upper case hex is not', ! majestic_tube_is_fingerprint( strtoupper( $ipv4 ) ) );
check( 'a short hash is not', ! majestic_tube_is_fingerprint( substr( $ipv4, 0, 31 ) ) );
check( 'and neither is a non-string', ! majestic_tube_is_fingerprint( 42 ) && ! majestic_tube_is_fingerprint( array( $ipv4 ) ) );

/* -------------------------------------------------------------------------
 * Per-visitor history
 * ---------------------------------------------------------------------- */

echo "\nPer-visitor history\n";

check(
	'both history keys get a day by default',
	array(
		'voted_IP'     => DAY_IN_SECONDS,
		'reported_ips' => DAY_IN_SECONDS,
	) === majestic_tube_ip_history_windows()
);
check( 'and a key can be asked for its own window', DAY_IN_SECONDS === majestic_tube_ip_history_window( 'reported_ips' ) );
check( 'an unknown key still has one', DAY_IN_SECONDS === majestic_tube_ip_history_window( 'nothing' ) );

mt_fresh();
mt_filter(
	'majestic_tube_ip_history_windows',
	function ( $windows ) {
		$windows['voted_IP']     = 120;
		$windows['reported_ips'] = 0;
		$windows['unrelated']    = 999;

		return $windows;
	}
);

$windows = majestic_tube_ip_history_windows();

check( 'a site can shorten a window', 120 === $windows['voted_IP'] );
check(
	'a zero window is raised to a second rather than switching expiry off',
	1 === $windows['reported_ips'],
	'got: ' . var_export( $windows['reported_ips'], true )
);
check( 'and a key the module does not know is not added', ! isset( $windows['unrelated'] ) );

$fp_a = mt_fake_fingerprint( 0xA );
$fp_b = mt_fake_fingerprint( 0xB );

check(
	'an entry inside its window is kept',
	array( $fp_a => 1000 ) === majestic_tube_clean_ip_history( array( $fp_a => 1000 ), 100, 1050 )
);
check(
	'an entry at the edge of it is dropped',
	array() === majestic_tube_clean_ip_history( array( $fp_a => 950 ), 100, 1050 ),
	'expiry is >= the window, so the window is the age an entry survives'
);
check(
	'a raw address an older release stored is re-hashed, not dropped',
	array( majestic_tube_ip_fingerprint( '203.0.113.9' ) => 1040 ) === majestic_tube_clean_ip_history( array( '203.0.113.9' => 1040 ), 100, 1050 ),
	'this is what lets a visitor who already voted stay recognized while the address leaves the database'
);
check( 'junk is dropped', array() === majestic_tube_clean_ip_history( array( 'not an address' => 1040 ), 100, 1050 ) );
check(
	'the current visitor is appended',
	array(
		$fp_a => 1000,
		$fp_b => 999,
	) === majestic_tube_clean_ip_history( array( $fp_a => 1000 ), 100, 1050, $fp_b, 999 )
);
check(
	'and stamped with the given time when no stamp is passed',
	( majestic_tube_clean_ip_history( array(), 100, 1050, $fp_b )[ $fp_b ] ?? 0 ) === 1050
);
check(
	'a zero window still expires everything but the current entry',
	array( $fp_b => 1050 ) === majestic_tube_clean_ip_history( array( $fp_a => 1049 ), 0, 1050, $fp_b )
);

$flood = array();

for ( $i = 1; $i <= MAJESTIC_TUBE_IP_HISTORY_MAX + 1; $i++ ) {
	$flood[ mt_fake_fingerprint( $i ) ] = 1000 + $i;
}

$capped = majestic_tube_clean_ip_history( $flood, 1000000, 100000 );

check( 'a flood is capped at the documented maximum', MAJESTIC_TUBE_IP_HISTORY_MAX === count( $capped ), 'got: ' . count( $capped ) );
check(
	'the oldest entries are the ones dropped',
	! isset( $capped[ mt_fake_fingerprint( 1 ) ] ) && isset( $capped[ mt_fake_fingerprint( MAJESTIC_TUBE_IP_HISTORY_MAX + 1 ) ] )
);

mt_fresh();
mt_client( '203.0.113.9' );
$me = mt_client_fingerprint();

check( 'nothing recorded yet is not a match', ! majestic_tube_ip_was_recorded_recently( 7, 'voted_IP' ) );

mt_meta( 7, array( 'voted_IP' => array( $me => time() - 10 ) ) );

check( 'a visitor recorded just now is recognized', majestic_tube_ip_was_recorded_recently( 7, 'voted_IP' ) );
check( 'a shorter window of the caller\'s own is honoured', ! majestic_tube_ip_was_recorded_recently( 7, 'voted_IP', 5 ) );
check( 'as is a longer one', majestic_tube_ip_was_recorded_recently( 7, 'voted_IP', 3600 ) );

mt_meta( 7, array( 'voted_IP' => array( $me => time() - 2 * DAY_IN_SECONDS ) ) );

check( 'while a visit outside the window is not', ! majestic_tube_ip_was_recorded_recently( 7, 'voted_IP' ) );

mt_meta( 7, array( 'voted_IP' => 'not an array' ) );

check( 'a row that is not a list is not a match', ! majestic_tube_ip_was_recorded_recently( 7, 'voted_IP' ) );

mt_meta( 7, array( 'voted_IP' => array( $me => time() - 10 ) ) );
mt_client( '' );

check( 'and a visitor with no address never matches', ! majestic_tube_ip_was_recorded_recently( 7, 'voted_IP' ) );

mt_fresh();
mt_client( '203.0.113.9' );
$me = mt_client_fingerprint();

check( 'recording writes the history', majestic_tube_record_ip_history( 7, 'voted_IP' ) );
check(
	'under the fingerprint, not the address',
	isset( $GLOBALS['meta'][7]['voted_IP'][ $me ] ) && ! isset( $GLOBALS['meta'][7]['voted_IP']['203.0.113.9'] )
);
check( 'as a single meta row', 1 === count( mt_writes_for( 'voted_IP' ) ) );
check( 'and the plain address is nowhere in what was stored', false === strpos( (string) json_encode( $GLOBALS['meta'][7]['voted_IP'] ), '203.0.113.9' ) );

mt_fresh();
mt_client( '203.0.113.9' );
$other = majestic_tube_ip_fingerprint( '198.51.100.7' );

mt_meta(
	7,
	array(
		'voted_IP' => array(
			$other         => time() - 2 * DAY_IN_SECONDS,
			'198.51.100.9' => time() - 10,
		),
	)
);

majestic_tube_record_ip_history( 7, 'voted_IP' );
$stored = $GLOBALS['meta'][7]['voted_IP'];

check( 'a write expires what has aged out', ! isset( $stored[ $other ] ) );
check( 'and converts what an older release left behind', isset( $stored[ majestic_tube_ip_fingerprint( '198.51.100.9' ) ] ) );
check( 'while the current visitor is added', isset( $stored[ mt_client_fingerprint() ] ) );

mt_fresh();
mt_client( '' );

check( 'a visitor with no address records nothing', ! majestic_tube_record_ip_history( 7, 'voted_IP' ) );
check( 'and no row is written', array() === $GLOBALS['writes'] );

mt_fresh();
mt_client( '203.0.113.9' );

check( 'a missing post ID records nothing', ! majestic_tube_record_ip_history( 0, 'voted_IP' ) );

/* -------------------------------------------------------------------------
 * One view per visitor per video
 * ---------------------------------------------------------------------- */

echo "\nOne view per visitor per video\n";

mt_fresh();
mt_client( '203.0.113.9' );

$key = majestic_tube_view_dedup_key( majestic_tube_get_client_ip() );

check( 'the transient name is prefixed', 0 === strpos( $key, 'mtviews_' ), 'got: ' . $key );
check( 'and it is the fingerprint, not the address', $key === 'mtviews_' . mt_client_fingerprint() );
check( 'with no address the name is still total', 'mtviews_' . md5( '' ) === majestic_tube_view_dedup_key( '' ) );

mt_fresh();
mt_client( '203.0.113.9' );

check( 'the first view of a video is not a repeat', false === majestic_tube_visitor_already_counted_view( 7 ) );
check( 'and the visit is remembered', 1 === count( $GLOBALS['transient_sets'] ) );
check( 'under the visitor\'s own name', $GLOBALS['transient_sets'][0][0] === 'mtviews_' . mt_client_fingerprint() );
check( 'with the post ID inside it', isset( $GLOBALS['transient_sets'][0][1][7] ) );
check(
	'the entry outlives the window it represents',
	majestic_tube_view_dedup_window() + HOUR_IN_SECONDS === $GLOBALS['transient_sets'][0][2],
	'got: ' . var_export( $GLOBALS['transient_sets'][0][2], true )
);
check( 'the second view of the same video is a repeat', majestic_tube_visitor_already_counted_view( 7 ) );
check( 'but a different video is not', ! majestic_tube_visitor_already_counted_view( 8 ) );

mt_fresh();
mt_client( '203.0.113.9' );
$GLOBALS['transients'][ 'mtviews_' . mt_client_fingerprint() ] = array(
	99 => time() - 2 * DAY_IN_SECONDS,
	7  => time() - 5,
);

check( 'a visit still inside the window is a repeat', majestic_tube_visitor_already_counted_view( 7 ) );
check(
	'and an entry that aged out is dropped from what is stored',
	! isset( $GLOBALS['transient_sets'][0][1][99] ),
	'this is what stops the list growing without bound and lets a returning visitor count again'
);
check( 'while the visit that was asked about is refreshed', isset( $GLOBALS['transient_sets'][0][1][7] ) );

mt_fresh();
mt_client( '203.0.113.9' );
$GLOBALS['transients'][ 'mtviews_' . mt_client_fingerprint() ] = array( 99 => time() - 2 * DAY_IN_SECONDS );

check( 'the same video seen outside the window counts again', false === majestic_tube_visitor_already_counted_view( 99 ) );

mt_fresh();
mt_client( '203.0.113.9' );
$history = array();

for ( $i = 1; $i <= MAJESTIC_TUBE_VIEW_DEDUP_MAX; $i++ ) {
	$history[ $i ] = time() - 10;
}

$GLOBALS['transients'][ 'mtviews_' . mt_client_fingerprint() ] = $history;

majestic_tube_visitor_already_counted_view( 9999 );
$kept = $GLOBALS['transient_sets'][0][1];

check( 'a visitor who browses a lot is capped too', MAJESTIC_TUBE_VIEW_DEDUP_MAX === count( $kept ), 'got: ' . count( $kept ) );
check( 'and the oldest view is the one dropped', ! isset( $kept[1] ) && isset( $kept[9999] ) );

mt_fresh();
mt_client( '203.0.113.9' );
mt_filter(
	'majestic_tube_view_dedup_enabled',
	function ( $enabled ) {
		return false;
	}
);

check( 'a site can switch de-duplication off', ! majestic_tube_visitor_already_counted_view( 7 ) );
check( 'and nothing is remembered while it is off', array() === $GLOBALS['transient_sets'] );

mt_fresh();
mt_client( '' );

check( 'a visitor with no address counts every view', ! majestic_tube_visitor_already_counted_view( 7 ) );
check( 'and leaves no history at all', array() === $GLOBALS['transient_sets'] );

mt_fresh();
mt_client( '203.0.113.9' );

check( 'a missing post ID counts nothing', ! majestic_tube_visitor_already_counted_view( 0 ) );

mt_filter(
	'majestic_tube_view_dedup_window',
	function ( $window ) {
		return 30;
	}
);

check( 'the window is filterable', 30 === majestic_tube_view_dedup_window() );

mt_filter(
	'majestic_tube_view_dedup_window',
	function ( $window ) {
		return 0;
	}
);

check( 'and a zero window falls back to the day rather than switching the check off', DAY_IN_SECONDS === majestic_tube_view_dedup_window() );

/* -------------------------------------------------------------------------
 * Expiring the history nobody touches
 * ---------------------------------------------------------------------- */

echo "\nExpiring the history nobody touches\n";

mt_fresh();
mt_client( '203.0.113.9' );

check( 'the sweep does nothing outside cron', 0 === majestic_tube_prune_expired_ip_history() );
check( 'and runs no query at all', array() === $GLOBALS['posts_queries'] );

mt_fresh();
mt_client( '203.0.113.9' );
$GLOBALS['doing_cron']    = true;
$GLOBALS['posts_fixture'] = array( array( 7, 8 ), array( 9 ) );

mt_meta( 7, array( 'voted_IP' => array( mt_fake_fingerprint( 1 ) => time() - 2 * DAY_IN_SECONDS ) ) );
mt_meta( 8, array( 'voted_IP' => array( mt_fake_fingerprint( 2 ) => time() - 10 ) ) );
mt_meta( 9, array( 'reported_ips' => array( '198.51.100.9' => time() - 10 ) ) );

$rows = majestic_tube_prune_expired_ip_history();

check( 'the sweep walks one batch per key', 2 === count( $GLOBALS['posts_queries'] ) );
check( 'over every post status, or a trashed video keeps its fingerprints', 'any' === $GLOBALS['posts_queries'][0]['post_status'] );
check(
	'looking only at posts that carry the key',
	'voted_IP' === $GLOBALS['posts_queries'][0]['meta_key'] && 'reported_ips' === $GLOBALS['posts_queries'][1]['meta_key']
);
check( 'in a bounded batch', 200 === $GLOBALS['posts_queries'][0]['posts_per_page'] );
check( 'and counting nothing it does not need', true === $GLOBALS['posts_queries'][0]['no_found_rows'] );

check( 'a row whose entries all expired is emptied', 2 === $rows, 'got: ' . var_export( $rows, true ) );
check(
	'and the emptied row is deleted, not kept as an empty list',
	array( 'delete', 7, 'voted_IP', null ) === $GLOBALS['writes'][0],
	'got: ' . var_export( $GLOBALS['writes'], true )
);
check( 'a row that only needed re-hashing is written', in_array( 'update', array_column( $GLOBALS['writes'], 0 ), true ) );
check( 'a row that did not change is not written', array() === mt_writes_on( 8 ), 'got: ' . var_export( $GLOBALS['writes'], true ) );
check( 'and the count is the rows it changed', 2 === count( $GLOBALS['writes'] ), 'got: ' . var_export( $GLOBALS['writes'], true ) );

mt_fresh();
mt_client( '203.0.113.9' );
$GLOBALS['doing_cron']    = true;
$GLOBALS['posts_fixture'] = array( array( 8 ), array() );

mt_meta( 8, array( 'voted_IP' => array( mt_fake_fingerprint( 3 ) => time() - 10 ) ) );
mt_filter(
	'majestic_tube_ip_prune_batch',
	function ( $batch ) {
		return 5;
	}
);

majestic_tube_prune_expired_ip_history();

check( 'a site can shrink the batch', 5 === $GLOBALS['posts_queries'][0]['posts_per_page'] );
check( 'and a row still inside its window is left alone', array() === $GLOBALS['writes'], 'got: ' . var_export( $GLOBALS['writes'], true ) );

echo "\nThe daily schedule\n";

mt_fresh();

$registered = isset( $GLOBALS['actions'][ MAJESTIC_TUBE_IP_PRUNE_HOOK ][0] ) ? $GLOBALS['actions'][ MAJESTIC_TUBE_IP_PRUNE_HOOK ][0] : null;

check(
	'the sweep is hooked under its own name',
	$registered && 'majestic_tube_prune_expired_ip_history' === $registered['callback'],
	'got: ' . var_export( $registered, true )
);

$GLOBALS['scheduled'] = array();
$before               = time();

majestic_tube_schedule_ip_history_prune();

check( 'activation schedules it', 1 === count( $GLOBALS['scheduled_calls'] ) );
check( 'daily', 'daily' === $GLOBALS['scheduled_calls'][0][1] );
check( 'under the same name the callback is hooked as', MAJESTIC_TUBE_IP_PRUNE_HOOK === $GLOBALS['scheduled_calls'][0][2] );
check( 'an hour out, so activation is not also its first run', $GLOBALS['scheduled_calls'][0][0] >= $before + HOUR_IN_SECONDS );

majestic_tube_schedule_ip_history_prune();

check( 'a second activation does not schedule it twice', 1 === count( $GLOBALS['scheduled_calls'] ) );

majestic_tube_unschedule_ip_history_prune();

check( 'switching theme drops the schedule', array( MAJESTIC_TUBE_IP_PRUNE_HOOK ) === $GLOBALS['scheduled_clear'] );
check(
	'and the switch is hooked',
	isset( $GLOBALS['actions']['switch_theme'][0] ) && 'majestic_tube_unschedule_ip_history_prune' === $GLOBALS['actions']['switch_theme'][0]['callback'],
	'a cron entry pointing at a theme that is gone never fires'
);

printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
