<?php
/**
 * Smoke test for the preview media a member can send with a video.
 *
 * A submitter has three ways to say what a card should show: one thumbnail
 * URL, a list of them to rotate through on hover, or a preview video that
 * plays when the pointer rests on the card. Three claims worth pinning,
 * because each is easy to undo by accident:
 *
 *   1. all three are offered, and the list is a plain box. The admin
 *      Thumbnails metabox adds one URL at a time through AJAX against an
 *      existing post; a submission has no post until it is saved, so the list
 *      has to be text a member can type;
 *   2. a list arrives one URL per line, is deduplicated and sanitized, and
 *      its first URL doubles as the main thumbnail - a card paints `thumb`
 *      before it rotates anything, so a submission with only a list would
 *      otherwise show the placeholder;
 *   3. one of the three is enough. "Thumbnail URL required" is answered by
 *      the list as well, and a preview video outranks the rotation the way
 *      the card template already has it.
 *
 * The form is really rendered and really posted: template-video-submit.php is
 * included with the shipping field helpers and validator lifted out of
 * inc/video-meta.php, so what runs is the code that ships rather than a copy
 * of the rule. The spam check is stubbed here; its own slot is covered by
 * tests/consent-smoke.php.
 *
 * Run:  php tests/video-submit-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['opts']               = array();
$GLOBALS['logged_in']          = true;
$GLOBALS['captcha_configured'] = false;
$GLOBALS['created']            = array();
$GLOBALS['meta']               = array();
$GLOBALS['terms']              = array();
$GLOBALS['next_post_id']       = 700;
$GLOBALS['url_raw']            = array();
$GLOBALS['headers']            = 0;
$GLOBALS['footers']            = 0;
$GLOBALS['opts']['enable-video-submission'] = 'on';
$_POST                         = array();

/* -------------------------------------------------------------------------
 * Core stubs.
 * ---------------------------------------------------------------------- */

function __( $s, $d = null ) {
	return $s;
}
function esc_html__( $s, $d = null ) {
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
function esc_html_e( $s, $d = null ) {
	echo esc_html( $s );
}
function esc_attr_e( $s, $d = null ) {
	echo esc_attr( $s );
}
/*
 * The text sanitizer as the submission path depends on it.
 *
 * Core's sanitize_text_field() throws away a script or style element with its
 * contents, drops every other tag and collapses runs of whitespace, and the
 * form leans on exactly that: a title a visitor types reaches wp_insert_post()
 * as text and cannot carry markup into the post. A stub that only trimmed
 * would let a title full of tags read as stored-as-typed, which is the one
 * thing this suite has to be able to tell apart from a sanitized title.
 *
 * @param string $s Raw value.
 * @return string
 */
function sanitize_text_field( $s ) {
	$s = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s );

	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}

/**
 * The textarea sanitizer: the same, with newlines kept.
 *
 * The rotation thumbnails arrive one URL per line, so a stub that collapsed
 * newlines the way the single-line sanitizer does would hide every check the
 * list parsing has.
 *
 * @param string $s Raw value.
 * @return string
 */
