<?php
/**
 * Smoke test for inc/basic-seo.php.
 *
 * The module is loaded for real against stubs, and each WordPress view is
 * simulated by changing one global, so the branches that matter - the SEO
 * plugin step-aside, the title override, the description chain, the canonical
 * split with core, and the two independent content-rating labels - are
 * exercised rather than described.
 *
 * Run:  php tests/basic-seo-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

/*
 * The current view, as WordPress would report it. Every is_*() stub below reads
 * it, so a test moves the site from "front page" to "category archive" by
 * assigning one string.
 */
$GLOBALS['view']    = 'front';
$GLOBALS['opts']    = array();
$GLOBALS['query']   = array(
	'paged'     => 0,
	'page'      => 0,
	'post_type' => '',
);
$GLOBALS['stored']  = array(
	'excerpt' => '',
	'content' => '',
);
$GLOBALS['hooks']     = array();
$GLOBALS['removed']   = array();
$GLOBALS['filtered']  = array();
$GLOBALS['site_icon'] = 'https://example.test/icon.png';
$GLOBALS['is_plugin'] = false;

class WP_Term {
	public $term_id  = 7;
	public $taxonomy = 'category';
	public $name     = 'Comedy';
	public $description = 'Funny videos';
}

function __( $s, $d = null ) {
	return $s;
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook . '::' . ( is_string( $cb ) ? $cb : 'closure' ) ] = $priority;
}
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook . '::' . ( is_string( $cb ) ? $cb : 'closure' ) ] = $priority;
}
function remove_action( $hook, $cb, $priority = 10 ) {
	$GLOBALS['removed'][] = $hook . '::' . $cb;
}
function apply_filters( $hook, $value ) {
	$GLOBALS['filtered'][] = $hook;

	return $value;
}

/* -------------------------------------------------------------------------
 * Site state
 * ---------------------------------------------------------------------- */

function majestic_tube_seo_view() {
	return $GLOBALS['view'];
}
function is_admin() {
	return false;
}
function is_feed() {
	return false;
}
function is_robots() {
	return false;
}
function is_search() {
	return 'search' === $GLOBALS['view'];
}
function is_404() {
	return '404' === $GLOBALS['view'];
}
function is_singular() {
	return in_array( $GLOBALS['view'], array( 'singular' ), true );
}
function is_front_page() {
	return 'front' === $GLOBALS['view'];
}
function is_home() {
	return 'home' === $GLOBALS['view'];
}
function is_category() {
	return 'category' === $GLOBALS['view'];
}
function is_tag() {
	return false;
}
function is_tax() {
	return false;
}
function is_author() {
	return 'author' === $GLOBALS['view'];
}
function is_post_type_archive() {
	return 'pt_archive' === $GLOBALS['view'];
}
function is_wp_error( $value ) {
	return $value instanceof WP_ErrorStub;
}
class WP_ErrorStub {}

function get_queried_object_id() {
	return is_singular() ? 42 : 7;
}
function get_queried_object() {
	if ( is_category() ) {
		return new WP_Term();
	}

	if ( is_author() ) {
		$author              = new stdClass();
		$author->description = 'About this author.';

		return $author;
	}

	return null;
}

/* -------------------------------------------------------------------------
 * Content, options and URLs
 * ---------------------------------------------------------------------- */

function get_post_field( $field, $post_id ) {
	// The real call passes 'post_excerpt' / 'post_content'.
	$key = 'post_excerpt' === $field ? 'excerpt' : ( 'post_content' === $field ? 'content' : $field );

	return isset( $GLOBALS['stored'][ $key ] ) ? $GLOBALS['stored'][ $key ] : '';
}
function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}
function strip_shortcodes( $text ) {
	return (string) $text;
}
function term_description() {
	$term = get_queried_object();

	return '<p>' . $term->description . '</p>';
}
function get_bloginfo( $what = '' ) {
	return 'description' === $what ? 'The site tagline.' : 'Majestic Tube';
}
function get_locale() {
	return 'en_US';
}
function get_option( $name, $default = false ) {
	return 'permalink_structure' === $name ? '/%postname%/' : $default;
}
function get_theme_mod( $name, $default = null ) {
	return $default;
}
function get_site_icon_url( $size = 512 ) {
	return (string) $GLOBALS['site_icon'];
}
function wp_get_attachment_image_src( $id, $size = 'full' ) {
	return array( 'https://example.test/logo.png', 100, 50 );
}
function get_post_time( $format, $gmt, $post_id ) {
	return '2026-01-01T10:00:00+00:00';
}
function get_post_modified_time( $format, $gmt, $post_id ) {
	return '2026-02-01T10:00:00+00:00';
}

