<?php
/**
 * Smoke test for the breadcrumb trail.
 *
 * The trail is one item list feeding one renderer, and both halves are reached
 * on every view of the site, so the body of inc/breadcrumbs.php is included here
 * and run for real against a stubbed WordPress. What is asserted is the markup
 * it prints, not a copy of the rule.
 *
 * Four claims worth pinning, because each was easy to lose silently:
 *
 *   1. a video page reads home, then Videos, then the title - and the last item
 *      is the current page, printed as text rather than as a link. A trail whose
 *      final crumb links to the page you are already on is the classic way this
 *      breaks, and it is invisible until someone clicks it;
 *   2. an archive crumb is stripped of markup before it is printed.
 *      get_the_archive_title() answers with a span wrapped around the term name,
 *      and printed raw that span lands inside the trail's own markup;
 *   3. every label and every URL is escaped where it is printed. A post title, a
 *      search term and a category name are all content - an imported or
 *      submitted one can carry a quote or an angle bracket;
 *   4. the item list is the documented extension point
 *      (majestic_tube_breadcrumb_items): a site that replaces it, or empties it,
 *      gets exactly what it asked for, and the front page prints nothing at all.
 *
 * Run:  php tests/breadcrumbs-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['view']          = 'single';
$GLOBALS['title']         = 'A Video Title';
$GLOBALS['archive_title'] = 'Category: HD';
$GLOBALS['search_query']  = '';
$GLOBALS['filter_value']  = null;
$GLOBALS['filters']       = array();

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as this module touches.
 * ---------------------------------------------------------------------- */

function __( $text, $domain = null ) {
	return $text;
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function is_single() {
	return 'single' === $GLOBALS['view'];
}

function is_singular() {
	return in_array( $GLOBALS['view'], array( 'single', 'page' ), true );
}

function is_archive() {
	return 'archive' === $GLOBALS['view'];
}

// The blog index takes the same branch as an archive, so one fixture covers it.
function is_home() {
	return 'home' === $GLOBALS['view'];
}

function is_search() {
	return 'search' === $GLOBALS['view'];
}

function is_front_page() {
	return 'front' === $GLOBALS['view'];
}

function get_the_title( $post = null ) {
	return $GLOBALS['title'];
}

function get_the_archive_title() {
	return $GLOBALS['archive_title'];
}

/**
 * The search term, handed back unescaped.
 *
 * Core's $escaped parameter defaults to true, but the value is filterable and a
 * filter can hand back the raw term, so the suite delivers the raw one: what is
 * then pinned by the checks below is the renderer's own escaping, which is the
 * half this module owns.
 *
 * @param bool $escaped Unused here; core's parameter.
 * @return string
 */
function get_search_query( $escaped = true ) {
	return $GLOBALS['search_query'];
}

/**
 * Core's strip, as the trail depends on it: tags go, the words stay.
 *
 * @param string $text          Text to strip.
 * @param bool   $remove_breaks Unused here; core's second parameter.
 * @return string
 */
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	return trim( strip_tags( (string) $text ) );
}

/*
 * esc_url() is stubbed the way core behaves rather than the way a permissive
 * stub would: core hands the value to string functions, so anything that is not
 * a string is a fatal error there too. The stub records what it was handed, so
 * a check can tell "escaped at print time" from "printed as built".
 */