function sanitize_textarea_field( $s ) {
	$s = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s );

	return trim( preg_replace( '/[\r\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function wp_unslash( $s ) {
	return $s;
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_kses( $html ) {
	return (string) $html;
}
function wp_kses_post( $s ) {
	return (string) $s;
}

/**
 * The sanitizer as the tests need it: an allowlisted scheme, or nothing.
 *
 * Core's esc_url_raw() keeps http, https and a handful of others and drops
 * everything else, which is the property the submission path leans on when it
 * sanitizes a URL a visitor typed. Each raw value is recorded, so a test can
 * tell "the field was sanitized" from "the field was stored as typed".
 *
 * @param string $url Raw URL.
 * @return string
 */
function esc_url_raw( $url ) {
	$url = trim( (string) $url );

	$GLOBALS['url_raw'][] = $url;

	if ( '' === $url ) {
		return '';
	}

	return preg_match( '#^(https?:)?//#i', $url ) ? $url : '';
}

function add_action() {}
function add_filter() {}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return true;
}

function wp_nonce_field( $action, $name ) {
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />';
}

function get_permalink( $post = null ) {
	return 'https://example.test/submit-a-video/';
}

function the_title( $before = '', $after = '' ) {
	echo $before . 'Submit a Video' . $after;
}

function get_header() {
	++$GLOBALS['headers'];
	echo '<header>';
}

function get_footer() {
	++$GLOBALS['footers'];
	echo '<footer>';
}

function wp_dropdown_categories( $args = array() ) {
	echo '<select name="' . esc_attr( $args['name'] ) . '"><option value="0">Select a category</option></select>';
}

function term_exists( $term, $taxonomy = '' ) {
	return $term ? (int) $term : 0;
}

function taxonomy_exists( $taxonomy ) {
	return false;
}

/* -------------------------------------------------------------------------
 * The post model, so a submission can be read back.
 * ---------------------------------------------------------------------- */

function is_wp_error( $thing ) {
	return false;
}

function wp_insert_post( $postarr, $wp_error = false ) {
	$id = $GLOBALS['next_post_id']++;

	$GLOBALS['created'][ $id ] = $postarr;
	$GLOBALS['meta'][ $id ]    = array();

	return $id;
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['meta'][ $post_id ][ $key ] = array( $value );

	return true;
}

function add_post_meta( $post_id, $key, $value, $unique = false ) {
	if ( ! isset( $GLOBALS['meta'][ $post_id ][ $key ] ) ) {
		$GLOBALS['meta'][ $post_id ][ $key ] = array();
	}

	if ( $unique && in_array( $value, $GLOBALS['meta'][ $post_id ][ $key ], true ) ) {
		return false;
	}

	$GLOBALS['meta'][ $post_id ][ $key ][] = $value;

	return true;
}

function delete_post_meta( $post_id, $key, $value = '' ) {
	unset( $GLOBALS['meta'][ $post_id ][ $key ] );

	return true;
}

function set_post_format( $post_id, $format ) {
	$GLOBALS['meta'][ $post_id ]['format'] = array( $format );
}

function wp_set_post_terms( $post_id, $terms, $taxonomy = 'post_tag' ) {
	$GLOBALS['terms'][ $post_id ][ $taxonomy ] = $terms;

	return array();
}

function wp_set_object_terms( $post_id, $terms, $taxonomy ) {
	$GLOBALS['terms'][ $post_id ][ $taxonomy ] = $terms;

	return array();
}

/* -------------------------------------------------------------------------
 * The theme seams the template reads.
 * ---------------------------------------------------------------------- */

function is_user_logged_in() {
	return (bool) $GLOBALS['logged_in'];
}

function majestic_tube_option_is_on( $id ) {
	return 'on' === ( isset( $GLOBALS['opts'][ $id ] ) ? $GLOBALS['opts'][ $id ] : 'off' );
}

function majestic_tube_video_submission_form_is_shown() {
	return $GLOBALS['logged_in'] && majestic_tube_option_is_on( 'enable-video-submission' );
}

function majestic_tube_consent_satisfied() {
	return ! empty( $_POST['wpst_accept_terms'] );
}

function majestic_tube_consent_error_message() {
	return 'Please accept the terms of use.';
}

function majestic_tube_consent_field( $context ) {
	echo '<div class="form-field"><label><input type="checkbox" name="wpst_accept_terms" value="1" required /></label></div>';
}

function majestic_tube_captcha_is_configured() {
	return (bool) $GLOBALS['captcha_configured'];
}

function majestic_tube_captcha_token_field() {
	return 'cf-turnstile-response';
}

function majestic_tube_captcha_site_key() {
	return '1x00000000000000000000AA';
}

function majestic_tube_verify_captcha( $token ) {
	return 'good' === $token;
}

function majestic_tube_captcha_widget() {
	if ( ! majestic_tube_captcha_is_configured() ) {
		echo '<div class="majestic-tube-captcha" data-mt-captcha="reserved" aria-hidden="true"></div>';

		return;
	}

	echo '<div class="cf-turnstile majestic-tube-captcha" data-mt-captcha="widget" data-sitekey="' . esc_attr( majestic_tube_captcha_site_key() ) . '"></div>';
}

/**
 * Extract functions from a theme file and define them, the way the other
 * smoke tests do: the shipping code runs, unchanged.
 *
 * @param string   $file  Theme file.
 * @param string[] $names Function names.
 * @return void
 */
function load_functions( $file, $names ) {
	$source = file_get_contents( MAJESTIC_TUBE_DIR . '/' . $file );

	foreach ( $names as $fn ) {
		if ( ! preg_match( '/^function ' . $fn . '\(.*?^}/ms', $source, $match ) ) {
			fwrite( STDERR, "could not extract {$fn} from {$file}\n" );
			exit( 1 );
		}

		eval( $match[0] );
	}
}

load_functions(
	'inc/video-meta.php',
	array(
		'majestic_tube_video_submission_required_fields',
		'majestic_tube_video_submission_field_is_required',
		'majestic_tube_render_submission_field',
		'majestic_tube_validate_video_submission',
	)
);

load_functions( 'inc/multithumbs.php', array( 'majestic_tube_parse_submitted_thumbs' ) );

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
	printf( "  [FAIL] %s%s\n", $label, $context ? "\n         {$context}" : '' );
}

