<?php
/**
 * Smoke test for inc/pagination.php.
 *
 * The module is the one place a visitor moves through a listing: the numbered
 * navigator under every archive, and the A-Z bar on the Categories, Actors and
 * Tags directories. It is also the only module that reads a value straight out
 * of the query string, which is why the letter reader is worth holding down.
 *
 * Four claims are pinned here, each one a way the module has to be right rather
 * than look right:
 *
 *   1. the navigator marks the current page and links its neighbours. A page
 *      that is printed as the current one must not also be a link, or a visitor
 *      can click it to reload where they already are, and the pages either side
 *      have to be links or pagination is a dead end;
 *   2. a listing with one page prints nothing at all, instead of a lone "1"
 *      under the last card;
 *   3. the letter filter reads one character from a query argument. `?letter=a`
 *      means A; `?letter[]=a`, a phrase, a punctuation mark and a value carrying
 *      markup are all discarded, because the value decides which terms a page
 *      lists and nothing guarantees what arrives;
 *   4. the term directory does the work once. Two reads of the same page run one
 *      terms query and one count query, and a term list that changed underneath
 *      the cache cannot be served from the entry built before the change - which
 *      is what WordPress's own `last_changed` marker in the key is for.
 *
 * inc/pagination.php is included, so the code under test is the code that
 * ships. What it is not: a real WordPress. The direct SELECT that builds the
 * letter map runs against a stub `$wpdb` that answers with fixture rows, so the
 * bucketing and the caching around it are pinned but the SQL statement itself
 * is not; the bar's URLs are built through stubs of add_query_arg() and
 * remove_query_arg(), which are WordPress's helpers rather than this module's.
 *
 * Run:  php tests/pagination-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

// inc/pagination.php builds one constant at include time, from these.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = 0;

/* -------------------------------------------------------------------------
 * State the fixtures read and write.
 * ---------------------------------------------------------------------- */

$GLOBALS['cache']         = array(); // Object cache, group => key => value.
$GLOBALS['transients']    = array();
$GLOBALS['options']       = array();
$GLOBALS['opts']          = array(); // wpst-options, as majestic_tube_get_option reads them.
$GLOBALS['query_vars']    = array();
$GLOBALS['paged']         = 0;
$GLOBALS['wp_query']      = null;
$GLOBALS['taxonomies']    = array( 'actors' );
$GLOBALS['_GET']          = array();

$GLOBALS['terms_args']    = array(); // Every get_terms() argument set, in order.
$GLOBALS['count_args']    = array();
$GLOBALS['cache_sets']    = 0;
$GLOBALS['db_reads']      = 0;

/**
 * The terms a directory lists: one that files under A, one with no videos at
 * all (so the bar must leave its letter out), and one that files under a digit.
 *
 * @return object[]
 */
function mt_terms_fixture() {
	return array(
		(object) array( 'term_id' => 1, 'name' => 'Anna', 'slug' => 'anna', 'count' => 4 ),
		(object) array( 'term_id' => 2, 'name' => 'Bruno', 'slug' => 'bruno', 'count' => 0 ),
		(object) array( 'term_id' => 3, 'name' => '7 Up', 'slug' => '7-up', 'count' => 2 ),
	);
}

/**
 * The rows the letter map's own SELECT answers with.
 *
 * `count` travels with the row because the map hides a term with no videos the
 * same way the listing does, so the two cannot disagree about what a letter
 * offers.
 *
 * @return object[]
 */
function mt_letter_rows_fixture() {
	return mt_terms_fixture();
}

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as this module touches.
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
function esc_html_e( $s, $d = null ) {
	echo esc_html( $s );
}
function esc_attr_e( $s, $d = null ) {
	echo esc_attr( $s );
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
function wp_kses_post( $s ) {
	return (string) $s;
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function wp_strip_all_tags( $text ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );

	return trim( strip_tags( (string) $text ) );
}

/*
 * The text sanitizer as the letter reader depends on it: tags thrown away, runs
 * of whitespace collapsed, ends trimmed. A stub that only trimmed would let a
 * value carrying markup read as stored-as-typed, which is the one thing this
 * suite has to be able to tell apart from a sanitized letter.
 */
function sanitize_text_field( $s ) {
	$s = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s );

	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}

