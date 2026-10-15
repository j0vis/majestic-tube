<?php
/**
 * Smoke test for inc/theme-options.php - the Customizer's own structure.
 *
 * The panel is data. majestic_tube_options_map() holds every field, and one
 * registration loop turns that data into panels, sections, settings and
 * controls. Nothing in a build connects what the map says to what the
 * Customizer is handed, so a field can name a section that does not exist, a
 * type can fall through to a sanitizer that is not there, a control can be
 * registered for an option no template reads, or a section can be left with
 * nothing in it - and the first person to notice is the one who opens
 * Appearance -> Customize, on a live site.
 *
 * Eight rules are pinned here:
 *
 *   1. every section belongs to a panel that exists, and every panel and
 *      section carries a title;
 *   2. every visible field lands in a section that exists, and no section is
 *      left empty;
 *   3. every setting is named once, under the theme's own prefix;
 *   4. every type has a sanitizer, a control, choices where the control needs
 *      them, and bounds that hold its own default;
 *   5. a dependency names a field the map holds, and the callback it builds
 *      shows or hides the control the way the dependency says;
 *   6. the registration loop hands every one of those to WordPress, with the
 *      section, sanitizer and dependency it declared;
 *   7. no visible control is dead: every option it exposes is read by another
 *      part of the theme;
 *   8. the keys kept only so an older install keeps its settings are still
 *      readable through the map.
 *
 * The registration loop runs against a recording stand-in for
 * WP_Customize_Manager rather than being inspected, so the wiring is exercised
 * and not merely described.
 *
 * Run:  php tests/theme-options-smoke.php
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
 * @param string $detail What was found instead, when it did not.
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
 * WordPress, as far as the options module touches it
 * ---------------------------------------------------------------------- */

$GLOBALS['theme_mods'] = array();
$GLOBALS['options']    = array();
$GLOBALS['added']      = array(
	'panels'   => array(),
	'sections' => array(),
	'settings' => array(),
	'controls' => array(),
	'colors'   => array(),
);

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return $text;
}

function esc_attr( $text ) {
	return $text;
}

function apply_filters( $hook, $value ) {
	return $value;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}

function current_user_can( $capability ) {
	return true;
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default;
}

function get_theme_mod( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['theme_mods'] ) ? $GLOBALS['theme_mods'][ $key ] : $default;
}

function sanitize_text_field( $value ) {
	return is_string( $value ) ? trim( $value ) : '';
}

function sanitize_textarea_field( $value ) {
	return (string) $value;
}

function wp_kses_post( $value ) {
	return $value;
}

function sanitize_hex_color( $value ) {
	return $value;
}

