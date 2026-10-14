<?php
/**
 * Smoke test for the release notes in both guides.
 *
 * The README and docs.html each open with a "What's New in <version>" section,
 * and each is written by hand: nothing in a build connects the version in that
 * heading to the version the theme actually ships. The guide went four releases
 * announcing 2.2.0 - 2.3.0, 2.4.0, 2.4.1, 2.4.2, 2.5.0 and 2.5.1 all shipped
 * behind the same page - so a visitor reading the release notes was told the
 * newest thing about the theme was a release from two weeks earlier, and every
 * setting those releases added was documented nowhere as new.
 *
 * Four rules hold the two files to each other:
 *
 *   1. the release notes open on the version style.css declares, in both files,
 *      so a release cannot ship without notes that claim it;
 *   2. the contents list reaches that section, by the anchor the heading really
 *      produces, so the notes are not orphaned at the top of the guide;
 *   3. the two files name the same releases. They are one guide in two formats,
 *      and a release documented in only one of them is documented in neither;
 *   4. the notes are a history: newest first, and every release line between the
 *      oldest release they describe and the one shipping has a note of its own,
 *      so a whole line cannot be skipped without the notes saying so.
 *
 * Run:  php tests/release-notes-smoke.php
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
 * Read a file beside style.css, or an empty string when it is not there.
 *
 * @param string $name File name, relative to the theme.
 * @return string
 */
function mt_read( $name ) {
	$path = MAJESTIC_TUBE_DIR . '/' . $name;

	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}

/**
 * The anchor GitHub builds for a heading: lowercase, punctuation dropped,
 * spaces turned into hyphens. The README's own contents test is the fuller
 * version of this; the release-notes headings are plain enough for the short
 * one.
 *
 * @param string $text Heading text.
 * @return string
 */
function mt_anchor( $text ) {
	$text = strtolower( (string) $text );
	$text = preg_replace( '/[^a-z0-9 -]/', '', $text );

	return str_replace( ' ', '-', (string) $text );
}

/**
 * The version the theme declares, from style.css.
 *
 * @param string $style Contents of style.css.
 * @return string
 */
function mt_style_version( $style ) {
	return preg_match( '/^Version:\s*(\S+)\s*$/m', $style, $match ) ? $match[1] : '';
}

/**
 * The version a release heading names: "### 2.5.1 - ...", "### 2.2.1 - 2.2.26 - ...".
 *
 * @param string $heading Heading text.
 * @return string
 */
function mt_heading_version( $heading ) {
	return preg_match( '/(\d+\.\d+\.\d+)/', (string) $heading, $match ) ? $match[1] : '';
}

/**
 * Every version a block of prose names.
 *
 * @param string $text Text to read.
 * @return string[] Sorted, unique.
 */
function mt_versions( $text ) {
	preg_match_all( '/\b\d+\.\d+\.\d+\b/', (string) $text, $found );

	$versions = array_values( array_unique( $found[0] ) );
	sort( $versions );

	return $versions;
}

/**
 * The lines of the contents list, as label => anchor.
 *
 * @param string $raw Whole file.
 * @return array<string,string>
 */
function mt_contents( $raw ) {
	$entries  = array();
	$in_list  = false;

	foreach ( preg_split( "/\r\n|\r|\n/", (string) $raw ) as $line ) {
		if ( preg_match( '/^\*\*Contents\*\*\s*$/', $line ) ) {
			$in_list = true;
			continue;
		}

		if ( ! $in_list ) {
			continue;
		}

		if ( preg_match( '/^-\s+\[([^\]]+)\]\(#([^)\s]+)\)\s*$/', $line, $entry ) ) {
			$entries[ $entry[1] ] = $entry[2];
			continue;
		}

		if ( '' !== trim( $line ) ) {
			break;
		}
	}

	return $entries;
}

/**
 * The release-notes section of the README, and the version its heading names.
 *
 * @param string $raw Whole file.
 * @return array{version:string,anchor:string,body:string,headings:string[]}
 */