/*
 * Mirrors inc/theme-options.php: a mapped field falls back to the map's
 * declared default, which is how "Basic SEO is on by default" is actually
 * decided.
 */
function majestic_tube_get_option( $group, $field_id, $default = false ) {
	$map_defaults = array(
		'enable-basic-seo'     => 'on',
		'home-seo-title'       => '',
		'home-seo-description' => '',
		'seo-footer-text'      => '',
		'adult-content-rating' => 'off',
		'rta-label'            => 'off',
	);

	if ( array_key_exists( $field_id, $GLOBALS['opts'] ) ) {
		return $GLOBALS['opts'][ $field_id ];
	}

	if ( array_key_exists( $field_id, $map_defaults ) ) {
		return $map_defaults[ $field_id ];
	}

	return $default;
}
function majestic_tube_option_is_on( $field_id ) {
	return 'on' === majestic_tube_get_option( 'wpst-options', $field_id, 'off' );
}

function get_permalink( $post_id = 0 ) {
	return 'https://example.test/video/amber-waves/';
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function get_term_link( $term ) {
	return 'https://example.test/category/comedy/';
}
function get_author_posts_url( $id ) {
	return 'https://example.test/author/joe/';
}
function get_post_type_archive_link( $type ) {
	return 'https://example.test/videos/';
}
function get_query_var( $key ) {
	return isset( $GLOBALS['query'][ $key ] ) ? $GLOBALS['query'][ $key ] : 0;
}
function trailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' ) . '/';
}
function user_trailingslashit( $string, $type = '' ) {
	return trailingslashit( $string );
}
function add_query_arg( $key, $value, $url ) {
	return $url . '?' . $key . '=' . $value;
}
function wp_get_document_title() {
	return 'Majestic Tube';
}
function majestic_tube_get_poster_url( $post_id = 0 ) {
	return (string) apply_filters( 'mt_test_poster', 'https://example.test/poster.jpg' );
}
function majestic_tube_get_term_image_url( $term_id, $taxonomy, $size = '', $allow = true ) {
	return '';
}

/** Faithful copy of core's helper, so the truncation check is real. */
function wp_html_excerpt( $str, $count, $more = null ) {
	if ( null === $more ) {
		$more = '';
	}

	$str     = wp_strip_all_tags( $str );
	$excerpt = mb_substr( $str, 0, $count );
	$excerpt = preg_replace( '/&[^;\s]{0,6}$/', '', $excerpt );

	if ( $str !== $excerpt ) {
		$excerpt = trim( $excerpt ) . $more;
	}

	return $excerpt;
}

require MAJESTIC_TUBE_DIR . '/inc/basic-seo.php';

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
 * Run one head callback and return what it printed.
 *
 * @return string
 */
function head_output() {
	ob_start();
	majestic_tube_seo_output_head();

	return (string) ob_get_clean();
}

function rating_output() {
	ob_start();
	majestic_tube_seo_output_rating_labels();

	return (string) ob_get_clean();
}

/** Reset per-request option state between checks. */
function reset_opts() {
	$GLOBALS['opts'] = array();
}

echo "\nRegistered on the page head\n";

check( 'description block runs before the stylesheets', 2 === $GLOBALS['hooks']['wp_head::majestic_tube_seo_output_head'] );
check( 'rating labels registered', isset( $GLOBALS['hooks']['wp_head::majestic_tube_seo_output_rating_labels'] ) );
check( 'title parts filtered, never printed again', isset( $GLOBALS['hooks']['document_title_parts::majestic_tube_seo_filter_title_parts'] ) );

echo "\nThe step-aside is the whole feature's safety net\n";

$GLOBALS['view'] = 'front';
check( 'on by default with no plugin', majestic_tube_basic_seo_enabled() );
check( 'offering a filter for the detection, for a plugin not on the list', in_array( 'majestic_tube_seo_plugin_detected', $GLOBALS['filtered'], true ) );

$GLOBALS['opts']['enable-basic-seo'] = 'off';
check( 'switched off by hand', ! majestic_tube_basic_seo_enabled() );
reset_opts();