/**
 * Render the submission template as the page would.
 *
 * @param array $post Simulated request body.
 * @return string Markup.
 */
function render_submission( $post = array() ) {
	$_POST = $post;

	ob_start();
	include MAJESTIC_TUBE_DIR . '/template-video-submit.php';

	return (string) ob_get_clean();
}

/**
 * Post the form with the parts a visitor always sends.
 *
 * @param array $fields Fields to add or override.
 * @return string Markup.
 */
function submit( $fields = array() ) {
	$defaults = array(
		'wpst-post_nonce_field' => 'nonce',
		'wpst_accept_terms'     => '1',
		'wpst-submitted'        => '1',
		'wpst-video_title'      => 'A submitted video',
	);

	return render_submission( array_merge( $defaults, $fields ) );
}

/**
 * The values written for one meta key of the newest submission.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return string[]
 */
function meta_values( $post_id, $key ) {
	return isset( $GLOBALS['meta'][ $post_id ][ $key ] ) ? $GLOBALS['meta'][ $post_id ][ $key ] : array();
}

/**
 * The ID of the most recently created submission.
 *
 * @return int
 */
function last_post_id() {
	return $GLOBALS['next_post_id'] - 1;
}

/**
 * Forget every option, so one check's switches cannot leak into the next.
 *
 * @return void
 */
function reset_options() {
	$GLOBALS['opts'] = array( 'enable-video-submission' => 'on' );
}

echo "\nMajestic Tube - submitted video preview media\n";
echo str_repeat( '-', 46 ) . "\n";

/* -------------------------------------------------------------------------
 * The fields on the form.
 * ---------------------------------------------------------------------- */

echo "\nThe fields on the form\n";

reset_options();
$form = render_submission();

