<?php
/**
 * Smoke test for the compatibility layer's category / actor / tag list.
 *
 * wpst_cats_tags() prints the three groups under a video, and it is called from
 * the original theme's templates as well as this theme's own, so it is reached
 * on ordinary article views and on the templates child themes still ship. The
 * body of the function is run for real against a stubbed WordPress: inc/compat.php
 * is included here, so what is asserted is the markup it prints.
 *
 * Three claims worth pinning, because each was easy to lose silently:
 *
 *   1. a term whose link cannot be built is skipped and the page survives.
 *      get_category_link(), get_tag_link() and get_term_link() all answer with
 *      a WP_Error when the term cannot be linked, and that error was handed
 *      straight to esc_url(), which takes a string - "Object of class WP_Error
 *      could not be converted to string" ended the request. One unlinkable
 *      tag on one post took down the article, the sidebar and everything
 *      printed after it;
 *   2. a term name is escaped where it is printed. A taxonomy name is content:
 *      an imported feed or a submission can carry a name with a quote or an
 *      angle bracket in it, and it lands in a title attribute here;
 *   3. each group still honors its own visibility switch, and the whole list is
 *      limited to a post on the video post type.
 *
 * Run:  php tests/compat-links-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['post']  = null;
$GLOBALS['opts']  = array();
$GLOBALS['cats']  = array();
$GLOBALS['tags']  = array();
$GLOBALS['actors'] = array();
$GLOBALS['broken'] = array();
$GLOBALS['post_type'] = 'post';

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as one function touches.
 * ---------------------------------------------------------------------- */

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

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function get_post_type( $post = null ) {
	return $GLOBALS['post_type'];
}

function get_the_category() {
	return $GLOBALS['cats'];
}

function get_the_tags() {
	return $GLOBALS['tags'];
}

function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
	return $GLOBALS['actors'];
}

/**
 * A term, as the three link helpers read it.
 *
 * @param int    $id   Term ID.
 * @param string $name Term name.
 * @return object
 */
function term_fixture( $id, $name ) {
	return (object) array(
		'term_id' => $id,
		'name'    => $name,
		'slug'    => 'term-' . $id,
		'count'   => 1,
	);
}

/**
 * Answer a link for one term, or a WP_Error when the fixture marks it broken.
 *
 * The three real helpers all build their URL through get_term_link(), and all
 * three return its WP_Error unchanged when it fails, so one switch drives the
 * fixtures for all of them.
 *
 * @param int $term_id Term ID.
 * @return string|WP_Error
 */
function term_link_fixture( $term_id ) {
	if ( in_array( (int) $term_id, $GLOBALS['broken'], true ) ) {
		return new WP_Error( 'invalid_term', 'The term could not be linked.' );
	}

	return 'https://example.test/term/' . (int) $term_id . '/';
}

function get_term_link( $term ) {
	return term_link_fixture( is_object( $term ) ? $term->term_id : $term );
}

function get_category_link( $category_id ) {
	return term_link_fixture( $category_id );
}

function get_tag_link( $tag_id ) {
	return term_link_fixture( $tag_id );
}

/*
 * esc_url() is stubbed the way core behaves rather than the way a permissive
 * stub would: core hands the value to string functions, so anything that is not
 * a string is a fatal error there too. A stub that quietly cast the value to a
 * string would have hidden the defect this file exists to catch.
 */
function esc_url( $url ) {
	if ( ! is_string( $url ) ) {
		throw new Error( 'esc_url() expects a string, ' . ( is_object( $url ) ? get_class( $url ) : gettype( $url ) ) . ' given' );
	}

	return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

function majestic_tube_option_is_on( $key ) {
	return ! empty( $GLOBALS['opts'][ $key ] );
}

function majestic_tube_get_icon( $name ) {
	return '<svg class="icon-' . $name . '"></svg>';
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

require_once MAJESTIC_TUBE_DIR . '/inc/compat.php';

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
 * Render the list and report a fatal as a failure rather than a white page.
 *
 * @return array{0:string,1:string} Markup and the fatal message, if any.
 */
function render_cats_tags() {
	$html  = '';
	$fatal = '';

	try {
		ob_start();
		wpst_cats_tags();
		$html = (string) ob_get_clean();
	} catch ( Throwable $e ) {
		$html = (string) ob_get_clean();
		$fatal = $e->getMessage();
	}

	return array( $html, $fatal );
}

/**
 * The label markup for one group, so a check can name what it expects.
 *
 * @param int    $id   Term ID.
 * @param string $name Term name.
 * @return string
 */
function label_markup( $id, $name ) {
	return '<a href="https://example.test/term/' . $id . '/" class="label" title="'
		. htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ) . '">'
		. '<svg class="icon-placeholder"></svg> '
		. htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ) . '</a>';
}

function on() {
	$GLOBALS['opts'] = array(
		'show-categories-video-about' => true,
		'show-actors-video-about'     => true,
		'show-tags-video-about'       => true,
	);
}

/* -------------------------------------------------------------------------
 * A post of the video type, all three groups visible
 * ---------------------------------------------------------------------- */

$GLOBALS['post']   = (object) array( 'ID' => 7 );
$GLOBALS['cats']   = array( term_fixture( 11, 'HD' ) );
$GLOBALS['tags']   = array( term_fixture( 22, 'asian' ) );
$GLOBALS['actors'] = array( term_fixture( 33, 'A Name' ) );
$GLOBALS['broken'] = array();

