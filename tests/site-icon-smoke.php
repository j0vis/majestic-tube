<?php
/**
 * Smoke test for the retired browser-tab icon moving into the Site Icon.
 *
 * The theme stopped printing its own favicon so WordPress's Site Icon could be
 * the one answer, which left every site that had already set one holding a
 * value nothing reads. This drives the real inc/site-icon.php and pins the
 * rules that make the move safe:
 *
 *   1. a value the media library already holds becomes the Site Icon by
 *      reference - the attachment is reused, not copied;
 *   2. a value the library does not hold is fetched once through core's own
 *      importer, which is what keeps a stored address from being aimed at a
 *      private host and what refuses a file type the library will not take;
 *   3. a Site Icon that is already set is never overwritten. The owner's
 *      choice outranks a setting the theme has retired;
 *   4. nothing is ever deleted: the stored value stays in the database for an
 *      older release that still reads it, and an icon that could not be moved
 *      is reported on the dashboard instead of disappearing;
 *   5. it all happens on an admin request from a user who could set a Site
 *      Icon by hand, so no visitor request writes an option or fetches a file.
 *
 * The option reader is a small stand-in for majestic_tube_get_option(), which
 * the other suites exercise; what is checked here is that this module asks it
 * for the retired field and nothing else, and that the field is still declared
 * in inc/theme-options.php for the reader to find.
 *
 * Run:  php tests/site-icon-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['options']     = array();
$GLOBALS['mods']        = array();
$GLOBALS['attachments'] = array();
$GLOBALS['urls']        = array();
$GLOBALS['fetchable']   = array();
$GLOBALS['fetches']     = array();
$GLOBALS['returns']     = array();
$GLOBALS['reads']       = array();
$GLOBALS['admin']       = true;
$GLOBALS['caps']        = array( 'edit_theme_options' => true );
$GLOBALS['user_meta']   = array();
$GLOBALS['hooks']       = array();

/* -------------------------------------------------------------------------
 * Core stubs.
 * ---------------------------------------------------------------------- */

class WP_Error {
	public $code;

	public function __construct( $code = '' ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function wp_json_encode_stub( $value ) {
	return json_encode( $value );
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function esc_html_e( $text, $domain = null ) {
	echo esc_html( $text );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = $hook . ':' . ( is_string( $callback ) ? $callback : 'closure' );
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function apply_filters( $hook, $value ) {
	return $value;
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default;
}

function update_option( $key, $value ) {
	$GLOBALS['options'][ $key ] = $value;

	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );

	return true;
}

function get_theme_mod( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['mods'] ) ? $GLOBALS['mods'][ $key ] : $default;
}

function get_current_user_id() {
	return 1;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['user_meta'][ $key ] ) ? $GLOBALS['user_meta'][ $key ] : '';
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['user_meta'][ $key ] = $value;

	return true;
}

function is_admin() {
	return (bool) $GLOBALS['admin'];
}

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['caps'][ $capability ] );
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

function add_query_arg( $args, $url ) {
	$pairs = array();

	foreach ( (array) $args as $key => $value ) {
		$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
	}

	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs );
}

function wp_nonce_url( $url, $action ) {
	return $url . '&_wpnonce=nonce-' . $action;
}

function wp_attachment_is_image( $post ) {
	$id = is_object( $post ) ? $post->ID : (int) $post;

	return isset( $GLOBALS['attachments'][ $id ] ) && 0 === strpos( $GLOBALS['attachments'][ $id ], 'image/' );
}

function attachment_url_to_postid( $url ) {
	return isset( $GLOBALS['urls'][ $url ] ) ? (int) $GLOBALS['urls'][ $url ] : 0;
}

/*
 * Core's importer, reduced to what the module depends on: an address either
 * becomes an attachment or comes back a WP_Error, and the caller has to ask for
 * the id. Anything not in the fixture table fails the way a 404, an offline
 * host or a refused file type fails.
 */
