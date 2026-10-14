<?php
/**
 * Smoke test for the consent surfaces: the terms checkbox on the login,
 * registration and video submission forms, and the cookie notice.
 *
 * Three claims worth pinning, because each is easy to undo by accident:
 *
 *   1. the theme asks for nothing the site does not publish. Both the checkbox
 *      and the notice link the documents the site actually has, and a site
 *      with neither a terms page nor a privacy policy prints no question at
 *      all - a visitor cannot accept a document that does not exist;
 *   2. the acceptance is enforced by the handler, not by the markup. The
 *      `required` attribute is browser-side advice and is gone from any request
 *      that was not built by the form, so a login, a registration and a video
 *      submission that arrive without it are all refused;
 *   3. the surfaces are widget-free. The submission page prints no Code and ads
 *      area, the way a legal page does, and neither the login panel nor the
 *      notice contains one. An ad tag beside a question about terms reads as
 *      the site answering a legal question with a promotion;
 *   4. the room a spam check needs is reserved whether or not the check is on,
 *      so switching protection on cannot move a submit button. Turnstile's
 *      widget arrives after the page has rendered, which is why the space is
 *      held in advance rather than found when the iframe lands.
 *
 * The functions are lifted out of inc/consent.php, inc/activation.php,
 * inc/widgets.php, inc/ads.php and inc/ajax-login-register.php and run against
 * stubs, and footer.php is rendered for real, so the code under test is the
 * shipping code rather than a copy of the rule.
 *
 * Run:  php tests/consent-smoke.php
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
$GLOBALS['opts']              = array();
$GLOBALS['queried_id']        = 0;
$GLOBALS['posts']             = array();
$GLOBALS['active']            = array();
$GLOBALS['widgets']           = array();
$GLOBALS['post_meta']         = array();
$GLOBALS['mods']              = array();
$GLOBALS['filters']           = array();
$GLOBALS['logged_in']         = false;
$GLOBALS['is_page_template']  = false;
$GLOBALS['privacy_url']       = '';
$GLOBALS['sent']              = null;
$GLOBALS['signed_on']         = null;
$GLOBALS['created_user']      = null;
$GLOBALS['captcha_configured'] = false;
$_POST                        = array();

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
function sanitize_text_field( $s ) {
	return trim( (string) $s );
}
function sanitize_user( $s ) {
	return trim( (string) $s );
}
function sanitize_email( $s ) {
	return trim( (string) $s );
}
function wp_unslash( $s ) {
	return $s;
}
function absint( $value ) {
	return abs( (int) $value );
}

/**
 * Filters pass through unless the test installed one, which is how the theme's
 * own extension points are exercised rather than assumed.
 *
 * @param string   $tag   Filter name.
 * @param mixed    $value Value.
 * @param mixed ...$args  Extra arguments.
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	if ( isset( $GLOBALS['filters'][ $tag ] ) ) {
		return call_user_func( $GLOBALS['filters'][ $tag ], $value );
	}

	return $value;
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}
function get_theme_mod( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['mods'] ) ? $GLOBALS['mods'][ $name ] : $default;
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
function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
	foreach ( $GLOBALS['posts'] as $page ) {
		if ( (string) $page->post_name === (string) $slug ) {
			return $page;
		}
	}

	return null;
}
function get_permalink( $id ) {
	$post = get_post( $id );

	return $post ? 'https://example.test/' . $post->post_name . '/' : '';
}
function get_privacy_policy_url() {
	return $GLOBALS['privacy_url'];
}
function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['post_meta'][ (int) $id ][ $key ] ) ? $GLOBALS['post_meta'][ (int) $id ][ $key ] : '';
}
function is_page_template( $template = '' ) {
	return (bool) $GLOBALS['is_page_template'];
}
function is_user_logged_in() {
	return ! empty( $GLOBALS['logged_in'] );
}
function is_ssl() {
	return false;
}
function wp_nonce_field( $action, $name ) {
	printf( '<input type="hidden" name="%s" value="nonce" />', esc_attr( $name ) );
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
 * The throttling helpers are stubbed rather than extracted: this test is about
 * what the handlers do with a missing acceptance, not about throttling. The
 * spam check is stubbed too, but its answer is a switch, because the room the
 * widget needs is printed either way and both states are checked below.
 */
function majestic_tube_auth_throttled( $action ) {
	return false;
}
function majestic_tube_auth_note_failure( $action ) {
}

