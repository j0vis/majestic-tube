<?php
/**
 * Smoke test for the social share block.
 *
 * inc/share.php prints the sharing row under the player, one link per network,
 * each behind its own wpst-options switch because those keys are part of the
 * option surface the original theme documented and custom code reads. The
 * module is loaded here for real against a stubbed WordPress, so what is
 * asserted is the markup and the URLs it builds rather than a copy of the rule.
 *
 * What is pinned, and why each one is worth pinning:
 *
 *   1. every enabled network builds its URL from the post's own permalink and
 *      title, encoded as a query value. A permalink that already carries a query
 *      string is the case that catches a change from rawurlencode() to urlencode()
 *      or to no encoding at all: a share URL truncating at its first "&" shares
 *      the wrong page, and it does so silently;
 *   2. a link a plugin adds through the majestic_tube_share_links filter is
 *      escaped where it is printed. The block prints an href, an id, an icon
 *      class and an aria-label out of that list, and the list is not the
 *      theme's to trust;
 *   3. a network switched off prints no anchor for itself and leaves no empty
 *      one in its place, and a block with no networks left prints nothing -
 *      toggle included, because a toggle that opens onto an empty box is worse
 *      than no share row;
 *   4. the description prefers the excerpt, strips shortcodes and markup, and
 *      trims to the length a link preview shows.
 *
 * The block makes no guard for a missing permalink - an empty one still yields a
 * share URL with nothing in its url parameter - so no check below claims that
 * difference. The guards the module does make (no post, master switch off, no
 * networks left) each have a case of their own.
 *
 * Run:  php tests/share-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['opts']               = array();
$GLOBALS['posts']              = array();
$GLOBALS['the_id']             = 0;
$GLOBALS['escaped_urls']       = array();
$GLOBALS['share_links_filter'] = null;
$GLOBALS['content_filter']     = null;

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as one module touches.
 * ---------------------------------------------------------------------- */

function __( $s, $d = null ) {
	return $s;
}

function esc_html_e( $s, $d = null ) {
	echo htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

/**
 * The URL escaper, recording what it was handed.
 *
 * Each raw value is recorded so a check can tell "printed through esc_url()"
 * from "printed as typed", and can name the exact URL the module built instead
 * of matching a pattern that would pass for any share link.
 *
 * @param string $url Raw URL.
 * @return string
 */
function esc_url( $url ) {
	$GLOBALS['escaped_urls'][] = $url;

	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function sanitize_html_class( $class, $fallback = '' ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}

/**
 * The filter runner, as far as this module needs it.
 *
 * Only two hooks are ever applied here: the share link list, which a plugin
 * replaces, and the_content, which a plugin decorates. Both are recorded so a
 * check can prove the module applied them rather than merely receiving a value
 * that happened to look right.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Value being filtered.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	if ( 'majestic_tube_share_links' === $hook && is_callable( $GLOBALS['share_links_filter'] ) ) {
		return call_user_func( $GLOBALS['share_links_filter'], $value );
	}

	if ( 'the_content' === $hook && is_callable( $GLOBALS['content_filter'] ) ) {
		return call_user_func( $GLOBALS['content_filter'], $value );
	}

	return $value;
}

/**
 * The post a fixture ID stands for.
 *
 * @param int $post_id Post ID.
 * @return object|null
 */
function share_fixture_post( $post_id ) {
	return isset( $GLOBALS['posts'][ (int) $post_id ] ) ? (object) $GLOBALS['posts'][ (int) $post_id ] : null;
}

function get_post( $post_id ) {
	return share_fixture_post( $post_id );
}

function get_permalink( $post_id ) {
	$post = share_fixture_post( $post_id );

	return $post ? $post->permalink : false;
}

function get_the_title( $post_id = 0 ) {
	$post = share_fixture_post( $post_id );

	return $post ? $post->post_title : '';
}

function get_the_ID() {
	return (int) $GLOBALS['the_id'];
}

// Core's own shape: a script or style element goes with its contents, every
// other tag goes, and the whitespace it leaves behind is the caller's to
// collapse.
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( (string) $text );

	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}

	return trim( $text );
}

function strip_shortcodes( $text ) {
	return preg_replace( '/\[[^\]]*\]/', '', (string) $text );
}

/**
 * Core's trimmer: words collapsed to single spaces, and the "more" marker
 * appended only when something was actually cut.
 *
 * @param string      $text      Text to trim.
 * @param int         $num_words Words to keep.
 * @param string|null $more      Marker for the cut.
 * @return string
 */