echo "\nHomepage search title\n";

$GLOBALS['opts']['home-seo-title'] = 'Free Amateur Videos';
$parts                             = majestic_tube_seo_filter_title_parts(
	array(
		'title'   => 'Majestic Tube',
		'tagline' => 'The site tagline.',
		'site'    => 'Majestic Tube',
	)
);

check( 'the parts are replaced, not a second title printed', 'Free Amateur Videos' === $parts['title'] );
check( 'core cannot append a tagline to it', ! isset( $parts['tagline'] ) );
reset_opts();

$GLOBALS['view'] = 'singular';
$parts           = majestic_tube_seo_filter_title_parts( array( 'title' => 'A video' ) );
check( 'a video page title is left alone', 'A video' === $parts['title'] );
$GLOBALS['view'] = 'front';

echo "\nDescriptions\n";

$GLOBALS['stored'] = array(
	'excerpt' => 'The written excerpt.',
	'content' => 'The body text.',
);
$GLOBALS['view']   = 'singular';
check( 'an excerpt wins', 'The written excerpt.' === majestic_tube_seo_description_text() );

$GLOBALS['stored']['excerpt'] = '';
check( 'then the body text', 'The body text.' === majestic_tube_seo_description_text() );

$GLOBALS['stored']['content'] = '<p>Body <em>with</em> markup</p>';
check( 'markup is stripped and spaces collapsed', 'Body with markup' === majestic_tube_seo_description_text() );

$GLOBALS['stored']['content'] = '';
check( 'then the site tagline, never nothing', 'The site tagline.' === majestic_tube_seo_description_text() );

$GLOBALS['view'] = 'category';
check( 'a term archive uses its own description', 'Funny videos' === majestic_tube_seo_description_text() );

$GLOBALS['view'] = 'front';
$GLOBALS['opts']['home-seo-description'] = 'What the homepage is about.';
check( 'the homepage uses its own field', 'What the homepage is about.' === majestic_tube_seo_description_text() );

$GLOBALS['opts']['home-seo-description'] = '';
$GLOBALS['opts']['seo-footer-text']      = 'A visible introduction the editor wrote. It has markup <em>inside</em>.';
check( 'then the visible homepage introduction, as that control promises', 'A visible introduction the editor wrote. It has markup inside.' === majestic_tube_seo_description_text() );

$GLOBALS['opts']['seo-footer-text'] = '';
check( 'and only then the site tagline', 'The site tagline.' === majestic_tube_seo_description_text() );
reset_opts();

$GLOBALS['stored']['content'] = str_repeat( 'long words here ', 40 );
$GLOBALS['view']              = 'singular';
$meta                         = majestic_tube_seo_excerpt( 'description' );
$social                       = majestic_tube_seo_excerpt( 'social' );

check( 'the search description is cut near the 160-character display width', mb_strlen( $meta ) <= 161, 'len=' . mb_strlen( $meta ) );
check( 'and says it was cut', '…' === mb_substr( $meta, -1 ) );
check( 'the social card is allowed to be longer', mb_strlen( $social ) > mb_strlen( $meta ) );

echo "\nCanonical: the theme only fills gaps core leaves\n";

$GLOBALS['stored'] = array( 'excerpt' => '', 'content' => '' );
$GLOBALS['view']   = 'singular';
check( 'a singular view leaves it to core', '' === majestic_tube_seo_canonical_url() );

$GLOBALS['view'] = 'front';
check( 'the front page gets one', 'https://example.test/' === majestic_tube_seo_canonical_url() );

$GLOBALS['query']['paged'] = 2;
check( 'page two canonicalises to itself', 'https://example.test/page/2/' === majestic_tube_seo_canonical_url() );
$GLOBALS['query']['paged'] = 0;

$GLOBALS['view'] = 'category';
check( 'a term archive gets one', 'https://example.test/category/comedy/' === majestic_tube_seo_canonical_url() );

$GLOBALS['view']                = 'pt_archive';
$GLOBALS['query']['post_type']  = 'post';
check( 'a post type archive gets one', 'https://example.test/videos/' === majestic_tube_seo_canonical_url() );
$GLOBALS['query']['post_type']  = '';

$GLOBALS['view'] = 'search';
check( 'a search result claims no address', '' === majestic_tube_seo_canonical_url() );