function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function apply_filters( $hook, $value ) {
	return $value;
}

function get_query_var( $var, $default = '' ) {
	return isset( $GLOBALS['query_vars'][ $var ] ) ? $GLOBALS['query_vars'][ $var ] : $default;
}

function taxonomy_exists( $taxonomy ) {
	return in_array( $taxonomy, $GLOBALS['taxonomies'], true );
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

/**
 * The icon helper, reduced to a marker.
 *
 * The real one returns SVG from inc/icons.php, which this suite is not about.
 * A marker keeps the assertion on where the arrows sit in the row.
 *
 * @param string $name  Icon name.
 * @param string $extra Optional extra class.
 * @return string
 */
function majestic_tube_get_icon( $name, $extra = '' ) {
	return '[icon:' . $name . ( '' !== $extra ? ':' . $extra : '' ) . ']';
}

/**
 * The options reader, for the keys this module asks for.
 *
 * inc/theme-options.php resolves a mapped field against the map's declared
 * default; the keys used here are read for their value alone, so the stub
 * returns the stored value or the caller's default.
 *
 * @param string $group    Option group.
 * @param string $field    Field id.
 * @param mixed  $default  Fallback.
 * @return mixed
 */
function majestic_tube_get_option( $group, $field, $default = false ) {
	return isset( $GLOBALS['opts'][ $field ] ) ? $GLOBALS['opts'][ $field ] : $default;
}

function get_pagenum_link( $page = 1 ) {
	return 'https://example.test/page/' . absint( $page ) . '/';
}

/**
 * The fixtures, narrowed to the term IDs a caller asked for.
 *
 * The module hands a letter's IDs to get_terms() as `include`, and the point
 * of the empty-letter check is that an empty set comes back empty rather than
 * as the whole directory, so the stub honors `include` the way WordPress does.
 *
 * @param object[] $terms Fixture terms.
 * @param array    $args  Query arguments.
 * @return object[]
 */
function mt_filter_terms( $terms, $args ) {
	if ( ! isset( $args['include'] ) ) {
		return $terms;
	}

	$include = array_map( 'absint', (array) $args['include'] );

	return array_values(
		array_filter(
			$terms,
			static function ( $term ) use ( $include ) {
				return in_array( (int) $term->term_id, $include, true );
			}
		)
	);
}

function get_terms( $args = array() ) {
	$GLOBALS['terms_args'][] = $args;

	return mt_filter_terms( mt_terms_fixture(), $args );
}

/*
 * Counted from the fixtures rather than by asking get_terms() again: the two
 * stubs are how the suite tells one query from two, so one of them cannot be
 * built on top of the other.
 */
function wp_count_terms( $args = array() ) {
	$GLOBALS['count_args'][] = $args;

	return count( mt_filter_terms( mt_terms_fixture(), $args ) );
}

function wp_cache_get( $key, $group = '' ) {
	return isset( $GLOBALS['cache'][ $group ][ $key ] ) ? $GLOBALS['cache'][ $group ][ $key ] : false;
}

function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
	$GLOBALS['cache'][ $group ][ $key ] = $value;
	++$GLOBALS['cache_sets'];

	return true;
}

function wp_cache_delete( $key, $group = '' ) {
	unset( $GLOBALS['cache'][ $group ][ $key ] );

	return true;
}

function get_transient( $key ) {
	return isset( $GLOBALS['transients'][ $key ] ) ? $GLOBALS['transients'][ $key ] : false;
}

function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['transients'][ $key ] = $value;

	return true;
}

function delete_transient( $key ) {
	unset( $GLOBALS['transients'][ $key ] );

	return true;
}

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['options'][ $key ] ) ? $GLOBALS['options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['options'][ $key ] = $value;

	return true;
}

/*
 * The two URL helpers are WordPress's, and the bar leans on them to keep the
 * page segment and any existing letter out of a link it builds. The stubs give
 * them the shape the assertions need: a fixed directory URL, and a query string
 * appended to it.
 */
