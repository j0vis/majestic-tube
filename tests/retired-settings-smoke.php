<?php
/**
 * Smoke test for every setting 2.5.0 and 2.5.1 retired or re-homed.
 *
 * Retiring a field from the Customizer is easy. The part that has to be
 * answered for is the value a site already stored: it stays in the database
 * whatever the code does, so a release either hands it to its new home or
 * leaves it with no reader at all. Two releases did that work:
 *
 *   1. 2.5.0 retired the theme's own favicon field, and asked site owners to
 *      set the WordPress Site Icon by hand (reported in tools/notes for that
 *      release, see the Changed section of the 2.5.0 notes);
 *   2. 2.5.1 moved the stored value into the Site Icon instead, reading it
 *      through the option map so both places a value can live are covered -
 *      inc/site-icon.php;
 *   3. 2.5.0 re-homed the visitor's own skin choice from one stored value to a
 *      value plus the default it was chosen against, and 2.5.1's successor here
 *      honors the older shape rather than deleting it - tests/design-smoke.php
 *      covers that pair, both in the head script and in the click handler;
 *   4. 2.5.0 stopped writing the `_majestic_tube_legal_redirect` marker,
 *      because the 2257 redirect now decides by document identity rather than
 *      by something stored on the old page.
 *
 * What this pins is the rule underneath all four: a stored value is never left
 * with no reader. A field the Customizer no longer offers still resolves
 * through the map, so both homes keep answering and a downgrade still finds
 * what it wrote; a field id read straight out of the legacy wpst-options row is
 * declared in the map, so neither home is a dead end; no two fields share a
 * storage key, because a rename is what orphans a value; and the retired
 * favicon is read through the field id the migration moves.
 *
 * Run:  php tests/retired-settings-smoke.php
 *
 * @package Majestic Tube
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'MAJESTIC_TUBE_DIR', dirname( __DIR__ ) );

$GLOBALS['options'] = array();
$GLOBALS['mods']    = array();
$GLOBALS['hooks']   = array();

/* -------------------------------------------------------------------------
 * Core stubs. inc/theme-options.php registers two hooks as it loads and calls
 * nothing else until a function in it is asked for something.
 * ---------------------------------------------------------------------- */

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
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