check(
	'the form asks for one thumbnail URL',
	false !== strpos( $form, 'id="wpst-thumb"' ) && false !== strpos( $form, 'type="url" id="wpst-thumb"' )
);
check(
	'and for a list of them, in a box that takes more than one line',
	false !== strpos( $form, '<textarea id="wpst-thumbs" name="wpst-thumbs" rows="3"' ),
	'the admin metabox adds thumbnails one AJAX request at a time against an existing post, which a submission does not have yet'
);
check(
	'and for a preview video to play on hover',
	false !== strpos( $form, 'type="url" id="wpst-trailer"' )
);
check(
	'none of the three carries the required attribute until a site asks for it',
	false === strpos( $form, 'id="wpst-thumb" name="wpst-thumb" required' ) &&
	false === strpos( $form, '<textarea id="wpst-thumbs" name="wpst-thumbs" rows="3" required' ) &&
	false === strpos( $form, 'id="wpst-trailer" name="wpst-trailer" required' ),
	$form
);

reset_options();
$GLOBALS['opts']['video-submit-thumbs-required']  = 'on';
$GLOBALS['opts']['video-submit-trailer-required'] = 'on';

$required_form = render_submission();

check(
	'the list and the preview video become required fields when the site says so',
	false !== strpos( $required_form, '<textarea id="wpst-thumbs" name="wpst-thumbs" rows="3" required' ) &&
	false !== strpos( $required_form, 'id="wpst-trailer" name="wpst-trailer" required' ),
	$required_form
);
check(
	'and both are answered by a switch in the Customizer',
	isset( majestic_tube_video_submission_required_fields()['thumbs'] ) &&
	isset( majestic_tube_video_submission_required_fields()['trailer'] ) &&
	'video-submit-thumbs-required' === majestic_tube_video_submission_required_fields()['thumbs'] &&
	'video-submit-trailer-required' === majestic_tube_video_submission_required_fields()['trailer']
);

/* -------------------------------------------------------------------------
 * What a typed list turns into.
 * ---------------------------------------------------------------------- */

echo "\nMany thumbnails\n";

$parsed = majestic_tube_parse_submitted_thumbs(
	"https://example.test/a.jpg\n\n  https://example.test/b.jpg  \r\nhttps://example.test/a.jpg\nnot-a-url\n"
);

check(
	'a typed list is split on newlines, trimmed and deduplicated',
	array( 'https://example.test/a.jpg', 'https://example.test/b.jpg' ) === $parsed,
	implode( ', ', $parsed )
);
check(
	'and a comma-separated list - the shape `thumbs` already has in the database - reads the same way',
	array( 'https://example.test/a.jpg', 'https://example.test/b.jpg' ) ===
		majestic_tube_parse_submitted_thumbs( 'https://example.test/a.jpg, https://example.test/b.jpg' )
);
check(
	'while an empty box is an empty list',
	array() === majestic_tube_parse_submitted_thumbs( "  \n \n" ) && array() === majestic_tube_parse_submitted_thumbs( '' )
);

reset_options();
$before = $GLOBALS['next_post_id'];
submit(
	array(
		'wpst-thumbs' => "https://example.test/one.jpg\nhttps://example.test/two.jpg\nhttps://example.test/three.jpg",
	)
);
$post_id = last_post_id();

check( 'the submission is saved', $GLOBALS['next_post_id'] > $before );
check(
	'and every thumbnail in the list is stored, one meta row per URL',
	array( 'https://example.test/one.jpg', 'https://example.test/two.jpg', 'https://example.test/three.jpg' ) === meta_values( $post_id, 'thumbs' ),
	implode( ', ', meta_values( $post_id, 'thumbs' ) )
);
check(
	'and the first URL becomes the main thumbnail, which is what the card paints before it rotates',
	array( 'https://example.test/one.jpg' ) === meta_values( $post_id, 'thumb' ),
	'majestic_tube_get_card_image() reads the `thumb` key and knows nothing about the list'
);
check( 'and no preview video is invented for it', array() === meta_values( $post_id, 'trailer_url' ) );

reset_options();
submit( array( 'wpst-thumb' => 'https://example.test/main.jpg' ) );
$single_id = last_post_id();

check(
	'a single thumbnail URL still lands in the main thumbnail key',
	array( 'https://example.test/main.jpg' ) === meta_values( $single_id, 'thumb' )
);
check( 'and adds nothing to the list', array() === meta_values( $single_id, 'thumbs' ) );