function mt_readme_notes( $raw ) {
	$raw = (string) $raw;

	if ( ! preg_match( '/^## What.{0,4}s New in (\d+\.\d+\.\d+)[^\n]*$/m', $raw, $match, PREG_OFFSET_CAPTURE ) ) {
		return array(
			'version'  => '',
			'anchor'   => '',
			'body'     => '',
			'headings' => array(),
		);
	}

	$start = $match[0][1];
	$after = substr( $raw, $start + strlen( $match[0][0] ) );

	// The section ends at the next level-two heading.
	$body = preg_match( '/^## /m', $after, $next, PREG_OFFSET_CAPTURE )
		? substr( $after, 0, $next[0][1] )
		: $after;

	preg_match_all( '/^### (.+)$/m', $body, $found );

	return array(
		'version'  => $match[1][0],
		// The marker is not part of the heading text GitHub hashes.
		'anchor'   => mt_anchor( preg_replace( '/^#+\s*/', '', $match[0][0] ) ),
		'body'     => $body,
		'headings' => $found[1],
	);
}

/**
 * The release-notes section of docs.html, and the version its heading names.
 *
 * @param string $raw Whole file.
 * @return array{version:string,id:string,body:string,headings:string[]}
 */
function mt_docs_notes( $raw ) {
	$raw = (string) $raw;

	if ( ! preg_match( '/<section id="([^"]+)">\s*<p class="eyebrow">Release<\/p>\s*<h2>What[^<]*s New in (\d+\.\d+\.\d+)<\/h2>/', $raw, $match, PREG_OFFSET_CAPTURE ) ) {
		return array(
			'version'  => '',
			'id'       => '',
			'body'     => '',
			'headings' => array(),
		);
	}

	$start   = $match[0][1];
	$closing = strpos( $raw, '</section>', $start );
	$body    = false === $closing ? substr( $raw, $start ) : substr( $raw, $start, $closing - $start );

	preg_match_all( '/<h3>(.+?)<\/h3>/s', $body, $found );

	return array(
		'version'  => $match[2][0],
		'id'       => $match[1][0],
		'body'     => $body,
		'headings' => $found[1],
	);
}

/**
 * Check that a list of release headings descends, newest first.
 *
 * @param string[] $headings Heading texts, in file order.
 * @return string The versions in order.
 */
function mt_ordering( $headings ) {
	$versions = array();

	foreach ( $headings as $heading ) {
		$versions[] = mt_heading_version( $heading );
	}

	return $versions;
}

/**
 * Whether a version list descends, with no heading missing a version.
 *
 * @param string[] $versions Versions in file order.
 * @return bool
 */
function mt_descends( $versions ) {
	$previous = null;

	foreach ( $versions as $version ) {
		if ( '' === $version ) {
			return false;
		}

		if ( null !== $previous && version_compare( $version, $previous, '>=' ) ) {
			return false;
		}

		$previous = $version;
	}

	return true;
}

/**
 * The minor release lines a set of versions belongs to: 2.2.1 and 2.2.26 are
 * both "2.2".
 *
 * @param string[] $versions Versions.
 * @return string[]
 */
function mt_lines( $versions ) {
	$lines = array();

	foreach ( $versions as $version ) {
		$parts = explode( '.', $version );

		if ( count( $parts ) >= 2 ) {
			$lines[] = $parts[0] . '.' . $parts[1];
		}
	}

	$lines = array_values( array_unique( $lines ) );
	sort( $lines );

	return $lines;
}

/* -------------------------------------------------------------------------
 * The two guides
 * ---------------------------------------------------------------------- */

$style  = mt_read( 'style.css' );
$readme = mt_read( 'README.md' );
$docs   = mt_read( 'docs.html' );

$shipped = mt_style_version( $style );

$readme_notes = mt_readme_notes( $readme );
$docs_notes   = mt_docs_notes( $docs );

echo "\nThe README's release notes\n";

check( 'the README is where the test expects it', '' !== $readme );

check(
	'the release notes open on the version the theme ships',
	'' !== $shipped && $shipped === $readme_notes['version'],
	'the theme ships ' . ( $shipped ?: 'no declared version' ) . ', the notes announce ' . ( $readme_notes['version'] ?: 'no version' )
);

$contents = mt_contents( $readme );
$reached  = array_key_exists( $readme_notes['anchor'], array_flip( $contents ) );