function media_sideload_image( $url, $post_id = 0, $desc = null, $return = 'html' ) {
	$GLOBALS['fetches'][] = $url;
	$GLOBALS['returns'][] = $return;

	if ( ! isset( $GLOBALS['fetchable'][ $url ] ) ) {
		return new WP_Error( 'image_sideload_failed' );
	}

	$id = (int) $GLOBALS['fetchable'][ $url ];

	// The library holds it now, exactly as it does after a real sideload.
	$GLOBALS['attachments'][ $id ] = 'image/png';
	$GLOBALS['urls'][ $url ]       = $id;

	return 'id' === $return ? $id : '<img src="' . $url . '" />';
}

/*
 * The option reader this module reads through, reduced to the precedence the
 * real one implements: the Customizer setting wins, then the legacy
 * wpst-options row. The call itself is recorded so a read of any other field
 * can be caught.
 */
function majestic_tube_get_option( $group, $field, $default = false ) {
	$GLOBALS['reads'][] = $group . ':' . $field;

	if ( 'wpst-options' !== $group || 'favicon' !== $field ) {
		return $default;
	}

	if ( array_key_exists( 'majestic_tube_favicon', $GLOBALS['mods'] ) ) {
		return $GLOBALS['mods']['majestic_tube_favicon'];
	}

	$legacy = get_option( 'wpst-options' );

	return isset( $legacy['favicon'] ) ? $legacy['favicon'] : $default;
}

/* -------------------------------------------------------------------------
 * Harness.
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
 * A site that has a media library but has not been touched by this test yet.
 *
 * The library is what the fixtures below register; everything that a run can
 * write - the options row, the Site Icon, the theme mod, the fetch log - is
 * cleared so each section starts from a known state.
 *
 * @return void
 */
function reset_site() {
	$GLOBALS['options'] = array();
	$GLOBALS['mods']    = array();
	$GLOBALS['fetches'] = array();
	$GLOBALS['returns'] = array();
	$GLOBALS['reads']   = array();
	$GLOBALS['admin']   = true;
	$GLOBALS['caps']    = array( 'edit_theme_options' => true );
}

/**
 * Render the dashboard notice, so what an administrator reads can be checked.
 *
 * @return string
 */
function render_notice() {
	ob_start();
	majestic_tube_favicon_notice();

	return (string) ob_get_clean();
}

/* A library: one uploaded image, one image reachable by URL only, one PDF. */
$GLOBALS['attachments'][42] = 'image/png';
$GLOBALS['attachments'][43] = 'image/jpeg';
$GLOBALS['attachments'][7]  = 'application/pdf';
$GLOBALS['urls']['https://example.test/wp-content/uploads/icon.png']    = 42;
$GLOBALS['urls']['https://example.test/wp-content/uploads/photo.jpg']   = 43;

require MAJESTIC_TUBE_DIR . '/inc/site-icon.php';

/* -------------------------------------------------------------------------
 * 1. The module is wired into the loader.
 * ---------------------------------------------------------------------- */

echo "The module ships with the theme\n";

$functions = file_get_contents( MAJESTIC_TUBE_DIR . '/functions.php' );

check(
	'functions.php loads inc/site-icon.php',
	false !== strpos( $functions, "'/inc/site-icon.php'" )
);

check(
	'and the move runs on the activation request',
	in_array( 'after_switch_theme:majestic_tube_migrate_favicon_to_site_icon', $GLOBALS['hooks'], true ),
	implode( ', ', $GLOBALS['hooks'] )
);

check(
	'and on every later admin request, which is the one an update takes',
	in_array( 'admin_init:majestic_tube_migrate_favicon_to_site_icon', $GLOBALS['hooks'], true )
);