function remove_query_arg( $keys, $url = '' ) {
	return 'https://example.test/actors/';
}

function add_query_arg( $key, $value, $url = '' ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
}

/**
 * A stand-in for $wpdb, with the two methods the letter map calls.
 *
 * get_results() answers with the fixture rows and counts the read, which is how
 * the suite can tell a rebuild from a copy served out of the option.
 */
class MT_WPDB_Stub {
	public $terms         = 'wp_terms';
	public $term_taxonomy = 'wp_term_taxonomy';

	public function prepare( $query, ...$args ) {
		return $query;
	}

	public function get_results( $query ) {
		++$GLOBALS['db_reads'];

		return mt_letter_rows_fixture();
	}
}

$GLOBALS['wpdb'] = new MT_WPDB_Stub();

require_once MAJESTIC_TUBE_DIR . '/inc/pagination.php';

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
 * Capture what a printing function writes.
 *
 * @param callable $callback Anything that echoes.
 * @return string
 */
function capture( $callback ) {
	ob_start();
	call_user_func( $callback );

	return (string) ob_get_clean();
}

/**
 * The same markup with its whitespace collapsed.
 *
 * An attribute and the one after it are separated by a newline and tabs in the
 * source, so an assertion about two attributes together has to compare them on
 * one line. Collapsing runs of whitespace is the whole change.
 *
 * @param string $html Markup.
 * @return string
 */
function flatten( $html ) {
	return trim( preg_replace( '/\s+/', ' ', (string) $html ) );
}

/**
 * Forget the object cache, the option copy and the transients, leaving the
 * terms marker in place, so one section's cache cannot answer for the next.
 *
 * @param string $last_changed WordPress's marker for the terms group.
 * @return void
 */
function reset_term_state( $last_changed = '100' ) {
	$GLOBALS['cache']      = array( 'terms' => array( 'last_changed' => $last_changed ) );
	$GLOBALS['options']    = array();
	$GLOBALS['transients'] = array();
	$GLOBALS['cache_sets'] = 0;
	$GLOBALS['db_reads']   = 0;
	$GLOBALS['terms_args'] = array();
	$GLOBALS['count_args'] = array();
}

/**
 * How many terms queries and term counts have run since the last reset.
 *
 * @return array{0:int,1:int}
 */
function term_query_counts() {
	return array( count( $GLOBALS['terms_args'] ), count( $GLOBALS['count_args'] ) );
}

/* -------------------------------------------------------------------------
 * The numbered navigator
 * ---------------------------------------------------------------------- */

echo "\nThe numbered navigator\n";

$markup = majestic_tube_pagination_markup( 12, 4, 5 );

check( 'the current page is printed as a current one', false !== strpos( $markup, '<li><a class="current">5</a></li>' ), $markup );
check( 'and exactly one page is marked current', 1 === substr_count( $markup, 'class="current"' ), $markup );
check( 'the current page is not also a link', false === strpos( $markup, 'page/5/' ), 'a link to the page you are on is a trap' );
check(
	'and it is anchored between the pages either side',
	false !== strpos( $markup, 'page/4/' ) && false !== strpos( $markup, 'page/6/' ),
	$markup
);
check( 'the page before is a previous link', false !== strpos( $markup, 'href="https://example.test/page/4/" rel="prev"' ), $markup );
check( 'the page after is a next link', false !== strpos( $markup, 'href="https://example.test/page/6/" rel="next"' ), $markup );
check( 'the last page is still a link', false !== strpos( $markup, 'href="https://example.test/page/12/" class="pagination-edge"' ), $markup );
check( 'the row opens and closes as one list', 0 === strpos( $markup, '<div class="pagination"><ul>' ) && '</ul></div>' === substr( $markup, -11 ), $markup );
check( 'the arrows are drawn by the icon set, not by characters', false !== strpos( $markup, '[icon:chevron-left]' ) && false !== strpos( $markup, '[icon:chevron-right]' ), $markup );

