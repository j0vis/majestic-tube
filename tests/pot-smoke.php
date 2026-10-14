<?php
/**
 * Smoke test for the theme's translation template.
 *
 * languages/majestic-tube.pot is generated from the theme's own source, and a
 * generated file that nothing reads back is a file that drifts: a string added
 * last month is missing from it, a string deleted last week is still in it, and
 * the first person to notice is a translator working from a template that lies.
 * This test is the read-back. It holds the template to two directions at once -
 * every string the theme wraps for translation is in it, and nothing in it has
 * outlived the string it was taken from - and to the shape a translation tool
 * expects: an empty msgstr per entry, plurals paired, entries ordered and
 * unique, and a header carrying no creation date and no per-string source
 * locations, because both change on edits that leave every string alone.
 *
 * The header's encoding fields are pinned exactly; its three free-text fields
 * (project, bug address, language team) are pinned by field, since what they say
 * is the maintainer's to write and that they say it is the point.
 *
 * The source scan is deliberately looser than the generator: it matches a
 * translation call, takes its first quoted argument, and requires the theme's
 * domain to appear inside the same call. The generator parses arguments and
 * merges plurals, so a string this scan can see it can see too. The looseness is
 * one way on purpose - a string this cannot see is a string the template is not
 * held to, which is a missed check rather than a false alarm.
 *
 * Run:  php tests/pot-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$passed = 0;
$failed = 0;

/**
 * Record one check.
 *
 * @param string $label  What is being checked.
 * @param bool   $ok     Whether it held.
 * @param string $detail Shown when it did not.
 * @return void
 */
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

/* -------------------------------------------------------------------------
 * The theme's own files
 * ---------------------------------------------------------------------- */

/**
 * The theme's PHP files, which are what the template is generated from.
 *
 * The directories design-smoke.php leaves out of a spelling check are left out
 * here too, plus .github: none of them is the theme, and a string inside one is
 * not a string a translator has to carry.
 *
 * @return string[] Paths relative to the theme root, sorted.
 */
function pot_php_files() {
	$skip  = array( '.git', '.github', '.kilo', '.freebuff', '.idea', '.vscode', 'node_modules', 'tests', 'tools', '__pycache__', 'vendor' );
	$files = array();

	$walk = function ( $dir ) use ( &$walk, &$files, $skip ) {
		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . '/' . $entry;

			if ( is_dir( $path ) ) {
				if ( ! in_array( $entry, $skip, true ) ) {
					$walk( $path );
				}

				continue;
			}

			if ( 'php' !== strtolower( (string) pathinfo( $entry, PATHINFO_EXTENSION ) ) ) {
				continue;
			}

			$files[] = ltrim( str_replace( MAJESTIC_TUBE_DIR, '', $path ), '/' );
		}
	};

	$walk( MAJESTIC_TUBE_DIR );

	sort( $files );

	return $files;
}

/**
 * The contents of the theme's PHP files, keyed by their relative path.
 *
 * @param string[] $files Relative paths.
 * @return array<string, string>
 */
function pot_sources( $files ) {
	$sources = array();

	foreach ( $files as $relative ) {
		$contents = file_get_contents( MAJESTIC_TUBE_DIR . '/' . $relative );

		if ( false !== $contents ) {
			$sources[ $relative ] = $contents;
		}
	}

	return $sources;
}

/* -------------------------------------------------------------------------
 * Reading the template
 * ---------------------------------------------------------------------- */

/**
 * The value inside one quoted segment of a template line.
 *
 * A template escapes exactly these: the quote, the backslash, and the three
 * control characters. Decoding in one pass matters - replacing the backslash
 * first would turn an escaped backslash into the start of an escape that was
 * never there.
 *
 * @param string $segment A quoted segment, quotes included.
 * @return string
 */
function pot_decode( $segment ) {
	$body = substr( (string) $segment, 1, -1 );

	return (string) preg_replace_callback(
		'/\\\\(.)/',
		static function ( $match ) {
			$map = array(
				'n'  => "\n",
				't'  => "\t",
				'r'  => "\r",
				'"'  => '"',
				'\\' => '\\',
				"'"  => "'",
			);

			return isset( $map[ $match[1] ] ) ? $map[ $match[1] ] : $match[0];
		},
		$body
	);
}