function wp_trim_words( $text, $num_words = 55, $more = null ) {
	$words = preg_split( '/[\n\r\t ]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY );

	if ( ! $words ) {
		$words = array();
	}

	if ( count( $words ) > $num_words ) {
		$words   = array_slice( $words, 0, $num_words );
		$trimmed = implode( ' ', $words ) . ( null === $more ? '&hellip;' : $more );

		return $trimmed;
	}

	return implode( ' ', $words );
}

function majestic_tube_option_is_on( $key ) {
	return ! empty( $GLOBALS['opts'][ $key ] );
}

function majestic_tube_icon( $name ) {
	echo '<svg class="icon-' . htmlspecialchars( (string) $name, ENT_QUOTES, 'UTF-8' ) . '"></svg>';
}

require_once MAJESTIC_TUBE_DIR . '/inc/share.php';

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
 * Render the block and report the URLs it printed through esc_url().
 *
 * @param int $post_id Post ID to render.
 * @return array{0:string,1:string[]} Markup and the escaped URLs.
 */
function render_share( $post_id = 0 ) {
	$GLOBALS['escaped_urls'] = array();

	ob_start();
	majestic_tube_share_buttons( $post_id );
	$html = (string) ob_get_clean();

	return array( $html, $GLOBALS['escaped_urls'] );
}

/**
 * What the printer's own escaping produces for a URL.
 *
 * Kept beside the stub rather than reused from it so a check that names an
 * expected href does not also record itself as a URL the module printed.
 *
 * @param string $url Raw URL.
 * @return string
 */
function share_attr( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function all_networks_on() {
	$GLOBALS['opts'] = array(
		'enable-video-share'  => true,
		'twitter-video-share' => true,
		'reddit-video-share'  => true,
		'email-video-share'   => true,
	);
}

/* -------------------------------------------------------------------------
 * Fixtures
 * ---------------------------------------------------------------------- */

$GLOBALS['posts'] = array(
	7 => array(
		'post_title'   => 'A Clip & More',
		'post_excerpt' => '<p>An excerpt with <em>markup</em> in it.</p>',
		'post_content' => 'Body text a link preview should never reach.',
		'permalink'    => 'https://example.test/watch/?id=7&t=2',
	),
	8 => array(
		'post_title'   => 'No Excerpt',
		'post_excerpt' => '',
		'post_content' => '[video]Body text used as the share description.[/video]',
		'permalink'    => 'https://example.test/no-excerpt/',
	),
	9 => array(
		'post_title'   => 'Long Excerpt',
		'post_excerpt' => implode( ' ', array_fill( 0, 60, 'word' ) ),
		'post_content' => '',
		'permalink'    => 'https://example.test/long-excerpt/',
	),
);

$permalink = $GLOBALS['posts'][7]['permalink'];
$title     = $GLOBALS['posts'][7]['post_title'];
$networks  = majestic_tube_share_networks();

// What each enabled network must build, from the module's own callbacks: the
// permalink and the title as encoded query values, in that network's own
// parameter names.
$twitter_url = 'https://twitter.com/intent/tweet?url=' . rawurlencode( $permalink ) . '&text=' . rawurlencode( $title );
$reddit_url  = 'https://www.reddit.com/submit?url=' . rawurlencode( $permalink ) . '&title=' . rawurlencode( $title );
$email_url   = 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $permalink );

$encoded_permalink = 'https%3A%2F%2Fexample.test%2Fwatch%2F%3Fid%3D7%26t%3D2';

/* -------------------------------------------------------------------------
 * Every network builds its own URL
 * ---------------------------------------------------------------------- */

echo "\nEvery network builds its own URL\n";

all_networks_on();

list( $html, $urls ) = render_share( 7 );

check( 'the wrapper and its toggle are printed', false !== strpos( $html, '<div class="video-share">' ) && false !== strpos( $html, '<button type="button" class="button video-share-toggle">' ) );
check( 'one anchor per enabled network', 3 === substr_count( $html, '<a ' ), 'anchors: ' . substr_count( $html, '<a ' ) );
check( 'the X/Twitter URL is the one the module builds', in_array( $twitter_url, $urls, true ), $twitter_url );
check( 'the Reddit URL is the one the module builds', in_array( $reddit_url, $urls, true ), $reddit_url );
check( 'the email URL is the one the module builds', in_array( $email_url, $urls, true ), $email_url );
check( 'a permalink already carrying a query string arrives encoded, not truncated', false !== strpos( $html, $encoded_permalink ), 'escaped permalink missing from the markup' );
check( 'so it is encoded as a query value, not merely escaped', false !== strpos( $html, 'url=' . $encoded_permalink ) && false === strpos( $html, 'url=https://example.test' ) );
check( 'each anchor opens in a new tab', 3 === substr_count( $html, 'target="_blank"' ) );
check( 'and gives the opener away with it', 3 === substr_count( $html, 'rel="noopener nofollow"' ) );
check( 'the href is the escaped URL', false !== strpos( $html, 'href="' . share_attr( $twitter_url ) . '"' ) );

// The ids and icon classes are what custom CSS written for the original theme
// targets, which is the reason this module keeps the original option keys.
check( 'a link keeps the id the original theme hard-coded', false !== strpos( $html, 'id="majestic-tube-share-twitter"' ) );
check( 'and the icon class its stylesheet expects', false !== strpos( $html, 'class="fa fa-twitter"' ) );
check( 'the anchor carries the network label', false !== strpos( $html, 'aria-label="' . esc_attr( $networks['twitter-video-share']['label'] ) . '"' ) );
check( 'and the icon is hidden from assistive tech, which has the label already', false !== strpos( $html, 'aria-hidden="true"' ) );

/* -------------------------------------------------------------------------
 * The switches
 * ---------------------------------------------------------------------- */

echo "\nThe switches\n";

$GLOBALS['opts'] = array();
list( $html, $urls ) = render_share( 7 );

check( 'the master switch off prints nothing', '' === trim( $html ) );
check( 'and builds no share URL at all', ! $urls );

all_networks_on();
$GLOBALS['the_id'] = 0;
list( $html, $urls ) = render_share( 0 );

check( 'no post to share prints nothing', '' === trim( $html ) );
check( 'and builds no share URL either', ! $urls );

all_networks_on();
$GLOBALS['opts']['reddit-video-share'] = false;
list( $html, $urls ) = render_share( 7 );

check( 'a network switched off prints no icon for itself', false === strpos( $html, 'fa-reddit-square' ) );
check( 'and leaves no empty anchor behind', 2 === substr_count( $html, '<a ' ), 'anchors: ' . substr_count( $html, '<a ' ) );
check( 'and its URL is never built', ! in_array( $reddit_url, $urls, true ) );
check( 'while the other networks still print', false !== strpos( $html, 'majestic-tube-share-twitter' ) && false !== strpos( $html, 'majestic-tube-share-email' ) );

$GLOBALS['opts'] = array( 'enable-video-share' => true );
list( $html, $urls ) = render_share( 7 );

check( 'a block with no networks left prints nothing at all', '' === trim( $html ) );
check( 'so no toggle is left opening onto an empty box', false === strpos( $html, 'video-share-toggle' ) );

/* -------------------------------------------------------------------------
 * A link from the filter is escaped where it is printed
 * ---------------------------------------------------------------------- */

echo "\nA link a plugin adds\n";

all_networks_on();
$GLOBALS['share_links_filter'] = function ( $links ) {
	$links[] = array(
		'id'    => 'injected',
		'icon'  => 'x',
		'label' => 'A "quoted" label',
		'url'   => 'https://example.test/?a=1&b=2',
	);

	return $links;
};

list( $html, $urls ) = render_share( 7 );

check( 'a label from the filter is escaped where it is printed', false !== strpos( $html, 'aria-label="A &quot;quoted&quot; label"' ) );
check( 'and its URL is escaped too', false !== strpos( $html, 'href="' . share_attr( 'https://example.test/?a=1&b=2' ) . '"' ) );
check( 'and it reaches the printer through esc_url', in_array( 'https://example.test/?a=1&b=2', $urls, true ) );
check( 'so the filter can add a network without opening the block to one', false !== strpos( $html, 'id="injected"' ) );

$GLOBALS['share_links_filter'] = null;

/* -------------------------------------------------------------------------
 * The description
 * ---------------------------------------------------------------------- */

echo "\nThe description\n";

check( 'a post that does not exist has no description', '' === majestic_tube_share_description( share_fixture_post( 404 ) ) );
check( 'no post at all has none either', '' === majestic_tube_share_description( null ) );
check( 'the excerpt is used when there is one', 'An excerpt with markup in it.' === majestic_tube_share_description( share_fixture_post( 7 ) ) );

$long = majestic_tube_share_description( share_fixture_post( 9 ) );

check( 'a long excerpt is trimmed to what a preview shows', implode( ' ', array_fill( 0, 55, 'word' ) ) . '...' === $long, $long );
check( 'and the words before it are kept in order', 0 === strpos( $long, 'word word word ' ) );

$GLOBALS['content_filter'] = function ( $content ) {
	return $content . ' FILTERED';
};

$from_content = majestic_tube_share_description( share_fixture_post( 8 ) );

check( 'with no excerpt the body text is used', false !== strpos( $from_content, 'Body text used as the share description.' ), $from_content );
check( 'and it is passed through the the_content filter first', false !== strpos( $from_content, 'FILTERED' ), $from_content );
check( 'with shortcodes out of it', false === strpos( $from_content, '[video]' ), $from_content );

$GLOBALS['content_filter'] = null;

check( 'and the body is not consulted when an excerpt exists', false === strpos( majestic_tube_share_description( share_fixture_post( 7 ) ), 'never reach' ) );

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
