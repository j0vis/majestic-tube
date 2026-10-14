<?php
/**
 * Smoke test for the single-video template's password gate.
 *
 * A video behind a password is not a video yet. Core puts its password form in
 * front of the post content inside the_content(), but this template prints the
 * player - and, for a self-hosted file, the direct <source> URLs - above that
 * content, so a password-protected video used to hand its file to anyone who
 * followed the link, password or no password. comments.php already refused the
 * same post, which is what made the player the odd one out.
 *
 * Four claims worth pinning, because each is easy to undo by accident:
 *
 *   1. a protected video prints no player markup at all - no <video> element,
 *      no .video-player wrapper and, the part that actually leaks, no source
 *      URL anywhere in the page;
 *   2. it prints core's own password form instead, under the title, so the
 *      visitor is asked for the password rather than shown a blank page;
 *   3. the guard runs before the template reads any player data, so a protected
 *      post's sources, counters, likes and related videos are never fetched,
 *      and its description and comments never render;
 *   4. a post with no password, and one whose password the visitor has already
 *      supplied, both still get the full page - the gate is the post's own
 *      state, not a switch that hides videos.
 *
 * single.php is rendered for real against a stubbed WordPress, so what is
 * asserted is the markup the template prints, not a copy of the rule.
 *
 * Run:  php tests/single-password-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

// Core's own date format, which single.php passes to get_the_date().
defined( 'DATE_W3C' ) || define( 'DATE_W3C', 'Y-m-d\TH:i:sP' );

// The video every stub hands back: the URL that must not reach a locked page.
defined( 'MT_VIDEO_URL' ) || define( 'MT_VIDEO_URL', 'https://cdn.test/secret-cut.mp4' );
defined( 'MT_POSTER' ) || define( 'MT_POSTER', 'https://cdn.test/poster.jpg' );

$GLOBALS['posts']       = array();
$GLOBALS['loop']        = -1;
$GLOBALS['current']     = null;
$GLOBALS['password_ok'] = false;
$GLOBALS['on']          = array();
$GLOBALS['opts']        = array();
$GLOBALS['calls']       = array();

/* -------------------------------------------------------------------------
 * WordPress, in as much of it as one template touches.
 * ---------------------------------------------------------------------- */

function __( $s, $d = null ) {
	return $s;
}