/*
 * The edge chips only appear when there is something to skip: with room for one
 * page on each side of the current one there is a First and a Last to offer,
 * and with four they would sit beside the pages they jump to.
 */
$narrow = majestic_tube_pagination_markup( 12, 1, 5 );

check( 'a window with room to skip offers the first page', false !== strpos( $narrow, 'href="https://example.test/page/1/" class="pagination-edge"' ) && false !== strpos( $narrow, ':pagination-double]First</a>' ), $narrow );
check( 'and the last page', false !== strpos( $narrow, 'Last[icon:chevron-right:pagination-double]</a>' ), $narrow );

/*
 * Each chip points the way its arrow does: the outward icons (First, Last) sit
 * beyond the label on the side they lead to, and the inward ones (Previous,
 * Next) sit beside it, so a chip reads in the direction of travel.
 */
check(
	'each chip carries its arrow on the side it leads to',
	false !== strpos( $narrow, '[icon:chevron-left:pagination-double]First' )
		&& false !== strpos( $narrow, 'Last[icon:chevron-right:pagination-double]' )
		&& false !== strpos( $narrow, '[icon:chevron-left]Previous' )
		&& false !== strpos( $narrow, 'Next[icon:chevron-right]' ),
	$narrow
);
check( 'while the neighbouring pages are the only ones in the middle', false !== strpos( $narrow, 'page/4/' ) && false !== strpos( $narrow, 'page/6/' ) && false === strpos( $narrow, 'page/3/' ) && false === strpos( $narrow, 'page/7/' ), $narrow );
check( 'a window that already shows the first page does not offer it again', false === strpos( $markup, ':pagination-double]First</a>' ), $markup );

$clamped = majestic_tube_pagination_markup( 5, 4, 99 );

check( 'a current page past the end is clamped to the last one', false !== strpos( $clamped, '<a class="current">5</a>' ), $clamped );

echo "\nA listing with one page\n";

check( 'prints nothing for a single page', '' === majestic_tube_pagination_markup( 1 ), 'a lone "1" under the last card is not navigation' );
check( 'and nothing for no pages at all', '' === majestic_tube_pagination_markup( 0 ) );
check(
	'and the wrapper prints nothing when there is no main query to read',
	'' === capture( 'majestic_tube_page_navi' ),
	'the default reads $wp_query; nothing to read is not a page'
);

$GLOBALS['wp_query'] = (object) array( 'max_num_pages' => 9 );

check( 'while a main query with pages does print one', false !== strpos( capture( 'majestic_tube_page_navi' ), 'class="pagination"' ) );

echo "\nWhere the current page comes from\n";

$GLOBALS['paged']      = 3;
$GLOBALS['query_vars'] = array();

check( 'the global paged value is read', 3 === majestic_tube_get_paged() );

$GLOBALS['paged']      = 0;
$GLOBALS['query_vars'] = array( 'page' => 4 );

check( 'and so is the page var a static front page uses', 4 === majestic_tube_get_paged() );

$GLOBALS['query_vars'] = array( 'paged' => 2 );

check( 'a paginated archive uses paged', 2 === majestic_tube_get_paged() );
check( 'and a current page of zero falls back to it', false !== strpos( majestic_tube_pagination_markup( 5, 4, 0 ), '<a class="current">2</a>' ) );

$GLOBALS['paged']      = 0;
$GLOBALS['query_vars'] = array();

check( 'nothing to read means page one', 1 === majestic_tube_get_paged() );

$GLOBALS['wp_query'] = (object) array( 'max_num_pages' => 6 );

check( 'the wrapper with no argument reads the main query', false !== strpos( capture( 'majestic_tube_page_navi' ), 'class="pagination"' ) );
check( 'and the alias prints the same navigator', false !== strpos( capture( 'majestic_tube_the_pagination' ), 'class="pagination"' ) );
check( 'while a single-page main query prints nothing', '' === capture( static function () { majestic_tube_page_navi( 1 ); } ) );

/* -------------------------------------------------------------------------
 * The letter filter
 * ---------------------------------------------------------------------- */

echo "\nThe letter filter\n";