/**
 * A string as a template writes it.
 *
 * Used the other way round from pot_decode(): a source file that carries the
 * string in a double-quoted literal spells the same escapes the template does.
 *
 * @param string $text Raw text.
 * @return string
 */
function pot_encode( $text ) {
	return str_replace(
		array( '\\', '"', "\n", "\r", "\t" ),
		array( '\\\\', '\\"', '\\n', '\\r', '\\t' ),
		(string) $text
	);
}

/**
 * A string as a single-quoted PHP literal would carry it.
 *
 * PHP unescapes only \\ and \' inside single quotes, so a string the theme wrote
 * that way appears in the file with its quotes escaped and nothing else.
 *
 * @param string $text Raw text.
 * @return string
 */
function pot_php_single( $text ) {
	return str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $text );
}

/**
 * The value on a `msgid "..."` line, or on a continuation of one.
 *
 * A template value is one or more quoted segments on the same line, and a long
 * string may be spread over several lines; both decode to one string.
 *
 * @param string $line Line of a template.
 * @return string
 */
function pot_line_value( $line ) {
	if ( ! preg_match_all( '/"(?:\\\\.|[^"\\\\])*"/', (string) $line, $matches ) ) {
		return '';
	}

	$value = '';

	foreach ( $matches[0] as $segment ) {
		$value .= pot_decode( $segment );
	}

	return $value;
}

/**
 * Parse one block of the template.
 *
 * @param string $block Lines between two blank lines.
 * @return array|null Entry, or null when the block declares no message.
 */
function pot_parse_block( $block ) {
	$entry  = array( 'id' => '', 'ctxt' => '', 'plural' => '', 'forms' => array() );
	$field  = '';
	$declares = false;

	foreach ( explode( "\n", (string) $block ) as $line ) {
		$line = rtrim( $line );

		if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
			continue;
		}

		if ( preg_match( '/^(msgctxt|msgid|msgid_plural|msgstr(?:\[(\d+)\])?)\s+(.*)$/', $line, $match ) ) {
			$field = 'msgstr' === $match[1] && isset( $match[2] ) && '' !== $match[2]
				? 'msgstr[' . $match[2] . ']'
				: $match[1];

			if ( 'msgctxt' === $field ) {
				$entry['ctxt'] = pot_line_value( $match[3] );
			} elseif ( 'msgid' === $field ) {
				$entry['id']   = pot_line_value( $match[3] );
				$declares      = true;
			} elseif ( 'msgid_plural' === $field ) {
				$entry['plural'] = pot_line_value( $match[3] );
			} else {
				$entry['forms'][ $field ] = pot_line_value( $match[3] );
			}

			continue;
		}

		// A line that is only quotes continues the field above it.
		if ( '"' === substr( $line, 0, 1 ) && '' !== $field ) {
			if ( 'msgctxt' === $field ) {
				$entry['ctxt'] .= pot_line_value( $line );
			} elseif ( 'msgid' === $field ) {
				$entry['id'] .= pot_line_value( $line );
			} elseif ( 'msgid_plural' === $field ) {
				$entry['plural'] .= pot_line_value( $line );
			} else {
				$entry['forms'][ $field ] = ( $entry['forms'][ $field ] ?? '' ) . pot_line_value( $line );
			}
		}
	}

	return $declares ? $entry : null;
}

/**
 * The template's entries, in the order the file holds them.
 *
 * The header block is one of these too and is left out here: its message id is
 * empty, which is what makes it the header.
 *
 * @param string $text Template.
 * @return array[] Each entry with id, ctxt, plural and forms.
 */
function pot_entries( $text ) {
	$entries = array();

	foreach ( preg_split( '/\n\s*\n/', (string) $text ) as $block ) {
		$entry = pot_parse_block( $block );

		if ( null !== $entry && '' !== $entry['id'] ) {
			$entries[] = $entry;
		}
	}

	return $entries;
}

/* -------------------------------------------------------------------------
 * Reading the source
 * ---------------------------------------------------------------------- */