check(
	'with the notice and its dismissal wired up beside it',
	in_array( 'admin_notices:majestic_tube_favicon_notice', $GLOBALS['hooks'], true ) &&
	in_array( 'admin_init:majestic_tube_handle_favicon_notice_dismissal', $GLOBALS['hooks'], true )
);

/* -------------------------------------------------------------------------
 * 2. An icon the media library already holds.
 * ---------------------------------------------------------------------- */

echo "\nA site whose tab icon is an image in the media library\n";

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = 'https://example.test/wp-content/uploads/icon.png';
majestic_tube_migrate_favicon_to_site_icon();

check( 'the Site Icon points at that image', 42 === get_option( 'site_icon' ), 'site_icon: ' . wp_json_encode_stub( get_option( 'site_icon' ) ) );
check( 'and nothing was fetched, because there was nothing to fetch', array() === $GLOBALS['fetches'] );
check( 'and the stored setting is still there for an older release to read', 'https://example.test/wp-content/uploads/icon.png' === $GLOBALS['mods']['majestic_tube_favicon'] );

reset_site();
$GLOBALS['options']['wpst-options'] = array( 'favicon' => 'https://example.test/wp-content/uploads/photo.jpg' );
majestic_tube_migrate_favicon_to_site_icon();

check( 'a value in the legacy options row is moved too', 43 === get_option( 'site_icon' ), 'site_icon: ' . wp_json_encode_stub( get_option( 'site_icon' ) ) );
check(
	'and the legacy row still holds it afterwards',
	'https://example.test/wp-content/uploads/photo.jpg' === $GLOBALS['options']['wpst-options']['favicon']
);
check(
	'and it was asked for as the favicon field of the options group, nothing else',
	array( 'wpst-options:favicon' ) === array_values( array_unique( $GLOBALS['reads'] ) ),
	implode( ', ', array_unique( $GLOBALS['reads'] ) )
);

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = '/wp-content/uploads/icon.png';
majestic_tube_migrate_favicon_to_site_icon();

check(
	'a root-relative address is completed against the site address',
	42 === get_option( 'site_icon' ),
	'site_icon: ' . wp_json_encode_stub( get_option( 'site_icon' ) )
);

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = 'https://example.test/wp-content/uploads/icon.png';
$GLOBALS['options']['wpst-options']       = array( 'favicon' => 'https://example.test/wp-content/uploads/photo.jpg' );
majestic_tube_migrate_favicon_to_site_icon();

check(
	'the Customizer value wins over the legacy row',
	42 === get_option( 'site_icon' ) && 'https://example.test/wp-content/uploads/photo.jpg' === $GLOBALS['options']['wpst-options']['favicon'],
	'site_icon: ' . wp_json_encode_stub( get_option( 'site_icon' ) )
);

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = '42';
majestic_tube_migrate_favicon_to_site_icon();

check( 'a stored attachment id is accepted when it names an image', 42 === get_option( 'site_icon' ) );

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = '7';
majestic_tube_migrate_favicon_to_site_icon();

check( 'but an id that names something else is not made into an icon', false === get_option( 'site_icon' ) );
check( 'and it is reported rather than dropped', '7' === get_option( 'majestic_tube_favicon_unmoved' ) );

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = '';
$GLOBALS['options']['wpst-options']       = array( 'favicon' => 'https://example.test/wp-content/uploads/icon.png' );
majestic_tube_migrate_favicon_to_site_icon();

check( 'a setting the owner deliberately cleared moves nothing', false === get_option( 'site_icon' ) );

/* -------------------------------------------------------------------------
 * 3. An icon hosted somewhere else.
 * ---------------------------------------------------------------------- */

echo "\nA site whose tab icon is hosted somewhere else\n";

reset_site();
$GLOBALS['fetchable']['https://elsewhere.test/favicon.png'] = 91;
$GLOBALS['mods']['majestic_tube_favicon']                   = 'https://elsewhere.test/favicon.png';
$GLOBALS['options']['majestic_tube_favicon_unmoved']        = 'https://old.test/favicon.png';
majestic_tube_migrate_favicon_to_site_icon();