$GLOBALS['_GET'] = array( 'letter' => 'a' );

check( 'a lowercase letter is read as its uppercase self', 'A' === majestic_tube_get_requested_letter() );

$GLOBALS['_GET'] = array( 'letter' => '7' );

check( 'a digit is a letter the directories can file under', '7' === majestic_tube_get_requested_letter() );

$GLOBALS['_GET'] = array( 'letter' => 'a ' );

check( 'a value with space around it is trimmed, not discarded', 'A' === majestic_tube_get_requested_letter() );

$GLOBALS['_GET'] = array( 'letter' => array( 'a' ) );

check( 'an array - what ?letter[]=a produces - is discarded', '' === majestic_tube_get_requested_letter() );

$GLOBALS['_GET'] = array( 'letter' => 'ab' );

check( 'a phrase is discarded rather than truncated to a letter', '' === majestic_tube_get_requested_letter() );

$GLOBALS['_GET'] = array( 'letter' => '#' );

check( 'punctuation is discarded', '' === majestic_tube_get_requested_letter() );

$GLOBALS['_GET'] = array( 'letter' => " <b>x</b>" );

check( 'a value carrying markup is reduced to a letter, never trusted as it arrived', 'X' === majestic_tube_get_requested_letter() );

$GLOBALS['_GET'] = array();

check( 'no argument at all is the full directory', '' === majestic_tube_get_requested_letter() );

/* -------------------------------------------------------------------------
 * The term directory's cache
 * ---------------------------------------------------------------------- */

echo "\nThe term directory runs its queries once\n";

reset_term_state();

$first = majestic_tube_get_term_directory( 'actors', 20, 1 );

check( 'a directory read answers with the terms', 3 === count( $first['terms'] ), var_export( $first['terms'], true ) );
check( 'and with the total behind them', 3 === $first['total'] );
check( 'having run one terms query and one count', array( 1, 1 ) === term_query_counts(), implode( ', ', term_query_counts() ) );

$again = majestic_tube_get_term_directory( 'actors', 20, 1 );

check( 'reading the same page again is served from the cache', array( 1, 1 ) === term_query_counts(), implode( ', ', term_query_counts() ) );
check( 'and answers the same thing', $first === $again );

majestic_tube_get_term_directory( 'actors', 20, 2 );

check( 'a different page is a different entry', array( 2, 2 ) === term_query_counts(), implode( ', ', term_query_counts() ) );

$GLOBALS['cache']['terms']['last_changed'] = '101';
majestic_tube_get_term_directory( 'actors', 20, 1 );

check(
	'a term list that changed cannot be served from the entry built before it',
	array( 3, 3 ) === term_query_counts(),
	'last_changed is what makes the old key unreachable: ' . implode( ', ', term_query_counts() )
);

reset_term_state();

$error = majestic_tube_get_term_directory( 'not_a_taxonomy', 20, 1 );

check( 'an unknown taxonomy is reported as an error', is_wp_error( $error['error'] ) && 'invalid_taxonomy' === $error['error']->get_error_code() );
check( 'with nothing listed', array() === $error['terms'] && 0 === $error['total'] );
check( 'and no query run for it', array( 0, 0 ) === term_query_counts(), implode( ', ', term_query_counts() ) );
check( 'and an error is not cached', 0 === $GLOBALS['cache_sets'], 'a transient failure must not stick' );

echo "\nA letter narrows the listing on purpose\n";

reset_term_state();

majestic_tube_get_term_directory( 'actors', 20, 1, 'A' );

$letter_args = end( $GLOBALS['terms_args'] );

check( 'a chosen letter lists only that letter\'s terms', array( 1 ) === array_map( 'absint', (array) $letter_args['include'] ), var_export( $letter_args, true ) );
check( 'and lists them on one page rather than paging a slice of one letter', 0 === $letter_args['number'] && 0 === $letter_args['offset'], var_export( $letter_args, true ) );

reset_term_state();

$empty = majestic_tube_get_term_directory( 'actors', 20, 1, 'Z' );
$empty_args = end( $GLOBALS['terms_args'] );

