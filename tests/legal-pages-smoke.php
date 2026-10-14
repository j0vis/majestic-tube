<?php
/**
 * Smoke test for the legal pages: the retired template notice, and the widget
 * areas those pages no longer carry.
 *
 * Two claims worth pinning, because both are easy to undo by accident:
 *
 *   1. the shipped legal copy is the policy and nothing else. It used to end
 *      with a bolded "Template notice" paragraph written for the administrator,
 *      which shipped inside the page body where a visitor reads it as the site
 *      disclaiming its own document;
 *   2. a legal page prints no widget area at all. An ad tag or a promo placed
 *      in one reads as the site's own wording, so the areas are withheld rather
 *      than hidden, while the header, menus, footer legal links and copyright
 *      the theme prints itself are untouched;
 *   3. and no advertising of any kind reaches it, from any placement. The
 *      in-feed block, the popunder, a legacy option key and a per-video
 *      snippet are not widget areas, so they are guarded by the printer they
 *      all share rather than by each of them remembering the rule.
 *
 * The functions are lifted out of inc/activation.php and inc/ads.php and run
 * against stubs, and footer.php is rendered for real, so the code under test is
 * the shipping code rather than a copy of the rule.
 *
 * Run:  php tests/legal-pages-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );
// Core's own output-mode constant, which the get_page_by_path() signature names.
defined( 'OBJECT' ) || define( 'OBJECT', 'OBJECT' );

/*
 * The post model is kept in $GLOBALS['posts'], not $GLOBALS['pages']: that name
 * is a global variable in this script too, and $GLOBALS['pages'] *is* the
 * top-level $pages, so a local list of page definitions assigned to $pages
 * quietly becomes the store get_post() reads.
 */
$GLOBALS['opts']       = array();
$GLOBALS['queried_id'] = 0;
$GLOBALS['posts']      = array();
$GLOBALS['active']     = array();
$GLOBALS['widgets']    = array();

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
function esc_attr_e( $s, $d = null ) {
	echo esc_attr( $s );
}
function esc_html_e( $s, $d = null ) {
	echo esc_html( $s );
}
function wp_kses_post( $s ) {
	return (string) $s;
}
function sanitize_html_class( $s ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s );
}
function absint( $value ) {
	return abs( (int) $value );
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}
function get_post( $id ) {
	foreach ( $GLOBALS['posts'] as $page ) {
		if ( (int) $page->ID === (int) $id ) {
			return $page;
		}
	}

	return null;
}
function get_queried_object_id() {
	return (int) $GLOBALS['queried_id'];
}
function get_bloginfo( $key = '' ) {
	return 'Example Site';
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}
function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? time() : $timestamp );
}
function has_nav_menu( $location ) {
	return false;
}
function wp_nav_menu( $args ) {
}
function wp_footer() {
}
function majestic_tube_icon( $name, $class = '' ) {
}
function majestic_tube_get_option( $group, $field_id, $default = false ) {
	return array_key_exists( $field_id, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $field_id ] : $default;
}
function majestic_tube_option_is_on( $field_id ) {
	return 'on' === majestic_tube_get_option( 'wpst-options', $field_id, 'off' );
}

/*
 * The combined guard asks the consent half of the question as well, so these
 * are the WordPress ends of that lookup. The consent functions themselves are
 * the shipping ones, lifted out of inc/consent.php below: with no terms page
 * and no privacy policy in the fixture the answer is "not this request", which
 * is the answer the legal half of this suite needs. The consent half is
 * covered in full by tests/consent-smoke.php.
 */
function apply_filters( $tag, $value ) {
	return $value;
}
function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
	foreach ( $GLOBALS['posts'] as $page ) {
		if ( (string) $page->post_name === (string) $slug ) {
			return $page;
		}
	}

	return null;
}
function get_permalink( $id ) {
	$page = get_post( $id );

	return $page ? 'https://example.test/' . $page->post_name . '/' : '';
}
function get_privacy_policy_url() {
	return '';
}
/*
 * Reads a store the test fills in, so a placement that prints post meta - the
 * per-video snippet under the player - can be run for real. An entry that was
 * never set reads as '', which is what the suite had before it needed one.
 */