/**
 * Every string the theme hands to a translation function with its own domain.
 *
 * @param array<string, string> $sources File contents, keyed by relative path.
 * @return array<string, string> String => the file it was found in.
 */
function pot_source_strings( $sources ) {
	// Longest first inside the alternation, so esc_html__ is not read as the two
	// underscores of a bare __ and _nx is not read as _n.
	$names   = '(?:esc_attr__|esc_attr_e|esc_attr_x|esc_html__|esc_html_e|esc_html_x|translate|_nx|_n|_ex|_x|__|_e)';
	$pattern = '/\b' . $names . '\s*\(((?:[^()\'"]|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')*)\)/';
	$found   = array();

	foreach ( $sources as $relative => $contents ) {
		if ( ! preg_match_all( $pattern, (string) $contents, $calls, PREG_SET_ORDER ) ) {
			continue;
		}

		foreach ( $calls as $call ) {
			$arguments = $call[1];

			if ( false === strpos( $arguments, "'majestic-tube'" ) && false === strpos( $arguments, '"majestic-tube"' ) ) {
				continue;
			}

			if ( ! preg_match( '/^\s*("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')/', $arguments, $first ) ) {
				continue;
			}

			$literal = $first[1];

			// PHP unescapes the two quote forms differently, so the argument's
			// own quotes decide which reading is the right one.
			$value = "'" === substr( $literal, 0, 1 )
				? str_replace( array( '\\\\', "\\'" ), array( '\\', "'" ), substr( $literal, 1, -1 ) )
				: pot_decode( $literal );

			if ( '' !== $value && ! isset( $found[ $value ] ) ) {
				$found[ $value ] = $relative;
			}
		}
	}

	return $found;
}

/* -------------------------------------------------------------------------
 * The file
 * ---------------------------------------------------------------------- */

echo "\nThe template\n";

$template_path = 'languages/majestic-tube.pot';
$template      = @file_get_contents( MAJESTIC_TUBE_DIR . '/' . $template_path );
$template      = false === $template ? '' : $template;

check( 'the template exists', '' !== $template, $template_path . ' is missing' );
check( 'and holds something', strlen( $template ) > 1000, strlen( $template ) . ' bytes' );
/*
 * The repository keeps the template with LF endings and the release export
 * writes it with the platform's, because git converts a text file on the way
 * out - every PHP file in the theme is exported the same way, and the installed
 * theme is the export. So the thing worth failing on is a file that mixes the
 * two: that is what a second editor on a second machine leaves behind, and a
 * gettext tool then reads it a line at a time.
 */
$crlf = substr_count( $template, "\r\n" );
$lone = substr_count( str_replace( "\r\n", '', $template ), "\r" );

check(
	'and does not mix line endings',
	0 === $lone && ( 0 === $crlf || $crlf === substr_count( $template, "\n" ) ),
	$crlf . ' CRLF line(s), ' . $lone . ' bare CR'
);
check( 'and ends with a newline', "\n" === substr( $template, -1 ) );

/* -------------------------------------------------------------------------
 * The header
 * ---------------------------------------------------------------------- */

echo "\nThe header\n";

foreach (
	array(
		'"Project-Id-Version: '                        => 'names the project',
		'"Report-Msgid-Bugs-To: '                      => 'says where a bug in a string is reported',
		'"MIME-Version: 1.0\n"'                        => 'declares the MIME version',
		'"Content-Type: text/plain; charset=UTF-8\n"'  => 'declares UTF-8',
		'"Content-Transfer-Encoding: 8bit\n"'          => 'declares the transfer encoding',
		'"Language-Team: '                             => 'leaves a line for the team',
	) as $field => $what
) {
	check( 'the header ' . $what, false !== strpos( $template, $field ), $field );
}

check(
	'the header carries no creation date',
	false === strpos( $template, 'POT-Creation-Date' ),
	'a date changes on every run, and a template that churns cannot be reviewed'
);
check(
	'and no per-string source locations',
	false === strpos( $template, "\n#:" ) && 0 !== strpos( $template, '#:' ),
	'line numbers move on edits that change no string at all'
);

/* -------------------------------------------------------------------------
 * The entries
 * ---------------------------------------------------------------------- */

echo "\nThe entries\n";