check( 'a letter with no terms asks for nothing', array( 0 ) === array_map( 'absint', (array) $empty_args['include'] ), 'an empty include would list everything: ' . var_export( $empty_args, true ) );
check( 'and the listing is empty rather than the whole directory', array() === $empty['terms'], var_export( $empty['terms'], true ) );

reset_term_state();

majestic_tube_get_term_directory( 'actors', 0, 1 );

$unlimited_args = end( $GLOBALS['terms_args'] );

check(
	'a per-page of zero means do not paginate, not one term per page',
	0 === $unlimited_args['number'] && 0 === $unlimited_args['offset'],
	var_export( $unlimited_args, true )
);
$unlimited_reads = count( $GLOBALS['terms_args'] );

majestic_tube_get_term_directory( 'actors', 0, 1 );

check( 'reading it again runs no query', $unlimited_reads === count( $GLOBALS['terms_args'] ), 'a per-page of zero is a page like any other to the cache' );

majestic_tube_get_term_directory( 'actors', 20, 1 );

check(
	'and the paged listing is a different entry',
	$unlimited_reads + 1 === count( $GLOBALS['terms_args'] ),
	'zero and twenty terms per page cannot share a key'
);

/* -------------------------------------------------------------------------
 * The letter map and the bar it draws
 * ---------------------------------------------------------------------- */

echo "\nThe letter map\n";

reset_term_state();

$map = majestic_tube_get_term_letter_map( 'actors' );

check( 'the map buckets terms under their initial', array( '7' => array( 3 ), 'A' => array( 1 ) ) === $map, var_export( $map, true ) );
check( 'a term with no videos gets no letter at all', ! isset( $map['B'] ), 'the bar and the listing must not disagree' );
check( 'and the read behind it ran once', 1 === $GLOBALS['db_reads'] );
check( 'the map is kept beside the marker it was built from', isset( $GLOBALS['options']['majestic_tube_letters_actors']['last_changed'] ) && '100' === $GLOBALS['options']['majestic_tube_letters_actors']['last_changed'] );

$GLOBALS['cache'] = array( 'terms' => array( 'last_changed' => '100' ) );

check( 'a copy built from the same marker is reused without a read', majestic_tube_get_term_letter_map( 'actors' ) === $map && 1 === $GLOBALS['db_reads'], 'the option copy is what keeps the bar off the database' );

$GLOBALS['cache'] = array( 'terms' => array( 'last_changed' => '101' ) );

majestic_tube_get_term_letter_map( 'actors' );

check( 'a copy built from an older marker is rebuilt', 2 === $GLOBALS['db_reads'], 'a stale copy cannot be served' );
check( 'and the marker it is kept beside moves with it', '101' === $GLOBALS['options']['majestic_tube_letters_actors']['last_changed'] );

$letters = majestic_tube_get_term_letters( 'actors' );

check( 'the counts are what a bar draws from', array( '7' => 1, 'A' => 1 ) === $letters, var_export( $letters, true ) );

echo "\nThe initial a term files under\n";

$initials = array(
	'Anna'          => 'A',
	'bruno'         => 'B',
	'7 Up'          => '7',
	'<b>Bob</b>'    => 'B',
	'#1 Hits'       => '',
	'   '           => '',
);

foreach ( $initials as $name => $expected ) {
	check(
		'a term named ' . var_export( $name, true ) . ' files under ' . var_export( $expected, true ),
		$expected === majestic_tube_term_initial( $name ),
		var_export( majestic_tube_term_initial( $name ), true )
	);
}

echo "\nThe alphabet bar\n";

reset_term_state();
$GLOBALS['_GET'] = array();

$bar = flatten( capture( static function () { majestic_tube_term_letter_nav( 'actors' ); } ) );