function set_theme_mod( $key, $value ) {
	$GLOBALS['mods'][ $key ] = $value;
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function is_admin() {
	return true;
}

function current_user_can( $capability ) {
	return true;
}

function is_wp_error( $thing ) {
	return false;
}

function wp_attachment_is_image( $attachment_id ) {
	return (int) $attachment_id > 0;
}

function attachment_url_to_postid( $url ) {
	return 0;
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

/* -------------------------------------------------------------------------
 * The real map, the real reader, and the real migration.
 * ---------------------------------------------------------------------- */

require MAJESTIC_TUBE_DIR . '/inc/theme-options.php';
require MAJESTIC_TUBE_DIR . '/inc/site-icon.php';

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
 * Every PHP file the release actually ships.
 *
 * The harness is not part of the theme, so its field ids are not the theme's to
 * answer for - the same rule design-smoke.php's shipped_text_files() applies.
 *
 * @return string[] Absolute paths.
 */
function retired_settings_shipped_files() {
	$skip    = array( '.git', '.freebuff', '.kilo', 'node_modules', 'tests', 'tools', 'vendor', '__pycache__' );
	$files   = array();
	$pending = array( MAJESTIC_TUBE_DIR );

	while ( $pending ) {
		$directory = array_pop( $pending );

		foreach ( scandir( $directory ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $directory . '/' . $entry;

			if ( is_dir( $path ) ) {
				if ( ! in_array( $entry, $skip, true ) ) {
					$pending[] = $path;
				}

				continue;
			}

			if ( 'php' === strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ) ) {
				$files[] = $path;
			}
		}
	}

	sort( $files );

	return $files;
}

$map     = majestic_tube_options_map();
$sources = array();

foreach ( retired_settings_shipped_files() as $path ) {
	$sources[ str_replace( MAJESTIC_TUBE_DIR, '', $path ) ] = file_get_contents( $path );
}

echo "\nThe map is where a stored value's home is declared\n";

check( 'it declares the fields a site can save', count( $map ) > 100, count( $map ) . ' fields' );

$settings   = array();
$unprefixed = array();
$keyless    = array();

foreach ( $map as $field => $config ) {
	$setting = isset( $config['setting'] ) ? (string) $config['setting'] : '';

	if ( '' === $setting ) {
		$keyless[] = $field;

		continue;
	}

	if ( 0 !== strpos( $setting, 'majestic_tube_' ) ) {
		$unprefixed[] = $field . ' => ' . $setting;
	}

	$settings[ $field ] = $setting;
}

check( 'every field names the setting it is stored in', ! $keyless, implode( ', ', $keyless ) );
check( 'every storage key is namespaced to the theme', ! $unprefixed, implode( ', ', $unprefixed ) );
check(
	'and no two fields share one, because a rename is what orphans a value',
	count( array_unique( $settings ) ) === count( $settings ),
	implode( ', ', array_diff_assoc( $settings, array_unique( $settings ) ) )
);
check(
	'every field declares the default that answers when neither home holds a value',
	count( array_filter( $map, static fn( $config ) => array_key_exists( 'default', $config ) ) ) === count( $map )
);

echo "\nA field the Customizer no longer offers still answers for its stored value\n";

$retired = array_keys(
	array_filter(
		$map,
		static fn( $config ) => isset( $config['customizer'] ) && ! $config['customizer']
	)
);

check( 'the Customizer fields that were retired are still declared', count( $retired ) > 0, count( $retired ) . ' retired' );
check(
	'the browser-tab icon is one of them, so an older release that downgrades still reads it',
	in_array( 'favicon', $retired, true )
);

$row  = 'wpst-options';
$miss = array();

foreach ( $retired as $field ) {
	// Only the legacy row holds a value: it is what a site that set the field
	// before this theme modelled it has.
	$GLOBALS['mods']            = array();
	$GLOBALS['options'][ $row ] = array( $field => 'stored-' . $field );
	$from_row                   = majestic_tube_get_option( $row, $field, 'never-returned' );

	if ( 'stored-' . $field !== $from_row ) {
		$miss[] = $field . ' gave ' . var_export( $from_row, true );
	}

	// The other home: the Customizer value outranks the row, which is what
	// makes the field's own saving continue to work.
	$GLOBALS['mods'][ $settings[ $field ] ] = 'chosen-' . $field;
	$from_mod                               = majestic_tube_get_option( $row, $field, 'never-returned' );

	if ( 'chosen-' . $field !== $from_mod ) {
		$miss[] = $field . ' ignored the Customizer value';
	}
}

check( 'each one hands its stored value over, from the row and from the Customizer', ! $miss, implode( '; ', array_slice( $miss, 0, 4 ) ) );

$GLOBALS['mods']            = array();
$GLOBALS['options'][ $row ] = array( 'show-icons' => 'on' );

check(
	'and with neither home set, the field answers with its declared default rather than nothing',
	'on' === majestic_tube_get_option( $row, 'show-icons', 'never-returned' )
);

$GLOBALS['options'] = array();
$GLOBALS['mods']    = array();

echo "\nEvery field id read straight out of the legacy row is declared\n";

$read_by_field = array();

foreach ( $sources as $path => $source ) {
	if ( false !== strpos( $path, '/theme-options.php' ) ) {
		continue; // The reader and the map are not a caller.
	}

	if ( preg_match_all( "/majestic_tube_get_option\(\s*'wpst-options'\s*,\s*'([^']+)'/", $source, $found ) ) {
		foreach ( $found[1] as $field ) {
			$read_by_field[ $field ][] = $path;
		}
	}

	if ( preg_match_all( "/majestic_tube_option_is_on\(\s*'([^']+)'/", $source, $found ) ) {
		foreach ( $found[1] as $field ) {
			$read_by_field[ $field ][] = $path;
		}
	}
}

check( 'the shipped files do read settings by field id', count( $read_by_field ) > 20, count( $read_by_field ) . ' field ids' );

$undeclared = array();

foreach ( $read_by_field as $field => $callers ) {
	if ( ! isset( $map[ $field ] ) ) {
		$undeclared[] = $field . ' (' . implode( ', ', array_unique( $callers ) ) . ')';
	}
}

check(
	'so a value saved in the Customizer reaches them too, not only the row',
	! $undeclared,
	implode( '; ', array_slice( $undeclared, 0, 4 ) )
);

echo "\nThe retired browser-tab icon is read from both homes the field had\n";

check( 'the map still declares the retired field', isset( $map['favicon'] ) );
check(
	'under the storage key it was always stored in, so nothing moved out from under a downgrade',
	isset( $map['favicon']['setting'] ) && 'majestic_tube_favicon' === $map['favicon']['setting']
);
check(
	'and the migration reads the setting, not a second copy of the field id',
	false !== strpos( $sources['/inc/site-icon.php'], "majestic_tube_get_option( 'wpst-options', 'favicon', '' )" )
);

$GLOBALS['options'][ $row ]              = array( 'favicon' => 'https://example.test/icon.png' );
$GLOBALS['options']['site_icon']         = 0;
$GLOBALS['mods']                         = array();
$from_legacy_row                         = majestic_tube_retired_favicon();

check( 'a value the row holds is the value the migration moves', 'https://example.test/icon.png' === $from_legacy_row, var_export( $from_legacy_row, true ) );

$GLOBALS['mods']['majestic_tube_favicon'] = 'https://example.test/from-customizer.png';
$from_customizer                          = majestic_tube_retired_favicon();

check( 'and so is one the Customizer holds, which is where a site that set it since saves it', 'https://example.test/from-customizer.png' === $from_customizer, var_export( $from_customizer, true ) );

$GLOBALS['mods']                      = array();
$GLOBALS['options'][ $row ]           = array( 'favicon' => array( 'value' => '/uploads/old-icon.png' ) );
$wrapped                              = majestic_tube_retired_favicon();

check( 'a value an older import wrapped in an array is unwrapped, not thrown away', '/uploads/old-icon.png' === $wrapped, var_export( $wrapped, true ) );

$GLOBALS['options'][ $row ] = array( 'favicon' => array( 'a.png', 'b.png' ) );
$list                       = majestic_tube_retired_favicon();

check( 'a value that is a list of something is not an address, and reads as none', '' === $list, var_export( $list, true ) );

$GLOBALS['options'][ $row ] = array();
$GLOBALS['mods']            = array();

check( 'nothing stored reads as nothing to move', '' === majestic_tube_retired_favicon() );

check(
	'the move runs on a theme update and on activation, because an update never switches theme',
	in_array( 'after_switch_theme:majestic_tube_migrate_favicon_to_site_icon', $GLOBALS['hooks'], true )
	&& in_array( 'admin_init:majestic_tube_migrate_favicon_to_site_icon', $GLOBALS['hooks'], true ),
	implode( ', ', array_filter( $GLOBALS['hooks'], static fn( $hook ) => false !== strpos( $hook, 'favicon' ) ) )
);
$writes  = array();
$deletes = array();

preg_match_all( "/update_option\(\s*'([^']+)'/", $sources['/inc/site-icon.php'], $found );
$writes = $found[1];

preg_match_all( "/delete_option\(\s*'([^']+)'/", $sources['/inc/site-icon.php'], $found );
$deletes = $found[1];

check(
	'it writes the Site Icon and its own marker, and deletes the marker rather than the setting',
	array( 'site_icon', 'majestic_tube_favicon_unmoved' ) === $writes
	&& array( 'majestic_tube_favicon_unmoved' ) === $deletes
	&& false === strpos( $sources['/inc/site-icon.php'], 'set_theme_mod' )
	&& false === strpos( $sources['/inc/site-icon.php'], 'remove_theme_mod' ),
	'writes ' . implode( ', ', $writes ) . '; deletes ' . implode( ', ', $deletes )
);

echo "\nThe 2257 marker is gone, and what replaced it is still there\n";

$marker = array();

foreach ( $sources as $path => $source ) {
	if ( false !== strpos( $source, '_majestic_tube_legal_redirect' ) ) {
		$marker[] = $path;
	}
}

check(
	'2.5.0 stopped writing the marker, and nothing reads one either',
	! $marker,
	implode( ', ', $marker )
);
check(
	'because the redirect decides by document identity instead',
	false !== strpos( $sources['/inc/activation.php'], 'function majestic_tube_retired_2257_redirect_url()' )
	&& false !== strpos( $sources['/inc/activation.php'], 'majestic_tube_legal_pages_by_title' )
);
check(
	'and the page itself is still renamed in place, which is what made a marker unnecessary',
	false !== strpos( $sources['/inc/activation.php'], 'function majestic_tube_repair_legal_slug(' )
	&& false !== strpos( $sources['/inc/activation.php'], "'post_name' => \$canonical" )
);

echo "\n" . str_repeat( '-', 44 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

exit( $failed ? 1 : 0 );