reset_options();
submit(
	array(
		'wpst-thumb'  => 'https://example.test/main.jpg',
		'wpst-thumbs' => "https://example.test/one.jpg\nhttps://example.test/two.jpg",
	)
);
$both_id = last_post_id();

check(
	'a main thumbnail is not overwritten by the list beside it',
	array( 'https://example.test/main.jpg' ) === meta_values( $both_id, 'thumb' ) &&
	array( 'https://example.test/one.jpg', 'https://example.test/two.jpg' ) === meta_values( $both_id, 'thumbs' )
);

/* -------------------------------------------------------------------------
 * The preview video.
 * ---------------------------------------------------------------------- */

echo "\nA preview video\n";

reset_options();
$before = $GLOBALS['next_post_id'];
$out    = submit( array( 'wpst-trailer' => 'https://example.test/preview.mp4' ) );
$trailer_id = last_post_id();

check(
	'a preview video is stored under the key the card template reads',
	$GLOBALS['next_post_id'] > $before && array( 'https://example.test/preview.mp4' ) === meta_values( $trailer_id, 'trailer_url' ),
	implode( ', ', meta_values( $trailer_id, 'trailer_url' ) )
);
check(
	'and a submission that carries only a preview video is a complete one',
	false === strpos( $out, 'Please provide' ) && false !== strpos( $out, 'Thanks for submitting a video' ),
	$out
);

$GLOBALS['url_raw'] = array();
reset_options();
$before             = $GLOBALS['next_post_id'];
submit( array( 'wpst-trailer' => 'javascript:alert(1)' ) );
$unsafe_id = last_post_id();

check(
	'the value reaches the meta key through the URL sanitizer, not as typed',
	in_array( 'javascript:alert(1)', $GLOBALS['url_raw'], true ),
	'esc_url_raw() is what drops a scheme that is not allowed'
);
check(
	'and what it refuses is not stored',
	$GLOBALS['next_post_id'] > $before && array() === meta_values( $unsafe_id, 'trailer_url' )
);

/* -------------------------------------------------------------------------
 * One of the three is enough.
 * ---------------------------------------------------------------------- */

echo "\nWhat a site can require\n";

reset_options();
$GLOBALS['opts']['video-submit-title-required'] = 'on';
$before = $GLOBALS['next_post_id'];
$out    = submit( array( 'wpst-video_title' => '' ) );

check(
	'an empty title is still refused, so the switches are the switches',
	$GLOBALS['next_post_id'] === $before && false !== strpos( $out, 'Please provide a video title.' ),
	$out
);

/*
 * A title a visitor types is text. It is the one field on this form that is
 * printed inside an <h1> and inside the structured data once an administrator
 * publishes the submission, so it is held to a sanitizer here rather than to
 * whatever the database is later handed - a submission cannot introduce markup
 * that the site then prints back to every reader.
 */
echo "\nA submitted title is text, not markup\n";

reset_options();
$before = $GLOBALS['next_post_id'];
submit( array( 'wpst-video_title' => 'Space <script>alert(1)</script> Voyage' ) );
$title_id = last_post_id();
$stored   = isset( $GLOBALS['created'][ $title_id ]['post_title'] ) ? $GLOBALS['created'][ $title_id ]['post_title'] : '';

check(
	'a script in a submitted title never reaches the post',
	$GLOBALS['next_post_id'] > $before && '' !== $stored && false === strpos( $stored, '<' ) && false === strpos( $stored, 'alert(1)' ),
	var_export( $stored, true )
);
check(
	'and the words around it are kept',
	false !== strpos( $stored, 'Space' ) && false !== strpos( $stored, 'Voyage' ),
	var_export( $stored, true )
);