function esc_url_raw( $value ) {
	return $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

/**
 * A stand-in for WP_Customize_Color_Control, which records its arguments.
 */
class WP_Customize_Color_Control {

	public $id;
	public $args;

	public function __construct( $manager, $id, $args = array() ) {
		$this->id   = $id;
		$this->args = $args;

		$GLOBALS['added']['colors'][ $id ] = $args;
	}
}

/**
 * A stand-in for WP_Customize_Manager that keeps what the theme registers.
 */
class MT_Customize_Manager {

	public function add_panel( $id, $args = array() ) {
		$GLOBALS['added']['panels'][ $id ] = $args;
	}

	public function add_section( $id, $args = array() ) {
		$GLOBALS['added']['sections'][ $id ] = $args;
	}

	public function add_setting( $id, $args = array() ) {
		$GLOBALS['added']['settings'][ $id ] = $args;
	}

	public function add_control( $id, $args = array() ) {
		if ( is_object( $id ) ) {
			return;
		}

		$GLOBALS['added']['controls'][ $id ] = $args;
	}
}

require MAJESTIC_TUBE_DIR . '/inc/theme-options.php';

$sections = majestic_tube_option_sections();
$panels   = majestic_tube_option_panels();
$map      = majestic_tube_options_map();

$visible = array();
$legacy  = array();

foreach ( $map as $field_id => $config ) {
	if ( isset( $config['customizer'] ) && ! $config['customizer'] ) {
		$legacy[ $field_id ] = $config;
		continue;
	}

	$visible[ $field_id ] = $config;
}

/* -------------------------------------------------------------------------
 * 1 and 2: the section list
 * ---------------------------------------------------------------------- */

echo "\nThe panels and their sections\n";

$untitled   = array();
$unknown    = array();

foreach ( $panels as $slug => $title ) {
	if ( '' === trim( (string) $title ) ) {
		$untitled[] = $slug;
	}
}

foreach ( $sections as $slug => $section ) {
	if ( '' === trim( (string) $section['title'] ) ) {
		$untitled[] = $slug;
	}

	if ( ! isset( $panels[ $section['panel'] ] ) ) {
		$unknown[] = $slug . ' -> ' . $section['panel'];
	}
}

check( 'every panel and section is titled', ! $untitled, implode( ', ', $untitled ) );
check( 'every section names a panel that exists', ! $unknown, implode( ', ', $unknown ) );

// A section title alone leaves the person reading it to work out what belongs
// there; every one of them says what it is for.
$silent = array();

foreach ( $sections as $slug => $section ) {
	if ( empty( $section['description'] ) || '' === trim( (string) $section['description'] ) ) {
		$silent[] = $slug;
	}
}

check( 'every section says what it is for', ! $silent, implode( ', ', $silent ) );

$per_section = array();

foreach ( $sections as $slug => $section ) {
	$per_section[ $slug ] = 0;
}

$homeless  = array();
$misplaced = array();

foreach ( $visible as $field_id => $config ) {
	if ( ! isset( $config['section'] ) ) {
		// The registration loop files a field with no section under the first
		// one, which is a silent way to lose a setting.
		$homeless[] = $field_id;
		continue;
	}

	if ( ! isset( $per_section[ $config['section'] ] ) ) {
		$misplaced[] = $field_id . ' -> ' . $config['section'];
		continue;
	}

	++$per_section[ $config['section'] ];
}

check(
	'every visible field says which section it belongs to',
	! $homeless,
	implode( ', ', $homeless )
);
check(
	'and every one of those sections exists',
	! $misplaced,
	implode( ', ', $misplaced )
);

$empty = array();

foreach ( $per_section as $slug => $count ) {
	if ( 0 === $count ) {
		$empty[] = $slug;
	}
}

check(
	'no section is left with nothing in it',
	! $empty,
	implode( ', ', $empty )
);

$docs = array(
	'README.md'  => (string) file_get_contents( MAJESTIC_TUBE_DIR . '/README.md' ),
	'docs.html'  => (string) file_get_contents( MAJESTIC_TUBE_DIR . '/docs.html' ),
);

$undocumented = array();

foreach ( array_merge( array_values( $panels ), array_column( $sections, 'title' ) ) as $title ) {
	$text = html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' );

	foreach ( $docs as $name => $doc ) {
		if ( false === strpos( html_entity_decode( $doc, ENT_QUOTES, 'UTF-8' ), $text ) ) {
			$undocumented[] = $text . ' (' . $name . ')';
		}
	}
}

check(
	'both guides name every panel and section a site owner will see',
	! $undocumented,
	implode( ', ', array_unique( $undocumented ) )
);

/* -------------------------------------------------------------------------
 * 3 and 4: every field, as registered
 * ---------------------------------------------------------------------- */

echo "\nThe fields\n";

$bad_setting = array();
$seen        = array();
$no_label    = array();
$bad_default = array();

foreach ( $visible as $field_id => $config ) {
	$setting = isset( $config['setting'] ) ? $config['setting'] : '';

	if ( '' === $setting || 0 !== strpos( $setting, 'majestic_tube_' ) ) {
		$bad_setting[] = $field_id . ' -> ' . ( '' === $setting ? '(none)' : $setting );
	} elseif ( isset( $seen[ $setting ] ) ) {
		$bad_setting[] = $setting . ' used by ' . $seen[ $setting ] . ' and ' . $field_id;
	}

	$seen[ $setting ] = $field_id;

	if ( '' === trim( (string) $config['label'] ) ) {
		$no_label[] = $field_id;
	}

	$type    = $config['type'];
	$default = $config['default'];

	if ( 'number' === $type && ! is_int( $default ) && ! is_float( $default ) ) {
		$bad_default[] = $field_id . ' (' . $type . ') = ' . var_export( $default, true );
	}

	if ( 'number' !== $type && ! is_string( $default ) ) {
		$bad_default[] = $field_id . ' (' . $type . ') = ' . var_export( $default, true );
	}
}

check(
	'every setting is named once, under the theme prefix',
	! $bad_setting,
	implode( ', ', $bad_setting )
);
check( 'every field is labelled', ! $no_label, implode( ', ', $no_label ) );
check(
	'every default has the kind of value its type stores',
	! $bad_default,
	implode( ', ', $bad_default )
);

$no_sanitizer = array();
$no_choices   = array();
$bad_bounds   = array();

foreach ( $visible as $field_id => $config ) {
	$sanitizer = isset( $config['sanitize'] ) ? $config['sanitize'] : majestic_tube_option_sanitizer( $config['type'] );

	if ( ! is_callable( $sanitizer ) ) {
		$no_sanitizer[] = $field_id . ' (' . $config['type'] . ') -> ' . $sanitizer;
	}

	if ( 'select' === $config['type'] && empty( $config['choices'] ) ) {
		$no_choices[] = $field_id;
	}

	if ( 'number' !== $config['type'] ) {
		continue;
	}

	$attrs = isset( $config['input_attrs'] ) ? $config['input_attrs'] : array();

	if ( ! isset( $attrs['min'], $attrs['max'], $attrs['step'] ) ) {
		$bad_bounds[] = $field_id . ' declares no bounds';

		continue;
	}

	if ( $config['default'] < $attrs['min'] || $config['default'] > $attrs['max'] ) {
		$bad_bounds[] = $field_id . ' defaults to ' . $config['default'] . ', outside ' . $attrs['min'] . '-' . $attrs['max'];
	}
}

check(
	'every type resolves to a sanitizer that exists',
	! $no_sanitizer,
	implode( ', ', $no_sanitizer )
);
check( 'every dropdown has something to choose from', ! $no_choices, implode( ', ', $no_choices ) );
check(
	'every number field declares bounds that hold its own default',
	! $bad_bounds,
	implode( ', ', $bad_bounds )
);

/* -------------------------------------------------------------------------
 * 5: the dependencies
 * ---------------------------------------------------------------------- */

echo "\nControls that only matter while another option is on\n";

/**
 * A stand-in for the setting the dependency reads.
 */
class MT_Setting {

	public $value;

	public function __construct( $value ) {
		$this->value = $value;
	}

	public function value() {
		return $this->value;
	}
}

/**
 * A stand-in for the control whose visibility is being decided.
 */
class MT_Control {

	public $manager;

	public function __construct( $manager ) {
		$this->manager = $manager;
	}
}

/**
 * A stand-in for the manager, holding a setting per name so a test can move it.
 */
class MT_Manager {

	public $settings = array();

	public function get_setting( $id ) {
		return isset( $this->settings[ $id ] ) ? $this->settings[ $id ] : null;
	}
}

$dependencies = array();
$unknown_dep  = array();
$wrong_state  = array();

foreach ( $visible as $field_id => $config ) {
	if ( empty( $config['active_when'] ) && empty( $config['inactive_when'] ) ) {
		continue;
	}

	foreach ( array( 'active_when', 'inactive_when' ) as $key ) {
		foreach ( (array) ( isset( $config[ $key ] ) ? $config[ $key ] : array() ) as $parent ) {
			$dependencies[ $field_id ][] = $parent;

			if ( ! isset( $map[ $parent ] ) ) {
				$unknown_dep[] = $field_id . ' -> ' . $parent;
			}
		}
	}

	$callback = majestic_tube_option_active_callback( $config );

	if ( ! is_callable( $callback ) ) {
		$wrong_state[] = $field_id . ' builds no callback';

		continue;
	}

	// The parent in the state it asks for: the control has to be there.
	foreach ( (array) ( isset( $config['active_when'] ) ? $config['active_when'] : array() ) as $parent ) {
		$manager = new MT_Manager();
		$manager->settings[ $map[ $parent ]['setting'] ] = new MT_Setting( 'on' );

		if ( true !== $callback( new MT_Control( $manager ) ) ) {
			$wrong_state[] = $field_id . ' stays hidden with ' . $parent . ' on';
		}
	}

	foreach ( (array) ( isset( $config['inactive_when'] ) ? $config['inactive_when'] : array() ) as $parent ) {
		$manager = new MT_Manager();
		$manager->settings[ $map[ $parent ]['setting'] ] = new MT_Setting( 'off' );

		if ( true !== $callback( new MT_Control( $manager ) ) ) {
			$wrong_state[] = $field_id . ' stays hidden with ' . $parent . ' off';
		}
	}

	// And the state it does not ask for: the control has to go away, or the
	// dependency is decoration.
	$inverse = new MT_Manager();

	foreach ( (array) ( isset( $config['active_when'] ) ? $config['active_when'] : array() ) as $parent ) {
		$inverse->settings[ $map[ $parent ]['setting'] ] = new MT_Setting( 'off' );
	}

	foreach ( (array) ( isset( $config['inactive_when'] ) ? $config['inactive_when'] : array() ) as $parent ) {
		$inverse->settings[ $map[ $parent ]['setting'] ] = new MT_Setting( 'on' );
	}

	if ( false !== $callback( new MT_Control( $inverse ) ) ) {
		$wrong_state[] = $field_id . ' shows itself when it cannot do anything';
	}
}

check(
	'a dependency names a field the map holds',
	! $unknown_dep,
	implode( ', ', $unknown_dep )
);
check(
	'and the callback follows it in both states',
	! $wrong_state,
	implode( ', ', $wrong_state )
);
check(
	'a field with no dependency is never hidden',
	! majestic_tube_option_active_callback( array() ) && ! majestic_tube_option_active_callback( array( 'label' => 'x' ) ),
	'a field with no dependency built a callback'
);

// A dependency is only useful when the option it names is on the same screen:
// a switch that waits on a setting the panel does not offer is either hidden
// for a reason the administrator cannot see, or shown on a value they cannot
// change. And a dependent control must not itself be somebody's dependency,
// because then a hidden switch would take its children with it.
$hidden_parent = array();
$chained       = array();

foreach ( $dependencies as $field_id => $parents ) {
	foreach ( $parents as $parent ) {
		if ( isset( $legacy[ $parent ] ) ) {
			$hidden_parent[] = $field_id . ' -> ' . $parent;
		}

		if ( isset( $dependencies[ $parent ] ) ) {
			$chained[] = $field_id . ' -> ' . $parent;
		}
	}
}

check(
	'every dependency is an option the Customizer itself offers',
	! $hidden_parent,
	implode( ', ', $hidden_parent )
);
check(
	'and no dependent control is itself a dependency',
	! $chained,
	implode( ', ', $chained )
);

// The belt to that brace: an option kept out of the Customizer can still be a
// dependency, and the control then follows the stored value rather than being
// hidden by a setting that is not on the screen.
$fallback = majestic_tube_option_active_callback( array( 'active_when' => 'header-ad-desktop' ) );
$blank    = new MT_Manager();

$GLOBALS['options']['wpst-options'] = array( 'header-ad-desktop' => 'on' );
$follows_stored = $fallback( new MT_Control( $blank ) );

$GLOBALS['options']['wpst-options'] = array( 'header-ad-desktop' => 'off' );
$follows_absent = $fallback( new MT_Control( $blank ) );

unset( $GLOBALS['options']['wpst-options'] );

check(
	'a dependency the panel does not offer follows the stored option',
	true === $follows_stored && false === $follows_absent,
	'stored on -> ' . var_export( $follows_stored, true ) . ', stored off -> ' . var_export( $follows_absent, true )
);

printf( "\n  %d dependent controls, %d declarations\n", count( $dependencies ), array_sum( array_map( 'count', $dependencies ) ) );

/* -------------------------------------------------------------------------
 * 6: what the registration loop hands over
 * ---------------------------------------------------------------------- */

echo "\nWhat the Customizer is handed\n";

majestic_tube_options_customize_register( new MT_Customize_Manager() );

check(
	'one panel per panel',
	count( $panels ) === count( $GLOBALS['added']['panels'] ),
	count( $GLOBALS['added']['panels'] ) . ' of ' . count( $panels )
);
check(
	'one section per section',
	count( $sections ) === count( $GLOBALS['added']['sections'] ),
	count( $GLOBALS['added']['sections'] ) . ' of ' . count( $sections )
);
check(
	'one setting per visible field',
	count( $visible ) === count( $GLOBALS['added']['settings'] ),
	count( $GLOBALS['added']['settings'] ) . ' of ' . count( $visible )
);
check(
	'and one control per setting, once the colour picker is counted',
	count( $visible ) === count( $GLOBALS['added']['controls'] ) + count( $GLOBALS['added']['colors'] ),
	count( $GLOBALS['added']['controls'] ) . ' controls plus ' . count( $GLOBALS['added']['colors'] ) . ' colour pickers'
);

$orphan_section = array();
$unsanitized    = array();
$no_capability  = array();

// A control names its section; a setting does not. The colour picker is a
// control too, so it is held to the same rule.
foreach ( array_merge( $GLOBALS['added']['controls'], $GLOBALS['added']['colors'] ) as $id => $args ) {
	if ( ! isset( $args['section'] ) || ! isset( $GLOBALS['added']['sections'][ $args['section'] ] ) ) {
		$orphan_section[] = $id . ' -> ' . ( isset( $args['section'] ) ? $args['section'] : '(none)' );
	}
}

foreach ( $GLOBALS['added']['settings'] as $id => $args ) {
	if ( ! is_callable( $args['sanitize_callback'] ) ) {
		$unsanitized[] = $id;
	}

	if ( 'edit_theme_options' !== $args['capability'] ) {
		$no_capability[] = $id;
	}
}

check(
	'every setting lands in a section that was registered',
	! $orphan_section,
	implode( ', ', $orphan_section )
);
check( 'every setting is sanitized by a callable', ! $unsanitized, implode( ', ', $unsanitized ) );
check(
	'and every one needs the capability that belongs to the panel',
	! $no_capability,
	implode( ', ', $no_capability )
);

$missing_rule = array();
$spare_rule   = array();

foreach ( $visible as $field_id => $config ) {
	$setting   = $config['setting'];
	$control   = isset( $GLOBALS['added']['controls'][ $setting ] ) ? $GLOBALS['added']['controls'][ $setting ] : null;
	$wants     = ! empty( $config['active_when'] ) || ! empty( $config['inactive_when'] );

	if ( ! $control ) {
		continue;
	}

	$has = isset( $control['active_callback'] ) && is_callable( $control['active_callback'] );

	if ( $wants && ! $has ) {
		$missing_rule[] = $field_id;
	}

	if ( ! $wants && $has ) {
		$spare_rule[] = $field_id;
	}
}

check(
	'every dependent control carries its visibility rule',
	! $missing_rule,
	implode( ', ', $missing_rule )
);
check(
	'and a control with nothing to depend on carries none',
	! $spare_rule,
	implode( ', ', $spare_rule )
);

/* -------------------------------------------------------------------------
 * 7: no control is dead
 * ---------------------------------------------------------------------- */

echo "\nControls that would do nothing\n";

/**
 * The theme's own PHP, with the option map taken out of it.
 *
 * The map is where a field is declared, so finding the field there proves
 * nothing; what is left is the code that would have to read it.
 *
 * @param string $source Contents of inc/theme-options.php.
 * @return string
 */
function mt_without_option_map( $source ) {
	$kept    = array();
	$inside  = false;

	foreach ( preg_split( "/\r\n|\r|\n/", (string) $source ) as $line ) {
		if ( ! $inside && 0 === strpos( $line, 'function majestic_tube_options_map(' ) ) {
			$inside = true;
			continue;
		}

		if ( $inside ) {
			if ( 0 === strpos( $line, '}' ) ) {
				$inside = false;
			}

			continue;
		}

		$kept[] = $line;
	}

	return implode( "\n", $kept );
}

$sources = array();

foreach ( array_merge(
	glob( MAJESTIC_TUBE_DIR . '/inc/*.php' ),
	glob( MAJESTIC_TUBE_DIR . '/*.php' ),
	glob( MAJESTIC_TUBE_DIR . '/template-parts/*.php' )
) as $file ) {
	$source = (string) file_get_contents( $file );

	if ( 'theme-options.php' === basename( $file ) ) {
		$source = mt_without_option_map( $source );
	}

	$sources[] = $source;
}

$haystack = implode( "\n", $sources );
$dead     = array();

foreach ( $visible as $field_id => $config ) {
	if ( false !== strpos( $haystack, "'" . $field_id . "'" ) ) {
		continue;
	}

	if ( false !== strpos( $haystack, "'" . $config['setting'] . "'" ) ) {
		continue;
	}

	$dead[] = $field_id . ' (' . $config['section'] . ')';
}

check(
	'every control the Customizer shows is read somewhere in the theme',
	! $dead,
	implode( ', ', $dead )
);

/* -------------------------------------------------------------------------
 * 8: the keys kept for an older install
 * ---------------------------------------------------------------------- */

echo "\nThe options kept for migration\n";

$unreadable = array();

foreach ( $legacy as $field_id => $config ) {
	if ( majestic_tube_get_option( 'wpst-options', $field_id, 'unset' ) !== $config['default'] ) {
		$unreadable[] = $field_id . ' does not answer with its own default';
	}

	$GLOBALS['options']['wpst-options'] = array( $field_id => 'stored-value' );

	if ( 'stored-value' !== majestic_tube_get_option( 'wpst-options', $field_id ) ) {
		$unreadable[] = $field_id . ' ignores the value an older install stored';
	}

	unset( $GLOBALS['options']['wpst-options'] );
}

check(
	'every key kept out of the Customizer is still readable, and still wins',
	! $unreadable,
	implode( ', ', $unreadable )
);
printf( "\n  %d keys are kept for migration and not shown\n", count( $legacy ) );

echo "\n" . str_repeat( '-', 44 ) . "\n";
printf( "%d passed, %d failed\n", $passed, $failed );

exit( $failed ? 1 : 0 );