$GLOBALS['view'] = '404';
check( 'nor does a 404', '' === majestic_tube_seo_canonical_url() );

echo "\nSocial card\n";

$GLOBALS['view'] = 'front';
$html            = head_output();
check( 'og:url points at the page', false !== strpos( $html, '<meta property="og:url" content="https://example.test/" />' ) );
check( 'an archive is a website', false !== strpos( $html, '<meta property="og:type" content="website" />' ) );
check( 'the site name is carried', false !== strpos( $html, 'og:site_name' ) );
check( 'a meta description is printed', false !== strpos( $html, '<meta name="description"' ) );
check( 'the Site Icon is the last-resort card image', false !== strpos( $html, '<meta property="og:image" content="https://example.test/icon.png" />' ) );
check( 'an image makes the card large', false !== strpos( $html, '<meta name="twitter:card" content="summary_large_image" />' ) );
check( 'no <title> is printed here', false === strpos( $html, '<title' ) );

$GLOBALS['site_icon'] = '';
$html                 = head_output();
check( 'with no image anywhere the card falls back to summary', false !== strpos( $html, '<meta name="twitter:card" content="summary" />' ) );
check( 'and no empty og:image is printed', false === strpos( $html, 'og:image' ) );
$GLOBALS['site_icon'] = 'https://example.test/icon.png';

$GLOBALS['view'] = 'singular';
$html            = head_output();
check( 'a video page is an article', false !== strpos( $html, '<meta property="og:type" content="article" />' ) );
check( 'with a publication time', false !== strpos( $html, 'article:published_time' ) );
check( 'and no second canonical', false === strpos( $html, 'rel="canonical"' ) );
check( 'the video poster becomes the card image', false !== strpos( $html, '<meta property="og:image" content="https://example.test/poster.jpg" />' ) );
check( 'and the card is large', false !== strpos( $html, '<meta name="twitter:card" content="summary_large_image" />' ) );

echo "\nContent rating labels are opt-in and independent\n";

check( 'off by default', '' === rating_output() );

$GLOBALS['opts']['adult-content-rating'] = 'on';
$html                                    = rating_output();
check( 'the adult value is exactly what Google documents', false !== strpos( $html, '<meta name="rating" content="adult" />' ) );
check( 'and the RTA label is not added with it', false === stripos( $html, 'RTA-5042' ) );

$GLOBALS['opts']['rta-label'] = 'on';
$html                         = rating_output();
check( 'the RTA label is verbatim', false !== strpos( $html, '<meta name="RATING" content="RTA-5042-1996-1400-1577-RTA" />' ) );

$GLOBALS['opts'] = array();

/*
 * The plugin constants are defined last on purpose: their detection is a chain
 * of defined()/class_exists() calls, so once a constant exists for the rest of
 * this process the theme rightly stands aside. Running these at the end keeps
 * every check above on the no-plugin path a fresh site is on.
 */
echo "\nThe step-aside, once a plugin is loaded\n";

$plugin_constants = array( 'WPSEO_VERSION', 'RANK_MATH_VERSION', 'AIOSEO_VERSION', 'SEOPRESS_VERSION', 'THE_SEO_FRAMEWORK_VERSION', 'SLIM_SEO_VER' );

foreach ( $plugin_constants as $constant ) {
	if ( ! defined( $constant ) ) {
		define( $constant, '1.0' );
	}

	check( "steps aside for {$constant}", ! majestic_tube_basic_seo_enabled() );
}

$GLOBALS['view'] = 'front';
check( 'and prints nothing at all while it stands aside', '' === head_output() );

$parts = majestic_tube_seo_filter_title_parts( array( 'title' => 'Majestic Tube' ) );
check( 'the title override stands down too', 'Majestic Tube' === $parts['title'] );
$GLOBALS['filtered'] = array();
majestic_tube_seo_filter_title_parts( array( 'title' => 'Majestic Tube' ) );
check( 'and nothing else in the module runs either', ! in_array( 'majestic_tube_seo_description', $GLOBALS['filtered'], true ) );

/*
 * The rating labels are not search appearance, so they keep working while a
 * plugin owns everything else.
 */
$GLOBALS['opts']['adult-content-rating'] = 'on';
check( 'the content-rating labels still print under a plugin', false !== strpos( rating_output(), 'content="adult"' ) );
$GLOBALS['opts'] = array();

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