check(
	'the contents list reaches the release notes',
	$reached,
	'no entry points at #' . $readme_notes['anchor']
);

$label = '';

foreach ( $contents as $text => $target ) {
	if ( $target === $readme_notes['anchor'] ) {
		$label = $text;
	}
}

check(
	'and the entry there names that same version',
	false !== strpos( $label, $readme_notes['version'] ) && '' !== $readme_notes['version'],
	'"' . $label . '"'
);

$readme_order = mt_ordering( $readme_notes['headings'] );

check(
	'the release headings run newest first, and every one names a version',
	mt_descends( $readme_order ),
	implode( ', ', $readme_order )
);

echo "\nThe HTML guide's release notes\n";

check( 'the HTML guide is where the test expects it', '' !== $docs );

check(
	'its release notes open on the same version',
	'' !== $readme_notes['version'] && $readme_notes['version'] === $docs_notes['version'],
	'README announces ' . ( $readme_notes['version'] ?: 'no version' ) . ', docs.html announces ' . ( $docs_notes['version'] ?: 'no version' )
);

check(
	'and its contents list reaches that section, by the id it actually carries',
	false !== strpos( $docs, 'href="#' . $docs_notes['id'] . '"' ) && '' !== $docs_notes['id'],
	'no link to #' . $docs_notes['id']
);

$ids  = array();
$dead = array();

preg_match_all( '/id="([^"]+)"/', $docs, $found_ids );
preg_match_all( '/href="#([^"]+)"/', $docs_notes['body'], $found_links );

foreach ( $found_ids[1] as $id ) {
	$ids[ $id ] = true;
}

foreach ( $found_links[1] as $target ) {
	if ( ! isset( $ids[ $target ] ) ) {
		$dead[] = '#' . $target;
	}
}

check(
	'every link inside its release notes points at a section that exists',
	! $dead,
	implode( ', ', array_unique( $dead ) )
);

$docs_order = mt_ordering( $docs_notes['headings'] );

check(
	'its release headings name the same releases, in the same order',
	mt_descends( $docs_order ) && $readme_order === $docs_order,
	implode( ', ', $docs_order )
);

echo "\nThe two guides agree on what shipped\n";

$readme_versions = mt_versions( $readme_notes['body'] );
$docs_versions   = mt_versions( $docs_notes['body'] );

$only_readme = array_values( array_diff( $readme_versions, $docs_versions ) );
$only_docs   = array_values( array_diff( $docs_versions, $readme_versions ) );

check(
	'every release the README describes is described in docs.html too',
	! $only_readme,
	implode( ', ', $only_readme )
);

check(
	'and every release docs.html describes is described in the README too',
	! $only_docs,
	implode( ', ', $only_docs )
);

/*
 * The notes reach back to the release the guide introduced last, and every
 * release line between that release and the one shipping has a note of its own.
 * A whole line skipped - the 2.3 releases, say - leaves the guide claiming a gap
 * that never happened, and it is the one kind of drift a reader cannot see: the
 * section still looks complete, it is just missing a release.
 *
 * The two versions are counted within one major line, which is what the range
 * spans today; a major release would start a fresh section rather than a longer
 * one, and there is nothing here to compare across that line.
 */
$oldest = '' !== $readme_order[ count( $readme_order ) - 1 ] ? $readme_order[ count( $readme_order ) - 1 ] : '';
$lines  = mt_lines( $readme_versions );
$gaps   = array();

if ( '' !== $oldest && '' !== $shipped ) {
	$from = explode( '.', $oldest );
	$to   = explode( '.', $shipped );

	if ( isset( $from[0], $from[1], $to[0], $to[1] ) && $from[0] === $to[0] ) {
		for ( $minor = (int) $from[1]; $minor <= (int) $to[1]; $minor++ ) {
			$line = $from[0] . '.' . $minor;

			if ( ! in_array( $line, $lines, true ) ) {
				$gaps[] = $line;
			}
		}
	}
}

check(
	'and every release line between the oldest note and the version shipping is covered',
	! $gaps,
	'no note for ' . implode( ', ', $gaps )
);

echo "\n" . str_repeat( '-', 44 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

exit( $failed ? 1 : 0 );
