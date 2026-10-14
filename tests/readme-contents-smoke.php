<?php
/**
 * Smoke test for the README's contents list.
 *
 * The contents list is hand-written markdown that resolves against anchors an
 * editor never sees: GitHub builds each anchor from the heading text, so a link
 * written as #dashboard is dead the moment the heading is called "Dashboard
 * Guide", and nothing in a normal build says so. Fourteen of the entries were
 * pointing at names that had never existed for exactly that reason.
 *
 * This test recomputes the anchors the way GitHub does - lowercase, strip
 * everything that is not a word character, a hyphen or a space, turn spaces
 * into hyphens, suffix repeats - and then holds the file to two rules:
 *
 *   1. Every internal link in the file points at a heading that exists.
 *   2. Every level-two heading has an entry in the contents list, so a new
 *      section cannot be added without a way to reach it.
 *
 * Run:  php tests/readme-contents-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

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
 * The anchor GitHub gives a heading.
 *
 * @param string $text Heading text, with its markdown still on it.
 * @return string
 */
function mt_anchor( $text ) {
	// Rendered text, not markup: entities become their character, and links,
	// images, code spans, emphasis and raw HTML lose their punctuation.
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/!?\[([^\]]*)\]\([^)]*\)/', '$1', $text );
	$text = preg_replace( '/<[^>]+>/', '', $text );
	$text = str_replace( array( '`', '*', '~' ), '', $text );

	// Trailing closing hashes are not part of the text: "## Title ##".
	$text = preg_replace( '/\s+#+\s*$/', '', (string) $text );

	$text = function_exists( 'mb_strtolower' )
		? mb_strtolower( (string) $text, 'UTF-8' )
		: strtolower( (string) $text );

	// Letters, marks, decimal digits and connector punctuation survive, which
	// is what GitHub keeps (its \p{Word}): an underscore in a heading stays in
	// the anchor, a comma, a full stop and an ampersand do not.
	$slug = preg_replace( '/[^\p{L}\p{M}\p{Nd}\p{Pc}\- ]/u', '', $text );

	return str_replace( ' ', '-', (string) $slug );
}

$path = MAJESTIC_TUBE_DIR . '/README.md';
$raw  = file_exists( $path ) ? file_get_contents( $path ) : false;

check( 'the README is where the test expects it', is_string( $raw ), $path );

if ( ! is_string( $raw ) ) {
	echo "\n" . str_repeat( '-', 44 ) . "\n";
	printf( "%d passed, %d failed\n", $passed, $failed );
	exit( 1 );
}

$lines = preg_split( "/\r\n|\r|\n/", $raw );

/* -------------------------------------------------------------------------
 * Anchors, and the links that have to resolve to them
 * ---------------------------------------------------------------------- */

$anchors = array();   // slug => heading text.
$headings = array();  // level => list of slugs.
$links    = array();  // external-style anchor => list of line numbers.
$fence    = '';       // The fence currently open, '' when outside one.
$in_list  = false;
$contents = array();  // label => anchor, in order.

foreach ( $lines as $index => $line ) {
	$number = $index + 1;

	if ( preg_match( '/^\s{0,3}(`{3,}|~{3,})/', $line, $match ) ) {
		$marker = $match[1];

		if ( '' === $fence ) {
			$fence = $marker[0];
		} elseif ( $marker[0] === $fence ) {
			$fence = '';
		}

		continue;
	}

	if ( '' !== $fence ) {
		// Fenced blocks are samples, not documents: a # comment in a PHP
		// snippet is not a heading and is not a link.
		continue;
	}

	if ( preg_match( '/^(#{1,6})\s+(.*?)\s*$/', $line, $match ) ) {
		$slug = mt_anchor( $match[2] );

		if ( isset( $anchors[ $slug ] ) ) {
			// GitHub numbers a repeated heading rather than leaving two
			// headings sharing one anchor.
			$suffix = 1;

			while ( isset( $anchors[ $slug . '-' . $suffix ] ) ) {
				++$suffix;
			}

			$slug = $slug . '-' . $suffix;
		}

		$anchors[ $slug ]  = $match[2];
		$headings[ strlen( $match[1] ) ][] = $slug;
	}

	preg_match_all( '/\]\(\s*#([^)\s]+)\s*\)/', $line, $found );

	foreach ( $found[1] as $target ) {
		$links[ $target ][] = $number;
	}

	// The contents list: the entries directly under the **Contents** label,
	// stopping at the first line that is neither an entry nor a blank one.
	if ( preg_match( '/^\*\*Contents\*\*\s*$/', $line ) ) {
		$in_list = true;
		continue;
	}

	if ( $in_list ) {
		if ( preg_match( '/^-\s+\[([^\]]+)\]\(#([^)\s]+)\)\s*$/', $line, $entry ) ) {
			$contents[ $entry[1] ] = $entry[2];
			continue;
		}

		if ( '' !== trim( $line ) ) {
			$in_list = false;
		}
	}
}

/* -------------------------------------------------------------------------
 * Checks
 * ---------------------------------------------------------------------- */

check(
	'the contents list is present, and carries more than a token entry',
	count( $contents ) >= 15,
	count( $contents ) . ' entr' . ( 1 === count( $contents ) ? 'y' : 'ies' )
);

$broken = array();

foreach ( $contents as $label => $target ) {
	if ( ! isset( $anchors[ $target ] ) ) {
		$broken[] = '[' . $label . '](#' . $target . ')';
	}
}

check(
	'every contents entry points at a heading that exists',
	! $broken,
	implode( ', ', $broken )
);

// The same rule for the cross-references in the prose, which break the same
// way and are just as invisible when they do.
$dead = array();

foreach ( $links as $target => $numbers ) {
	if ( ! isset( $anchors[ $target ] ) ) {
		$dead[] = '#' . $target . ' (line ' . implode( ', ', $numbers ) . ')';
	}
}

check(
	'every internal link in the file points at a heading that exists',
	! $dead,
	implode( '; ', $dead )
);

// Two entries pointing at one heading is a copy-paste slip, and it also hides
// a section that lost its own entry.
$repeated  = array();
$seen_slug  = array();

foreach ( $contents as $label => $target ) {
	if ( isset( $seen_slug[ $target ] ) ) {
		$repeated[] = '#' . $target . ' (' . $seen_slug[ $target ] . ' and ' . $label . ')';
		continue;
	}

	$seen_slug[ $target ] = $label;
}

check( 'no heading is listed twice', ! $repeated, implode( ', ', $repeated ) );

// The drift that produced this test: a section that exists with no way to
// reach it from the list at the top.
$top    = isset( $headings[2] ) ? $headings[2] : array();
$listed = array_values( $contents );
$unlisted = array();

foreach ( $top as $slug ) {
	if ( ! in_array( $slug, $listed, true ) ) {
		$unlisted[] = '#' . $slug;
	}
}

check(
	'every level-two section has an entry in the contents list',
	! $unlisted,
	implode( ', ', $unlisted )
);

// A contents list nobody can scan is a list of dead names, so the labels are
// checked for the one thing that makes a link row readable.
$empty = array();

foreach ( $contents as $label => $target ) {
	if ( '' === trim( $label ) || '' === trim( $target ) ) {
		$empty[] = $label;
	}
}

check( 'no entry is a blank label or a blank target', ! $empty, implode( ', ', $empty ) );

echo "\n" . str_repeat( '-', 44 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

exit( $failed ? 1 : 0 );