$entries = pot_entries( $template );
$ids     = array();

check( 'the template holds the theme\'s strings', count( $entries ) > 100, count( $entries ) . ' entries' );

$empty_id   = array();
$translated = array();
$plurals    = array();
$lone_form  = array();

foreach ( $entries as $entry ) {
	$ids[ $entry['id'] ] = true;

	if ( '' === $entry['id'] ) {
		$empty_id[] = 'entry with no message id';
	}

	foreach ( $entry['forms'] as $name => $value ) {
		if ( '' !== $value ) {
			$translated[] = $entry['id'] . ' => ' . $name;
		}
	}

	if ( '' !== $entry['plural'] ) {
		if ( ! isset( $entry['forms']['msgstr[0]'], $entry['forms']['msgstr[1]'] ) ) {
			$plurals[] = $entry['id'];
		}
	} elseif ( isset( $entry['forms']['msgstr[0]'] ) ) {
		$lone_form[] = $entry['id'];
	}
}

check( 'every entry states the string it translates', array() === $empty_id, implode( ', ', array_slice( $empty_id, 0, 4 ) ) );
check(
	'and leaves the translation empty, because this is a template',
	array() === $translated,
	implode( ', ', array_slice( $translated, 0, 4 ) )
);
check(
	'every plural entry is paired with the plural string and both forms',
	array() === $plurals,
	implode( ', ', array_slice( $plurals, 0, 4 ) )
);
check(
	'and no singular entry carries a plural form',
	array() === $lone_form,
	implode( ', ', array_slice( $lone_form, 0, 4 ) )
);

// Ordered by context and then by string, which is the order a translator reads
// them in and the order a diff of the file stays readable in.
$previous   = null;
$out_of_order = array();
$duplicated = array();
$seen       = array();

foreach ( $entries as $entry ) {
	$key = $entry['ctxt'] . "\0" . $entry['id'];

	if ( isset( $seen[ $key ] ) ) {
		$duplicated[] = $entry['id'];
	}

	$seen[ $key ] = true;

	if ( null !== $previous && strcmp( $key, $previous ) < 0 ) {
		$out_of_order[] = $entry['id'];
	}

	$previous = $key;
}

check( 'the entries are ordered by context and string', array() === $out_of_order, implode( ', ', array_slice( $out_of_order, 0, 4 ) ) );
check( 'and no string appears twice', array() === $duplicated, implode( ', ', array_slice( $duplicated, 0, 4 ) ) );

/* -------------------------------------------------------------------------
 * The source, both ways round
 * ---------------------------------------------------------------------- */

echo "\nThe source and the template\n";

$files    = pot_php_files();
$sources  = pot_sources( $files );
$wrapped  = pot_source_strings( $sources );
$missing  = array();

foreach ( $wrapped as $string => $relative ) {
	if ( ! isset( $ids[ $string ] ) ) {
		$missing[] = $string . ' (' . $relative . ')';
	}
}

check( 'the scan finds the theme\'s own strings', count( $wrapped ) > 100, count( $wrapped ) . ' strings across ' . count( $files ) . ' files' );
check(
	'every string the theme wraps for translation is in the template',
	array() === $missing,
	implode( ', ', array_slice( $missing, 0, 6 ) ) . ' - regenerate the template'
);

// The other direction: a string that was deleted leaves nothing behind, which
// is the failure a template nobody regenerates always has.
$blob  = implode( "\n", $sources );
$stale = array();

foreach ( array_keys( $ids ) as $string ) {
	$candidates = array(
		(string) $string,
		pot_encode( $string ),
		pot_php_single( $string ),
	);

	$found_it = false;

	foreach ( $candidates as $candidate ) {
		if ( '' !== $candidate && false !== strpos( $blob, $candidate ) ) {
			$found_it = true;
			break;
		}
	}

	if ( ! $found_it ) {
		$stale[] = $string;
	}
}

check(
	'no entry outlives the string it was taken from',
	array() === $stale,
	implode( ', ', array_slice( $stale, 0, 6 ) ) . ' - the string is gone from the source'
);

echo "\n" . str_repeat( '-', 46 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

if ( $failed ) {
	exit( 1 );
}
