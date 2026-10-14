<?php
/**
 * Smoke test for the front-end loading changes.
 *
 * Three claims are worth pinning, because each one is easy to undo by accident
 * while editing something that looks unrelated:
 *   1. the parent theme's style.css is not requested at all, while a child
 *      theme's still is;
 *   2. the first card of a listing is loaded eagerly and marked high priority,
 *      and the poster of a self-hosted video is preloaded from the head - but
 *      an embedded player preloads nothing it will not show;
 *   3. core's emoji stylesheet and detection script are unhooked on the front
 *      end, and left alone in the admin.
 *
 * The functions are lifted out of the theme files and run against stubs, so the
 * real code is exercised without a WordPress install.
 *
 * Run:  php tests/performance-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );
define( 'MAJESTIC_TUBE_VERSION', '2.5.0' );

$GLOBALS['view']         = 'front';
$GLOBALS['removed']      = array();
$GLOBALS['enqueued']     = array();
$GLOBALS['child_theme']  = false;
$GLOBALS['sources']      = array( 'type' => 'self-hosted' );
$GLOBALS['poster']       = 'https://example.test/poster.jpg';
$GLOBALS['thumb_kind']   = 'attachment';
$GLOBALS['attachments']  = array();

function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function apply_filters( $hook, $value ) {
	return $value;
}
function is_admin() {
	return 'admin' === $GLOBALS['view'];
}
function is_singular() {
	return 'singular' === $GLOBALS['view'];
}
function is_single() {
	return 'singular' === $GLOBALS['view'];
}
function remove_action( $hook, $cb, $priority = 10 ) {
	$GLOBALS['removed'][] = $hook . '::' . $cb;
}

/* -------------------------------------------------------------------------
 * Enqueue state
 * ---------------------------------------------------------------------- */

function get_stylesheet_uri() {
	return 'https://example.test/theme/style.css';
}
function get_stylesheet_directory() {
	return $GLOBALS['child_theme'] ? '/themes/majestic-tube-child' : '/themes/majestic-tube';
}
function get_template_directory() {
	return '/themes/majestic-tube';
}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = null ) {
	$GLOBALS['enqueued'][ $handle ] = array(
		'src'  => $src,
		'deps' => $deps,
	);
}
function majestic_tube_asset_version( $relative ) {
	return '2.5.0';
}
function majestic_tube_uses_bundled_font() {
	return false;
}

/* -------------------------------------------------------------------------
 * Card image state
 * ---------------------------------------------------------------------- */

function has_post_thumbnail( $post_id = 0 ) {
	return 'none' !== $GLOBALS['thumb_kind'];
}
function get_post_thumbnail_id( $post_id = 0 ) {
	return 99;
}
function the_title_attribute( $args = array() ) {
	return 'A video title';
}
function majestic_tube_get_thumb_size() {
	return 'wpst_thumb_medium';
}
function majestic_tube_get_thumb_url( $post_id = 0, $size = '' ) {
	return 'https://example.test/remote.jpg';
}
function majestic_tube_card_image_sizes() {
	return '(max-width: 600px) calc(100vw / 2)';
}
function wp_get_attachment_image( $id, $size = '', $icon = false, $attr = array() ) {
	$GLOBALS['attachments'][] = $attr;

	$out = '<img src="https://example.test/thumb-640.jpg"';

	foreach ( $attr as $name => $value ) {
		$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}

	return $out . ' />';
}

/* -------------------------------------------------------------------------
 * Poster preload state
 * ---------------------------------------------------------------------- */

function get_queried_object_id() {
	return is_singular() ? 42 : 0;
}
function majestic_tube_get_video_sources( $post_id = 0 ) {
	return $GLOBALS['sources'];
}
function majestic_tube_get_poster_url( $post_id = 0 ) {
	return $GLOBALS['poster'];
}