$GLOBALS['url_raw'] = array();
reset_options();
submit( array( 'wpst-video_title' => 'Tag <b>bold</b> and <img src=x onerror=alert(1)> image' ) );
$bold_id = last_post_id();
$bold    = isset( $GLOBALS['created'][ $bold_id ]['post_title'] ) ? $GLOBALS['created'][ $bold_id ]['post_title'] : '';

check(
	'a tag in a submitted title is dropped as text too, not carried into the post',
	'' !== $bold && false === strpos( $bold, '<' ) && false === strpos( $bold, 'onerror' ),
	var_export( $bold, true )
);

reset_options();
$GLOBALS['opts']['video-submit-thumbs-required'] = 'on';
$before                                          = $GLOBALS['next_post_id'];
$out                                             = submit( array( 'wpst-thumbs' => '' ) );

check(
	'a required list that arrives empty is refused with its own message',
	false !== strpos( $out, 'Please provide at least one thumbnail URL to rotate' ) &&
	$GLOBALS['next_post_id'] === $before,
	$out
);

reset_options();
$GLOBALS['opts']['video-submit-trailer-required'] = 'on';
$before                                           = $GLOBALS['next_post_id'];
$out                                              = submit( array( 'wpst-trailer' => '' ) );

check(
	'a required preview video that arrives empty is refused too',
	false !== strpos( $out, 'Please provide a preview video URL' ) &&
	$GLOBALS['next_post_id'] === $before,
	$out
);

reset_options();
$GLOBALS['opts']['video-submit-thumbnail-link-required'] = 'on';
$GLOBALS['opts']['video-submit-thumbs-required']         = 'on';
$before                                                  = $GLOBALS['next_post_id'];
$out                                                     = submit( array( 'wpst-thumbs' => 'https://example.test/one.jpg' ) );

check(
	'"Thumbnail URL required" is answered by the list, so a member who sent several is not asked again for one',
	$GLOBALS['next_post_id'] > $before && false === strpos( $out, 'Please provide a thumbnail URL.' ),
	$out
);

reset_options();
$GLOBALS['opts']['video-submit-thumbnail-link-required'] = 'on';
$before                                                  = $GLOBALS['next_post_id'];
$out                                                     = submit( array( 'wpst-thumb' => '', 'wpst-thumbs' => '' ) );

check(
	'while a submission with neither is refused',
	$GLOBALS['next_post_id'] === $before && false !== strpos( $out, 'Please provide a thumbnail URL.' ),
	$out
);

/* -------------------------------------------------------------------------
 * The spam check's slot.
 * ---------------------------------------------------------------------- */

echo "\nThe spam check beside it\n";

reset_options();
$GLOBALS['captcha_configured'] = false;
$off_form                      = render_submission();

check(
	'the form keeps the slot the spam check sits in while protection is off',
	false !== strpos( $off_form, 'data-mt-captcha="reserved"' ) && false === strpos( $off_form, 'cf-turnstile' ),
	'a slot printed only while the check is on is the layout shift reserving it exists to prevent'
);

$GLOBALS['captcha_configured'] = true;
$on_form                       = render_submission();

check(
	'and fills it once protection is on',
	false !== strpos( $on_form, 'class="cf-turnstile majestic-tube-captcha"' ) &&
	false !== strpos( $on_form, 'data-sitekey="1x00000000000000000000AA"' )
);

reset_options();
$before = $GLOBALS['next_post_id'];
$out    = submit( array( 'cf-turnstile-response' => 'bad' ) );

check(
	'and a token that does not verify stops the submission',
	$GLOBALS['next_post_id'] === $before && false !== strpos( $out, 'Captcha verification failed' ),
	$out
);

reset_options();
$before = $GLOBALS['next_post_id'];
submit( array( 'cf-turnstile-response' => 'good' ) );

check( 'while one that does verify is let through', $GLOBALS['next_post_id'] > $before );

$GLOBALS['captcha_configured'] = false;

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