echo "\nEvery term links\n";
on();

list( $html, $fatal ) = render_cats_tags();

check( 'no fatal', '' === $fatal, $fatal );
check( 'wrapper printed', false !== strpos( $html, '<div class="tags-list">' ) );
check( 'category label printed', false !== strpos( $html, 'href="https://example.test/term/11/"' ) );
check( 'actor label printed', false !== strpos( $html, 'href="https://example.test/term/33/"' ) );
check( 'tag label printed', false !== strpos( $html, 'href="https://example.test/term/22/"' ) );
// The group icon sits inside the anchor ahead of the name, so the name is
// asserted against the text that closes the anchor rather than the opening tag.
check( 'term name printed inside its link', false !== strpos( $html, ' HD</a>' ) );

/* -------------------------------------------------------------------------
 * A term that cannot be linked
 * ---------------------------------------------------------------------- */

echo "\nOne term cannot be linked\n";

$GLOBALS['cats'] = array( term_fixture( 11, 'HD' ), term_fixture( 12, 'Broken Category' ) );
$GLOBALS['broken'] = array( 12 );

list( $html, $fatal ) = render_cats_tags();

check( 'a broken category link does not fatal', '' === $fatal, $fatal );
check( 'the good category is still printed', false !== strpos( $html, 'href="https://example.test/term/11/"' ) );
check( 'the broken category is left out', false === strpos( $html, 'Broken Category' ) );
check( 'the other groups are untouched', false !== strpos( $html, 'href="https://example.test/term/22/"' ) && false !== strpos( $html, 'href="https://example.test/term/33/"' ) );

$GLOBALS['cats']   = array( term_fixture( 11, 'HD' ) );
$GLOBALS['tags']   = array( term_fixture( 22, 'asian' ), term_fixture( 23, 'Broken Tag' ) );
$GLOBALS['broken'] = array( 23 );

list( $html, $fatal ) = render_cats_tags();

check( 'a broken tag link does not fatal', '' === $fatal, $fatal );
check( 'the good tag is still printed', false !== strpos( $html, 'href="https://example.test/term/22/"' ) );
check( 'the broken tag is left out', false === strpos( $html, 'Broken Tag' ) );
check( 'the category group survives it', false !== strpos( $html, 'href="https://example.test/term/11/"' ) );

// Every group broken at once: the wrapper still closes, so the page after it
// is not swallowed by an unclosed div.
$GLOBALS['tags']   = array( term_fixture( 23, 'Broken Tag' ) );
$GLOBALS['actors'] = array( term_fixture( 34, 'Broken Actor' ) );
$GLOBALS['broken'] = array( 11, 23, 34 );

list( $html, $fatal ) = render_cats_tags();

check( 'every term broken still does not fatal', '' === $fatal, $fatal );
check( 'the wrapper is still closed', false !== strpos( $html, '</div>' ) );

/* -------------------------------------------------------------------------
 * A term name is content
 * ---------------------------------------------------------------------- */

echo "\nA term name is content\n";

$GLOBALS['cats']   = array( term_fixture( 11, 'Sci-Fi & "Horror" <script>' ) );
$GLOBALS['tags']   = array();
$GLOBALS['actors'] = array();
$GLOBALS['broken'] = array();

list( $html, $fatal ) = render_cats_tags();

check( 'no fatal', '' === $fatal, $fatal );
check( 'the ampersand is escaped', false !== strpos( $html, 'Sci-Fi &amp;' ) );
check( 'the quote is escaped', false !== strpos( $html, '&quot;Horror&quot;' ) );
check( 'no raw tag is printed from a term name', false === strpos( $html, '<script>' ) );
check( 'the angle bracket is escaped', false !== strpos( $html, '&lt;script&gt;' ) );

/* -------------------------------------------------------------------------
 * The switches, and the post type
 * ---------------------------------------------------------------------- */

echo "\nVisibility switches and post type\n";

$GLOBALS['cats']   = array( term_fixture( 11, 'HD' ) );
$GLOBALS['tags']   = array( term_fixture( 22, 'asian' ) );
$GLOBALS['actors'] = array( term_fixture( 33, 'A Name' ) );

$GLOBALS['opts'] = array( 'show-categories-video-about' => true );
list( $html ) = render_cats_tags();

check( 'categories only: no actor label', false === strpos( $html, 'term/33/' ) );
check( 'categories only: no tag label', false === strpos( $html, 'term/22/' ) );
check( 'categories only: category still printed', false !== strpos( $html, 'term/11/' ) );

/*
 * With every switch off no group is printed. The wrapper element itself is
 * still emitted, empty, because the early return above it answers the question
 * "does this post have terms" and not "is any group shown"; an empty div
 * carries no label and no link, and it is left as it was found rather than
 * changed here.
 */
$GLOBALS['opts'] = array();
list( $html ) = render_cats_tags();

check( 'every switch off prints no label', false === strpos( $html, 'class="label"' ) );
check( 'every switch off prints no link', false === strpos( $html, 'href=' ) );

on();
$GLOBALS['post_type'] = 'page';
list( $html ) = render_cats_tags();

check( 'a post of another type prints nothing', '' === trim( $html ) );

$GLOBALS['post_type'] = 'post';
$GLOBALS['post']      = null;
list( $html ) = render_cats_tags();

check( 'no global post prints nothing', '' === trim( $html ) );

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