function _n( $single, $plural, $number, $d = null ) {
	return 1 === (int) $number ? $single : $plural;
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

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function wp_reset_postdata() {}

function do_shortcode( $content ) {
	return (string) $content;
}

/**
 * The loop, driven the way the template calls it.
 *
 * have_posts() advances nothing and the_post() moves the pointer, which is how
 * core's own loop works: the condition is what ends it, so a `continue` in the
 * body is safe and has to be proven to be.
 *
 * @return bool
 */
function have_posts() {
	return ( $GLOBALS['loop'] + 1 ) < count( $GLOBALS['posts'] );
}

function the_post() {
	++$GLOBALS['loop'];
	$GLOBALS['current'] = $GLOBALS['posts'][ $GLOBALS['loop'] ];
}

function get_the_ID() {
	return $GLOBALS['current']->ID;
}

function get_post( $post = null ) {
	return $GLOBALS['current'];
}

function get_the_title( $post = 0 ) {
	return $GLOBALS['current']->post_title;
}

function the_ID() {
	echo (int) $GLOBALS['current']->ID;
}

function post_class( $extra = '' ) {
	echo 'class="video post ' . esc_attr( $extra ) . '"';
}

function the_title( $before = '', $after = '' ) {
	echo $before . esc_html( $GLOBALS['current']->post_title ) . $after;
}

function get_the_date( $format = '' ) {
	return DATE_W3C === $format ? '2026-10-01T10:00:00+00:00' : 'October 1, 2026';
}

function the_content() {
	echo '<p>' . esc_html( $GLOBALS['current']->post_content ) . '</p>';
}

function wp_link_pages( $args = array() ) {}

function comments_open() {
	return true;
}

function get_comments_number() {
	return 0;
}

function comments_template() {
	$GLOBALS['calls'][] = 'comments';
	echo '<!-- comments -->';
}

function is_active_sidebar( $sidebar ) {
	return false;
}

function get_template_part( $slug, $name = '' ) {}

function get_header() {
	echo '<!-- header -->';
}

function get_footer() {
	echo '<!-- footer -->';
}

/**
 * Core's password form, shaped like the real one.
 *
 * @return string
 */
function get_the_password_form( $post = null ) {
	$GLOBALS['calls'][] = 'password-form';

	return '<form action="https://example.test/secret/" class="post-password-form">' .
		'<p><label for="pwbox-43">Password</label> <input name="post_password" id="pwbox-43" type="password" size="20" /></p>' .
		'<p><input type="submit" name="Submit" value="Enter" /></p></form>';
}

/**
 * Whether the visitor still owes the post its password.
 *
 * Keyed off the post's own stored password and a cookie-free "already entered"
 * flag, the way core decides it: a post with no password is never locked, and a
 * protected one opens once the password is known.
 *
 * @param mixed $post Post to test, or nothing.
 * @return bool
 */
function post_password_required( $post = null ) {
	$post = $post ? $post : get_post();

	if ( ! $post || '' === (string) $post->post_password ) {
		return false;
	}

	return empty( $GLOBALS['password_ok'] );
}

/* -------------------------------------------------------------------------
 * The theme helpers single.php reaches for on the way to the player.
 * ---------------------------------------------------------------------- */

function majestic_tube_get_video_sources( $post_id = 0 ) {
	$GLOBALS['calls'][] = 'video-sources';

	return array(
		'type'      => 'self-hosted',
		'sources'   => array( 'video_url' => MT_VIDEO_URL ),
		'main'      => MT_VIDEO_URL,
		'embed'     => '',
		'shortcode' => '',
	);
}

function majestic_tube_get_player_sources( $post_id = 0, $data = null ) {
	$GLOBALS['calls'][] = 'player-sources';

	return array(
		array(
			'key'   => 'video_url',
			'url'   => MT_VIDEO_URL,
			'label' => '1080p',
			'type'  => 'video/mp4',
		),
	);
}

function majestic_tube_analytics_get( $post_id = 0 ) {
	$GLOBALS['calls'][] = 'analytics';

	return array(
		'views'    => 4210,
		'likes'    => 12,
		'dislikes' => 1,
	);
}

function majestic_tube_player_enabled() {
	return false;
}

function majestic_tube_option_is_on( $option ) {
	return ! empty( $GLOBALS['on'][ $option ] );
}

function majestic_tube_get_option( $group, $key, $default = '' ) {
	return isset( $GLOBALS['opts'][ $key ] ) ? $GLOBALS['opts'][ $key ] : $default;
}

function majestic_tube_widget_area_content( $area ) {
	return '';
}

function majestic_tube_breadcrumbs() {}

function majestic_tube_get_poster_url( $post_id = 0 ) {
	return MT_POSTER;
}

function majestic_tube_under_player_content( $post_id = 0 ) {}

function majestic_tube_get_post_like_rate( $post_id = 0 ) {
	return 75;
}

function wpst_get_post_like_link( $post_id = 0 ) {
	return '';
}

function majestic_tube_icon( $name ) {
	echo '<svg data-icon="' . esc_attr( $name ) . '"></svg>';
}

function majestic_tube_share_buttons( $post_id = 0 ) {
	echo '<div class="share"></div>';
}

function majestic_tube_report_button( $post_id = 0 ) {
	echo '<button type="button" class="report"></button>';
}

function majestic_tube_get_tracking_url( $post_id = 0 ) {
	return '';
}

function majestic_tube_get_human_number( $number ) {
	return (string) $number;
}

function majestic_tube_kses_player_markup( $markup ) {
	return (string) $markup;
}

function majestic_tube_is_mobile() {
	return false;
}

function majestic_tube_is_ctpl_active() {
	return false;
}

function majestic_tube_get_related_videos( $post_id = 0, $count = 6 ) {
	$GLOBALS['calls'][] = 'related';

	return null;
}

function wpst_cats_tags() {}

/* -------------------------------------------------------------------------
 * The harness.
 * ---------------------------------------------------------------------- */

$passed = 0;
$failed = 0;

function check( $label, $ok, $detail = '' ) {
	global $passed, $failed;

	if ( $ok ) {
		++$passed;
		echo "  ok   $label\n";

		return;
	}

	++$failed;
	echo "  FAIL $label" . ( $detail ? "  ($detail)" : '' ) . "\n";
}

/**
 * Render single.php as a request would, and report what it printed and asked for.
 *
 * @param object $post              Queried post.
 * @param bool   $password_supplied Whether the visitor already knows the password.
 * @return array{0: string, 1: string[]} Markup, then the helpers it called.
 */
function render_single( $post, $password_supplied = false ) {
	$GLOBALS['posts']       = array( $post );
	$GLOBALS['loop']        = -1;
	$GLOBALS['current']     = $post;
	$GLOBALS['password_ok'] = $password_supplied;
	$GLOBALS['calls']       = array();

	ob_start();
	include MAJESTIC_TUBE_DIR . '/single.php';

	return array( (string) ob_get_clean(), $GLOBALS['calls'] );
}

/**
 * Whether a helper was reached for.
 *
 * @param string[] $calls Recorded calls.
 * @param string   $name  Helper.
 * @return bool
 */
function called( $calls, $name ) {
	return in_array( $name, $calls, true );
}

/*
 * Everything the template can switch on is switched on, so a missing panel in
 * the locked page is the gate and not a setting that was off.
 */
$GLOBALS['on'] = array(
	'enable-breadcrumbs'           => true,
	'enable-views-system'          => true,
	'enable-rating-system'         => true,
	'enable-video-share'           => true,
	'enable-video-report'          => true,
	'show-description-video-about' => true,
	'truncate-description'         => true,
	'display-related-videos'       => true,
	'enable-comments'              => true,
	'autoplay-video-player'        => false,
);

$open_post = (object) array(
	'ID'            => 41,
	'post_title'    => 'A Very Secret Video',
	'post_content'  => 'The hidden cut.',
	'post_password' => '',
);

$locked_post = (object) array(
	'ID'            => 43,
	'post_title'    => 'A Very Secret Video',
	'post_content'  => 'The hidden cut.',
	'post_password' => 'letmein',
);

echo "\nMajestic Tube - the single video's password gate\n";
echo str_repeat( '-', 46 ) . "\n";

/* -------------------------------------------------------------------------
 * A post with no password: the page nothing may change.
 * ---------------------------------------------------------------------- */

echo "\nAn open video still plays\n";

list( $open, $open_calls ) = render_single( $open_post );

check(
	'the player is printed',
	false !== strpos( $open, '<video' ) && false !== strpos( $open, 'wpst-video' ) && false !== strpos( $open, 'class="video-player"' ),
	'the control page is what tells a missing player apart from a harness that renders nothing'
);
check( 'with its source URL in it', false !== strpos( $open, MT_VIDEO_URL ), MT_VIDEO_URL );
check( 'and no password form, which this post does not have', false === strpos( $open, 'post-password-form' ) );
check(
	'while the rest of the page renders too',
	false !== strpos( $open, '4210' ) && false !== strpos( $open, 'video-description' ) && false !== strpos( $open, '<!-- comments -->' )
);
check(
	'and it reached for the player data it needs',
	called( $open_calls, 'video-sources' ) && called( $open_calls, 'analytics' ),
	implode( ', ', $open_calls )
);

/* -------------------------------------------------------------------------
 * The same post behind a password.
 * ---------------------------------------------------------------------- */

echo "\nA protected video does not play until it is asked for\n";

list( $locked, $locked_calls ) = render_single( $locked_post );

check(
	'no player is printed, not even an empty one',
	false === strpos( $locked, '<video' ) && false === strpos( $locked, 'wpst-video' ) && false === strpos( $locked, 'class="video-player"' )
);
check(
	'and the source URL the player would have published is nowhere on the page',
	false === strpos( $locked, MT_VIDEO_URL ) && false === strpos( $locked, 'secret-cut' ),
	'this is the leak the gate exists for: a self-hosted file is reachable from its own URL'
);
check(
	'core\'s password form is printed instead',
	false !== strpos( $locked, 'post-password-form' ) && false !== strpos( $locked, 'name="post_password"' )
);
check(
	'under the title, so the visitor knows what they are being asked for',
	false !== strpos( $locked, 'A Very Secret Video' ) &&
	strpos( $locked, 'A Very Secret Video' ) < strpos( $locked, 'post-password-form' )
);
check(
	'and nothing else of the article is rendered around it',
	false === strpos( $locked, 'video-infos' ) &&
	false === strpos( $locked, 'video-description' ) &&
	false === strpos( $locked, 'The hidden cut.' ) &&
	false === strpos( $locked, '<!-- comments -->' )
);
check(
	'so its counters, its like button and its actions are gone with it',
	false === strpos( $locked, '4210' ) &&
	false === strpos( $locked, 'class="share"' ) &&
	false === strpos( $locked, 'class="report"' ) &&
	false === strpos( $locked, 'video-rate-bar' )
);
check(
	'and its player data was never even fetched',
	! called( $locked_calls, 'video-sources' ) &&
	! called( $locked_calls, 'player-sources' ) &&
	! called( $locked_calls, 'analytics' ) &&
	! called( $locked_calls, 'related' ) &&
	! called( $locked_calls, 'comments' ),
	implode( ', ', $locked_calls )
);
check(
	'while the password form itself did come from core',
	called( $locked_calls, 'password-form' ),
	'the guard has to hand the visitor the form WordPress offers, not a dead end'
);
check( 'and the page still carries the header and footer around it', false !== strpos( $locked, '<!-- header -->' ) && false !== strpos( $locked, '<!-- footer -->' ) );
check( 'and it is one article, not two', 1 === substr_count( $locked, '<article' ), (string) substr_count( $locked, '<article' ) );

/* -------------------------------------------------------------------------
 * The visitor who knows the password: the gate opens.
 * ---------------------------------------------------------------------- */

echo "\nAnd the same post opens once the password is supplied\n";

list( $opened, $opened_calls ) = render_single( $locked_post, true );

check(
	'the player is back',
	false !== strpos( $opened, '<video' ) && false !== strpos( $opened, MT_VIDEO_URL )
);
check( 'and the password form is not asked for twice', false === strpos( $opened, 'post-password-form' ) );
check(
	'and the rest of the article comes with it',
	false !== strpos( $opened, 'video-infos' ) && false !== strpos( $opened, '4210' ) && called( $opened_calls, 'video-sources' )
);

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