check( 'the icon is fetched once', array( 'https://elsewhere.test/favicon.png' ) === $GLOBALS['fetches'] );
check( 'through core\'s importer, asking for the attachment id', array( 'id' ) === $GLOBALS['returns'] );
check( 'and the Site Icon points at what it kept', 91 === get_option( 'site_icon' ), 'site_icon: ' . wp_json_encode_stub( get_option( 'site_icon' ) ) );
check( 'and an earlier warning is cleared with it', false === get_option( 'majestic_tube_favicon_unmoved' ) );

majestic_tube_migrate_favicon_to_site_icon();

check( 'a second run does not fetch it again', 1 === count( $GLOBALS['fetches'] ) );

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = 'https://elsewhere.test/favicon.png';
$GLOBALS['admin']                         = false;
majestic_tube_migrate_favicon_to_site_icon();

check( 'a visitor request fetches nothing', array() === $GLOBALS['fetches'] );
check( 'and writes nothing', false === get_option( 'site_icon' ) );
check( 'and does not record a failure that never happened', false === get_option( 'majestic_tube_favicon_unmoved' ) );

$GLOBALS['admin'] = true;
$GLOBALS['caps']  = array();
majestic_tube_migrate_favicon_to_site_icon();

check( 'and neither does a request from a role that cannot set a Site Icon', array() === $GLOBALS['fetches'] );

$GLOBALS['caps']['edit_theme_options'] = true;
$GLOBALS['fetchable']['https://elsewhere.test/favicon.png'] = 91;
majestic_tube_migrate_favicon_to_site_icon();

check( 'the next admin request that may make the move makes it', 91 === get_option( 'site_icon' ) );

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = 'https://gone.test/favicon.ico';
majestic_tube_migrate_favicon_to_site_icon();

check( 'an address that no longer answers leaves no Site Icon', false === get_option( 'site_icon' ) );
check( 'and the value is remembered as the one that could not be moved', 'https://gone.test/favicon.ico' === get_option( 'majestic_tube_favicon_unmoved' ) );
check( 'and the value itself is untouched', 'https://gone.test/favicon.ico' === $GLOBALS['mods']['majestic_tube_favicon'] );

majestic_tube_migrate_favicon_to_site_icon();

check( 'so it is not fetched again on every page load', 1 === count( $GLOBALS['fetches'] ) );

// A different address, which the library does not hold yet.
$GLOBALS['fetchable']['https://elsewhere.test/newer.png'] = 92;
$GLOBALS['mods']['majestic_tube_favicon']                 = 'https://elsewhere.test/newer.png';
majestic_tube_migrate_favicon_to_site_icon();

check( 'while a value the owner changes does get a fresh attempt', 2 === count( $GLOBALS['fetches'] ) );
check( 'and it moves', 92 === get_option( 'site_icon' ) );
check( 'and the warning is cleared', false === get_option( 'majestic_tube_favicon_unmoved' ) );

reset_site();
$GLOBALS['mods']['majestic_tube_favicon'] = 'not-an-address';
majestic_tube_migrate_favicon_to_site_icon();

check( 'a value that is not an address is never fetched', array() === $GLOBALS['fetches'] );
check( 'and is reported instead', 'not-an-address' === get_option( 'majestic_tube_favicon_unmoved' ) );

/* -------------------------------------------------------------------------
 * 4. A site that already has a Site Icon, and a site that never set one.
 * ---------------------------------------------------------------------- */

echo "\nA site WordPress already has an answer for\n";

reset_site();
$GLOBALS['options']['site_icon'] = 55;
$GLOBALS['fetchable']['https://elsewhere.test/favicon.png'] = 91;
$GLOBALS['mods']['majestic_tube_favicon'] = 'https://elsewhere.test/favicon.png';
majestic_tube_migrate_favicon_to_site_icon();