// Pull the functions under test out of the theme files.
function extract_functions( $file, $names ) {
	$source = file_get_contents( MAJESTIC_TUBE_DIR . '/' . $file );

	foreach ( $names as $fn ) {
		if ( ! preg_match( '/^function ' . $fn . '\(.*?^}/ms', $source, $match ) ) {
			fwrite( STDERR, "could not extract {$fn} from {$file}\n" );
			exit( 1 );
		}

		eval( $match[0] );
	}
}

extract_functions( 'inc/assets.php', array( 'majestic_tube_preload_poster', 'majestic_tube_trim_emoji_assets' ) );
extract_functions( 'inc/template-tags.php', array( 'majestic_tube_get_card_image' ) );

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
 * Render the card image and capture the attributes WordPress was handed.
 *
 * @return string
 */
function card_image() {
	$GLOBALS['attachments'] = array();

	return majestic_tube_get_card_image( 42 );
}

echo "\nThe first card carries the largest contentful paint\n";

/*
 * Priority follows the position on the page, not where the picture came from,
 * so the first card is checked on the remote-thumbnail path - the one that
 * cannot build a srcset - before the counter moves on.
 */
$GLOBALS['view']       = 'front';
$GLOBALS['thumb_kind'] = 'none';
$html                  = card_image();
check( 'the first card is loaded eagerly', false !== strpos( $html, 'loading="eager"' ) );
check( 'and marked high priority', false !== strpos( $html, 'fetchpriority="high"' ) );
check( 'including a remote thumbnail, which is the same element', false !== strpos( $html, 'src="https://example.test/remote.jpg"' ) );
$GLOBALS['thumb_kind'] = 'attachment';

$html = card_image();
check( 'the second card is lazy', false !== strpos( $html, 'loading="lazy"' ) );
check( 'and claims no priority', false === strpos( $html, 'fetchpriority' ) );
check( 'every card still gets the sizes attribute', false !== strpos( $html, 'sizes="(max-width: 600px) calc(100vw / 2)"' ) );

// A video page's cards are related videos below the fold; the poster is above.
$GLOBALS['view'] = 'singular';
check( 'no card claims priority on a video page', false === strpos( card_image(), 'fetchpriority' ) );

echo "\nThe poster starts loading from the head\n";

$GLOBALS['view'] = 'singular';

function poster_output() {
	ob_start();
	majestic_tube_preload_poster();

	return (string) ob_get_clean();
}

check( 'a self-hosted poster is preloaded', false !== strpos( poster_output(), '<link rel="preload" as="image" href="https://example.test/poster.jpg" fetchpriority="high" />' ) );

$GLOBALS['sources'] = array( 'type' => 'embed' );
check( 'an embedded player preloads nothing it will not show', '' === poster_output() );
$GLOBALS['sources'] = array( 'type' => 'self-hosted' );

$GLOBALS['poster'] = '';
check( 'and a video with no poster preloads nothing', '' === poster_output() );
$GLOBALS['poster'] = 'https://example.test/poster.jpg';

$GLOBALS['view'] = 'front';
check( 'an archive preloads no poster', '' === poster_output() );
$GLOBALS['view'] = 'page';
check( 'and neither does a static page, which has no player to paint it', '' === poster_output() );
$GLOBALS['view'] = 'singular';

echo "\nCore's emoji assets are dropped from the front end\n";

majestic_tube_trim_emoji_assets();
check( 'the emoji detection script is unhooked', in_array( 'wp_head::print_emoji_detection_script', $GLOBALS['removed'], true ) );
check( 'and the emoji stylesheet is unhooked', in_array( 'wp_print_styles::print_emoji_styles', $GLOBALS['removed'], true ) );

$GLOBALS['removed'] = array();
$GLOBALS['view']    = 'admin';
majestic_tube_trim_emoji_assets();
check( 'the admin keeps its emoji', array() === $GLOBALS['removed'] );
$GLOBALS['view'] = 'front';

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
