<?php
/**
 * Smoke test for the member-area links.
 *
 * The membership dropdown links to three theme pages. They used to be built by
 * hand as home_url( '/slug' ), and one of them was simply wrong: the profile
 * link pointed at /my-profile/ while the activation routine creates that page
 * at /profile/, so every signed-in member followed a 404. This exercises the
 * resolver that replaced the hand-built URL, and pins the source so the wrong
 * path cannot quietly return.
 *
 * Run:  php tests/member-links-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

define( 'OBJECT', 'OBJECT' );

$GLOBALS['pages'] = array();

function sanitize_title( $title ) {
	$slug = strtolower( (string) $title );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );

	return trim( $slug, '-' );
}

function get_page_by_path( $path, $output = OBJECT, $post_type = 'page' ) {
	foreach ( $GLOBALS['pages'] as $page ) {
		if ( $page->post_name === $path ) {
			return $page;
		}
	}

	return null;
}

function get_permalink( $id ) {
	return 'https://example.test/profile-page-' . (int) $id . '/';
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

// Pull the function under test out of the real file.
$source = file_get_contents( MAJESTIC_TUBE_DIR . '/inc/ajax-login-register.php' );

if ( ! preg_match( '/^function majestic_tube_member_page_url\(.*?^}/ms', $source, $match ) ) {
	fwrite( STDERR, "could not extract majestic_tube_member_page_url\n" );
	exit( 1 );
}

eval( $match[0] );

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

echo "\nThe resolver finds the page\n";

$GLOBALS['pages'] = array(
	(object) array(
		'ID'        => 42,
		'post_name' => 'profile',
	),
);

check( 'a page that exists resolves to its permalink', 'https://example.test/profile-page-42/' === majestic_tube_member_page_url( 'profile' ) );
check( 'an unrelated slug falls back to the slug URL', 'https://example.test/nowhere' === majestic_tube_member_page_url( 'nowhere' ) );

echo "\nA missing page still yields a link\n";

$GLOBALS['pages'] = array();

check( 'a page that is gone falls back to the slug URL', 'https://example.test/submit-a-video' === majestic_tube_member_page_url( 'submit-a-video' ) );

echo "\nThe dropdown uses the resolver, not a hand-built path\n";

check( 'the profile link resolves the profile page', false !== strpos( $source, "majestic_tube_member_page_url( 'profile' )" ) );
check( 'the submit link resolves its page too', false !== strpos( $source, "majestic_tube_member_page_url( 'submit-a-video' )" ) );
check( 'the dead /my-profile URL is gone for good', false === strpos( $source, "home_url( '/my-profile' )" ) );
check( 'and the submit link is no longer hand-built either', false === strpos( $source, "home_url( '/submit-a-video' )" ) );

echo "\n" . str_repeat( '-', 44 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

exit( $failed ? 1 : 0 );