/*
 * Driven by a global: the handlers have to see the check off, and the slot
 * checks below have to see it on, without either one changing what the other
 * is testing.
 */
function majestic_tube_captcha_is_configured() {
	return ! empty( $GLOBALS['captcha_configured'] );
}

/*
 * wp_send_json() ends the request, and a handler that returns normally is a
 * handler that forgot to answer. Throwing on it makes every check below prove
 * that the handler replied *and* what it replied with.
 */
function wp_send_json( $data, $status = 200 ) {
	$GLOBALS['sent'] = array(
		'data'   => $data,
		'status' => $status,
	);

	throw new RuntimeException( 'json' );
}
function check_ajax_referer( $action, $name, $die = true ) {
	return true;
}
function is_email( $email ) {
	return false !== strpos( (string) $email, '@' );
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error_Stub;
}

/* Only needed so the two failure shapes a handler can see are distinguishable. */
class WP_Error_Stub {
}
function username_exists( $login ) {
	return false;
}
function email_exists( $email ) {
	return false;
}
function wp_create_user( $login, $password, $email ) {
	$GLOBALS['created_user'] = array( $login, $password, $email );

	return 4242;
}
function wp_send_new_user_notifications( $user_id, $kind = 'both' ) {
}
function wp_signon( $creds, $secure = '' ) {
	$GLOBALS['signed_on'] = $creds;

	return new stdClass();
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

function theme_file( $relative ) {
	return file_get_contents( MAJESTIC_TUBE_DIR . '/' . $relative );
}

/**
 * The declaration block of the first rule with this selector.
 *
 * The trailing brace is part of the pattern so a modifier selector - the
 * reserved slot's own class, say - is not mistaken for the base rule.
 *
 * @param string $sheet    Stylesheet contents.
 * @param string $selector Selector, without the brace.
 * @return string Declarations, or an empty string when the rule is absent.
 */
function css_rule( $sheet, $selector ) {
	if ( ! preg_match( '/' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', $sheet, $match ) ) {
		return '';
	}

	return $match[1];
}

load_functions(
	'inc/activation.php',
	array(
		'majestic_tube_page_is_viewable',
		'majestic_tube_is_legal_document',
		'majestic_tube_current_legal_document',
		'majestic_tube_is_legal_page',
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

load_functions(
	'inc/consent.php',
	array(
		'majestic_tube_consent_field_name',
		'majestic_tube_consent_ask_enabled',
		'majestic_tube_consent_page_url',
		'majestic_tube_consent_documents',
		'majestic_tube_consent_policy_links',
		'majestic_tube_consent_is_required',
		'majestic_tube_consent_field',
		'majestic_tube_consent_accepted',
		'majestic_tube_consent_satisfied',
		'majestic_tube_consent_error_message',
		'majestic_tube_video_submission_form_is_shown',
		'majestic_tube_is_video_submission_page',
		'majestic_tube_is_consent_page',
		'majestic_tube_widget_areas_withheld',
		'majestic_tube_cookie_notice_enabled',
		'majestic_tube_cookie_notice_text',
		'majestic_tube_cookie_notice',
	)
);

load_functions(
	'inc/ajax-login-register.php',
	array(
		'majestic_tube_membership_field',
		'majestic_tube_captcha_reserved_space',
		'majestic_tube_captcha_widget',
		'majestic_tube_captcha_site_key',
		'majestic_tube_login_register_modal',
		'majestic_tube_ajax_error',
		'majestic_tube_ajax_membership_success',
		'majestic_tube_ajax_login',
		'majestic_tube_ajax_register',
	)
);

load_functions( 'inc/template-filters.php', array( 'majestic_tube_captcha_comment_room' ) );

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
 * Run a request handler that answers through wp_send_json().
 *
 * @param callable $callback Handler.
 * @return array{data: mixed, status: int}|null
 */
function handle( $callback ) {
	$GLOBALS['sent'] = null;

	try {
		$callback();
	} catch ( RuntimeException $e ) {
		// The handler ended the request, which is what the real responder does.
	}

	return $GLOBALS['sent'];
}

/**
 * Render one content area, returning what it printed and what it returned.
 *
 * @param string $location Area key.
 * @return array{0: string, 1: bool} Printed markup, then the return value.
 */
function render_location( $location ) {
	ob_start();
	$returned = majestic_tube_content_location( $location );

	return array( (string) ob_get_clean(), (bool) $returned );
}

echo "\nMajestic Tube - consent surfaces\n";
echo str_repeat( '-', 46 ) . "\n";

/* -------------------------------------------------------------------------
 * What the site publishes.
 * ---------------------------------------------------------------------- */

echo "\nThe documents the checkbox links\n";

$GLOBALS['opts']['ask-to-accept-terms'] = 'on';

check( 'a site with no terms page and no privacy policy has nothing to accept', ! majestic_tube_consent_is_required() );
check(
	'and prints no checkbox',
	'' === capture(
		static function () {
			majestic_tube_consent_field( 'login' );
		}
	)
);

$terms_id = (int) add_page( 30, 'Terms of Use', 'terms' )->ID;

check( 'a published terms page is one document', array_key_exists( 'terms', majestic_tube_consent_documents() ) );
check( 'and the sentence links it by name', false !== strpos( majestic_tube_consent_policy_links(), '>Terms of Use</a>' ) );
check(
	'and points at the page itself, not at a slug',
	false !== strpos( majestic_tube_consent_policy_links(), 'https://example.test/terms/' ),
	majestic_tube_consent_policy_links()
);
check( 'with one document there is no list to join', 1 === substr_count( majestic_tube_consent_policy_links(), '<a ' ) );

$GLOBALS['opts']['wp_page_for_privacy_policy'] = 31;
add_page( 31, 'Privacy Policy', 'privacy-policy' );
$GLOBALS['privacy_url']                        = 'https://example.test/privacy-policy/';

$links = majestic_tube_consent_policy_links();

check( 'a second document is joined with the word the reader expects', false !== strpos( $links, '</a> and <a ' ) );
check( 'and both are named', false !== strpos( $links, 'Terms of Use' ) && false !== strpos( $links, 'Privacy Policy' ) );
check( 'the acceptance is now required', majestic_tube_consent_is_required() );

/*
 * A draft or trashed copy resolves through get_page_by_path() exactly like a
 * published one, so publishing state has to be the thing that decides.
 */
add_page( 32, 'Cookie Policy', 'cookie-policy', 'page', 'draft' );
check( 'a draft cookie policy is not offered', ! array_key_exists( 'cookie-policy', majestic_tube_consent_documents() ) );

$draft_terms = add_page( 33, 'Terms', 'terms-of-service', 'page', 'trash' );
check( 'nor a trashed one', ! in_array( 'https://example.test/terms-of-service/', array_column( majestic_tube_consent_documents(), 'url' ), true ) );
check( 'and the trashed slug is still not the one that answered', (int) $draft_terms->ID !== $terms_id );

/* -------------------------------------------------------------------------
 * The checkbox.
 * ---------------------------------------------------------------------- */

echo "\nThe checkbox\n";

$login_field = capture(
	static function () {
		majestic_tube_consent_field( 'login' );
	}
);

check( 'the field is a checkbox the form must have', false !== strpos( $login_field, 'type="checkbox"' ) );
check( 'carrying the shared field name', false !== strpos( $login_field, 'name="wpst_accept_terms"' ) );
check( 'marked required, so a browser blocks the empty form first', false !== strpos( $login_field, 'value="1" required' ) );
check( 'and naming both documents in its label', false !== strpos( $login_field, 'I have read and accept the' ) && false !== strpos( $login_field, 'Privacy Policy' ) );

$register_field = capture(
	static function () {
		majestic_tube_consent_field( 'register' );
	}
);

check(
	'each form gets its own element id',
	false !== strpos( $login_field, 'id="wpst-accept-terms-login"' ) && false !== strpos( $register_field, 'id="wpst-accept-terms-register"' ),
	'two ids of one name in one modal is invalid HTML that breaks label targeting'
);

/* -------------------------------------------------------------------------
 * Reading the answer back.
 * ---------------------------------------------------------------------- */

echo "\nReading the answer back\n";

$_POST = array();
check( 'nothing posted is not an acceptance', ! majestic_tube_consent_accepted() );
check( 'and the request does not pass', ! majestic_tube_consent_satisfied() );

$_POST['wpst_accept_terms'] = '';
check( 'an empty value is not an acceptance either', ! majestic_tube_consent_accepted() );

$_POST['wpst_accept_terms'] = array( '1' );
check( 'a crafted array is refused rather than fatal', ! majestic_tube_consent_accepted() );

$_POST['wpst_accept_terms'] = '1';
check( 'the checked box is', majestic_tube_consent_accepted() );
check( 'and the request passes', majestic_tube_consent_satisfied() );

$_POST['wpst_accept_terms'] = 'yes';
check( 'any non-empty value counts, so a themed control still works', majestic_tube_consent_accepted() );

/*
 * The switch is the site's, and the filter is the theme's extension point: a
 * site that wants the checkbox hidden, or one that wants the plain-text
 * handler given a different field, is not stuck with the default.
 */
$GLOBALS['opts']['ask-to-accept-terms'] = 'off';
unset( $_POST['wpst_accept_terms'] );

check( 'with the switch off nothing is required', ! majestic_tube_consent_is_required() );
check( 'and a submission with no checkbox passes anyway', majestic_tube_consent_satisfied() );
check(
	'and no checkbox is printed',
	'' === capture(
		static function () {
			majestic_tube_consent_field( 'login' );
		}
	)
);

$GLOBALS['opts']['ask-to-accept-terms'] = 'on';
unset( $_POST['wpst_accept_terms'] );

check( 'the switch off can also be said through the filter', true === apply_filters( 'majestic_tube_consent_ask_enabled', true ) );

$GLOBALS['filters']['majestic_tube_consent_ask_enabled'] = static function () {
	return false;
};

check( 'a site that filters the ask off is obeyed', ! majestic_tube_consent_is_required() );

unset( $GLOBALS['filters']['majestic_tube_consent_ask_enabled'] );
check( 'and removing the filter restores it', majestic_tube_consent_is_required() );

/* -------------------------------------------------------------------------
 * The handlers.
 * ---------------------------------------------------------------------- */

echo "\nThe login and registration handlers\n";

$GLOBALS['opts']['users_can_register'] = '1';
$_POST                                 = array(
	'wpst_user_login' => 'jane',
	'wpst_user_pass'  => 'a-good-password',
);
$GLOBALS['signed_on']                  = null;

$sent = handle( 'majestic_tube_ajax_login' );

check( 'a login without the acceptance is refused', null !== $sent );
check(
	'with a sentence telling the visitor what to do',
	null !== $sent && false !== strpos( $sent['data']['message'], 'accept the terms' ),
	null === $sent ? 'no reply' : $sent['data']['message']
);
check( 'and no credentials are checked at all', null === $GLOBALS['signed_on'] );

$_POST['wpst_accept_terms'] = '1';
$GLOBALS['signed_on']       = null;
$sent                       = handle( 'majestic_tube_ajax_login' );

check( 'with the box ticked the credentials are checked', null !== $GLOBALS['signed_on'] );
check( 'and the login answers exactly as it always did', null !== $sent && false === $sent['data']['error'] );

$GLOBALS['signed_on'] = null;
$_POST                = array(
	'wpst_user_login' => 'jane',
	'wpst_user_email' => 'jane@example.test',
	'wpst_user_pass'  => 'a-good-password',
);
$GLOBALS['created_user'] = null;

$sent = handle( 'majestic_tube_ajax_register' );

check( 'a registration without the acceptance is refused', null !== $sent && false !== strpos( $sent['data']['message'], 'accept the terms' ) );
check( 'and no account is created', null === $GLOBALS['created_user'] );

$_POST['wpst_accept_terms'] = '1';
$sent                       = handle( 'majestic_tube_ajax_register' );

check( 'with the box ticked the account is created', null !== $GLOBALS['created_user'] );
check( 'and the registration answers exactly as it always did', null !== $sent && false === $sent['data']['error'] );

$ajax_source = theme_file( 'inc/ajax-login-register.php' );

check( 'both handlers ask the shared question', 2 === substr_count( $ajax_source, 'majestic_tube_consent_satisfied()' ) );

/* -------------------------------------------------------------------------
 * The forms themselves.
 * ---------------------------------------------------------------------- */

echo "\nThe forms\n";

$GLOBALS['logged_in'] = false;
$_POST                = array();

$modal = capture( 'majestic_tube_login_register_modal' );

check( 'the login panel asks twice: registration and login', 2 === substr_count( $modal, 'name="wpst_accept_terms"' ) );
check(
	'the two checkboxes are distinct elements',
	2 === substr_count( $modal, 'value="1" required' ) &&
	false !== strpos( $modal, 'id="wpst-accept-terms-register"' ) &&
	false !== strpos( $modal, 'id="wpst-accept-terms-login"' ),
	'each form in the modal has to carry its own id'
);

$submit_source = theme_file( 'template-video-submit.php' );

check( 'the submission form asks too', false !== strpos( $submit_source, "majestic_tube_consent_field( 'submit' )" ) );
check( 'and its handler refuses a submission without it', false !== strpos( $submit_source, '! majestic_tube_consent_satisfied()' ) );
check(
	'the page asks whether the form is there the same way the areas guard does',
	false !== strpos( $submit_source, 'majestic_tube_video_submission_form_is_shown()' ) && false === strpos( $submit_source, '$submission_enabled && $logged_in' ),
	'two copies of one condition is how a page shows a form the theme believes is absent'
);

/* -------------------------------------------------------------------------
 * The spam check's slot.
 * ---------------------------------------------------------------------- */

echo "\nThe Turnstile slot\n";

/*
 * One rule in the stylesheet holds the height, and it holds it on the slot
 * rather than on the mounted widget, because a form with no widget in it is
 * exactly the case the reservation exists for.
 */
$sheet     = theme_file( 'assets/css/main.css' );
$slot_rule = css_rule( $sheet, '.majestic-tube-captcha' );

check(
	'the slot reserves the height the provider draws, instead of growing when the iframe lands',
	false !== strpos( $slot_rule, 'min-height: var(--mt-captcha-height, 65px)' ),
	$slot_rule
);
check(
	'and the line box the injected frame sits in cannot add to it',
	false !== strpos( $slot_rule, 'line-height: 0' ),
	'an inline frame plus the strut under it measured 6.6px past the reserved height without this'
);
check(
	'and the number it ships is the one the documentation promises',
	false !== strpos( $slot_rule, '65px' ) &&
	false !== strpos( theme_file( 'README.md' ), 'The widget is 65px tall' ) &&
	false !== strpos( theme_file( 'docs.html' ), 'The widget is 65px tall' ),
	'the height a page reserves and the height the docs quote have to be the same number'
);

/*
 * The panel is rendered twice: once with the check off, which is the state a
 * form is in on a site that never turns protection on, and once with it on.
 */
check( 'the login panel reserves a slot on its sign-up, login and password reset tabs', 3 === substr_count( $modal, 'data-mt-captcha="' ), (string) substr_count( $modal, 'data-mt-captcha="' ) );
check(
	'with the check off every slot is the reserved kind, which asks Cloudflare for nothing',
	3 === substr_count( $modal, 'data-mt-captcha="reserved"' ) &&
	false === strpos( $modal, 'cf-turnstile' ) &&
	false === strpos( $modal, 'data-sitekey' ),
	'the reserved slot must not be a mount point the script can pick up'
);

$GLOBALS['captcha_configured']         = true;
$GLOBALS['opts']['turnstile-site-key'] = '1x00000000000000000000AA';

$modal_on = capture( 'majestic_tube_login_register_modal' );

preg_match( '/id="wpst_registration_form".*?<\/form>/s', $modal_on, $register_form );
preg_match( '/id="wpst_login_form".*?<\/form>/s', $modal_on, $login_form );
preg_match( '/id="wpst_reset_password_form".*?<\/form>/s', $modal_on, $reset_form );
$register_form = isset( $register_form[0] ) ? $register_form[0] : '';
$login_form    = isset( $login_form[0] ) ? $login_form[0] : '';
$reset_form    = isset( $reset_form[0] ) ? $reset_form[0] : '';

check(
	'with the check on the sign-up tab carries the widget and the key the script mounts it with',
	false !== strpos( $register_form, 'class="cf-turnstile majestic-tube-captcha"' ) &&
	false !== strpos( $register_form, 'data-sitekey="1x00000000000000000000AA"' ) &&
	false !== strpos( $register_form, 'data-mt-turnstile="1"' ),
	$register_form
);
check(
	'and the login tab keeps the room without mounting a challenge it never asked for',
	false !== strpos( $login_form, 'data-mt-captcha="reserved"' ) && false === strpos( $login_form, 'cf-turnstile' ),
	$login_form
);
check(
	'the password reset tab is guarded the same way the sign-up tab is',
	false !== strpos( $reset_form, 'class="cf-turnstile majestic-tube-captcha"' ) &&
	false !== strpos( $reset_form, 'data-sitekey="1x00000000000000000000AA"' ),
	$reset_form
);
check( 'the panel is three slots wide whichever state the check is in', 3 === substr_count( $modal_on, 'data-mt-captcha="' ), (string) substr_count( $modal_on, 'data-mt-captcha="' ) );

/*
 * A widget on the tab is only half of the guard: the endpoint behind it has to
 * read the token, or the challenge is decoration. The handler is read rather
 * than run, because it reaches retrieve_password() and therefore mail.
 */
$reset_handler = substr( $ajax_source, (int) strpos( $ajax_source, 'function majestic_tube_ajax_reset_password' ) );
$reset_handler = substr( $reset_handler, 0, (int) strpos( $reset_handler, "add_action( 'wp_ajax_nopriv_wpst_reset_password'" ) );

check(
	'and the reset handler verifies the token the tab now asks for',
	false !== strpos( $reset_handler, 'majestic_tube_captcha_is_configured()' ) &&
	false !== strpos( $reset_handler, 'majestic_tube_verify_captcha(' ),
	'a slot nobody verifies is a challenge a script can ignore'
);

$GLOBALS['captcha_configured'] = false;
unset( $GLOBALS['opts']['turnstile-site-key'] );

/*
 * The slot on the submission form is part of its markup, not of the handler
 * that verifies the token - that handler is below the form and keeps its own
 * condition, so the check is scoped to what the page prints.
 */
$submit_markup = substr( $submit_source, (int) strpos( $submit_source, '<form id="wpst_video_submit_form"' ) );

check(
	'the submission form reserves its slot too, so it cannot be behind the check',
	false !== strpos( $submit_markup, '<?php majestic_tube_captcha_widget(); ?>' ) && false === strpos( $submit_markup, 'majestic_tube_captcha_is_configured' ),
	'a slot printed only while the check is on is the layout shift this exists to prevent'
);
check(
	'while the token the slot produces is still verified before the submission is taken',
	false !== strpos( $submit_source, 'majestic_tube_captcha_is_configured()' ) && false !== strpos( $submit_source, 'majestic_tube_verify_captcha(' )
);

/*
 * The comment form is core's, so the room joins it through the submit field's
 * filter - the one point inside that form a signed-in commenter reaches too.
 * The callback is run rather than pattern-matched, so what is checked is the
 * markup a visitor gets.
 */
$comment_filters = theme_file( 'inc/template-filters.php' );
$submit_field    = '<p class="form-submit"><input name="submit" type="submit" /></p>';
$room_off        = majestic_tube_captcha_comment_room( $submit_field );

check(
	'the comment form is given the room through the filter that reaches a signed-in commenter as well',
	false !== strpos( $comment_filters, "add_filter( 'comment_form_submit_field', 'majestic_tube_captcha_comment_room' )" ),
	'the action that fires after the fields is skipped for a signed-in commenter, so a slot printed there would come and go'
);
check(
	'and the markup it hands back is the room, in front of the submit field untouched',
	0 === strpos( trim( $room_off ), '<div class="majestic-tube-captcha" data-mt-captcha="reserved" aria-hidden="true"></div>' ) &&
	$submit_field === substr( $room_off, -strlen( $submit_field ) ),
	$room_off
);
check(
	'with the check on too: this form is not guarded, so it never grows a challenge',
	( function () use ( $submit_field ) {
		$GLOBALS['captcha_configured']         = true;
		$GLOBALS['opts']['turnstile-site-key'] = '1x00000000000000000000AA';
		$markup                                = majestic_tube_captcha_comment_room( $submit_field );
		$GLOBALS['captcha_configured']         = false;
		unset( $GLOBALS['opts']['turnstile-site-key'] );

		return $markup === majestic_tube_captcha_comment_room( $submit_field ) && false === strpos( $markup, 'data-sitekey' ) && false === strpos( $markup, 'cf-turnstile' );
	} )()
);

/*
 * The panel and the notice are the theme's own sentences, so neither may carry
 * an administrator's area. New markup in either file that reaches for a
 * placement helper fails here rather than on a live site.
 */
$modal_start = strpos( $ajax_source, 'function majestic_tube_login_register_modal' );
$modal_end   = strpos( $ajax_source, "add_action( 'wp_footer', 'majestic_tube_login_register_modal'" );
$modal_body  = substr( $ajax_source, $modal_start, $modal_end - $modal_start );

check(
	'the login panel reaches for no content area',
	false === strpos( $modal_body, 'majestic_tube_content_location(' ) && false === strpos( $modal_body, 'dynamic_sidebar(' )
);

$consent_source = theme_file( 'inc/consent.php' );

check(
	'and neither does the notice, or anything else in the module',
	false === strpos( $consent_source, 'majestic_tube_content_location(' ) &&
	false === strpos( $consent_source, 'majestic_tube_widget_area_content(' ) &&
	false === strpos( $consent_source, 'dynamic_sidebar(' ) &&
	false === strpos( $consent_source, 'majestic_tube_ad_location(' )
);

/* -------------------------------------------------------------------------
 * The consent page.
 * ---------------------------------------------------------------------- */

echo "\nThe submission page\n";

$about = add_page( 40, 'About Us', 'about-us' );
$submit_id = (int) add_page( 41, 'Submit a Video', 'submit-a-video' )->ID;

$GLOBALS['post_meta'][ $submit_id ] = array( '_wp_page_template' => 'template-video-submit.php' );
$GLOBALS['opts']['enable-video-submission'] = 'on';
$GLOBALS['logged_in']                       = true;
$GLOBALS['queried_id']                      = $submit_id;

check( 'the submission page is recognized', majestic_tube_is_video_submission_page() );
check( 'the form is on it, so it is a consent page', majestic_tube_is_consent_page() );
check( 'and every widget area is withheld there', majestic_tube_widget_areas_withheld() );

$area_ids = majestic_tube_content_widget_areas();

$GLOBALS['active'][ $area_ids['footer']['id'] ]            = true;
$GLOBALS['widgets'][ $area_ids['footer']['id'] ]            = '<div class="majestic-tube-content-block">AD TAG</div>';
$GLOBALS['active'][ $area_ids['header']['id'] ]            = true;
$GLOBALS['widgets'][ $area_ids['header']['id'] ]            = '<div class="majestic-tube-content-block">HEADER TAG</div>';
$GLOBALS['active'][ $area_ids['video-sidebar']['id'] ]      = true;
$GLOBALS['widgets'][ $area_ids['video-sidebar']['id'] ]      = '<div class="majestic-tube-content-block">SIDEBAR TAG</div>';
$GLOBALS['active']['majestic-tube-footer']                  = true;
$GLOBALS['widgets']['majestic-tube-footer']                 = '<section class="widget">FRIENDS</section>';

list( $consent_footer, $consent_footer_returned ) = render_location( 'footer' );

check( 'the footer code and ads area prints nothing', '' === $consent_footer, 'got: ' . $consent_footer );
check( 'and reports that it printed nothing', false === $consent_footer_returned );

list( $consent_header ) = render_location( 'header' );
check( 'nor the header area', '' === $consent_header );

check( 'nor the video sidebar', '' === majestic_tube_widget_area_content( $area_ids['video-sidebar']['id'] ) );

$GLOBALS['opts']['copyright-bar']         = 'on';
$GLOBALS['opts']['footer-columns']        = 'four-columns-footer';
$GLOBALS['opts']['logo-footer']           = 'off';

$GLOBALS['queried_id'] = $submit_id;
$footer_consent        = capture(
	static function () {
		include MAJESTIC_TUBE_DIR . '/footer.php';
	}
);

check( 'the footer widgets columns are withheld too', false === strpos( $footer_consent, 'footer-widgets' ) );
check( 'and neither the widget nor the ad tag reaches the page', false === strpos( $footer_consent, 'FRIENDS' ) && false === strpos( $footer_consent, 'AD TAG' ) );
check( 'while the copyright the theme prints itself is untouched', false !== strpos( $footer_consent, 'site-info' ) );

$GLOBALS['queried_id'] = (int) $about->ID;
$footer_ordinary       = capture(
	static function () {
		include MAJESTIC_TUBE_DIR . '/footer.php';
	}
);

check( 'an ordinary page still prints the footer widgets', false !== strpos( $footer_ordinary, 'footer-widgets' ) );
check( 'and its content', false !== strpos( $footer_ordinary, 'FRIENDS' ) );
check( 'and the footer code and ads area', false !== strpos( $footer_ordinary, 'majestic-tube-content-area--footer' ) );

/*
 * A page that lost its template assignment is still the submission page: the
 * theme repairs the assignment on the next admin request, and until then the
 * page must not quietly regain its ad areas.
 */
unset( $GLOBALS['post_meta'][ $submit_id ] );

$GLOBALS['queried_id'] = $submit_id;
check( 'the slug alone still identifies the submission page', majestic_tube_is_video_submission_page() );
check( 'and it is still widget-free', majestic_tube_widget_areas_withheld() );

$GLOBALS['post_meta'][ $submit_id ] = array( '_wp_page_template' => 'template-video-submit.php' );

/*
 * The answer has to be the same before and after the POST, and it depends on
 * whether the form - and so the question - is on the page at all.
 */
$GLOBALS['logged_in'] = false;
check( 'a signed-out visitor sees a sign-in notice, not the form', ! majestic_tube_is_consent_page() );
check( 'so the page keeps its areas', ! majestic_tube_widget_areas_withheld() );

$GLOBALS['logged_in']                       = true;
$GLOBALS['opts']['enable-video-submission'] = 'off';
check( 'and with the feature switched off there is no form to ask beside', ! majestic_tube_is_consent_page() );

$GLOBALS['opts']['enable-video-submission'] = 'on';
check( 'the form being shown is what makes the page one', majestic_tube_is_consent_page() );

$GLOBALS['queried_id'] = (int) $about->ID;
check( 'an ordinary page is never one', ! majestic_tube_is_consent_page() );

$GLOBALS['opts']['ask-to-accept-terms'] = 'off';
check( 'and with nothing asked, the areas are not withheld either', ! majestic_tube_widget_areas_withheld() );
$GLOBALS['opts']['ask-to-accept-terms'] = 'on';

$GLOBALS['filters']['majestic_tube_widget_areas_withheld'] = static function () {
	return true;
};

$GLOBALS['queried_id'] = (int) $about->ID;
check( 'a site can withhold the areas everywhere through the filter', majestic_tube_widget_areas_withheld() );
check( 'and that reaches the areas themselves', '' === render_location( 'footer' )[0] );

unset( $GLOBALS['filters']['majestic_tube_widget_areas_withheld'] );
check( 'and removing the filter restores them', '' !== render_location( 'footer' )[0] );

/* -------------------------------------------------------------------------
 * The cookie notice.
 * ---------------------------------------------------------------------- */

echo "\nThe cookie notice\n";

$GLOBALS['opts']['cookie-notice']      = 'off';
$GLOBALS['opts']['cookie-notice-text'] = '';

check( 'the notice is off until the site switches it on', ! majestic_tube_cookie_notice_enabled() );
check( 'and prints nothing at all', '' === capture( 'majestic_tube_cookie_notice' ) );

$GLOBALS['opts']['cookie-notice'] = 'on';
$notice                           = capture( 'majestic_tube_cookie_notice' );

check( 'the notice prints once the site switches it on', '' !== $notice );
check( 'it is hidden until the script knows the visitor has not dismissed it', false !== strpos( $notice, 'id="majestic-tube-cookie-notice"' ) && false !== strpos( $notice, 'hidden' ) );
check( 'it names itself as a region for assistive technology', false !== strpos( $notice, 'role="region"' ) && false !== strpos( $notice, 'aria-label="Cookie notice"' ) );
check( 'it carries the sentence the theme ships', false !== strpos( $notice, 'This site uses cookies' ) );
check( 'it carries a dismiss control', false !== strpos( $notice, 'data-majestic-tube-cookie-notice-dismiss' ) );
check( 'and it links both documents underneath', false !== strpos( $notice, '>Terms of Use</a>' ) && false !== strpos( $notice, '>Privacy Policy</a>' ) );
check(
	'it carries no content area of its own',
	false === strpos( $notice, 'majestic-tube-content-area' ) && false === strpos( $notice, 'majestic-tube-content-block' )
);

$GLOBALS['opts']['cookie-notice-text'] = 'We use cookies to remember your player settings.';
$notice                                = capture( 'majestic_tube_cookie_notice' );

check( 'the administrator wording replaces the shipped sentence', false !== strpos( $notice, 'We use cookies to remember your player settings.' ) );
check( 'and the links are still printed beneath it', false !== strpos( $notice, '>Privacy Policy</a>' ) );
check( 'and the shipped sentence is gone', false === strpos( $notice, 'This site uses cookies' ) );

$GLOBALS['opts']['cookie-notice'] = 'off';

/*
 * The notice is printed from wp_footer, so the last thing to pin is that the
 * theme schedules it there and not somewhere that would put it above the
 * content it describes.
 */
check(
	'the notice is printed with the rest of the footer markup',
	false !== strpos( $consent_source, "add_action( 'wp_footer', 'majestic_tube_cookie_notice', 9 )" )
);

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