function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['post_meta'][ $id ][ $key ] ) ? $GLOBALS['post_meta'][ $id ][ $key ] : '';
}
function is_page_template( $template = '' ) {
	return false;
}
/*
 * Switchable, because the withheld rule covers a second kind of page - the
 * video submission form - and that page only exists for a signed-in member
 * with submissions enabled.
 */
function is_user_logged_in() {
	return ! empty( $GLOBALS['logged_in'] );
}

function is_admin() {
	return ! empty( $GLOBALS['is_admin'] );
}

function wp_doing_ajax() {
	return ! empty( $GLOBALS['doing_ajax'] );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function do_shortcode( $content ) {
	return (string) $content;
}

/*
 * The sidebar stubs mirror core: is_active_sidebar() asks the registered
 * assignment list, and dynamic_sidebar() prints the widgets assigned to that
 * area. A fixture area is "active" only when this test put a widget in it, so
 * an empty area prints nothing - the same thing a real site does.
 */
function is_active_sidebar( $area_id ) {
	return ! empty( $GLOBALS['active'][ $area_id ] );
}

function dynamic_sidebar( $area_id ) {
	if ( empty( $GLOBALS['active'][ $area_id ] ) ) {
		return false;
	}

	echo $GLOBALS['widgets'][ $area_id ];

	return true;
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
	'inc/activation.php',
	array(
		'majestic_tube_legal_pages',
		'majestic_tube_legal_page_former_titles',
		'majestic_tube_legal_page_titles',
		'majestic_tube_strip_template_notice',
		'majestic_tube_page_is_viewable',
		'majestic_tube_is_legal_document',
		'majestic_tube_current_legal_document',
		'majestic_tube_is_legal_page',
	)
);

/*
 * The areas ask one combined question - legal page or consent page - so the
 * consent chain is loaded here too rather than stubbed. An ordinary page has
 * to come back false through the real code, or this suite would keep passing
 * while the guard it is testing had stopped asking anything.
 */
load_functions(
	'inc/consent.php',
	array(
		'majestic_tube_consent_ask_enabled',
		'majestic_tube_consent_page_url',
		'majestic_tube_consent_documents',
		'majestic_tube_consent_policy_links',
		'majestic_tube_consent_is_required',
		'majestic_tube_video_submission_form_is_shown',
		'majestic_tube_is_video_submission_page',
		'majestic_tube_is_consent_page',
		'majestic_tube_widget_areas_withheld',
	)
);

load_functions(
	'inc/widgets.php',
	array(
		'majestic_tube_content_widget_areas',
		'majestic_tube_widget_area_content',
	)
);

load_functions( 'inc/ads.php', array( 'majestic_tube_content_location' ) );

/*
 * The placements that are not widget areas. They are code printed by a hook or
 * a template, so they never pass through the capture function above and have to
 * ask the withheld question for themselves.
 */
load_functions(
	'inc/ads.php',
	array(
		'majestic_tube_ads_allowed',
		'majestic_tube_get_ad',
		'majestic_tube_get_in_feed_ad',
		'majestic_tube_has_ad',
		'majestic_tube_get_popunder_ad',
		'majestic_tube_output_popunder_ad',
		'majestic_tube_prepare_content',
		'majestic_tube_content_block',
		'majestic_tube_render_shortcodes',
		'majestic_tube_ad',
		'majestic_tube_get_post_ad',
		'majestic_tube_under_player_content',
	)
);

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
 * Whether a string ends with another.
 *
 * The suite runs on the oldest PHP the theme supports, and that floor is 7.4,
 * where str_ends_with() does not exist: the check below asserted the sentence
 * with the notice removed ends where it should, and on 7.4 the call was an
 * uncaught Error that ended the whole file instead of a single check. Two lines
 * here rather than a version test at every call site.
 *
 * @param string $haystack String to search.
 * @param string $needle   Text the string should end with.
 * @return bool
 */
function mt_ends_with( $haystack, $needle ) {
	$haystack = (string) $haystack;
	$needle   = (string) $needle;

	return '' === $needle || substr( $haystack, -strlen( $needle ) ) === $needle;
}

/**
 * Whether a string starts with another.
 *
 * The counterpart of mt_ends_with(), and there for the same reason: the suite
 * has to answer on PHP 7.4, where str_starts_with() is not defined either.
 *
 * @param string $haystack String to search.
 * @param string $needle   Text the string should start with.
 * @return bool
 */
function mt_starts_with( $haystack, $needle ) {
	$haystack = (string) $haystack;
	$needle   = (string) $needle;

	return '' === $needle || 0 === strpos( $haystack, $needle );
}

/**
 * Add a page to the model.
 *
 * @param int    $id     Page ID.
 * @param string $title  Page title.
 * @param string $slug   Page slug.
 * @param string $type   Post type.
 * @param string $status Post status.
 * @return object
 */
function add_page( $id, $title, $slug, $type = 'page', $status = 'publish' ) {
	$page               = new stdClass();
	$page->ID           = $id;
	$page->post_title   = $title;
	$page->post_name    = $slug;
	$page->post_content = '';
	$page->post_type    = $type;
	$page->post_status  = $status;

	$GLOBALS['posts'][] = $page;

	return $page;
}

/**
 * Capture whatever a render prints.
 *
 * @param callable $callback Renderer.
 * @return string
 */
function capture( $callback ) {
	ob_start();
	$callback();

	return (string) ob_get_clean();
}

/**
 * Render one content area, returning what it printed and what it returned.
 *
 * Both halves matter: the guard could print nothing and still report success,
 * or report failure after printing.
 *
 * @param string $location Area key.
 * @return array{0: string, 1: bool} Printed markup, then the return value.
 */
function render_location( $location ) {
	ob_start();
	$returned = majestic_tube_content_location( $location );

	return array( (string) ob_get_clean(), (bool) $returned );
}

echo "\nMajestic Tube - legal pages\n";
echo str_repeat( '-', 46 ) . "\n";

/* -------------------------------------------------------------------------
 * The shipped copy.
 * ---------------------------------------------------------------------- */

echo "\nThe shipped copy carries the policy, not a notice\n";

$definitions = majestic_tube_legal_pages();

check( 'all three legal pages still ship', 3 === count( $definitions ), 'found ' . count( $definitions ) );

/*
 * The 2257 document is named the way the statute is written, which is a rename
 * of a page that already exists on every live site. The old name is kept as a
 * former title so the page can still be found, and so a page the operator typed
 * a title onto is not mistaken for the theme's own.
 */
check(
	'the 2257 document is named the way the statute is written',
	isset( $definitions['18 U.S.C. § 2257'] )
);
check(
	'and the name it was created under is still a name it answers to',
	in_array( '18 USC 2257', majestic_tube_legal_page_former_titles( $definitions['18 U.S.C. § 2257'] ), true ),
	implode( ', ', majestic_tube_legal_page_former_titles( $definitions['18 U.S.C. § 2257'] ) )
);
check(
	'while a name nobody shipped is not',
	! in_array( 'My Own 2257 Notice', majestic_tube_legal_page_former_titles( $definitions['18 U.S.C. § 2257'] ), true )
);
check(
	'the title lookup searches both names',
	array( '18 U.S.C. § 2257', '18 USC 2257' ) === majestic_tube_legal_page_titles( '18 U.S.C. § 2257', $definitions['18 U.S.C. § 2257'] ),
	implode( ', ', majestic_tube_legal_page_titles( '18 U.S.C. § 2257', $definitions['18 U.S.C. § 2257'] ) )
);
check(
	'and a document that was never renamed has only its current name',
	array( 'DMCA' ) === majestic_tube_legal_page_titles( 'DMCA', $definitions['DMCA'] ),
	implode( ', ', majestic_tube_legal_page_titles( 'DMCA', $definitions['DMCA'] ) )
);

foreach ( $definitions as $title => $definition ) {
	$content = $definition['content'];

	check( $title . ': no template notice in the page body', false === strpos( $content, 'Template notice' ) );
	check( $title . ': no separator left dangling above it', false === strpos( $content, '<hr' ) );
	check( $title . ': the policy itself is still there', strlen( $content ) > 400, strlen( $content ) . ' bytes' );
}

check(
	'the DMCA page still ends with the response section',
	false !== strpos( $definitions['DMCA']['content'], 'repeat infringers' )
);
check(
	'the 2257 page still ends with the contact section',
	false !== strpos( $definitions['18 U.S.C. § 2257']['content'], 'Questions about records' )
);
check(
	'the privacy page still ends with the contact section',
	false !== strpos( $definitions['Privacy Policy']['content'], 'Privacy questions and requests' )
);

/* -------------------------------------------------------------------------
 * The upgrade path: a page an earlier release created.
 * ---------------------------------------------------------------------- */

echo "\nA page an earlier release already created\n";

$stored = "<h2>DMCA Copyright Policy</h2>\n"
	. "<p>The operator may review and remove alleged infringing material.</p>\n"
	. "<hr />\n"
	. '<p><strong>Template notice:</strong> This page is a general starting point, not legal advice.</p>';

check( 'the paragraph is removed', false === strpos( majestic_tube_strip_template_notice( $stored ), 'Template notice' ) );
check(
	'the separator that introduced it goes with it',
	mt_ends_with( majestic_tube_strip_template_notice( $stored ), 'infringing material.</p>' ),
	'got: ' . json_encode( majestic_tube_strip_template_notice( $stored ) )
);
check(
	'the administrator wording around it survives untouched',
	false !== strpos( majestic_tube_strip_template_notice( $stored ), '<h2>DMCA Copyright Policy</h2>' )
);

$without_hr = "<p>Contact us.</p>\n"
	. '<p><strong>Template notice:</strong> A notice with no separator above it.</p>';

check( 'a notice with no separator above it still goes', false === strpos( majestic_tube_strip_template_notice( $without_hr ), 'Template notice' ) );
check( 'and the sentence before it is kept whole', mt_starts_with( majestic_tube_strip_template_notice( $without_hr ), '<p>Contact us.</p>' ) );

/*
 * The no-op half: restarting the setup must not disturb a page that never had
 * a notice, and administrator text that merely talks about notices (a records
 * custodian, a takedown agent) must not be mistaken for one.
 */
$clean = "<p>Questions about records or this notice may be sent to our agent.</p>\n<p>Send a notice to [majestic_tube_default_email_link].</p>";

check( 'a page with no notice is returned byte for byte', $clean === majestic_tube_strip_template_notice( $clean ) );
check( 'a page that only talks about notices is left alone', false !== strpos( majestic_tube_strip_template_notice( $clean ), 'Send a notice to' ) );
check( 'an empty string survives', '' === majestic_tube_strip_template_notice( '' ) );
check( 'a non-string is not fatal', '' === majestic_tube_strip_template_notice( null ) );

/* -------------------------------------------------------------------------
 * Which requests count as a legal page.
 * ---------------------------------------------------------------------- */

echo "\nWhich requests count as a legal page\n";

$dmca = add_page( 10, 'DMCA', 'dmca' );
add_page( 11, 'About Us', 'about-us' );
$privacy_id = add_page( 12, 'Privacy Policy', 'privacy-policy' )->ID;
$post       = add_page( 13, 'Terms I agreed to', 'terms-post', 'post' );

$GLOBALS['opts']['wp_page_for_privacy_policy'] = $privacy_id;

$GLOBALS['queried_id'] = 10;
check( 'the DMCA page is one', majestic_tube_is_legal_page() );

$GLOBALS['queried_id'] = 11;
check( 'an ordinary page is not', ! majestic_tube_is_legal_page() );

$GLOBALS['queried_id'] = 12;
check( "WordPress's own privacy page is one", majestic_tube_is_legal_page() );

$GLOBALS['queried_id'] = 13;
check( 'a post that merely mentions terms is not', ! majestic_tube_is_legal_page() );

$GLOBALS['queried_id'] = 999;
check( 'an unmatched request is not', ! majestic_tube_is_legal_page() );

/* -------------------------------------------------------------------------
 * The widget areas.
 * ---------------------------------------------------------------------- */

echo "\nA legal page prints no content widget area\n";

$area_ids = majestic_tube_content_widget_areas();

$GLOBALS['active'][ $area_ids['footer']['id'] ]  = true;
$GLOBALS['widgets'][ $area_ids['footer']['id'] ] = '<div class="majestic-tube-content-block">AD TAG</div>';
$GLOBALS['active'][ $area_ids['header']['id'] ]  = true;
$GLOBALS['widgets'][ $area_ids['header']['id'] ] = '<div class="majestic-tube-content-block">HEADER TAG</div>';

$GLOBALS['queried_id'] = 10;
$legal                 = render_location( 'footer' );
$legal_header          = render_location( 'header' );

check( 'the footer content area prints nothing on a legal page', '' === $legal[0], 'got: ' . $legal[0] );
check( 'and it reports that it printed nothing', false === $legal[1] );
check( 'the header content area prints nothing there either', '' === $legal_header[0], 'got: ' . $legal_header[0] );
check( 'and it reports that too', false === $legal_header[1] );

$GLOBALS['queried_id'] = 11;
$elsewhere             = render_location( 'footer' );

check( 'the same area still prints on an ordinary page', false !== strpos( $elsewhere[0], 'AD TAG' ), 'got: ' . $elsewhere[0] );
check( 'and it reports that it did', true === $elsewhere[1] );

$GLOBALS['queried_id'] = 0;
$no_document           = render_location( 'header' );

check( 'a request with no document at all renders the area', true === $no_document[1] );
check( 'and prints it', false !== strpos( $no_document[0], 'HEADER TAG' ), 'got: ' . $no_document[0] );
$unknown = render_location( 'nowhere' );
check( 'an unknown area key is still refused', '' === $unknown[0] && false === $unknown[1] );

/*
 * The refusal sits in the capture function rather than at each call site, so
 * an area this page would never place - the video sidebar, which single.php
 * prints directly - is covered too.
 */
$GLOBALS['active'][ $area_ids['video-sidebar']['id'] ]  = true;
$GLOBALS['widgets'][ $area_ids['video-sidebar']['id'] ] = '<div class="majestic-tube-content-block">SIDEBAR TAG</div>';

$GLOBALS['queried_id'] = 10;
check(
	'the video sidebar prints nothing on a legal page either',
	'' === majestic_tube_widget_area_content( $area_ids['video-sidebar']['id'] )
);

$GLOBALS['queried_id'] = 11;
$sidebar_returned      = (string) majestic_tube_widget_area_content( $area_ids['video-sidebar']['id'] );

check(
	'and the same area still renders on an ordinary page',
	false !== strpos( $sidebar_returned, 'SIDEBAR TAG' ),
	'got: ' . $sidebar_returned
);

/* -------------------------------------------------------------------------
 * footer.php, rendered for real.
 * ---------------------------------------------------------------------- */

echo "\nThe footer, rendered\n";

$GLOBALS['opts']['copyright-bar'] = 'on';
$GLOBALS['opts']['footer-columns'] = 'four-columns-footer';
$GLOBALS['active']['majestic-tube-footer']     = true;
$GLOBALS['widgets']['majestic-tube-footer']    = '<section class="widget"><h2 class="widget-title">FRIENDS</h2></section>';

$GLOBALS['queried_id'] = 10;
$footer_legal          = capture(
	static function () {
		include MAJESTIC_TUBE_DIR . '/footer.php';
	}
);

check( 'no footer widget columns on a legal page', false === strpos( $footer_legal, 'footer-widgets' ), 'got: ' . $footer_legal );
check( 'no footer widgets either', false === strpos( $footer_legal, 'FRIENDS' ) );
check( 'no footer content area on a legal page', false === strpos( $footer_legal, 'majestic-tube-content-area--footer' ) );
check( 'the footer itself is still there', false !== strpos( $footer_legal, 'site-footer' ) );
check( 'the copyright line is still there', false !== strpos( $footer_legal, 'site-info' ) );
check( 'the back-to-top link is still there', false !== strpos( $footer_legal, 'back-to-top' ) );

$GLOBALS['queried_id'] = 11;
$footer_ordinary       = capture(
	static function () {
		include MAJESTIC_TUBE_DIR . '/footer.php';
	}
);

check( 'the footer widgets still print on an ordinary page', false !== strpos( $footer_ordinary, 'footer-widgets' ), 'got: ' . $footer_ordinary );
check( 'and their content does too', false !== strpos( $footer_ordinary, 'FRIENDS' ) );
check( 'and so does the footer content area', false !== strpos( $footer_ordinary, 'majestic-tube-content-area--footer' ) );

/* -------------------------------------------------------------------------
 * The popunder, which is not a widget area.
 *
 * A placement printed by a hook never passed through the capture function, so
 * the withheld rule did not reach it and a policy page could still open an
 * interstitial over the document a visitor was reading.
 * ---------------------------------------------------------------------- */

echo "\nThe popunder, on a page that carries no advertising\n";

$GLOBALS['opts']['enable-popunder-ad'] = 'on';
$GLOBALS['opts']['popunder-ad-code']   = '<script id="popunder"></script>';
$GLOBALS['opts']['enable-video-submission'] = 'on';
$GLOBALS['opts']['ask-to-accept-terms']     = 'on';

$submit_id = add_page( 40, 'Submit a Video', 'submit-a-video' )->ID;

$GLOBALS['logged_in']  = true;
$GLOBALS['is_admin']   = false;
$GLOBALS['doing_ajax'] = false;

$GLOBALS['queried_id'] = 11;
$popunder_ordinary     = capture( 'majestic_tube_output_popunder_ad' );

check( 'the popunder prints on an ordinary page', false !== strpos( $popunder_ordinary, 'popunder' ), 'got: ' . $popunder_ordinary );

$GLOBALS['queried_id'] = 10;
check( 'and nothing on a legal page', '' === capture( 'majestic_tube_output_popunder_ad' ), 'got: ' . capture( 'majestic_tube_output_popunder_ad' ) );

$GLOBALS['queried_id'] = 12;
check( 'nor on the privacy policy', '' === capture( 'majestic_tube_output_popunder_ad' ), 'got: ' . capture( 'majestic_tube_output_popunder_ad' ) );

/*
 * The submission form is the other withheld surface, and this is the check that
 * proves the popunder asked the combined question rather than only the legal
 * half of it.
 */
$GLOBALS['queried_id'] = $submit_id;

check(
	'the submission page really is the withheld kind before the check below',
	majestic_tube_widget_areas_withheld(),
	'page ' . $submit_id . ' is not withheld'
);
check( 'and the popunder is withheld there too', '' === capture( 'majestic_tube_output_popunder_ad' ), 'got: ' . capture( 'majestic_tube_output_popunder_ad' ) );

// And the placements still answer to the ordinary switch as well.
$GLOBALS['opts']['enable-popunder-ad'] = 'off';
$GLOBALS['queried_id']                 = 11;

check( 'with the placement switched off, it prints nowhere at all', '' === capture( 'majestic_tube_output_popunder_ad' ) );
check( 'and the code helper agrees', '' === majestic_tube_get_popunder_ad() );

// An admin screen is not a page a visitor is reading.
$GLOBALS['opts']['enable-popunder-ad'] = 'on';
$GLOBALS['is_admin']                   = true;

check( 'and nothing prints in wp-admin either', '' === capture( 'majestic_tube_output_popunder_ad' ) );

/* -------------------------------------------------------------------------
 * The rest of the module, printed through one printer.
 *
 * A legacy option key, the per-video snippet under the player and the in-feed
 * block are not widget areas either. None of them asks the withheld question
 * itself: the printer they all share does, which is what makes the answer the
 * same for a placement shipped today and one a child theme adds tomorrow.
 * ---------------------------------------------------------------------- */

echo "\nThe other placements, on a page that carries no advertising\n";

$GLOBALS['is_admin']   = false;
$GLOBALS['doing_ajax'] = false;
$GLOBALS['logged_in']  = false;

/*
 * A legacy option key, as a child theme or an older integration renders it.
 * The adapter is still supported, so its output has to obey the same rule.
 */
$GLOBALS['opts']['header-ad-desktop'] = '<script id="legacy-header-ad"></script>';
$legacy_ad                            = static function () {
	majestic_tube_ad( 'header-ad-desktop' );
};

$GLOBALS['queried_id'] = 11;
check(
	'a legacy option key prints on an ordinary page',
	false !== strpos( capture( $legacy_ad ), 'legacy-header-ad' )
);

$GLOBALS['queried_id'] = 10;
check( 'and nothing on a legal page', '' === capture( $legacy_ad ) );

/*
 * The per-video snippet under the player. It is post meta rather than an
 * option, and it used to print on any request that reached the template.
 */
$GLOBALS['post_meta'][13]['unique_ad_under_player'] = '<script id="per-video-ad"></script>';
$under_player_ad                                      = static function () {
	majestic_tube_under_player_content( 13 );
};

$GLOBALS['queried_id'] = 11;
check(
	'a per-video snippet prints on an ordinary page',
	false !== strpos( capture( $under_player_ad ), 'per-video-ad' )
);

$GLOBALS['queried_id'] = 10;
check( 'and nothing on a legal page', '' === capture( $under_player_ad ) );

/*
 * And the printer itself, which is the thing every placement above - and any
 * placement added later - ends up in. It has to print for an ordinary page and
 * refuse for a withheld one, and it has to say which it did: a caller that
 * falls back on its own markup needs the return value to be honest.
 */
$GLOBALS['queried_id'] = 11;
$direct_printed        = null;
$direct_ordinary       = capture(
	static function () use ( &$direct_printed ) {
		$direct_printed = majestic_tube_content_block( '<script id="direct-ad"></script>' );
	}
);

check(
	'the printer prints an ordinary page, and reports that it did',
	true === $direct_printed && false !== strpos( $direct_ordinary, 'direct-ad' ),
	'got: ' . $direct_ordinary
);

$GLOBALS['queried_id']  = 10;
$direct_printed_legal   = null;
$direct_legal           = capture(
	static function () use ( &$direct_printed_legal ) {
		$direct_printed_legal = majestic_tube_content_block( '<script id="direct-ad"></script>' );
	}
);

check(
	'and refuses a legal page, printing nothing and reporting nothing printed',
	false === $direct_printed_legal && '' === $direct_legal,
	'got: ' . $direct_legal
);

$GLOBALS['logged_in']  = true;
$GLOBALS['queried_id'] = $submit_id;
check(
	'and refuses the surface that asks a visitor to accept something',
	false === majestic_tube_content_block( '<script id="direct-ad"></script>' )
);
$GLOBALS['logged_in'] = false;

/*
 * The in-feed block is the fourth path, and it is printed from the grid rather
 * than from this module. The check pins the wiring rather than the markup: the
 * block goes through the printer, and the grid does not answer the page
 * question for itself, so there is one answer and one place that gives it.
 */
$template_tags = file_get_contents( MAJESTIC_TUBE_DIR . '/inc/template-tags.php' );

check(
	'the in-feed block is printed by the printer, and the grid does not answer the page question itself',
	false !== strpos( $template_tags, 'majestic_tube_content_block(' ) && false === strpos( $template_tags, 'majestic_tube_widget_areas_withheld' ),
	'a placement that asks the rule at its own call site is how the next one comes to not ask it at all'
);

$GLOBALS['post_meta'] = array();

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