function esc_url( $url ) {
	if ( ! is_string( $url ) ) {
		throw new Error( 'esc_url() expects a string, ' . ( is_object( $url ) ? get_class( $url ) : gettype( $url ) ) . ' given' );
	}

	return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr__( $text, $domain = null ) {
	return esc_attr( $text );
}

/**
 * apply_filters(), with a fixture standing in for a site's own callback.
 *
 * The trail is a documented extension point, so the stub can answer with a
 * replacement list; the hook name is recorded either way, which is how the
 * check below can tell it was consulted at all.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Value being filtered.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	$GLOBALS['filters'][] = $hook;

	return isset( $GLOBALS['filter_value'] ) ? $GLOBALS['filter_value'] : $value;
}

require_once MAJESTIC_TUBE_DIR . '/inc/breadcrumbs.php';

/* -------------------------------------------------------------------------
 * Harness
 * ---------------------------------------------------------------------- */

$passed = 0;
$failed = 0;

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
 * Render the trail on one view, and report a fatal as a failure.
 *
 * @param string $view      Which view the stubs should answer for.
 * @param array  $overrides Fixtures to set first: title, archive_title, search_query, filter.
 * @return array{0:string,1:string} Markup and the fatal message, if any.
 */
function render_trail( $view, $overrides = array() ) {
	$GLOBALS['view']          = $view;
	$GLOBALS['title']         = isset( $overrides['title'] ) ? $overrides['title'] : 'A Video Title';
	$GLOBALS['archive_title'] = isset( $overrides['archive_title'] ) ? $overrides['archive_title'] : 'Category: HD';
	$GLOBALS['search_query']  = isset( $overrides['search_query'] ) ? $overrides['search_query'] : '';
	$GLOBALS['filter_value']  = array_key_exists( 'filter', $overrides ) ? $overrides['filter'] : null;
	$GLOBALS['filters']       = array();

	$html  = '';
	$fatal = '';

	try {
		ob_start();
		majestic_tube_breadcrumbs();
		$html = (string) ob_get_clean();
	} catch ( Throwable $e ) {
		$html  = (string) ob_get_clean();
		$fatal = $e->getMessage();
	}

	return array( $html, $fatal );
}

/**
 * The trail as a list of items, on one view.
 *
 * @param string $view View the stubs should answer for.
 * @return array
 */
function trail_items( $view ) {
	$GLOBALS['view']         = $view;
	$GLOBALS['filter_value'] = null;
	$GLOBALS['filters']      = array();

	return majestic_tube_get_breadcrumb_items();
}

/* -------------------------------------------------------------------------
 * A video page
 * ---------------------------------------------------------------------- */

echo "\nA video page\n";

list( $html, $fatal ) = render_trail( 'single' );

check( 'no fatal', '' === $fatal, $fatal );
check(
	'the nav carries its label',
	false !== strpos( $html, '<nav class="breadcrumbs" aria-label="Breadcrumb">' ),
	$html
);
check( 'home comes first, as a link', false !== strpos( $html, '<a href="https://example.test/">Home</a>' ) );
check( 'then Videos, as a link', false !== strpos( $html, '<a href="https://example.test/">Videos</a>' ) );
check(
	'and the title last, as the current page',
	false !== strpos( $html, '<span class="breadcrumb-current">A Video Title</span>' ),
	$html
);
check(
	'the current page is not a link to itself',
	false === strpos( $html, '<a href="https://example.test/">A Video Title' )
);
check( 'the trail is closed', false !== strpos( $html, '</nav>' ) );
check(
	'the crumbs are separated, one separator fewer than there are items',
	2 === substr_count( $html, '<span class="breadcrumb-separator" aria-hidden="true">/</span>' )
		&& 3 === substr_count( $html, 'breadcrumb-current' ) + substr_count( $html, '<a href=' ),
	'separators: ' . substr_count( $html, 'breadcrumb-separator' )
		. ', crumbs: ' . ( substr_count( $html, 'breadcrumb-current' ) + substr_count( $html, '<a href=' ) )
);

$home_at    = strpos( $html, 'Home</a>' );
$videos_at  = strpos( $html, 'Videos</a>' );
$current_at = strpos( $html, 'breadcrumb-current' );

check(
	'and they run home, Videos, title in that order',
	false !== $home_at && false !== $videos_at && false !== $current_at
		&& $home_at < $videos_at && $videos_at < $current_at
);

echo "\nThe item list behind it\n";

$items = trail_items( 'single' );

check( 'three items', 3 === count( $items ), 'count: ' . count( $items ) );
check( 'the first is Home with a URL', isset( $items[0]['label'], $items[0]['url'] ) && 'Home' === $items[0]['label'] && '' !== $items[0]['url'] );
check( 'the middle one is Videos', isset( $items[1]['label'] ) && 'Videos' === $items[1]['label'] );
check(
	'and the last one is the title with an empty URL, which is the contract the renderer reads',
	isset( $items[2]['label'], $items[2]['url'] ) && 'A Video Title' === $items[2]['label'] && '' === $items[2]['url'],
	isset( $items[2]['url'] ) ? var_export( $items[2]['url'], true ) : 'no url key'
);
check( 'and it passed through its filter', array( 'majestic_tube_breadcrumb_items' ) === $GLOBALS['filters'], implode( ', ', $GLOBALS['filters'] ) );

/* -------------------------------------------------------------------------
 * Everything that is not a video
 * ---------------------------------------------------------------------- */

echo "\nA page\n";

list( $html, $fatal ) = render_trail( 'page' );

check( 'no fatal', '' === $fatal, $fatal );
check( 'home then the page title', false !== strpos( $html, 'Home</a>' ) && false !== strpos( $html, '<span class="breadcrumb-current">A Video Title</span>' ) );
check( 'one separator', 1 === substr_count( $html, 'breadcrumb-separator' ) );
// "Videos" is the video page's own middle crumb and has no business on a page.
check( 'no Videos crumb on a page', false === strpos( $html, 'Videos' ) );

echo "\nAn archive\n";

list( $html, $fatal ) = render_trail( 'archive', array( 'archive_title' => '<span class="term-name">Category: HD</span>' ) );

check( 'no fatal', '' === $fatal, $fatal );
check(
	'the archive title is printed stripped of its markup',
	false !== strpos( $html, '<span class="breadcrumb-current">Category: HD</span>' ),
	$html
);
check(
	'and the markup around it is not printed',
	false === strpos( $html, '<span class="term-name">' )
);
check( 'with home as the only link', 1 === substr_count( $html, '<a href=' ) );

list( $blog ) = render_trail( 'home', array( 'archive_title' => '<span class="term-name">All Videos</span>' ) );

check(
	'the blog index takes the same branch as an archive',
	false !== strpos( $blog, '<span class="breadcrumb-current">All Videos</span>' ) && false === strpos( $blog, '<span class="term-name">' ),
	$blog
);

echo "\nA search\n";

list( $html, $fatal ) = render_trail( 'search', array( 'search_query' => 'rock & roll <script>' ) );

check( 'no fatal', '' === $fatal, $fatal );
check(
	'the query is escaped where it is printed',
	false !== strpos( $html, 'Search: rock &amp; roll &lt;script&gt;' ),
	$html
);
check( 'and nothing from it is printed raw', false === strpos( $html, '<script>' ) );

echo "\nA 404\n";

list( $html, $fatal ) = render_trail( '404' );

check( 'no fatal', '' === $fatal, $fatal );
check(
	'the trail is the home link alone, with nothing invented for the missing page',
	1 === substr_count( $html, '<a href=' ) && false !== strpos( $html, 'Home</a>' ),
	$html
);
check( 'no separator and no current crumb', 0 === substr_count( $html, 'breadcrumb-separator' ) && false === strpos( $html, 'breadcrumb-current' ) );

echo "\nThe front page\n";

list( $html, $fatal ) = render_trail( 'front' );

check( 'no fatal', '' === $fatal, $fatal );
check( 'the front page prints no trail at all', '' === trim( $html ), var_export( $html, true ) );

/* -------------------------------------------------------------------------
 * A label is content
 * ---------------------------------------------------------------------- */

echo "\nA title is content\n";

list( $html, $fatal ) = render_trail( 'single', array( 'title' => 'A "Clip" & <script>' ) );

check( 'no fatal', '' === $fatal, $fatal );
check(
	'the title is escaped where it is printed',
	false !== strpos( $html, 'A &quot;Clip&quot; &amp; &lt;script&gt;' ),
	$html
);
check( 'and nothing from it is printed raw', false === strpos( $html, '<script>' ) );

/* -------------------------------------------------------------------------
 * The extension point
 * ---------------------------------------------------------------------- */

echo "\nA site that replaces the trail\n";

list( $filtered, $fatal ) = render_trail(
	'single',
	array(
		'filter' => array(
			array( 'label' => 'Start', 'url' => 'https://example.test/' ),
			array( 'label' => 'Custom Item', 'url' => '' ),
		),
	)
);

check( 'no fatal', '' === $fatal, $fatal );
check(
	'the replacement list is what gets printed',
	false !== strpos( $filtered, '<a href="https://example.test/">Start</a>' )
		&& false !== strpos( $filtered, '<span class="breadcrumb-current">Custom Item</span>' ),
	$filtered
);
check( 'and the built-in crumbs are gone', false === strpos( $filtered, 'Videos' ) );

list( $html, $fatal ) = render_trail( 'single', array( 'filter' => array() ) );

check( 'no fatal', '' === $fatal, $fatal );
check(
	'an emptied list prints the nav and nothing in it',
	false !== strpos( $html, '<nav class="breadcrumbs"' ) && false !== strpos( $html, '</nav>' )
		&& false === strpos( $html, '<a href=' ) && false === strpos( $html, 'breadcrumb-current' )
		&& false === strpos( $html, 'breadcrumb-separator' ),
	$html
);

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