check( 'an icon the owner set in WordPress is never overwritten', 55 === get_option( 'site_icon' ) );
check( 'and the retired setting does not fetch anything over it', array() === $GLOBALS['fetches'] );

$GLOBALS['mods']['majestic_tube_favicon'] = 'https://example.test/wp-content/uploads/icon.png';
majestic_tube_migrate_favicon_to_site_icon();

check( 'not even for one the library already holds', 55 === get_option( 'site_icon' ) );

reset_site();
$before = $GLOBALS['options'];
majestic_tube_migrate_favicon_to_site_icon();

check( 'a site that never set the retired icon is left completely alone', $before === $GLOBALS['options'] );

/* -------------------------------------------------------------------------
 * 5. The dashboard warning.
 * ---------------------------------------------------------------------- */

echo "\nTelling the owner about an icon that could not be moved\n";

reset_site();

check( 'nothing is said while there is nothing to move', '' === render_notice() );

$GLOBALS['options']['majestic_tube_favicon_unmoved'] = 'https://gone.test/favicon.ico';
$notice = render_notice();

check( 'an unmoved icon is named on the dashboard', false !== strpos( $notice, 'https://gone.test/favicon.ico' ) );
check( 'with the screen it belongs on', false !== strpos( $notice, 'Site Identity' ) );
check( 'and a link to it', false !== strpos( $notice, 'customize.php?autofocus[section]=title_tagline' ) );
check( 'and a way to stop reading it', false !== strpos( $notice, 'Dismiss this warning' ) );

$GLOBALS['admin'] = false;

check( 'and it is not printed outside the admin', '' === render_notice() );

$GLOBALS['admin'] = true;
$GLOBALS['caps']  = array();

check( 'nor to a user who cannot act on it', '' === render_notice() );

$GLOBALS['caps']['edit_theme_options']                = true;
$GLOBALS['options']['majestic_tube_favicon_unmoved'] = 'https://gone.test/a.png?x=<script>alert(1)</script>';
$notice = render_notice();

check(
	'the address is printed escaped, not as markup',
	false === strpos( $notice, '<script>' ) && false !== strpos( $notice, '&lt;script&gt;' )
);

$key = majestic_tube_favicon_notice_key();

check( 'the notice key follows the address it is about', md5( 'https://gone.test/a.png?x=<script>alert(1)</script>' ) === $key );
check( 'nothing is dismissed before the administrator has read it', false === majestic_tube_favicon_notice_is_dismissed( $key ) );

// What the dismissal handler records, whose redirect and exit cannot run here.
update_user_meta( get_current_user_id(), 'majestic_tube_favicon_notice', $key );

check( 'a dismissal silences the notice', '' === render_notice() );
check( 'and is remembered for that address', true === majestic_tube_favicon_notice_is_dismissed( $key ) );

$GLOBALS['options']['majestic_tube_favicon_unmoved'] = 'https://gone.test/another.png';

check( 'while a different address is told about again', '' !== render_notice() );

/* -------------------------------------------------------------------------
 * 6. The retired field still exists for the reader to find.
 * ---------------------------------------------------------------------- */

echo "\nThe retired field is still declared where the reader looks\n";

$map = file_get_contents( MAJESTIC_TUBE_DIR . '/inc/theme-options.php' );

preg_match( "/'favicon'\s*=> array\((.*?)\n\t\t\),/s", $map, $field );
$definition = isset( $field[1] ) ? $field[1] : '';

check( 'the field is declared at all', '' !== $definition );
check(
	'it still names the setting a stored value lives in',
	(bool) preg_match( "/'setting'\s*=>\s*'majestic_tube_favicon'/", $definition )
);
check(
	'and is still kept out of the Customizer',
	(bool) preg_match( "/'customizer'\s*=>\s*false/", $definition )
);

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}

echo "All checks passed.\n";