check( 'the bar is labeled for a screen reader', false !== strpos( $bar, 'aria-label="Browse by letter"' ), $bar );
check( 'it offers the full directory', false !== strpos( $bar, 'class="term-letter is-active" href="https://example.test/actors/"' ), $bar );
check( 'and marks that as where the visitor is', 1 === substr_count( $bar, 'aria-current="true"' ), $bar );
check( 'a letter with terms is offered', false !== strpos( $bar, 'letter=A' ), $bar );
check( 'so is a digit', false !== strpos( $bar, 'letter=7' ), $bar );
check( 'a letter with nothing behind it is left off', false === strpos( $bar, 'letter=B' ), 'a chip that cannot be clicked is dead weight in the row' );

$GLOBALS['_GET'] = array( 'letter' => 'a' );

$filtered = flatten( capture( static function () { majestic_tube_term_letter_nav( 'actors' ); } ) );

check( 'the chosen letter is the one marked', false !== strpos( $filtered, 'class="term-letter is-active" href="https://example.test/actors/?letter=A"' ), $filtered );
check( 'and only one entry is marked current', 1 === substr_count( $filtered, 'aria-current="true"' ), $filtered );
check( 'while the full directory is still one click away', false !== strpos( $filtered, 'class="term-letter" href="https://example.test/actors/"' ), $filtered );

$GLOBALS['cache']      = array( 'terms' => array( 'last_changed' => '200' ) );
$GLOBALS['options']    = array();
$GLOBALS['transients'] = array();

/*
 * Every term has no videos, so no letter leads anywhere. A bar whose only
 * entry is "All" is a control that does nothing, so the whole nav is skipped.
 */
class MT_WPDB_Empty_Stub extends MT_WPDB_Stub {
	public function get_results( $query ) {
		++$GLOBALS['db_reads'];

		return array(
			(object) array( 'term_id' => 9, 'name' => 'Quiet', 'count' => 0 ),
		);
	}
}

$GLOBALS['wpdb'] = new MT_WPDB_Empty_Stub();

$empty_bar = capture( static function () { majestic_tube_term_letter_nav( 'actors' ); } );

check( 'a directory where no letter leads anywhere prints no bar', '' === flatten( $empty_bar ), $empty_bar );

/* -------------------------------------------------------------------------
 * Paging a term directory
 * ---------------------------------------------------------------------- */

echo "\nPaging a term directory\n";

$GLOBALS['paged']      = 1;
$GLOBALS['query_vars'] = array();

$paged_markup = capture( static function () { majestic_tube_term_pagination( 40, 20 ); } );

check( 'two pages of terms are paged', false !== strpos( $paged_markup, 'class="pagination"' ), $paged_markup );
check( 'with the second page reachable', false !== strpos( $paged_markup, 'page/2/' ), $paged_markup );
check( 'and the first marked current rather than linked', false !== strpos( $paged_markup, '<a class="current">1</a>' ), $paged_markup );

check( 'a single page of terms prints nothing', '' === capture( static function () { majestic_tube_term_pagination( 20, 20 ); } ) );
check(
	'a directory that does not paginate prints nothing either',
	'' === capture( static function () { majestic_tube_term_pagination( 200, 0 ); } ),
	'max( 1, 0 ) would otherwise read as one term per page'
);
check( 'and neither does an empty directory', '' === capture( static function () { majestic_tube_term_pagination( 0, 20 ); } ) );

echo "\nThe per-page reader\n";

$GLOBALS['opts'] = array( 'actors-per-page' => 12 );

check( 'a stored value is used', 12 === majestic_tube_terms_per_page( 'actors-per-page', 20 ) );

$GLOBALS['opts'] = array( 'actors-per-page' => 0 );

check( 'a value of zero falls back to the default', 20 === majestic_tube_terms_per_page( 'actors-per-page', 20 ), 'zero terms per page is not a page' );

$GLOBALS['opts'] = array();

check( 'and an unset option uses it too', 20 === majestic_tube_terms_per_page( 'actors-per-page', 20 ) );

$GLOBALS['opts'] = array( 'actors-per-page' => '7' );

check( 'a string of digits is read as its number', 7 === majestic_tube_terms_per_page( 'actors-per-page', 20 ) );

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $GLOBALS['passed'], $GLOBALS['failed'] );

if ( $GLOBALS['failed'] ) {
	exit( 1 );
}

echo "All checks passed.\n";
